<?php

namespace Acelle\S3;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Symfony\Component\HttpFoundation\File\File;
use App\Library\Storage\StorageInterface;
use App\Library\Storage\StorableInterface as Storable;
use App\Library\Storage\Capabilities\ProvidesPublicUrl;
use App\Library\Storage\Capabilities\HasSetupPage;
use App\Library\Storage\ProbeResult;

/**
 * Amazon S3.
 *
 * Scope: Amazon S3 only. Another vendor is another plugin — that is the whole
 * point of the engine registry, and it keeps this class free of the `endpoint`
 * / `path_style` / ACL-dialect branching that "S3-compatible" support drags in.
 * Because the vendor is fixed, the public URL of a bucket is DERIVABLE here,
 * which a multi-vendor driver could never do.
 *
 * Talks to the AWS SDK directly rather than through Flysystem: the SDK is
 * already a host dependency (it ships for SES), while
 * league/flysystem-aws-s3-v3 is NOT installed — and the `s3` disk block in
 * config/filesystems.php is configured 'throw' => false, which would turn every
 * failure into a silently wrong value (a failed write returning false, a failed
 * mimeType() returning false and being coerced into an empty Content-Type).
 */
class S3Storage implements StorageInterface, ProvidesPublicUrl, HasSetupPage
{
    /**
     * Where this engine gets configured.
     *
     * Resolved at render time, not at registration: this plugin's routes are
     * loaded in its own boot(), so route() during register() would name a
     * route that does not exist yet.
     */
    public static function setupUrl(): string
    {
        return route('plugin.acelle.s3.settings');
    }

