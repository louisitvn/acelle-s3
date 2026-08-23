@extends('refactor.layouts.admin')

@section('title', trans('s3::messages.title'))

@section('page-header')
    <div class="mc-page-header">
        <div>
            <h1 class="mc-page-title">{{ trans('s3::messages.title') }}</h1>
            <p class="mc-page-subtitle">{{ trans('s3::messages.subtitle') }}</p>
        </div>
    </div>
@endsection

@section('content')

@if (session('alert-error'))
    <div class="mc-alert mc-alert-danger" style="margin-bottom:var(--space-3)">
        <span class="material-symbols-rounded" aria-hidden="true">error</span>
        {{ session('alert-error') }}
    </div>
@endif

@if (session('alert-success'))
    <div class="mc-alert mc-alert-success" style="margin-bottom:var(--space-3)">
        <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
        {{ session('alert-success') }}
    </div>
@endif

{{-- Connected account ---------------------------------------------------- --}}
<div class="mc-card" style="margin-bottom:var(--space-4);max-width:760px">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.section.account') }}</h2>

        <dl class="mc-definition-list">
            <dt>{{ trans('s3::messages.field.access_key') }}</dt>
            <dd><code>{{ $options['access_key'] ?? '' }}</code></dd>
            <dt>{{ trans('s3::messages.field.region') }}</dt>
            <dd><code>{{ $options['region'] ?? '' }}</code></dd>
        </dl>

        <form method="POST" style="margin-top:var(--space-3)"
              action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'disconnect']) }}">
            @csrf
            <button type="submit" class="mc-btn mc-btn-default">
                {{ trans('s3::messages.action.disconnect') }}
            </button>
        </form>
    </div>
</div>

{{-- Bucket + delivery ---------------------------------------------------- --}}
<div class="mc-card" style="margin-bottom:var(--space-4);max-width:760px">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.section.bucket') }}</h2>

        @if ($regionMismatch)
            <div class="mc-alert mc-alert-warning" style="margin-bottom:var(--space-3)">
                <span class="material-symbols-rounded" aria-hidden="true">warning</span>
                {{ trans('s3::messages.warning.region_mismatch', [
                    'configured' => $options['region'] ?? '',
                    'actual' => $regionMismatch,
                ]) }}
            </div>
        @endif

        <form method="POST" action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'save']) }}">
            @csrf

            <div class="mc-form-group">
                <label for="s3-bucket" class="mc-form-label">{{ trans('s3::messages.field.bucket') }}</label>

                @if ($bucketListingFailed)
                    {{-- A key scoped to one bucket cannot list, which is a normal
                         production setup. Say so and take the name by hand rather
                         than showing an empty dropdown that reads as "no buckets". --}}
                    <input id="s3-bucket" type="text" name="bucket"
                           class="mc-form-input @error('bucket') is-invalid @enderror"
                           value="{{ old('bucket', $options['bucket'] ?? '') }}">
                    <p class="mc-form-help">
                        {{ trans('s3::messages.bucket.listing_failed') }}
                        <br><small>{{ $bucketListingMessage }}</small>
                    </p>
                @else
                    <select id="s3-bucket" name="bucket"
                            class="mc-form-input @error('bucket') is-invalid @enderror">
                        <option value="">{{ trans('s3::messages.bucket.choose') }}</option>
                        @foreach ($buckets as $name)
                            <option value="{{ $name }}"
                                {{ old('bucket', $options['bucket'] ?? '') === $name ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mc-form-help">
                        {{ trans_choice('s3::messages.bucket.found', count($buckets), ['count' => count($buckets)]) }}
                    </p>
                @endif

                @error('bucket')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            <hr class="mc-divider">

            <h3 class="mc-form-section-title">{{ trans('s3::messages.section.delivery') }}</h3>
            <p class="mc-text-muted">{{ trans('s3::messages.delivery.intro') }}</p>

            <div class="mc-form-group">
                <label class="mc-form-check">
                    <input type="hidden" name="public_access" value="0">
                    <input type="checkbox" name="public_access" value="1"
                           {{ old('public_access', $options['public_access'] ?? false) ? 'checked' : '' }}>
                    <span>{{ trans('s3::messages.field.public_access') }}</span>
                </label>
                <p class="mc-form-help">
                    {{ trans('s3::messages.field.public_access.help') }}
                    @if ($bucketUrl)
                        <br><code>{{ $bucketUrl }}/…</code>
                    @endif
                </p>
            </div>

            <div class="mc-form-group">
                <label for="s3-cdn" class="mc-form-label">{{ trans('s3::messages.field.public_base_url') }}</label>
                <input id="s3-cdn" type="text" name="public_base_url"
                       class="mc-form-input @error('public_base_url') is-invalid @enderror"
                       value="{{ old('public_base_url', $options['public_base_url'] ?? '') }}"
                       placeholder="https://cdn.example.com">
                <p class="mc-form-help">{{ trans('s3::messages.field.public_base_url.help') }}</p>
                @error('public_base_url')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            <div style="margin-top:var(--space-4)">
                <button type="submit" class="mc-btn mc-btn-primary">
                    {{ trans('s3::messages.action.save') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Activation ----------------------------------------------------------- --}}
<div class="mc-card" style="max-width:760px">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.section.status') }}</h2>

        @if ($isActive)
            <p><span class="mc-badge mc-badge-success">{{ trans('s3::messages.status.active') }}</span></p>
        @elseif ($isConfigured)
            <p><span class="mc-badge mc-badge-default">{{ trans('s3::messages.status.configured') }}</span></p>
            <p class="mc-text-muted">{{ trans('s3::messages.status.other_active', ['driver' => $activeDriver]) }}</p>
        @else
            <p><span class="mc-badge mc-badge-default">{{ trans('s3::messages.status.not_configured') }}</span></p>
        @endif

        {{-- Stated before the switch, not after: nothing in the app moves
             existing files, so switching strands whatever is already stored. --}}
        <div class="mc-alert mc-alert-warning" style="margin-top:var(--space-3)">
            <span class="material-symbols-rounded" aria-hidden="true">warning</span>
            <div>
                <strong>{{ trans('s3::messages.notice.no_migration.title') }}</strong><br>
                {{ trans('s3::messages.notice.no_migration.body') }}
            </div>
        </div>

        <div style="margin-top:var(--space-3);display:flex;gap:var(--space-2)">
            @if ($isActive)
                <form method="POST" action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'deactivate']) }}">
                    @csrf
                    <button type="submit" class="mc-btn mc-btn-default">
                        {{ trans('s3::messages.action.deactivate') }}
                    </button>
                </form>
            @elseif ($isConfigured)
                <form method="POST" action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'activate']) }}">
                    @csrf
                    <button type="submit" class="mc-btn mc-btn-primary">
                        {{ trans('s3::messages.action.activate') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

@endsection
