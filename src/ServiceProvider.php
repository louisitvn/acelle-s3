<?php

namespace Acelle\S3;

use Illuminate\Support\ServiceProvider as Base;
use App\Library\Facades\Hook;
use App\Library\Storage\StorageEngineRegistry;
use App\Model\StorageEngine;

class ServiceProvider extends Base
{
    public const PLUGIN = 'acelle/s3';

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        defined('S3_PLUGIN_FULL_NAME') || define('S3_PLUGIN_FULL_NAME', 'acelle/s3');
        defined('S3_PLUGIN_SHORT_NAME') || define('S3_PLUGIN_SHORT_NAME', 's3');

        // ⛔ MUST be in register() (not boot). AppServiceProvider's collect
        //    loop runs in its own boot phase; a hook added in the plugin's
        //    boot() would be invisible to it.
        // ⛔ Do NOT also call loadTranslationsFrom() in boot() — that overrides
        //    the namespace hint and turns the dump-clones into zombie files.
        Hook::add('add_translation_file', function () {
            return [
                'id'                      => '#acelle/s3_translation_file',
                'plugin_name'             => 'acelle/s3',
                'file_title'              => 'Translation for acelle/s3 plugin',
                'translation_folder'      => storage_path('app/data/plugins/acelle/s3/lang/'),
                'translation_prefix'      => 's3',
                'file_name'               => 'messages.php',
                'master_translation_file' => realpath(__DIR__ . '/../resources/lang/en/messages.php'),
            ];
        });
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // The whole point of the plugin: teach the host about one more engine.
        //
        // A direct static call, not a Hook — the payload is only a class-string
        // and every piece of metadata (key, label, config schema) already lives
        // on the class, which is the rationale recorded for verification
        // drivers. It also sidesteps the boot-order dependency a
        // Hook::collect() would introduce.
        //
        // Safe to register unconditionally, including while the plugin is
        // disabled: registering only makes the engine *selectable*. Nothing is
        // stored here until an admin configures and activates it.
        StorageEngineRegistry::register(S3Storage::key(), S3Storage::class);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 's3');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');

        Hook::set('icon_url_acelle/s3', fn () => route('plugin.acelle.s3.icon'));

        // Refuse to activate an engine that is not configured — Plugin::activate()
        // propagates the throw and the admin UI renders it as a 422.
        Hook::on('activate_plugin_acelle/s3', function () {
            $engine = StorageEngine::where('driver', S3Storage::key())->first();

            if ($engine === null || empty($engine->options['bucket'])) {
                throw new \RuntimeException(
                    'Configure the S3 connection in the plugin settings before activating it.'
                );
            }
        });

        // Uninstall. The engine's *registration* disappears with the class, so
        // the only durable state is its configuration row.
        //
        // NOTE on the deliberate asymmetry with `disable_plugin_*`, which this
        // plugin does NOT hook: disabling while S3 is the active engine is
        // meant to fail loudly — StorageEngineRegistry::resolve() throws for a
        // key nothing registers, and that is the chosen behaviour, not an
        // oversight. Recovery is flipping is_active in `storage_engines`.
        //
        // Deleting is different only when the config row goes with it: keeping
        // is_active on a row we are about to remove would leave the table in a
        // state no code path can produce or repair. So the flag moves ONLY in
        // the branch that deletes the row; `--keep-data` preserves the
        // fail-loud behaviour exactly.
        Hook::on('delete_plugin_acelle/s3', function ($keepData = false) {
            $engine = StorageEngine::where('driver', S3Storage::key())->first();

            if ($engine === null || $keepData) {
                return;
            }

            if ($engine->is_active) {
                $local = StorageEngine::where('driver', 'local')->first();
                if ($local !== null) {
                    $local->is_active = true;
                    $local->save();
                }
            }

            $engine->delete();
        });
    }
}
