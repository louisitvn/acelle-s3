<?php

namespace Acelle\S3\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\StorageEngine;
use App\Model\Plugin;
use Acelle\S3\S3Storage;

class SettingsController extends Controller
{
    public const PLUGIN = 'acelle/s3';

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = $this->engineRow();
        $active = StorageEngine::where('is_active', true)->value('driver');

        return view('s3::settings', [
            'plugin' => Plugin::getByName(self::PLUGIN),
            // maskedOptions() never puts a secret into HTML.
            'options' => $engine->maskedOptions(),
            'fields' => S3Storage::configFields()['frontend_cols'],
            'isConfigured' => $engine->exists,
            'isActive' => $active === S3Storage::key(),
            'activeDriver' => $active,
        ]);
    }

    /**
     * Save IS the connection test.
     *
     * The probe runs against the SUBMITTED values and nothing is written when
     * it fails, so a bucket the app cannot write to can never become the
     * configured engine. Mirrors the spam-score settings page.
     */
    public function save(Request $request)
    {
        $this->authorizeAdmin($request);

        $rules = [];
        foreach (S3Storage::configFields()['cols'] as $field => $rule) {
            $rules[$field] = $rule;
        }

        // A blank secret means "keep the stored one", so it is only required
        // the first time.
        $engine = $this->engineRow();
        if ($engine->exists && !empty($engine->options['secret_key'])) {
            $rules['secret_key'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        // Merge onto the stored row IN MEMORY so the probe tests exactly what
        // would be saved — including a preserved secret the form left blank.
        $engine->driver = S3Storage::key();
        $engine->mergeOptions($validated);

        $probe = (new S3Storage($engine->decryptedOptions()))->probe();

        if (!$probe->ok) {
            return redirect()->back()
                ->withInput($request->except('secret_key'))
                ->with('alert-error', $probe->message);
        }

        $engine->save();

        $message = trans('s3::messages.saved');
        foreach ($probe->warnings as $warning) {
            $message .= ' '.$warning;
        }

        return redirect()->action([self::class, 'index'])->with('alert-success', $message);
    }

    /**
     * Make S3 the engine that holds new files.
     *
     * Existing files are NOT moved — nothing here moves them. The confirmation
     * copy says so, and `php artisan storage:verify` reports what would break.
     */
    public function activate(Request $request)
    {
        $this->authorizeAdmin($request);

        $engine = $this->engineRow();

        if (!$engine->exists) {
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
     * Hand storage back to the local disk.
     *
     * Deliberately NOT gated on a probe: switching an engine off must work
     * even when that engine is broken, otherwise a bad configuration is a
     * one-way door.
     */
    public function deactivate(Request $request)
    {
        $this->authorizeAdmin($request);

        $local = StorageEngine::where('driver', 'local')->first();

        if ($local === null) {
            return redirect()->back()->with('alert-error', trans('s3::messages.error.no_local'));
        }

        StorageEngine::query()->update(['is_active' => false]);
        $local->is_active = true;
        $local->save();

        return redirect()->action([self::class, 'index'])
            ->with('alert-success', trans('s3::messages.deactivated'));
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
