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

{{-- Pitch banner — the host's own component, same shape and 180×140
     illustration slot as the Campaigns list, rather than a bespoke panel. --}}
<div class="mc-banner mc-banner-open">
    <div class="mc-banner-content">
        <div class="mc-banner-title">{{ trans('s3::messages.pitch.heading') }}</div>
        <div class="mc-banner-desc">{{ trans('s3::messages.pitch.lede') }}</div>
        <div class="mc-banner-meta" style="flex-wrap:wrap;row-gap:var(--space-2)">
            <span class="mc-banner-meta-item">
                <span class="material-symbols-rounded">verified</span>
                <span class="mc-banner-meta-value">{{ trans('s3::messages.pitch.durability_value') }}</span>
                {{ trans('s3::messages.pitch.durability_label') }}
            </span>
            <span class="mc-banner-meta-item">
                <span class="material-symbols-rounded">all_inclusive</span>
                {{ trans('s3::messages.pitch.unlimited_label') }}
            </span>
            <span class="mc-banner-meta-item">
                <span class="material-symbols-rounded">bolt</span>
                {{ trans('s3::messages.pitch.delivery_label') }}
            </span>
            <span class="mc-banner-meta-item">
                <span class="material-symbols-rounded">account_balance</span>
                {{ trans('s3::messages.pitch.ownership_label') }}
            </span>
        </div>

        {{-- What the next step actually asks for. The banner previously stopped
             at the pitch, so the reader met the credentials form without having
             been told what kind of key it wants. --}}
        <p class="mc-banner-desc" style="margin-bottom:0">{{ trans('s3::messages.pitch.requirement') }}</p>
    </div>

    <div class="mc-banner-illustration">
        {{-- Drawn on the --illust-* tokens, so it follows the theme with no
             second asset. Simplified for 180×140: at this size dashed leads and
             small sparkles turn to mush.

             The cylinder wall spans ellipse CENTRE to ellipse CENTRE (44 → 100),
             because that is where an ellipse is widest. Running it to the
             ellipse's top edge instead leaves the wall wider than the curve and
             square shoulders poke out at every seam. --}}
        <svg viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img"
             aria-label="{{ trans('s3::messages.pitch.illust_alt') }}">
            <circle cx="90" cy="70" r="58" fill="var(--illust-teal)"/>

            {{-- objects going in --}}
            <g transform="rotate(-10 62 34)">
                <rect x="44" y="20" width="34" height="26" rx="4" fill="var(--color-card-bg)"
                      stroke="var(--illust-stroke)" stroke-width="1.2"/>
                <rect x="50" y="27" width="17" height="2.5" rx="1.25" fill="var(--illust-stroke-bold)"/>
                <rect x="50" y="33" width="22" height="2.5" rx="1.25" fill="var(--illust-stroke)"/>
            </g>
            <g transform="rotate(12 122 30)">
                <rect x="105" y="16" width="32" height="26" rx="4" fill="var(--color-card-bg)"
                      stroke="var(--illust-stroke)" stroke-width="1.2"/>
                <circle cx="113" cy="25" r="3" fill="var(--color-teal)" fill-opacity=".55"/>
                <path d="M108 38l8-9 6 7 4-4 6 6z" fill="var(--color-teal)" fill-opacity=".3"/>
            </g>

            {{-- the bucket --}}
            <rect x="52" y="66" width="76" height="46" fill="var(--color-teal)" fill-opacity=".45"/>
            <ellipse cx="90" cy="112" rx="38" ry="13" fill="var(--color-teal)" fill-opacity=".62"/>
            <ellipse cx="90" cy="82"  rx="38" ry="13" fill="var(--color-teal)" fill-opacity=".3"/>
            <ellipse cx="90" cy="97"  rx="38" ry="13" fill="var(--color-teal)" fill-opacity=".38"/>
            <ellipse cx="90" cy="66"  rx="38" ry="13" fill="var(--color-card-bg)" fill-opacity=".6"
                     stroke="var(--color-teal)" stroke-width="1.4" stroke-opacity=".75"/>
            <ellipse cx="90" cy="66"  rx="24" ry="7" fill="var(--color-teal)" fill-opacity=".2"/>

            {{-- durability seal --}}
            <circle cx="139" cy="96" r="14" fill="var(--color-card-bg)"
                    stroke="var(--color-teal)" stroke-width="1.6"/>
            <path d="M132 96l5 5 9-10" stroke="var(--color-teal)" stroke-width="2.4"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
</div>

@if (session('alert-error'))
    <div class="mc-alert mc-alert-danger" style="margin-bottom:var(--space-3);max-width:640px">
        <span class="material-symbols-rounded" aria-hidden="true">error</span>
        {{ session('alert-error') }}
    </div>
@endif

<div class="mc-card" style="max-width:640px">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.connect.heading') }}</h2>
        <p class="mc-text-muted">{{ trans('s3::messages.connect.intro') }}</p>

        {{-- autocomplete off at form level AND per field: Chrome sees a text
             input followed by a password input and offers the signed-in user's
             email as a "username", which lands in Access key ID. --}}
        <form method="POST" autocomplete="off"
              action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'connect']) }}">
            @csrf

            <div class="mc-form-group">
                <label for="s3-access-key" class="mc-form-label">{{ trans('s3::messages.field.access_key') }}</label>
                <input id="s3-access-key" type="text" name="access_key"
                       autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other"
                       class="mc-form-input @error('access_key') is-invalid @enderror"
                       value="{{ old('access_key', $options['access_key'] ?? '') }}"
                       placeholder="AKIA…" spellcheck="false">
                @error('access_key')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            <div class="mc-form-group">
                <label for="s3-secret-key" class="mc-form-label">{{ trans('s3::messages.field.secret_key') }}</label>
                {{-- A stored secret is never echoed back into HTML. --}}
                <input id="s3-secret-key" type="password" name="secret_key"
                       autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other"
                       class="mc-form-input @error('secret_key') is-invalid @enderror"
                       @if (!empty($options['secret_key'])) placeholder="{{ $options['secret_key'] }}" @endif>
                @if (!empty($options['secret_key']))
                    <p class="mc-form-help">{{ trans('s3::messages.field.secret_key.help') }}</p>
                @endif
                @error('secret_key')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            {{-- No region field. An IAM key is global, and the region belongs to
                 the bucket — it is read from whichever bucket gets chosen on the
                 next screen. --}}

            <div style="margin-top:var(--space-4);display:flex;gap:var(--space-2);align-items:center">
                <button type="submit" class="mc-btn mc-btn-primary">
                    {{ trans('s3::messages.action.connect') }}
                </button>
                <a href="{{ url('rui/admin/plugins') }}" class="mc-btn mc-btn-ghost">
                    {{ trans('s3::messages.action.back') }}
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