    /** Regions the connect form offers. */
    public const REGIONS = [
        'us-east-1' => 'US East (N. Virginia)',
        'us-east-2' => 'US East (Ohio)',
        'us-west-1' => 'US West (N. California)',
        'us-west-2' => 'US West (Oregon)',
        'af-south-1' => 'Africa (Cape Town)',
        'ap-east-1' => 'Asia Pacific (Hong Kong)',
        'ap-south-1' => 'Asia Pacific (Mumbai)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-northeast-3' => 'Asia Pacific (Osaka)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-southeast-3' => 'Asia Pacific (Jakarta)',
        'ca-central-1' => 'Canada (Central)',
        'eu-central-1' => 'Europe (Frankfurt)',
        'eu-west-1' => 'Europe (Ireland)',
        'eu-west-2' => 'Europe (London)',
        'eu-west-3' => 'Europe (Paris)',
        'eu-north-1' => 'Europe (Stockholm)',
        'eu-south-1' => 'Europe (Milan)',
        'il-central-1' => 'Israel (Tel Aviv)',
        'me-central-1' => 'Middle East (UAE)',
        'me-south-1' => 'Middle East (Bahrain)',
        'sa-east-1' => 'South America (São Paulo)',
    ];

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
        return 'Amazon S3';
    }

    /**
     * Validation + secret declaration for the whole configuration.
     *
     * The settings screen collects these in two stages (credentials first, then
     * bucket) but that is a UI concern owned by this plugin's own controller —
     * the host renders nothing from this and needs no notion of a wizard.
     */
    public static function configFields(): array
    {
        return [
            'cols' => [
                'access_key' => 'required|string|max:255',
                'secret_key' => 'required|string|max:255',
                'region' => 'required|string|max:64',
                'bucket' => 'required|string|max:255',
                'public_access' => 'nullable|boolean',
                'public_base_url' => 'nullable|url|max:255',
            ],
            'frontend_cols' => [
                'access_key' => ['type' => 'text', 'label' => 'Access key ID'],
                'secret_key' => ['type' => 'password', 'label' => 'Secret access key', 'secret' => true],
                'region' => ['type' => 'select', 'label' => 'Region'],
                'bucket' => ['type' => 'select', 'label' => 'Bucket'],
                'public_access' => ['type' => 'checkbox', 'label' => 'Serve files directly from S3'],
                'public_base_url' => ['type' => 'text', 'label' => 'CDN base URL'],
            ],
        ];
    }

    // ─── stage 1: credentials ───

    /**
     * Are these credentials valid?
     *
     * Separate from probe() because at this point no bucket has been chosen —
     * there is nothing to write to yet. Plugin-internal; the host never calls
     * it, so it stays off StorageInterface.
     */
    public function probeCredentials(): ProbeResult
    {
        try {
            $this->client()->listBuckets();
        } catch (AwsException $e) {
            return ProbeResult::fail($this->humanise($e));
        } catch (\Throwable $e) {
            return ProbeResult::fail(trans('s3::messages.error.unreachable', ['reason' => $e->getMessage()]));
        }

        return ProbeResult::ok(trans('s3::messages.probe.credentials_ok'));
    }

    /**
     * Buckets these credentials can see.
     *
     * @return array{ok: bool, buckets: list<string>, message: ?string}
     *
     * A key scoped to one bucket legitimately cannot list — a common
     * production setup, not an error. The caller falls back to a text input,
     * but it must SAY so rather than render an empty dropdown that reads as
     * "you have no buckets".
     */
    public function listBuckets(): array
    {
        try {
            $result = $this->client()->listBuckets();
        } catch (AwsException $e) {
            return ['ok' => false, 'buckets' => [], 'message' => $this->humanise($e)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'buckets' => [], 'message' => $e->getMessage()];
        }

        $buckets = array_map(static fn ($b) => (string) $b['Name'], $result['Buckets'] ?? []);
        sort($buckets);

        return ['ok' => true, 'buckets' => $buckets, 'message' => null];
    }

    /**
     * The region a bucket actually lives in, or null when it cannot be read.
     *
     * A bucket in a different region than the one configured still works for
     * most calls (the SDK follows the redirect) but produces a wrong public
     * URL, so the settings screen offers to correct it.
     */
    public function bucketRegion(string $bucket): ?string
    {
        try {
            // determineBucketRegion(), NOT getBucketLocation(). The latter is
            // itself signed for the client's region, so asking it about a
            // bucket in another region fails with AuthorizationHeaderMalformed
            // — it cannot answer the one question worth asking it. The SDK
            // helper exists precisely for this and reads the region off the
            // redirect instead.
            return $this->client()->determineBucketRegion($bucket);
        } catch (\Throwable $e) {
            // Denied by policy, or the bucket does not exist. The caller keeps
            // the configured region and lets the write probe fail loudly.
            return null;
        }
    }

    // ─── stage 2: the bucket ───

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
            // A probe answers a question; it never propagates.
            return ProbeResult::fail($this->humanise($e));
        } catch (\Throwable $e) {
            return ProbeResult::fail(trans('s3::messages.error.unreachable', ['reason' => $e->getMessage()]));
        }

        if ($body !== 'probe') {
            return ProbeResult::fail(trans('s3::messages.error.readback_mismatch'));
        }

        $warnings = [];
        if ($this->publicUrlBase() === null) {
            $warnings[] = trans('s3::messages.warning.proxying');
        }

        return ProbeResult::ok(trans('s3::messages.probe.ok'), $warnings);
    }

    // ─── StorageInterface ───

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
            throw new \RuntimeException("Failed to copy [{$fromKey}] to [{$toKey}]: ".$this->humanise($e), 0, $e);
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
            throw new \RuntimeException("Cannot read [{$key}]: ".$this->humanise($e), 0, $e);
        }
    }

    public function readStream(Storable $asset): mixed
    {
        $key = $this->keyFor($asset);

        try {
            // The s3:// wrapper streams the object to the client instead of
            // buffering the whole body in PHP memory.
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
            // "Does it exist?" has two honest answers and an auth or network
            // failure is neither. Returning false would render a broken bucket
            // as "you have no files".
            throw new \RuntimeException('Cannot check the storage service: '.$this->humanise($e), 0, $e);
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
     * A durable URL, or null to let the app serve the bytes.
     *
     * Null is a configuration answer, not a failure: proxying always works, it
     * just carries the traffic.
     */
    public function publicUrl(Storable $asset): ?string
    {
        $base = $this->publicUrlBase();

        return $base === null ? null : $base.'/'.$this->keyFor($asset);
    }

    /**
     * Where public objects are served from — a CDN if one is configured,
     * otherwise the bucket's own regional endpoint.
     *
     * Derivable only because the vendor is fixed. Returns null when direct
     * serving was not switched on, which is the default: a bucket is private
     * until someone deliberately opens it, and guessing otherwise would hand
     * out URLs that 403.
     */
    private function publicUrlBase(): ?string
    {
        $cdn = $this->options['public_base_url'] ?? null;
        if (is_string($cdn) && $cdn !== '') {
            return rtrim($cdn, '/');
        }

        if (empty($this->options['public_access'])) {
            return null;
        }

        return $this->bucketUrl();
    }

    /** The bucket's regional website-style base URL. */
    public function bucketUrl(): string
    {
        $region = $this->options['region'] ?? 'us-east-1';

        return 'https://'.$this->bucket().'.s3.'.$region.'.amazonaws.com';
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

        // No 'ACL' key. Modern buckets have ACLs disabled by default
        // (BucketOwnerEnforced) and reject the parameter outright; public
        // access is a bucket policy, set in the AWS console.
        if ($contentType !== null && $contentType !== '') {
            $args['ContentType'] = $contentType;
        }

        try {
            $this->client()->putObject($args);
        } catch (AwsException $e) {
            throw new \RuntimeException("Failed to write [{$key}]: ".$this->humanise($e), 0, $e);
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
                .'Open the plugin settings and choose one.'
            );
        }

        return $bucket;
    }

    private function humanise(AwsException $e): string
    {
        // Named cases only — anything unrecognised keeps the vendor's own
        // wording rather than being flattened into a generic message.
        return match ($e->getAwsErrorCode()) {
            'InvalidAccessKeyId' => trans('s3::messages.error.bad_key_id'),
            'SignatureDoesNotMatch' => trans('s3::messages.error.bad_secret'),
            'AccessDenied' => trans('s3::messages.error.access_denied'),
            'NoSuchBucket' => trans('s3::messages.error.no_such_bucket'),
            'AuthorizationHeaderMalformed', 'PermanentRedirect' => $this->wrongRegionMessage($e),
            default => trans('s3::messages.error.vendor', [
                'code' => $e->getAwsErrorCode() ?: 'unknown',
                'message' => $e->getAwsErrorMessage() ?: $e->getMessage(),
            ]),
        };
    }

    /**
     * A wrong-region refusal, phrased as something to act on.
     *
     * Normally unreachable — the settings screen adopts the bucket's own region
     * before it ever writes. It still happens when GetBucketLocation is denied
     * by the key's policy, and AWS helpfully names the region it wanted, so
     * lift that out rather than making the admin read a signing error.
     */
    private function wrongRegionMessage(AwsException $e): string
    {
        $raw = $e->getAwsErrorMessage() ?: $e->getMessage();

        if (preg_match("/expecting '([a-z0-9-]+)'/", $raw, $m) === 1) {
            return trans('s3::messages.error.wrong_region_known', [
                'bucket' => $this->options['bucket'] ?? '',
                'region' => $m[1],
            ]);
        }

        return trans('s3::messages.error.wrong_region', [
            'bucket' => $this->options['bucket'] ?? '',
        ]);
    }

    private function client(): S3Client
    {
        return $this->client ??= new S3Client([
            'version' => 'latest',
            'region' => $this->options['region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $this->options['access_key'] ?? '',
                'secret' => $this->options['secret_key'] ?? '',
            ],
        ]);
    }
}
