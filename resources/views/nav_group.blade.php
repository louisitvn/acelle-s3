{{-- Admin sidebar group contributed via the `admin.sidebar.groups.top` hook.

     Self-contained markup on the host's own nav classes, because the hook
     collects rendered strings — a second plugin contributing to the same hook
     renders its own group beside this one. --}}
<div class="mc-nav-group">
    <div class="mc-nav-group-label">{{ trans('s3::messages.nav.group') }}</div>

    {{-- Points at the HOST picker, not this plugin's settings: choosing the
         engine is one decision and it lives on one page. --}}
    <a href="{{ route('refactor.admin.settings.storage') }}"
       class="mc-nav-item {{ request()->is('rui/admin/settings/storage*') ? 'active' : '' }}">
        <span class="mc-nav-item-icon">
            <span class="material-symbols-rounded" style="font-size:20px">database</span>
        </span>
        <span class="mc-nav-item-label">{{ trans('s3::messages.nav.storage_engine') }}</span>
        <span class="mc-nav-badge">{{ trans('s3::messages.nav.badge') }}</span>
    </a>
</div>
