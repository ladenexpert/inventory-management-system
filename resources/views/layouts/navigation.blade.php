@php
    $modules = app(\App\Services\ModuleService::class)->all();
    $user = Auth::user();

    $sections = [];

    $dashboardItems = [];
    if ($user->hasPermission('dashboard', 'view') && ($modules['rni'] || $modules['reports'])) {
        $dashboardItems[] = [
            'label' => 'RNI Operations',
            'href' => route('dashboard', ['view' => 'rni-operations']),
            'active' => request()->routeIs('dashboard') && request('view', 'rni-operations') !== 'business-insights',
        ];
        if ($user->canViewInventoryValue() || $user->canAccessFinance()) {
            $dashboardItems[] = [
                'label' => 'Business Insights',
                'href' => route('dashboard', ['view' => 'business-insights']),
                'active' => request()->routeIs('dashboard') && request('view') === 'business-insights',
            ];
        }
    }

    if ($dashboardItems !== []) {
        $sections[] = ['label' => 'Dashboard', 'icon' => 'dashboard', 'items' => $dashboardItems];
    }

    $masterDataItems = [];
    if ($modules['materials'] && $user->hasPermission('materials', 'view')) {
        $masterDataItems[] = ['label' => 'Materials', 'href' => route('products.index'), 'active' => request()->routeIs('products.*')];
        if ($user->hasPermission('master_data', 'view')) {
            $masterDataItems[] = ['label' => 'Categories', 'href' => route('categories.index'), 'active' => request()->routeIs('categories.*')];
            $masterDataItems[] = ['label' => 'Units', 'href' => route('units.index'), 'active' => request()->routeIs('units.*')];
            $masterDataItems[] = ['label' => 'Physical Forms', 'href' => route('physical-forms.index'), 'active' => request()->routeIs('physical-forms.*')];
        }
    }
    if ($user->hasPermission('master_data', 'view') && $modules['purchases']) {
        $masterDataItems[] = ['label' => 'Suppliers', 'href' => route('suppliers.index'), 'active' => request()->routeIs('suppliers.*')];
    }
    if ($user->hasPermission('master_data', 'view') && $modules['sales']) {
        $masterDataItems[] = ['label' => 'Customers', 'href' => route('customers.index'), 'active' => request()->routeIs('customers.*')];
    }
    if ($user->hasPermission('master_data', 'view') && $modules['materials']) {
        $masterDataItems[] = ['label' => 'Storage Locations', 'href' => route('storage-locations.index'), 'active' => request()->routeIs('storage-locations.*')];
    }
    if ($user->hasPermission('master_data', 'view') && $modules['rni']) {
        $masterDataItems[] = ['label' => 'Teams', 'href' => route('teams.index'), 'active' => request()->routeIs('teams.*')];
    }
    if ($masterDataItems !== []) {
        $sections[] = ['label' => 'Master Data', 'icon' => 'master', 'items' => $masterDataItems];
    }

    $operationsItems = [];
    if ($user->hasPermission('material_receipt', 'view') && $modules['rni']) {
        $operationsItems[] = ['label' => 'Material Receipt', 'href' => route('material-receipts.index'), 'active' => request()->routeIs('material-receipts.*')];
    }
    if ($user->hasPermission('legacy_purchase', 'view') && $modules['purchases']) {
        $operationsItems[] = ['label' => 'Legacy Purchases', 'href' => route('purchases.index'), 'active' => request()->routeIs('purchases.*')];
    }
    if ($modules['rni'] && $user->hasPermission('material_usage', 'view')) {
        $operationsItems[] = ['label' => 'Material Usage', 'href' => route('material-usages.index'), 'active' => request()->routeIs('material-usages.*')];
    }
    if ($modules['rni'] && $user->hasAnyPermission([['stock_take', 'view'], ['stock_take', 'import'], ['stock_take', 'confirm']])) {
        $operationsItems[] = ['label' => 'Stock Take', 'href' => route('stock-take.index'), 'active' => request()->routeIs('stock-take.*')];
    }
    if ($user->hasPermission('legacy_sales', 'view') && $modules['sales']) {
        $operationsItems[] = ['label' => 'Legacy Sales', 'href' => route('sales.index'), 'active' => request()->routeIs('sales.*')];
    }
    if ($modules['materials'] && $user->hasPermission('batches', 'view')) {
        $operationsItems[] = ['label' => 'Batch Monitoring', 'href' => route('batches.index'), 'active' => request()->routeIs('batches.*')];
    }
    if ($operationsItems !== []) {
        $sections[] = ['label' => 'Operations', 'icon' => 'operations', 'items' => $operationsItems];
    }

    $financeItems = [];
    if ($modules['finance'] && $user->canAccessFinance()) {
        $financeItems[] = ['label' => 'Transactions', 'href' => route('finance.transactions.index'), 'active' => request()->routeIs('finance.transactions.*')];
        $financeItems[] = ['label' => 'Categories', 'href' => route('finance.categories.index'), 'active' => request()->routeIs('finance.categories.*')];
    }

    $reportItems = [];
    if ($modules['reports'] && $user->hasPermission('reports', 'view')) {
        $reportItems[] = [
            'label' => 'Inventory & Expiry Monitoring',
            'href' => route('reports.inventory'),
            'active' => request()->routeIs('reports.inventory') || request()->routeIs('reports.expiry'),
        ];
        $reportItems[] = ['label' => 'Inventory Movement History', 'href' => route('reports.inventory-movement-history'), 'active' => request()->routeIs('reports.inventory-movement-history*')];
        $reportItems[] = ['label' => 'Usage Report', 'href' => route('reports.usage-history'), 'active' => request()->routeIs('reports.usage-history')];
        if ($user->hasPermission('legacy_purchase', 'view')) {
            $reportItems[] = ['label' => 'Inbound & Purchase Analysis', 'href' => route('reports.purchase-analysis'), 'active' => request()->routeIs('reports.purchase-analysis')];
        }
        if ($user->hasPermission('legacy_sales', 'view')) {
            $reportItems[] = ['label' => 'Sales Analysis', 'href' => route('reports.sales-analysis'), 'active' => request()->routeIs('reports.sales-analysis')];
        }
        $reportItems[] = ['label' => 'Stock Movement Classification', 'href' => route('reports.stock-movement-classification'), 'active' => request()->routeIs('reports.stock-movement-classification')];
    }
    if ($reportItems !== []) {
        $sections[] = ['label' => 'Reports', 'icon' => 'reports', 'items' => $reportItems];
    }
    if ($financeItems !== []) {
        $sections[] = ['label' => 'Finance', 'icon' => 'finance', 'items' => $financeItems];
    }

    $administrationItems = [];
    if ($user->hasPermission('user_access', 'view') && $modules['users']) {
        $administrationItems[] = ['label' => 'Users', 'href' => route('users.index'), 'active' => request()->routeIs('users.*')];
        $administrationItems[] = ['label' => 'Roles', 'href' => route('roles.index'), 'active' => request()->routeIs('roles.*')];
    }
    if ($user->hasPermission('settings', 'view')) {
        $administrationItems[] = ['label' => 'Module Settings', 'href' => route('settings.index'), 'active' => request()->routeIs('settings.*')];
    }
    if ($administrationItems !== []) {
        $sections[] = ['label' => 'Administration', 'icon' => 'admin', 'items' => $administrationItems];
    }
