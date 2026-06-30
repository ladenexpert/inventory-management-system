<x-app-layout title="Stock Movement Classification">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">Stock Movement Classification</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Material-level fast, slow, and dead stock classification based on RNI Material Usage / internal outbound movement only.
            </p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Fast Moving</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format($summary['fast_moving']) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Slow Moving</p>
                    <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($summary['slow_moving']) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium">Dead Stock</p>
                    <p class="mt-2 text-2xl font-bold text-rose-700">{{ number_format($summary['dead_stock']) }}</p>
                </div>
                @if($hasUnclassifiedMaterials)
                    <div class="rounded-xl border bg-card p-4 shadow-sm">
                        <p class="text-sm font-medium">Normal / Unclassified</p>
                        <p class="mt-2 text-2xl font-bold text-slate-700">{{ number_format($summary['normal_unclassified'] + $summary['no_usage_unclassified']) }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b p-4">
                    <h3 class="font-semibold">Classification Summary</h3>
                    <p class="text-xs text-muted-foreground">Materials with stock on hand grouped by outbound-usage age.</p>
                </div>
                <div class="p-4">
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                        RNI pilot classification uses Material Usage / internal outbound movement only. Legacy sales remain part of Sales Analysis and are not mixed into this report.
                    </div>
                    <x-report-chart :config="$classificationChart" height="20rem" />
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <livewire:reports.stock-movement-classification-table />
            </div>
        </div>
    </div>
</x-app-layout>
