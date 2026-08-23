<?php

namespace Acelle\S3;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Exception\AwsException;
use Symfony\Component\HttpFoundation\File\File;
use App\Library\Storage\StorageInterface;
use App\Library\Storage\StorableInterface as Storable;
use App\Library\Storage\Capabilities\ProvidesPublicUrl;
use App\Library\Storage\ProbeResult;

/**
 * S3-compatible object storage.
 *
 * Talks to the AWS SDK directly rather than through Flysystem: the SDK is
 * already a host dependency (it ships for SES), while
 * league/flysystem-aws-s3-v3 is NOT installed — and the `s3` disk block in
 * config/filesystems.php is configured 'throw' => false, which would turn every
 * failure into a silently wrong value (a failed write returning false, a failed
 * mimeType() returning false and being coerced to an empty Content-Type).
 * Going straight to the client keeps StorageInterface the only contract.
 *
 * "S3-compatible" covers the object operations, not the whole product: this
 * driver works against AWS S3, Cloudflare R2, Backblaze B2, Wasabi,
 * DigitalOcean Spaces and MinIO by changing `endpoint` and credentials.
 * It deliberately does NOT set object ACLs and does NOT construct a public
 * hostname, because those differ per vendor — R2 implements no ACL parameters
 * at all, and its public host is a bound custom domain, not its API endpoint.
 * Public serving is one explicit setting: public_base_url.
 */
class S3Storage implements StorageInterface, ProvidesPublicUrl
{
    private ?S3Client $client = null;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(private array $options = [])
    {
    }

    public static function key(): string
    {
        return 's3';
    }

    public static function displayName(): string
    {
        return 'S3-compatible object storage';
    }

    public static function configFields(): array
    {
        return [
            'cols' => [
                'bucket' => 'required|string|max:255',
                'region' => 'required|string|max:64',
                'access_key' => 'required|string|max:255',
                'secret_key' => 'required|string|max:255',
                'endpoint' => 'nullable|url|max:255',
                'public_base_url' => 'nullable|url|max:255',
                'path_style' => 'nullable|boolean',
            ],
            'frontend_cols' => [
                'bucket' => ['type' => 'text', 'label' => 'Bucket'],
                'region' => ['type' => 'text', 'label' => 'Region'],
                'access_key' => ['type' => 'text', 'label' => 'Access key ID'],
                'secret_key' => ['type' => 'password', 'label' => 'Secret access key', 'secret' => true],
                'endpoint' => ['type' => 'text', 'label' => 'Endpoint'],
                'public_base_url' => ['type' => 'text', 'label' => 'Public base URL'],
                'path_style' => ['type' => 'checkbox', 'label' => 'Use path-style addressing'],
            ],
        ];
    }

    public function probe(): ProbeResult
    {
        $key = '.probe-'.bin2hex(random_bytes(8));

        try {
            $this->client()->putObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'Body' => 'probe',
            ]);

