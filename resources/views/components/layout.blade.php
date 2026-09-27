@props(['title' => 'Dashboard', 'showGuestHeader' => true])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · RHU Calasiao</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/rhu-favicon.svg') }}?v={{ filemtime(public_path('images/rhu-favicon.svg')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-slate-900 antialiased"
    x-data="{ menuOpen: false, collapsed: false, accountOpen: false, sidebarWidth: 288, resizing: false, dragStart: 0, dragWidth: 0, pointerId: null,
        restoreSidebar() {
            try {
                this.collapsed = localStorage.getItem('rhu-sidebar-collapsed') === '1';
                const saved = Number(localStorage.getItem('rhu-sidebar-width'));
                if (Number.isFinite(saved) &amp;&amp; saved >= 240 &amp;&amp; saved &lt;= 420) this.sidebarWidth = saved;
            } catch (e) {}
        },
        saveSidebar() {
            try {
                localStorage.setItem('rhu-sidebar-collapsed', this.collapsed ? '1' : '0');
                localStorage.setItem('rhu-sidebar-width', String(this.sidebarWidth));
            } catch (e) {}
        },
        toggleSidebar() { this.collapsed = !this.collapsed; this.accountOpen = false; this.saveSidebar() },
        startResize(event) {
            if (event.button !== 0) return;
            this.resizing = true; this.accountOpen = false;
            this.dragStart = event.clientX; this.dragWidth = this.collapsed ? 80 : this.sidebarWidth;
            this.pointerId = event.pointerId;
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        resize(event) {
            if (!this.resizing || event.pointerId !== this.pointerId) return;
            const width = this.dragWidth + event.clientX - this.dragStart;
            this.collapsed = width &lt;= 160;
            if (!this.collapsed) this.sidebarWidth = Math.round(Math.min(420, Math.max(240, width)));
        },
        finishResize() { if (this.resizing) { this.resizing = false; this.pointerId = null; this.saveSidebar() } },
        keyboardResize(direction) {
            this.accountOpen = false;
            if (direction === 'min' || (direction === 'left' &amp;&amp; (this.collapsed || this.sidebarWidth &lt;= 240))) this.collapsed = true;
            else {
                this.sidebarWidth = direction === 'max' ? 420 : this.collapsed ? 240 : Math.min(420, Math.max(240, this.sidebarWidth + (direction === 'left' ? -20 : 20)));
                this.collapsed = false;
            }
            this.saveSidebar();
        }
    }"
    x-init="restoreSidebar()"
    :class="{ 'sidebar-collapsed': collapsed, 'sidebar-resizing': resizing }"
    :style="{ '--sidebar-width': sidebarWidth + 'px' }"
    @pointerup.window="finishResize()" @pointercancel.window="finishResize()" @blur.window="finishResize()"
    @keydown.escape.window="if (accountOpen) { accountOpen = false; $refs.accountButton.focus() } else if (menuOpen) { menuOpen = false; $refs.menuButton.focus() }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:p-4">Skip to content</a>
    @auth
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 lg:hidden">
            <span class="font-semibold text-emerald-900">RHU Calasiao</span>
            <button type="button" x-ref="menuButton" @click="menuOpen = !menuOpen; accountOpen = false" aria-controls="staff-sidebar" aria-expanded="false" :aria-expanded="menuOpen.toString()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Menu</button>
        </div>
        <aside id="staff-sidebar" class="staff-sidebar hidden flex-col border-b border-emerald-100 bg-white lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:border-b-0 lg:border-r" :class="{ 'hidden': !menuOpen, 'flex': menuOpen }">
            <div class="sidebar-brand flex items-center gap-2 px-3 py-5">
                <button type="button" @click="toggleSidebar()" aria-label="Expand sidebar" title="Expand sidebar" aria-controls="staff-sidebar" :aria-expanded="(!collapsed).toString()" class="sidebar-logo-toggle group relative hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg hover:bg-stone-100">
                    <img src="{{ asset('images/rhu-logo.jpg') }}" alt="" class="h-8 w-8 object-contain group-hover:opacity-0 group-focus-visible:opacity-0">
                    <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center text-slate-600 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100"><x-sidebar-icon name="collapse" /></span>
                </button>
                <img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU Calasiao logo" class="sidebar-brand-logo h-8 w-8 shrink-0 object-contain">
                <strong class="sidebar-label flex-1 whitespace-nowrap text-sm text-emerald-950">RHU Calasiao</strong>
                <button type="button" @click="toggleSidebar()" aria-controls="staff-sidebar" :aria-expanded="(!collapsed).toString()" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" class="sidebar-toggle hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-stone-100 lg:flex">
                    <x-sidebar-icon name="collapse" />
                </button>
            </div>
            <nav aria-label="Main navigation" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-6">
                @if(auth()->user()->must_change_password)
                    <a href="{{ route('password.edit') }}" class="sidebar-link" aria-current="page"><x-sidebar-icon name="staff" /><span class="sidebar-label">Change password</span></a>
                @else
                @can('dashboard.view')
                    <a href="{{ route('dashboard') }}" aria-label="Dashboard" title="Dashboard" @if(request()->routeIs('dashboard')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="dashboard" /><span class="sidebar-label">Dashboard</span></a>
                @endcan
                @can('viewAny', App\Models\Patient::class)
                    <a href="{{ route('patients.index') }}" aria-label="Patients" title="Patients" @if(request()->routeIs('patients.*') && !request()->routeIs('patients.visits.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="patients" /><span class="sidebar-label">Patients</span></a>
                @endcan
                @if(auth()->user()->can('viewAny', App\Models\Visit::class) || auth()->user()->can('analytics.overview'))
                    <a href="{{ route('visits.index') }}" aria-label="Patient visits" title="Patient visits" @if(request()->routeIs('visits.*', 'patients.visits.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Patient visits</span></a>
                @endif
                @can('vitals.view')
                    <a href="{{ route('assessments.index') }}" aria-label="Initial assessment" title="Initial assessment" @if(request()->routeIs('assessments.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Initial assessment</span></a>
                @endcan
                @can('eligibility.view')
                    <a href="{{ route('eligibility.index') }}" aria-label="PhilHealth confirmation" title="PhilHealth confirmation" @if(request()->routeIs('eligibility.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">PhilHealth confirmation</span></a>
                @endcan
                @can('consultations.view')
                    <a href="{{ route('consultations.index') }}" aria-label="Doctor visits" title="Doctor visits" @if(request()->routeIs('consultations.*', 'itr.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Doctor visits</span></a>
                @endcan
                @can('laboratory.view')
                    <a href="{{ route('laboratory.index') }}" aria-label="Laboratory" title="Laboratory" @if(request()->routeIs('laboratory.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Laboratory</span></a>
                @endcan
                @can('midwife-care.view')
                    <a href="{{ route('midwife-care.index') }}" aria-label="Midwife care" title="Midwife care" @if(request()->routeIs('midwife-care.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Midwife care</span></a>
                @endcan
                @can('vaccinations.view')
                    <a href="{{ route('vaccinations.index') }}" aria-label="Vaccination" title="Vaccination" @if(request()->routeIs('vaccinations.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Vaccination</span></a>
                @endcan
                @can('pharmacy.view')
                    <a href="{{ route('pharmacy.index') }}" aria-label="Pharmacy" title="Pharmacy" @if(request()->routeIs('pharmacy.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Pharmacy</span></a>
                @endcan
                @can('analytics.view')
                    <a href="{{ route('analytics.index') }}" aria-label="Analytics" title="Analytics" @if(request()->routeIs('analytics.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="analytics" /><span class="sidebar-label">Analytics</span></a>
                @endcan
                @can('reports.view')
                    <a href="{{ route('reports.index') }}" aria-label="Reports" title="Reports" @if(request()->routeIs('reports.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Reports</span></a>
                @endcan
                @can('users.manage')
                    @can('roles.manage')
                        <a href="{{ route('staff.index') }}" aria-label="User Accounts" title="User Accounts" @if(request()->routeIs('staff.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="staff" /><span class="sidebar-label">User Accounts</span></a>
                    @endcan
                @endcan
                @can('settings.manage')
                    <a href="{{ route('services.index') }}" aria-label="Services" title="Services" @if(request()->routeIs('services.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="staff" /><span class="sidebar-label">Services</span></a>
                @endcan
                @can('audit.view')
                    <a href="{{ route('audit.index') }}" aria-label="Audit log" title="Audit log" @if(request()->routeIs('audit.*')) aria-current="page" @endif class="sidebar-link"><x-sidebar-icon name="visits" /><span class="sidebar-label">Audit log</span></a>
                @endcan
                @endif
            </nav>
            <div class="relative mt-auto border-t border-slate-100 p-3" @click.outside="accountOpen = false" @focusout="if (!$el.contains($event.relatedTarget)) accountOpen = false">
                <div id="account-panel" x-cloak x-show="accountOpen" x-transition.opacity class="absolute bottom-full left-3 z-50 mb-2 w-72 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-white/10 bg-neutral-800 p-3 text-white shadow-xl">
                    <div class="flex items-start gap-3 border-b border-white/15 px-2 pb-4 pt-2">
                        <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-sm font-semibold">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="min-w-0"><p class="break-words text-sm font-medium">{{ auth()->user()->name }}</p><p class="mt-1 text-xs leading-5 text-neutral-300">{{ auth()->user()->getRoleNames()->join(' · ') }}</p></div>
                    </div>
                    <a href="{{ route('password.edit') }}" class="mt-2 block rounded-lg px-3 py-3 text-sm hover:bg-white/10">Change password</a>
                    <form method="POST" action="{{ route('logout') }}" class="pt-2">
                        @csrf
                        <button type="submit" x-ref="logoutButton" class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left text-sm hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-emerald-300"><x-sidebar-icon name="logout" />Log out</button>
                    </form>
                </div>
                <button type="button" x-ref="accountButton" @click="accountOpen = !accountOpen; if (accountOpen) $nextTick(() => $refs.logoutButton.focus())" aria-controls="account-panel" aria-expanded="false" :aria-expanded="accountOpen.toString()" aria-label="Open account menu for {{ auth()->user()->name }}" title="{{ auth()->user()->name }}" class="account-trigger flex w-full items-center gap-3 rounded-xl px-2 py-3 text-left hover:bg-stone-100">
                    <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="sidebar-label min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ auth()->user()->name }}</span><span class="mt-1 block text-xs text-slate-500">{{ auth()->user()->getRoleNames()->join(' · ') }}</span></span>
                </button>
            </div>
            <div role="separator" aria-label="Resize sidebar" aria-orientation="vertical" aria-controls="staff-sidebar" tabindex="0" aria-valuemin="80" aria-valuemax="420" :aria-valuenow="collapsed ? 80 : sidebarWidth" :aria-valuetext="collapsed ? 'Collapsed to icons' : sidebarWidth + ' pixels wide'" title="Drag to resize sidebar" class="sidebar-resize-handle hidden lg:block"
                @pointerdown.prevent="startResize($event)" @pointermove="resize($event)" @lostpointercapture="finishResize()"
                @keydown.left.prevent="keyboardResize('left')" @keydown.right.prevent="keyboardResize('right')" @keydown.home.prevent="keyboardResize('min')" @keydown.end.prevent="keyboardResize('max')"></div>
        </aside>
    @else
        @if($showGuestHeader)
        <header class="border-b border-emerald-100 bg-white">
            <a href="{{ url('/') }}" class="mx-auto flex max-w-6xl items-center gap-3 px-6 py-5">
                <img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU Calasiao logo" class="h-12 w-12 object-contain">
                <span><strong class="block text-emerald-900">RHU Calasiao</strong><span class="text-sm text-slate-500">Patient Records Management System</span></span>
            </a>
        </header>
        @endif
    @endauth
    <div @class(['staff-content' => auth()->check()])>
        <main id="main" tabindex="-1" class="mx-auto min-w-0 max-w-6xl px-5 py-8 sm:px-8 lg:py-10">
            @if(session('status'))<x-alert class="mb-6" dismissible>{{ session('status') }}</x-alert>@endif
            @if(session('auth_notice'))<x-alert type="error" title="Session updated" class="mb-6">{{ session('auth_notice') }}</x-alert>@endif
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
