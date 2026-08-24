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
        {{-- Title left, state and its one action right. Disconnect belongs with
             the status it acts on, not stranded under the key it is not about. --}}
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4)">
            <h2 class="mc-card-title" style="margin-bottom:0">{{ trans('s3::messages.section.account') }}</h2>

            <div style="display:flex;align-items:center;gap:var(--space-3);flex-shrink:0">
                {{-- The app's own connected indicator, not a bespoke dot. --}}
                <span class="mc-form-grid-meta-connected">
                    <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
                    {{ trans('s3::messages.status.connected') }}
                </span>

                <form method="POST"
                      action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'disconnect']) }}">
                    @csrf
                    <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">
                        {{ trans('s3::messages.action.disconnect') }}
                    </button>
                </form>
            </div>
        </div>

        <div style="display:flex;gap:var(--space-8);flex-wrap:wrap;margin-top:var(--space-4)">
            <div>
                <div class="mc-form-label">{{ trans('s3::messages.field.access_key') }}</div>
                <code>{{ $options['access_key'] ?? '' }}</code>
            </div>
            @if (!empty($options['region']))
                {{-- Derived from the bucket, never chosen by hand. --}}
                <div>
                    <div class="mc-form-label">{{ trans('s3::messages.field.region') }}</div>
                    <code>{{ $options['region'] }}</code>
                    @if ($regionLabel)<span class="mc-text-muted">— {{ $regionLabel }}</span>@endif
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Bucket + delivery ---------------------------------------------------- --}}
<div class="mc-card" style="margin-bottom:var(--space-4);max-width:760px">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.section.bucket') }}</h2>

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

            {{-- One question, one control. This was a checkbox plus a CDN field
                 with a precedence rule between them, which is what made the help
                 text read backwards and left "which should hide when the other
                 is set?" unanswerable. Three exclusive choices, no precedence. --}}
            @php $delivery = old('delivery', $effectiveDelivery['mode']); @endphp

            {{-- mc-import-settings — the app's own "pick one of N, each with an
                 explanation, one of them revealing sub-options" component,
                 including the :has(input:checked) selected state. --}}
            <div class="mc-import-settings" data-s3-delivery>
                @foreach (\Acelle\S3\S3Storage::DELIVERY_MODES as $mode)
                    <label class="mc-import-settings-mode">
                        <input type="radio" name="delivery" value="{{ $mode }}"
                               {{ $delivery === $mode ? 'checked' : '' }}>
                        <span class="mc-import-settings-mode-body">
                            <span class="mc-import-settings-mode-label">{{ trans('s3::messages.delivery.' . $mode . '.label') }}</span>
                            <span class="mc-import-settings-mode-desc">
                                {{ trans('s3::messages.delivery.' . $mode . '.help') }}
                                @if ($mode === 'bucket' && $bucketUrl)
                                    <br><code>{{ $bucketUrl }}/…</code>
                                @endif
                            </span>
                        </span>
                    </label>

                    @if ($mode === 'cdn')
                        {{-- Revealed only by its own option, so the address can
                             never sit there looking active while another mode is
                             selected. --}}
                        <div class="mc-import-settings-suboptions" data-s3-cdn-field
                             @unless ($delivery === 'cdn') style="display:none" @endunless>
                            <input type="text" name="public_base_url"
                                   class="mc-form-input @error('public_base_url') is-invalid @enderror"
                                   value="{{ old('public_base_url', $options['public_base_url'] ?? '') }}"
                                   placeholder="https://d111111abcdef8.cloudfront.net">
                            <p class="mc-form-help">{{ trans('s3::messages.field.public_base_url.help') }}</p>
                            @error('public_base_url')<p class="mc-form-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                @endforeach

                @error('delivery')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            {{-- What is live right now, which is not always what is selected: a
                 'cdn' choice saved with a blank address serves through the app. --}}
            <div class="mc-alert {{ $effectiveDelivery['direct'] ? 'mc-alert-success' : 'mc-alert-info' }}"
                 style="margin-top:var(--space-3)">
                <span class="material-symbols-rounded" aria-hidden="true">
                    {{ $effectiveDelivery['direct'] ? 'bolt' : 'dns' }}
                </span>
                <div>
                    <strong>{{ $effectiveDelivery['label'] }}</strong>
                    @if ($effectiveDelivery['base'])
                        <br><code>{{ $effectiveDelivery['base'] }}/…</code>
                    @endif
                </div>
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
            <p><span class="mc-badge mc-badge-green">{{ trans('s3::messages.status.active') }}</span></p>
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
                    <button type="submit" class="mc-btn mc-btn-secondary">
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

@section('scripts')
<script>
// Reveal the CDN address only for the option that uses it. Server-side
// validation still requires it when 'cdn' is chosen — this only keeps the form
// from showing a field that would be ignored.
(function () {
    var group = document.querySelector('[data-s3-delivery]');
    if (!group) return;
    var field = group.querySelector('[data-s3-cdn-field]');
    if (!field) return;

    group.addEventListener('change', function (e) {
        if (e.target.name !== 'delivery') return;
        field.style.display = e.target.value === 'cdn' ? '' : 'none';
    });
})();
</script>
@endsection