            $body = (string) $this->client()->getObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
            ])['Body'];

            $this->client()->deleteObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
            ]);
        } catch (AwsException $e) {
            // A probe answers a question; it never propagates. The admin sees
            // this message, so give them the vendor's own words.
            return ProbeResult::fail(
                'Could not write to the bucket: '.($e->getAwsErrorMessage() ?: $e->getMessage())
            );
        } catch (\Throwable $e) {
            return ProbeResult::fail('Could not reach the storage service: '.$e->getMessage());
        }

        if ($body !== 'probe') {
            return ProbeResult::fail('Wrote a test object but read back different bytes.');
        }

        $warnings = [];
        if ($this->publicBaseUrl() === null) {
            $warnings[] = trans('s3::messages.warning.no_public_base_url');
        }

        return ProbeResult::ok(trans('s3::messages.probe.ok'), $warnings);
    }

    public function store(File $file, Storable $asset): string
    {
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Cannot read the uploaded file for upload.');
        }

        try {
            return $this->put($this->keyFor($asset), $stream, $file->getMimeType());
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function putContents(string $bytes, Storable $asset): string
    {
        return $this->put($this->keyFor($asset), $bytes, null);
    }

    public function copy(Storable $from, Storable $to): bool
    {
        $fromKey = $this->keyFor($from);
        $toKey = $this->keyFor($to);

        try {
            // Server-side copy: the bytes never travel through this process.
            $this->client()->copyObject([
                'Bucket' => $this->bucket(),
                'Key' => $toKey,
                'CopySource' => rawurlencode($this->bucket().'/'.$fromKey),
            ]);
        } catch (AwsException $e) {
            throw new \RuntimeException(
                "Failed to copy [{$fromKey}] to [{$toKey}]: ".($e->getAwsErrorMessage() ?: $e->getMessage()),
                0,
                $e
            );
        }

        return true;
    }

    public function getFileContent(Storable $asset): string
    {
        $key = $this->keyFor($asset);

        try {
            return (string) $this->client()->getObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
            ])['Body'];
        } catch (AwsException $e) {
            throw new \RuntimeException(
                "Cannot read [{$key}]: ".($e->getAwsErrorMessage() ?: $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function readStream(Storable $asset): mixed
    {
        $key = $this->keyFor($asset);

        try {
            // Register the s3:// wrapper so the object can be streamed to the
            // client instead of buffered whole in PHP memory.
            $this->client()->registerStreamWrapper();
            $stream = fopen('s3://'.$this->bucket().'/'.$key, 'rb');
        } catch (\Throwable $e) {
            throw new \RuntimeException("Cannot open a stream for [{$key}]: ".$e->getMessage(), 0, $e);
        }

        if ($stream === false) {
            throw new \RuntimeException("Cannot open a stream for [{$key}].");
        }

        return $stream;
    }

    public function fileExists(Storable $asset): bool
    {
        try {
            return $this->client()->doesObjectExist($this->bucket(), $this->keyFor($asset));
        } catch (AwsException $e) {
            // "Does it exist?" has two honest answers, and an auth or network
            // failure is neither. Returning false here would render a broken
            // bucket as "you have no files".
            throw new \RuntimeException(
                'Cannot check the storage service: '.($e->getAwsErrorMessage() ?: $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function mimeType(Storable $asset): ?string
    {
        try {
            $result = $this->client()->headObject([
                'Bucket' => $this->bucket(),
                'Key' => $this->keyFor($asset),
            ]);
        } catch (AwsException $e) {
            return null;
        }

        $type = $result['ContentType'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    public function delete(Storable $asset): bool
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket(),
                'Key' => $this->keyFor($asset),
            ]);
        } catch (AwsException $e) {
            return false;
        }

        return true;
    }

    /**
     * A durable URL, or null when this configuration has no public front.
     *
     * Never derived from bucket + region: that shape is AWS-specific, and on R2
     * the public host is a bound custom domain unrelated to the API endpoint.
     * The admin tells us the URL — which is also how a CDN in front of the
     * bucket (CloudFront, Cloudflare, Bunny) is configured: it is just a
     * different base.
     */
    public function publicUrl(Storable $asset): ?string
    {
        $base = $this->publicBaseUrl();

        if ($base === null) {
            return null;
        }

        return rtrim($base, '/').'/'.$this->keyFor($asset);
    }

    // ─── internals ───

    /**
     * @param resource|string $body
     */
    private function put(string $key, $body, ?string $contentType): string
    {
        $args = [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'Body' => $body,
        ];

        // No 'ACL' key: R2 implements no ACL parameters, and a bucket that
        // should be public is made public in the vendor's console. Sending one
        // would fail on some providers and silently do nothing on others.
        if ($contentType !== null && $contentType !== '') {
            $args['ContentType'] = $contentType;
        }

        try {
            $this->client()->putObject($args);
        } catch (AwsException $e) {
            throw new \RuntimeException(
                "Failed to write [{$key}]: ".($e->getAwsErrorMessage() ?: $e->getMessage()),
                0,
                $e
            );
        }

        return $key;
    }

    /**
     * The one key derivation, shared by reads and writes.
     *
     * "a/b" and "a/b/" are different objects here, and StoragePathResolver is
     * inconsistent about trailing slashes, so normalise in exactly one place.
     */
    private function keyFor(Storable $asset): string
    {
        return trim(preg_replace('#/+#', '/', $asset->getFullPath()), '/');
    }

    private function bucket(): string
    {
        $bucket = $this->options['bucket'] ?? null;

        if (!is_string($bucket) || $bucket === '') {
            throw new \RuntimeException(
                'The S3 storage engine is active but has no bucket configured. '
                .'Open the plugin settings and configure it.'
            );
        }

        return $bucket;
    }

    private function publicBaseUrl(): ?string
    {
        $url = $this->options['public_base_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function client(): S3Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = [
            'version' => 'latest',
            'region' => $this->options['region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $this->options['access_key'] ?? '',
                'secret' => $this->options['secret_key'] ?? '',
            ],
        ];

        // Anything that is not AWS itself: R2, B2, Wasabi, Spaces, MinIO.
        if (!empty($this->options['endpoint'])) {
            $config['endpoint'] = $this->options['endpoint'];
        }

        // MinIO and some gateways cannot do virtual-hosted-style addressing.
        if (!empty($this->options['path_style'])) {
            $config['use_path_style_endpoint'] = true;
        }

        return $this->client = new S3Client($config);
    }
}
