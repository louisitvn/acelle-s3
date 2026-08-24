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

        // One call, and it is global — every bucket on the account regardless
        // of region. Resolving each bucket's region for the dropdown was
        // measured at 12s serially and 6s in parallel for 11 buckets, and it
        // grows with the account, so the region is resolved for the ONE bucket
        // that gets chosen instead (see save()).
        $listing = $driver->listBuckets();

        return view('s3::settings', [
            'plugin' => Plugin::getByName(self::PLUGIN),
            'options' => $engine->maskedOptions(),
            'buckets' => $listing['buckets'],
            'bucketListingFailed' => !$listing['ok'],
            'bucketListingMessage' => $listing['message'],
            'regionLabel' => S3Storage::REGIONS[$options['region'] ?? ''] ?? null,
            'bucketUrl' => !empty($options['bucket']) ? $driver->bucketUrl() : null,
            // Only to pre-select the radio. Read off the driver rather than the
            // raw option so a pre-rename row still selects correctly.
            'delivery' => $driver->deliveryMode()['mode'],
        ]);
    }

    public function connectForm(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('s3::connect', [
            'plugin' => Plugin::getByName(self::PLUGIN),
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

        // No region here on purpose. An IAM key is global — it is not bound to
        // a region — and ListBuckets is a global call that returns every bucket
        // on the account whatever region the client was built for. Only the
        // BUCKET has a region, and it is resolved from the bucket in save().
        // Asking for it at this point would be asking the admin to guess.
        $validated = $request->validate([
            'access_key' => 'required|string|max:255',
            'secret_key' => $secretRule.'|string|max:255',
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
            'delivery' => 'required|string|in:'.implode(',', S3Storage::DELIVERY_MODES),
            // Choosing "from a CDN" without an address would silently fall back
            // to serving through the app — refuse instead of half-applying it.
            'public_base_url' => 'required_if:delivery,cdn|nullable|url|max:255',
        ]);

        $previousRegion = $engine->decryptedOptions()['region'] ?? null;
        $engine->mergeOptions($validated);

        // The region follows the BUCKET, not the other way round.
        //
        // A bucket lives in exactly one region and S3 signs every request with
        // it, so a bucket picked from the list while the account is set to a
        // different region fails with AuthorizationHeaderMalformed. Asking an
        // admin to have guessed the right region one screen earlier is a
        // puzzle, not a setting — and it was unescapable, because the probe
        // refused the save and the mismatch warning only rendered for a bucket
        // already stored. Look it up and adopt it.
        $actualRegion = (new S3Storage($engine->decryptedOptions()))
            ->bucketRegion($validated['bucket']);

        if ($actualRegion !== null) {
            $engine->mergeOptions(['region' => $actualRegion]);
        }

        $probe = (new S3Storage($engine->decryptedOptions()))->probe();

        if (!$probe->ok) {
            return redirect()->back()->withInput()->with('alert-error', $probe->message);
        }

        $engine->save();

        $message = trans('s3::messages.saved');

        // Say so rather than silently changing something they chose.
        if ($actualRegion !== null && $actualRegion !== $previousRegion) {
            $message .= ' '.trans('s3::messages.region_adopted', ['region' => $actualRegion]);
        }
        foreach ($probe->warnings as $warning) {
            $message .= ' '.$warning;
        }

        // Configuring here does not switch the app over — that is the host
        // picker's job. Point at it, since finishing this form otherwise looks
        // like the whole job is done.
        if (StorageEngine::where('is_active', true)->value('driver') !== S3Storage::key()) {
            $message .= ' '.trans('s3::messages.saved_next_step');
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
