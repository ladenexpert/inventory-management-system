<x-app-layout title="Batches">
    @php
        $today = now()->startOfDay();
        $nearExpiryDays = app(\App\Services\BatchPolicyService::class)->nearExpiryThresholdDays();
        $nearExpiryUntil = $today->copy()->addDays($nearExpiryDays)->endOfDay();
        $activeCount = \App\Models\Batch::query()
            ->where('available_quantity', '>', 0)
            ->where('source', '!=', 'quarantined')
            ->where(function ($query) use ($nearExpiryUntil) {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>', $nearExpiryUntil);
            })
            ->count();
        $nearExpiryCount = \App\Models\Batch::query()
            ->where('available_quantity', '>', 0)
            ->where('source', '!=', 'quarantined')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $nearExpiryUntil)
            ->count();
        $expiredCount = \App\Models\Batch::query()
            ->where('available_quantity', '>', 0)
            ->where('source', '!=', 'quarantined')
            ->whereDate('expiry_date', '<', $today)
            ->count();
        $depletedCount = \App\Models\Batch::query()
            ->where('available_quantity', '<=', 0)
            ->count();
        $quarantinedCount = \App\Models\Batch::query()
            ->where('source', 'quarantined')
            ->count();
        $zeroCostCount = \App\Models\Batch::query()
            ->where('available_quantity', '>', 0)
            ->where('unit_cost', 0)
            ->count();
    @endphp
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Batch Monitoring') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">Track expiry, physical form, and storage location by batch number.</p>
            </div>
            <x-secondary-button href="{{ route('products.index') }}">
                {{ __('Back to Products') }}
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Active</div>
                    <div class="mt-2 text-2xl font-bold text-emerald-600">{{ $activeCount }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Near Expiry</div>
                    <div class="mt-2 text-2xl font-bold text-amber-600">{{ $nearExpiryCount }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Expired</div>
                    <div class="mt-2 text-2xl font-bold text-red-600">{{ $expiredCount }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Depleted</div>
                    <div class="mt-2 text-2xl font-bold text-zinc-700">{{ $depletedCount }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Quarantined</div>
                    <div class="mt-2 text-2xl font-bold text-violet-600">{{ $quarantinedCount }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs font-semibold uppercase text-muted-foreground">Zero Cost</div>
                    <div class="mt-2 text-2xl font-bold text-sky-600">{{ $zeroCostCount }}</div>
                </div>
            </div>

            <livewire:batches.batch-table />
        </div>
    </div>
</x-app-layout>
