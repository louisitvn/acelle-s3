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
            <svg viewBox="0 0 340 250" fill="none" xmlns="http://www.w3.org/2000/svg" role="img"
                 aria-label="{{ trans('s3::messages.pitch.illust_alt') }}">
                <defs>
                    {{-- Volume comes from gradients rather than outlines: a flat
                         wireframe reads as a diagram, not an object. --}}
                    <linearGradient id="s3-drum-a" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%"   stop-color="var(--color-teal)" stop-opacity=".95"/>
                        <stop offset="100%" stop-color="var(--color-teal)" stop-opacity=".55"/>
                    </linearGradient>
                    <linearGradient id="s3-drum-b" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%"   stop-color="var(--color-teal)" stop-opacity=".72"/>
                        <stop offset="100%" stop-color="var(--color-teal)" stop-opacity=".38"/>
                    </linearGradient>
                    <linearGradient id="s3-drum-c" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%"   stop-color="var(--color-teal)" stop-opacity=".5"/>
                        <stop offset="100%" stop-color="var(--color-teal)" stop-opacity=".24"/>
                    </linearGradient>
                    <radialGradient id="s3-glow" cx="50%" cy="50%" r="50%">
                        <stop offset="0%"   stop-color="var(--color-teal)" stop-opacity=".16"/>
                        <stop offset="100%" stop-color="var(--color-teal)" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="s3-card" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%"   stop-color="var(--color-card-bg)"/>
                        <stop offset="100%" stop-color="var(--illust-bg)"/>
                    </linearGradient>
                </defs>

                <circle cx="170" cy="128" r="118" fill="url(#s3-glow)"/>
                <ellipse cx="170" cy="221" rx="96" ry="13" fill="var(--illust-bg)"/>

                {{-- One cylinder, banded to read as stacked layers.
                     The side wall spans ellipse CENTRE to ellipse CENTRE
                     (118 → 200), because that is where an ellipse is at its
                     widest. Running it to the ellipse's top edge instead — the
                     obvious way — leaves the wall wider than the curve and
                     square shoulders poke out at every seam. --}}
                <g>
                    <rect x="104" y="118" width="132" height="82" fill="url(#s3-drum-b)"/>
                    <ellipse cx="170" cy="200" rx="66" ry="21" fill="url(#s3-drum-a)"/>

                    {{-- seams: narrower + softer so they read as bands, not edges --}}
                    <ellipse cx="170" cy="146" rx="66" ry="21" fill="url(#s3-drum-c)" opacity=".85"/>
                    <ellipse cx="170" cy="173" rx="66" ry="21" fill="url(#s3-drum-b)" opacity=".85"/>

                    {{-- open top --}}
                    <ellipse cx="170" cy="118" rx="66" ry="21"
                             fill="var(--color-card-bg)" fill-opacity=".55"
                             stroke="var(--color-teal)" stroke-width="1.5" stroke-opacity=".7"/>
                    <ellipse cx="170" cy="118" rx="44" ry="13" fill="var(--color-teal)" fill-opacity=".18"/>
                </g>

                {{-- objects dropping in, tilted so they feel in motion --}}
                <g transform="rotate(-9 132 62)">
                    <rect x="108" y="44" width="48" height="36" rx="6" fill="url(#s3-card)"
                          stroke="var(--illust-stroke)" stroke-width="1.3"/>
                    <rect x="116" y="54" width="24" height="3" rx="1.5" fill="var(--illust-stroke-bold)"/>
                    <rect x="116" y="62" width="32" height="3" rx="1.5" fill="var(--illust-stroke)"/>
                    <rect x="116" y="70" width="18" height="3" rx="1.5" fill="var(--illust-stroke)"/>
                </g>
                <g transform="rotate(11 208 56)">
                    <rect x="186" y="34" width="44" height="34" rx="6" fill="url(#s3-card)"
                          stroke="var(--illust-stroke)" stroke-width="1.3"/>
                    <circle cx="198" cy="46" r="4" fill="var(--color-teal)" fill-opacity=".55"/>
                    <path d="M190 62l11-12 9 9 6-5 8 8z" fill="var(--color-teal)" fill-opacity=".3"/>
                </g>

                {{-- motion hints into the mouth of the stack --}}
                <path d="M140 86c4 9 8 15 14 20" stroke="var(--color-teal)" stroke-opacity=".55"
                      stroke-width="2" stroke-linecap="round" stroke-dasharray="2 7"/>
                <path d="M204 74c-3 12-8 20-14 26" stroke="var(--color-teal)" stroke-opacity=".45"
                      stroke-width="2" stroke-linecap="round" stroke-dasharray="2 7"/>

                {{-- durability seal --}}
                <circle cx="256" cy="150" r="21" fill="var(--color-card-bg)"
                        stroke="var(--color-teal)" stroke-width="2"/>
                <path d="M246 150l7 7 14-15" stroke="var(--color-teal)" stroke-width="3"
                      stroke-linecap="round" stroke-linejoin="round"/>

                {{-- delivery edge node --}}
                <circle cx="74" cy="150" r="17" fill="var(--color-card-bg)"
                        stroke="var(--illust-stroke)" stroke-width="1.5"/>
                <path d="M74 142v16M66 150h16" stroke="var(--illust-stroke-bold)" stroke-width="1.6" stroke-linecap="round"/>
                <ellipse cx="74" cy="150" rx="7" ry="16" stroke="var(--illust-stroke)" stroke-width="1.2"/>
                <path d="M91 158q12 8 22 12" stroke="var(--illust-stroke)" stroke-width="1.4"
                      stroke-linecap="round" stroke-dasharray="3 5"/>

                <circle cx="286" cy="72" r="4" fill="var(--color-teal)" fill-opacity=".5"/>
                <circle cx="58" cy="86" r="3" fill="var(--color-teal)" fill-opacity=".35"/>
                <circle cx="300" cy="192" r="2.5" fill="var(--color-teal)" fill-opacity=".3"/>
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
