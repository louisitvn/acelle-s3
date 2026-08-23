<?php

namespace Acelle\S3\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\StorageEngine;
use App\Model\Plugin;
use Acelle\S3\S3Storage;

/**
 * Two-stage setup, the same shape as the AWS Whitelabel plugin.
 *
 *   stage 1  no credentials      -> connect form (key, secret, region)
 *   stage 2  credentials valid   -> bucket + delivery options
 *
 * The staging lives here, in the plugin, NOT in the storage contract: the host
 * has no notion of a wizard and must not grow one, or every future engine
 * inherits a shape it may not want.
 */
class SettingsController extends Controller
{
    public const PLUGIN = 'acelle/s3';

    /**
     * Router for the two stages. Which one you get is derived from what is
     * stored, never from a query string.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = $this->engineRow();
        $options = $engine->decryptedOptions();

        if (empty($options['access_key']) || empty($options['secret_key'])) {
            return $this->connectForm($request);
        }

        $driver = new S3Storage($options);
        $listing = $driver->listBuckets();

        // A bucket in another region still reads and writes (the SDK follows
        // the redirect) but yields a public URL that 404s, so surface the
        // mismatch instead of letting it produce broken image links.
        $regionMismatch = null;
        if (!empty($options['bucket'])) {
            $actual = $driver->bucketRegion($options['bucket']);
            if ($actual !== null && $actual !== ($options['region'] ?? null)) {
                $regionMismatch = $actual;
            }
        }

        return view('s3::settings', [
            'plugin' => Plugin::getByName(self::PLUGIN),
            'options' => $engine->maskedOptions(),
            'buckets' => $listing['buckets'],
            'bucketListingFailed' => !$listing['ok'],
            'bucketListingMessage' => $listing['message'],
            'regionMismatch' => $regionMismatch,
            'bucketUrl' => !empty($options['bucket']) ? $driver->bucketUrl() : null,
            'regions' => S3Storage::REGIONS,
            'isConfigured' => !empty($options['bucket']),
            'isActive' => StorageEngine::where('is_active', true)->value('driver') === S3Storage::key(),
            'activeDriver' => StorageEngine::where('is_active', true)->value('driver'),
        ]);
    }

    public function connectForm(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('s3::connect', [
            'plugin' => Plugin::getByName(self::PLUGIN),
            'regions' => S3Storage::REGIONS,
            'options' => $this->engineRow()->maskedOptions(),
        ]);
    }

    /**
     * Stage 1. Verifies the credentials before storing them; a failed check
     * writes nothing, so an unusable key can never be persisted.
     */
    public function connect(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = $this->engineRow();
        $secretRule = !empty($engine->options['secret_key']) ? 'nullable' : 'required';

        $validated = $request->validate([
            'access_key' => 'required|string|max:255',
            'secret_key' => $secretRule.'|string|max:255',
            'region' => 'required|string|in:'.implode(',', array_keys(S3Storage::REGIONS)),
        ]);

        $engine->driver = S3Storage::key();
        $engine->mergeOptions($validated);

        $probe = (new S3Storage($engine->decryptedOptions()))->probeCredentials();

        if (!$probe->ok) {
            return redirect()->action([self::class, 'connectForm'])
                ->withInput($request->except('secret_key'))
                ->with('alert-error', $probe->message);
        }

        $engine->save();

        return redirect()->action([self::class, 'index'])
            ->with('alert-success', $probe->message);
    }

    /**
     * Stage 2. Save IS the connection test: the probe writes, reads back and
     * deletes a real object in the chosen bucket, and nothing is persisted if
     * that fails.
     */
    public function save(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = $this->engineRow();

        if (empty($engine->options['access_key'])) {
            return redirect()->action([self::class, 'connectForm'])
                ->with('alert-error', trans('s3::messages.error.connect_first'));
        }

        $validated = $request->validate([
            'bucket' => 'required|string|max:255',
            'public_access' => 'nullable|boolean',
            'public_base_url' => 'nullable|url|max:255',
        ]);

        $engine->mergeOptions($validated);

        $probe = (new S3Storage($engine->decryptedOptions()))->probe();

        if (!$probe->ok) {
            return redirect()->back()->withInput()->with('alert-error', $probe->message);
        }

        $engine->save();

        $message = trans('s3::messages.saved');
        foreach ($probe->warnings as $warning) {
            $message .= ' '.$warning;
        }

        return redirect()->action([self::class, 'index'])->with('alert-success', $message);
    }

    /**
     * Forget the credentials, returning the plugin to stage 1.
     *
     * Hands storage back to the local disk first when S3 is active — otherwise
     * this would leave an active engine with no credentials, which fails on
     * every request including the page needed to fix it.
     */
    public function disconnect(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = StorageEngine::where('driver', S3Storage::key())->first();

        if ($engine === null) {
            return redirect()->action([self::class, 'index']);
        }

        if ($engine->is_active) {
            $this->makeLocalActive();
        }

        $engine->delete();

        return redirect()->action([self::class, 'connectForm'])
            ->with('alert-success', trans('s3::messages.disconnected'));
    }

    public function activate(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = $this->engineRow();

        if (!$engine->exists || empty($engine->options['bucket'])) {
            return redirect()->back()->with('alert-error', trans('s3::messages.error.not_configured'));
        }

        $probe = (new S3Storage($engine->decryptedOptions()))->probe();

        if (!$probe->ok) {
            return redirect()->back()->with('alert-error', $probe->message);
        }

        StorageEngine::query()->update(['is_active' => false]);
        $engine->is_active = true;
        $engine->save();

        return redirect()->action([self::class, 'index'])
            ->with('alert-success', trans('s3::messages.activated'));
    }

    /**
     * Deliberately NOT gated on a probe: switching an engine off must work even
     * when that engine is broken, or a bad configuration is a one-way door.
     */
    public function deactivate(Request $request)
    {
        $this->authorizeAdmin($request);

        if (!$this->makeLocalActive()) {
            return redirect()->back()->with('alert-error', trans('s3::messages.error.no_local'));
        }

        return redirect()->action([self::class, 'index'])
            ->with('alert-success', trans('s3::messages.deactivated'));
    }

    private function makeLocalActive(): bool
    {
        $local = StorageEngine::where('driver', 'local')->first();

        if ($local === null) {
            return false;
        }

        StorageEngine::query()->update(['is_active' => false]);
        $local->is_active = true;
        $local->save();

        return true;
    }

    private function engineRow(): StorageEngine
    {
        return StorageEngine::firstOrNew(['driver' => S3Storage::key()]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()->admin && $request->user()->admin->getPermission('setting_general') == 'yes',
            403
        );
    }
}
