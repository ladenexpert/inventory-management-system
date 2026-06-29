<x-app-layout :title="'Stock Take ' . $session->session_code">
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">Stock Take Session {{ $session->session_code }}</h2>
                <p class="text-sm text-muted-foreground mt-1">Existing batches only. Posting uses the current batch quantity guard and remains operational-only for RNI with no finance side effects.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-secondary-button :href="route('stock-take.index')">Back to Stock Take</x-secondary-button>
                @if(auth()->user()?->hasPermission('stock_take', 'export'))
                    <x-secondary-button :href="route('stock-take.export', ['stockTakeSession' => $session, 'format' => 'xlsx'])">Export XLSX</x-secondary-button>
                    <x-secondary-button :href="route('stock-take.export', ['stockTakeSession' => $session, 'format' => 'csv'])">Export CSV</x-secondary-button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Processed</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['processed_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-green-700">Valid</div>
                    <div class="mt-2 text-2xl font-semibold text-green-800">{{ $summary['valid_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-amber-700">Needs Adjustment</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-800">{{ $summary['adjustment_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-blue-700">Zero Variance</div>
                    <div class="mt-2 text-2xl font-semibold text-blue-800">{{ $summary['zero_variance_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-red-700">Errors / Unmatched</div>
                    <div class="mt-2 text-2xl font-semibold text-red-800">{{ $summary['error_rows'] + $summary['stale_rows'] }}</div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-emerald-700">Posted Rows</div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-800">{{ $summary['posted_rows'] }}</div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-white p-6 space-y-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Session Evidence</h3>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-4 text-sm text-gray-700">
                            <div><span class="font-medium">Status:</span> {{ Str::headline($session->status) }}</div>
                            <div><span class="font-medium">Reference:</span> {{ $session->reference ?: '-' }}</div>
                            <div><span class="font-medium">Imported:</span> {{ $session->imported_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            <div><span class="font-medium">Imported By:</span> {{ $session->importedByUser?->name ?? 'System' }}</div>
                            <div><span class="font-medium">Reviewed:</span> {{ $session->reviewed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            <div><span class="font-medium">Reviewed By:</span> {{ $session->reviewedByUser?->name ?? '-' }}</div>
                            <div><span class="font-medium">Posted:</span> {{ $session->posted_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            <div><span class="font-medium">Posted By:</span> {{ $session->postedByUser?->name ?? '-' }}</div>
                            <div><span class="font-medium">Closed:</span> {{ $session->closed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            <div><span class="font-medium">Closed By:</span> {{ $session->closedByUser?->name ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if(in_array($session->status, ['imported', 'reviewed'], true) && auth()->user()?->hasPermission('stock_take', 'update'))
                            <form action="{{ route('stock-take.recalculate', $session) }}" method="POST">
                                @csrf
                                <x-secondary-button type="submit">Recalculate Variance</x-secondary-button>
                            </form>
                        @endif

                        @if(in_array($session->status, ['imported', 'reviewed'], true) && auth()->user()?->hasPermission('stock_take', 'confirm'))
                            <form action="{{ route('stock-take.apply', $session) }}" method="POST">
                                @csrf
                                <x-primary-button type="submit" :disabled="$summary['error_rows'] > 0">Post Adjustment</x-primary-button>
                            </form>
                        @endif

                        @if($session->status === 'posted' && auth()->user()?->hasPermission('stock_take', 'confirm'))
                            <form action="{{ route('stock-take.close', $session) }}" method="POST">
                                @csrf
                                <x-primary-button type="submit">Close Session</x-primary-button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                    Stock Take remains quantity-focused in RNI. Finance stays disabled, batch unit cost is never overwritten, and any valuation shown here is admin-visible reporting only.
                </div>

                @if($summary['error_rows'] > 0)
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        This session contains unmatched or invalid rows. Posting is blocked until the file is corrected and re-imported.
                    </div>
                @endif

                @if($summary['stale_rows'] > 0)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        One or more batch quantities changed after review. Recalculate the session before posting so the variance uses the latest system quantity.
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Row</th>
                                <th class="px-4 py-3 text-left">SKU</th>
                                <th class="px-4 py-3 text-left">Item Code</th>
                                <th class="px-4 py-3 text-left">Material</th>
                                <th class="px-4 py-3 text-left">Batch No</th>
                                <th class="px-4 py-3 text-right">System Qty</th>
                                <th class="px-4 py-3 text-right">Counted Qty</th>
                                <th class="px-4 py-3 text-right">Variance Qty</th>
                                <th class="px-4 py-3 text-left">Expiry Date</th>
                                <th class="px-4 py-3 text-left">Storage Location</th>
                                <th class="px-4 py-3 text-left">Reference Number</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                @if($canViewValuation)
                                    <th class="px-4 py-3 text-right">Unit Cost</th>
                                    <th class="px-4 py-3 text-right">Adjustment Value</th>
                                    <th class="px-4 py-3 text-right">Inventory Value</th>
                                    <th class="px-4 py-3 text-right">Average Cost</th>
                                @endif
                                <th class="px-4 py-3 text-left">Notes</th>
                                <th class="px-4 py-3 text-left">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($rows as $row)
                                @php
                                    $statusClass = match ($row->status) {
                                        'imported' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'reviewed' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'stale' => 'bg-red-50 text-red-700 border-red-200',
                                        'posted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'closed' => 'bg-zinc-100 text-zinc-700 border-zinc-200',
                                        'error' => 'bg-red-50 text-red-700 border-red-200',
                                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                                    };
                                    $unitCost = (int) ($row->batch?->unit_cost ?? 0);
                                    $adjustmentValue = $row->variance_qty === null ? null : ((int) $row->variance_qty * $unitCost);
                                    $inventoryValue = (int) (($row->batch?->available_quantity ?? 0) * $unitCost);
                                    $averageCost = (int) (($row->product?->purchase_price ?? $row->batch?->product?->purchase_price) ?? 0);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->row_number }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->sku ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->item_code ?: '-' }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row->material_name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->batch_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">{{ $row->system_qty === null ? '-' : number_format($row->system_qty) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">{{ $row->counted_qty === null ? '-' : number_format($row->counted_qty) }}</td>
                                    <td class="px-4 py-3 text-right {{ ($row->variance_qty ?? 0) < 0 ? 'text-red-600' : (($row->variance_qty ?? 0) > 0 ? 'text-emerald-600' : 'text-gray-600') }}">{{ $row->variance_qty === null ? '-' : number_format($row->variance_qty) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->expiry_date?->format('Y-m-d') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->storage_location ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row->reference ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                                            {{ Str::headline($row->status) }}
                                        </span>
                                    </td>
                                    @if($canViewValuation)
                                        <td class="px-4 py-3 text-right text-gray-700">{{ $row->status === 'error' ? '-' : format_money($unitCost) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">{{ $row->status === 'error' || $adjustmentValue === null ? '-' : format_money($adjustmentValue) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">{{ $row->status === 'error' ? '-' : format_money($inventoryValue) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">{{ $row->status === 'error' ? '-' : format_money($averageCost) }}</td>
                                    @endif
                                    <td class="px-4 py-3 text-gray-600">{{ $row->notes ?: '-' }}</td>
                                    <td class="px-4 py-3 text-red-700">{{ $row->error_message ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canViewValuation ? 18 : 14 }}" class="px-4 py-10 text-center text-gray-500">No stock take rows available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t bg-gray-50 px-4 py-3">
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
