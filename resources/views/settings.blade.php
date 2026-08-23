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

{{-- Status ------------------------------------------------------------- --}}
<div class="mc-card" style="margin-bottom:var(--space-4)">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.section.status') }}</h2>

        @if ($isActive)
            <p><span class="mc-badge mc-badge-success">{{ trans('s3::messages.status.active') }}</span></p>
        @elseif ($isConfigured)
            <p><span class="mc-badge mc-badge-default">{{ trans('s3::messages.status.configured') }}</span></p>
            <p class="mc-text-muted">
                {{ trans('s3::messages.status.other_active', ['driver' => $activeDriver]) }}
            </p>
        @else
            <p><span class="mc-badge mc-badge-default">{{ trans('s3::messages.status.not_configured') }}</span></p>
        @endif

        {{-- Stated up front, not after the fact: nothing in the app moves
             existing files, so switching is only safe once they are copied. --}}
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

{{-- Credentials --------------------------------------------------------- --}}
<div class="mc-card">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.section.credentials') }}</h2>

        <form method="POST" action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'save']) }}">
            @csrf

            @foreach ($fields as $name => $meta)
                @php
                    $isSecret = ($meta['secret'] ?? false) === true;
                    $isCheckbox = ($meta['type'] ?? 'text') === 'checkbox';
                    $stored = $options[$name] ?? null;
                    $helpKey = 's3::messages.field.' . $name . '.help';
                    $help = trans($helpKey);
                @endphp

                <div class="mc-form-group">
                    @if ($isCheckbox)
                        <label class="mc-form-check">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1"
                                   {{ old($name, $stored) ? 'checked' : '' }}>
                            <span>{{ trans('s3::messages.field.' . $name) }}</span>
                        </label>
                    @else
                        <label for="s3-{{ $name }}" class="mc-form-label">
                            {{ trans('s3::messages.field.' . $name) }}
                        </label>
                        <input id="s3-{{ $name }}"
                               type="{{ $isSecret ? 'password' : 'text' }}"
                               class="mc-form-input @error($name) is-invalid @enderror"
                               name="{{ $name }}"
                               autocomplete="off"
                               {{-- A stored secret is never echoed back; the
                                    placeholder shows it exists and blank keeps it. --}}
                               value="{{ $isSecret ? '' : old($name, $stored) }}"
                               @if ($isSecret && $stored) placeholder="{{ $stored }}" @endif>
                    @endif

                    @if ($help !== $helpKey)
                        <p class="mc-form-help">{{ $help }}</p>
                    @endif

                    @error($name)
                        <p class="mc-form-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div style="margin-top:var(--space-4);display:flex;gap:var(--space-2)">
                <button type="submit" class="mc-btn mc-btn-primary">
                    {{ trans('s3::messages.action.save') }}
                </button>
                <a href="{{ url('rui/admin/plugins') }}" class="mc-btn mc-btn-ghost">
                    {{ trans('refactor/common.cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
