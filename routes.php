<?php

use Illuminate\Support\Facades\Route;

// Plugin's own icon, served straight from storage/. Referenced by the
// Hook::set('icon_url_…') registration in src/ServiceProvider.php.
// __DIR__ rather than storage_path() so it also resolves for a symlinked
// development install.
Route::get('plugins/acelle/s3/icon.svg', function () {
    $path = __DIR__ . '/icon.svg';
    abort_unless(file_exists($path), 404);
    return response()->file($path, [
        'Content-Type'  => 'image/svg+xml',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->name('plugin.acelle.s3.icon');

// Settings — admin only.
//
// Lives under the plugin's own URI space rather than /rui/admin/*: the host
// owns that namespace and a plugin squatting there is one core release away
// from being silently shadowed.
//
// Deliberately NOT behind an "is the plugin active" gate — an engine has to be
// configurable BEFORE it can be activated, so gating this would brick the flow.
//
// 'web' must be declared here: plugin route files are loaded via
// loadRoutesFrom(), outside the host's web group.
Route::group([
    'middleware' => ['web', 'not_installed', 'auth', 'backend', '2fa', 'demo_guard'],
    'namespace'  => '\Acelle\S3\Controllers',
    'prefix'     => 'plugins/acelle/s3',
], function () {
    // index() routes between the two stages from what is stored, so there is
    // no way to reach the bucket form without valid credentials.
    Route::get('/', 'SettingsController@index')->name('plugin.acelle.s3.settings');
    Route::get('connect', 'SettingsController@connectForm')->name('plugin.acelle.s3.connect_form');
    Route::post('connect', 'SettingsController@connect')->name('plugin.acelle.s3.connect');
    Route::post('disconnect', 'SettingsController@disconnect')->name('plugin.acelle.s3.disconnect');
    Route::post('save', 'SettingsController@save')->name('plugin.acelle.s3.save');
    Route::post('activate', 'SettingsController@activate')->name('plugin.acelle.s3.activate');
    Route::post('deactivate', 'SettingsController@deactivate')->name('plugin.acelle.s3.deactivate');
});