@endphp

<section class="border-b border-border bg-background py-4">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="{ mobileMenuOpen: false }">
        <nav class="hidden items-center justify-between lg:flex">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-8 fill-current text-foreground" />
                    <span class="text-md font-semibold tracking-tighter text-foreground">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="flex items-center gap-0.5">
                    @foreach($sections as $section)
                        @php($isSectionActive = collect($section['items'])->contains(fn ($item) => $item['active']))
                        <x-nav-dropdown active="{{ $isSectionActive }}">
                            <x-slot name="icon">
                                @if($section['icon'] === 'dashboard')
                                    <x-heroicon-o-squares-2x2 class="mr-2 h-4 w-4" />
                                @elseif($section['icon'] === 'master')
                                    <x-heroicon-o-rectangle-stack class="mr-2 h-4 w-4" />
                                @elseif($section['icon'] === 'operations')
                                    <x-heroicon-o-cube class="mr-2 h-4 w-4" />
                                @elseif($section['icon'] === 'reports')
                                    <x-heroicon-o-document-chart-bar class="mr-2 h-4 w-4" />
                                @elseif($section['icon'] === 'finance')
                                    <x-heroicon-o-banknotes class="mr-2 h-4 w-4" />
                                @else
                                    <x-heroicon-o-cog-6-tooth class="mr-2 h-4 w-4" />
                                @endif
                            </x-slot>
                            <x-slot name="trigger">{{ $section['label'] }}</x-slot>
                            <x-slot name="content">
                                @foreach($section['items'] as $item)
                                    <x-dropdown-link :href="$item['href']" :active="$item['active']">
                                        {{ $item['label'] }}
                                    </x-dropdown-link>
                                @endforeach
                            </x-slot>
                        </x-nav-dropdown>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-medium transition-colors">
                            <span class="hidden md:inline-flex">{{ $user->name }}</span>
                            <x-avatar :name="$user->name" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.index')" :active="request()->routeIs('profile.*')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </nav>

        <div class="block lg:hidden">
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-8 fill-current text-foreground" />
                </a>

                <button @click="mobileMenuOpen = true" class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-input bg-background hover:bg-accent hover:text-accent-foreground">
                    <x-heroicon-o-bars-3 class="h-4 w-4" />
                </button>
            </div>

            <div x-show="mobileMenuOpen" class="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm" style="display: none;" @click="mobileMenuOpen = false"></div>

            <div x-show="mobileMenuOpen" class="fixed inset-y-0 right-0 z-50 h-full w-3/4 border-l bg-background p-6 shadow-lg sm:max-w-sm" style="display: none;" @click.stop>
                <div class="flex flex-col gap-6">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                            <x-application-logo class="h-8 w-8 fill-current text-foreground" />
                            <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                        </a>
                        <button @click="mobileMenuOpen = false" class="rounded-sm opacity-70 transition-opacity hover:opacity-100">
                            <span class="sr-only">Close</span>
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="flex flex-col gap-4">
                        @foreach($sections as $section)
                            @php($isSectionActive = collect($section['items'])->contains(fn ($item) => $item['active']))
                            <div x-data="{ expanded: {{ $isSectionActive ? 'true' : 'false' }} }">
                                <button @click="expanded = !expanded" class="flex w-full items-center justify-between text-left text-md font-semibold {{ $isSectionActive ? 'text-primary' : '' }}">
                                    {{ $section['label'] }}
                                    <x-heroicon-o-chevron-down :class="{'rotate-180': expanded}" class="h-4 w-4 shrink-0 transition-transform duration-200" />
                                </button>
                                <div x-show="expanded" x-collapse>
                                    <div class="ml-2 mt-2 flex flex-col gap-2 border-l border-border pl-4">
                                        @foreach($section['items'] as $item)
                                            <a href="{{ $item['href'] }}" class="py-1 text-sm font-medium hover:underline {{ $item['active'] ? 'text-primary' : '' }}">{{ $item['label'] }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-4 border-t border-border pt-4">
                            <div class="mb-2 text-base font-medium text-foreground">{{ $user->name }}</div>
                            <div class="flex flex-col gap-3">
                                <a href="{{ route('profile.index') }}" class="inline-flex h-9 w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground {{ request()->routeIs('profile.*') ? 'bg-accent text-accent-foreground' : '' }}">
                                    Profile
                                </a>
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                                        Log Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
