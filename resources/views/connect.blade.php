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

<div class="mc-card" style="max-width:640px">
    <div class="mc-card-body">
        <h2 class="mc-card-title">{{ trans('s3::messages.connect.heading') }}</h2>
        <p class="mc-text-muted">{{ trans('s3::messages.connect.intro') }}</p>

        <form method="POST" action="{{ action([\Acelle\S3\Controllers\SettingsController::class, 'connect']) }}">
            @csrf

            <div class="mc-form-group">
                <label for="s3-access-key" class="mc-form-label">{{ trans('s3::messages.field.access_key') }}</label>
                <input id="s3-access-key" type="text" name="access_key" autocomplete="off"
                       class="mc-form-input @error('access_key') is-invalid @enderror"
                       value="{{ old('access_key', $options['access_key'] ?? '') }}"
                       placeholder="AKIA…">
                @error('access_key')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            <div class="mc-form-group">
                <label for="s3-secret-key" class="mc-form-label">{{ trans('s3::messages.field.secret_key') }}</label>
                {{-- A stored secret is never echoed back into HTML. --}}
                <input id="s3-secret-key" type="password" name="secret_key" autocomplete="off"
                       class="mc-form-input @error('secret_key') is-invalid @enderror"
                       @if (!empty($options['secret_key'])) placeholder="{{ $options['secret_key'] }}" @endif>
                @if (!empty($options['secret_key']))
                    <p class="mc-form-help">{{ trans('s3::messages.field.secret_key.help') }}</p>
                @endif
                @error('secret_key')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            <div class="mc-form-group">
                <label for="s3-region" class="mc-form-label">{{ trans('s3::messages.field.region') }}</label>
                <select id="s3-region" name="region" class="mc-form-input @error('region') is-invalid @enderror">
                    @foreach ($regions as $code => $label)
                        <option value="{{ $code }}"
                            {{ old('region', $options['region'] ?? 'us-east-1') === $code ? 'selected' : '' }}>
                            {{ $code }} — {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('region')<p class="mc-form-error">{{ $message }}</p>@enderror
            </div>

            <div style="margin-top:var(--space-4);display:flex;gap:var(--space-2)">
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
