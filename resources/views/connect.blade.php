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
    <div class="mc-alert mc-alert-danger" style="margin-bottom:var(--space-3);max-width:640px">
        <span class="material-symbols-rounded" aria-hidden="true">error</span>
        {{ session('alert-error') }}
    </div>
@endif

<div class="s3-connect-grid">

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    <div class="mc-card">
        <div class="mc-card-body">
            <h2 class="mc-card-title">{{ trans('s3::messages.connect.heading') }}</h2>
            <p class="mc-text-muted">{{ trans('s3::messages.connect.intro') }}</p>

            {{-- autocomplete off at form level AND per field: Chrome sees a text
                 input followed by a password input and offers the signed-in
                 user's email as a "username", which lands in Access key ID. --}}
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

                {{-- No region field. An IAM key is global, and the region
                     belongs to the bucket — it is read from whichever bucket
                     gets chosen on the next screen. --}}

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

    {{-- ── Illustration + pitch ─────────────────────────────────────────── --}}
    <aside class="s3-pitch" aria-label="{{ trans('s3::messages.pitch.heading') }}">

        <div class="s3-pitch-illust">
            {{-- Wireframe style + --illust-* tokens, so it follows the theme
                 with no dark-mode variant to maintain. --}}
            <svg viewBox="0 0 320 240" fill="none" xmlns="http://www.w3.org/2000/svg" role="img"
                 aria-label="{{ trans('s3::messages.pitch.illust_alt') }}">

                {{-- backdrop --}}
                <ellipse cx="160" cy="196" rx="118" ry="16" fill="var(--illust-bg)"/>

                {{-- cloud --}}
                <path d="M96 62c0-13 11-24 24-24 9 0 17 5 21 12a18 18 0 0 1 27 11 16 16 0 0 1-3 32H99a19 19 0 0 1-3-31z"
                      fill="var(--illust-fill)" stroke="var(--illust-stroke)" stroke-width="1.5" stroke-linejoin="round"/>

                {{-- objects falling into the bucket --}}
                <rect x="118" y="86" width="26" height="20" rx="3"
                      fill="var(--color-card-bg)" stroke="var(--illust-stroke)" stroke-width="1.4"/>
                <line x1="124" y1="93" x2="138" y2="93" stroke="var(--illust-stroke-bold)" stroke-width="1.6" stroke-linecap="round"/>
                <line x1="124" y1="99" x2="133" y2="99" stroke="var(--illust-stroke)" stroke-width="1.4" stroke-linecap="round"/>

                <rect x="152" y="92" width="26" height="20" rx="3"
                      fill="var(--color-card-bg)" stroke="var(--illust-stroke)" stroke-width="1.4"/>
                <circle cx="160" cy="102" r="3.4" fill="var(--illust-teal)" stroke="var(--color-teal)" stroke-width="1"/>
                <path d="M166 108l5-6 4 5" stroke="var(--illust-stroke)" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>

                {{-- the bucket: three stacked layers --}}
                <g>
                    <ellipse cx="160" cy="132" rx="62" ry="15"
                             fill="var(--illust-teal)" stroke="var(--color-teal)" stroke-width="1.6"/>
                    <path d="M98 132v20c0 8 28 15 62 15s62-7 62-15v-20"
                          fill="var(--illust-fill)" stroke="var(--illust-stroke)" stroke-width="1.5"/>
                    <ellipse cx="160" cy="152" rx="62" ry="15"
                             fill="var(--illust-bg)" stroke="var(--illust-stroke)" stroke-width="1.4"/>
                    <path d="M98 152v18c0 8 28 15 62 15s62-7 62-15v-18"
                          fill="var(--illust-fill)" stroke="var(--illust-stroke)" stroke-width="1.5"/>
                    <ellipse cx="160" cy="170" rx="62" ry="15"
                             fill="var(--illust-bg)" stroke="var(--illust-stroke)" stroke-width="1.4"/>
                </g>

                {{-- durability tick --}}
                <circle cx="222" cy="118" r="15" fill="var(--illust-teal)" stroke="var(--color-teal)" stroke-width="1.3"/>
                <path d="M215 118l5 5 10-11" stroke="var(--color-teal)" stroke-width="2.2"
                      stroke-linecap="round" stroke-linejoin="round"/>

                {{-- delivery nodes --}}
                <circle cx="62" cy="112" r="11" fill="var(--color-card-bg)" stroke="var(--illust-stroke)" stroke-width="1.4"/>
                <path d="M57 112h10M62 107v10" stroke="var(--illust-stroke-bold)" stroke-width="1.4" stroke-linecap="round"/>
                <path d="M73 116c10 6 16 10 25 14" stroke="var(--illust-stroke)" stroke-width="1.3"
                      stroke-linecap="round" stroke-dasharray="3 4"/>

                {{-- sparkles --}}
                <circle cx="252" cy="66" r="3" fill="var(--illust-chart-2)"/>
                <circle cx="72" cy="58" r="2.5" fill="var(--illust-teal)"/>
                <circle cx="268" cy="158" r="2.5" fill="var(--illust-chart-3)"/>
            </svg>
        </div>

        <h2 class="s3-pitch-title">{{ trans('s3::messages.pitch.heading') }}</h2>
        <p class="s3-pitch-lede">{{ trans('s3::messages.pitch.lede') }}</p>

        <ul class="s3-pitch-list">
            @foreach (['durability', 'unlimited', 'delivery', 'ownership'] as $point)
                <li>
                    <span class="material-symbols-rounded" aria-hidden="true">check_circle</span>
                    <span>
                        <strong>{{ trans('s3::messages.pitch.' . $point . '.title') }}</strong>
                        {{ trans('s3::messages.pitch.' . $point . '.body') }}
                    </span>
                </li>
            @endforeach
        </ul>
    </aside>
</div>

@endsection

@section('scripts')
<style>
.s3-connect-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: var(--space-5, 24px);
    align-items: start;
    max-width: 1180px;
}
@media (max-width: 1100px) {
    .s3-connect-grid { grid-template-columns: minmax(0, 1fr); }
    .s3-pitch { order: -1; }
}

.s3-pitch {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-card);
    background: var(--color-bg-subtle);
    padding: var(--space-5, 24px);
}
.s3-pitch-illust {
    background: var(--color-card-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-3);
    margin-bottom: var(--space-4);
}
.s3-pitch-illust svg { display: block; width: 100%; height: auto; }

.s3-pitch-title {
    font-size: var(--text-lg, 18px);
    font-weight: var(--weight-semibold, 600);
    color: var(--color-text);
    margin: 0 0 var(--space-2);
}
.s3-pitch-lede {
    color: var(--color-text-secondary);
    font-size: var(--text-sm);
    margin: 0 0 var(--space-4);
}
.s3-pitch-list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.s3-pitch-list li { display: flex; gap: var(--space-2); align-items: flex-start; }
.s3-pitch-list .material-symbols-rounded {
    color: var(--color-teal);
    font-size: 20px;
    line-height: 1.35;
    flex: 0 0 auto;
}
.s3-pitch-list span:last-child {
    font-size: var(--text-sm);
    color: var(--color-text-secondary);
    line-height: 1.5;
}
.s3-pitch-list strong { color: var(--color-text); display: block; }
</style>
@endsection
