@php
    $user = auth()->user();
    $isMaterialUsage = ($context ?? 'sale') === 'material_usage';
    $pageTitle = $isMaterialUsage ? 'Material Usage Details' : 'Legacy Sale Details';
    $infoTitle = $isMaterialUsage ? 'Material Usage Information' : 'Legacy Sale Information';
    $infoDescription = $isMaterialUsage
        ? 'Details of the issued raw material transaction.'
        : 'Details of the legacy sales transaction.';
    $documentLabel = 'Transaction Number';
    $referenceLabel = $isMaterialUsage ? 'Reference' : 'Invoice Reference';
    $canViewUsageValue = !$isMaterialUsage || (($user?->canViewInventoryValue() ?? false) || ($user?->canAccessFinance() ?? false));
    $ownsMaterialUsage = $isMaterialUsage && $user && $sale->created_by === $user->id;
    $canCompleteUsage = !$isMaterialUsage || (($user?->hasPermission('material_usage', 'confirm') ?? false) && (!($user?->isRmDesk() ?? false) || $ownsMaterialUsage));
    $canCancelUsage = !$isMaterialUsage || (($user?->hasPermission('material_usage', 'cancel') ?? false) && (!($user?->isRmDesk() ?? false) || $ownsMaterialUsage));
    $canRestoreUsage = !$isMaterialUsage || (($user?->hasPermission('material_usage', 'restore') ?? false) && (!($user?->isRmDesk() ?? false) || $ownsMaterialUsage));
    $unitAmountLabel = $isMaterialUsage && !$canViewUsageValue ? 'Visibility' : ($isMaterialUsage ? 'Unit Cost' : 'Price');
    $adjustmentLabel = $isMaterialUsage && !$canViewUsageValue ? 'Visibility' : ($isMaterialUsage ? 'Total Cost' : 'Discount');
    $lineTotalLabel = $isMaterialUsage && !$canViewUsageValue ? 'Visibility' : ($isMaterialUsage ? 'Issued Cost' : 'Subtotal');
@endphp
<x-app-layout :title="$pageTitle">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __($pageTitle) }} #{{ $sale->display_transaction_number }}
            </h2>
            <div class="flex items-center gap-2">
                <x-secondary-button href="{{ route($indexRoute ?? 'sales.index') }}">
                    &larr; {{ __('Back to List') }}
                </x-secondary-button>
                <x-primary-button href="{{ route($printRoute ?? 'sales.print', $sale) }}" target="_blank">
                    <x-heroicon-o-printer class="w-4 h-4 mr-2" />
                    {{ $isMaterialUsage ? __('Print Usage Slip') : __('Print Invoice') }}
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Main Info Card -->
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
                <div class="p-6">
                    <!-- Header Info -->
                    <div class="flex items-start justify-between border-b border-gray-100 pb-4 mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ __($infoTitle) }}</h3>
                            <p class="text-sm text-gray-500">{{ __($infoDescription) }}</p>
                        </div>
                        <div class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                            ID: #{{ $sale->id }}
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Customer -->
                        @if(!$isMaterialUsage)
                        <x-detail-item label="Customer" :value="$sale->customer->name ?? 'Guest'">
                            <x-heroicon-o-user class="w-4 h-4 text-gray-400" />
                        </x-detail-item>
                        @endif

                        <x-detail-item :label="$documentLabel" :value="$sale->display_transaction_number">
                            <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <x-detail-item :label="$referenceLabel" :value="$sale->reference_number ?? '-'">
                            <x-heroicon-o-document-duplicate class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <!-- Sale Date -->
                        <x-detail-item :label="$isMaterialUsage ? 'Usage Date' : 'Sale Date'" :value="($sale->usage_date ?? $sale->sale_date)->format('d M Y')">
                            <x-heroicon-o-calendar class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <!-- Payment Method -->
                        @if(!$isMaterialUsage)
                        <x-detail-item label="Payment Method" :value="$sale->payment_method->label()">
                            <x-heroicon-o-credit-card class="w-4 h-4 text-gray-400" />
                        </x-detail-item>
                        @endif

                        @if($isMaterialUsage)
                        <x-detail-item label="Purpose" :value="$sale->purpose ?? '-'">
                            <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <x-detail-item label="Formula" :value="$sale->formula ?? '-'">
                            <x-heroicon-o-beaker class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <x-detail-item label="Team" :value="$sale->team?->name ?? $sale->project ?? '-'">
                            <x-heroicon-o-briefcase class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <x-detail-item label="Requested By" :value="$sale->requested_by ?? '-'">
                            <x-heroicon-o-user class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <x-detail-item label="Issued By" :value="$sale->issuer->name ?? $sale->creator->name ?? 'Unknown'">
                            <x-heroicon-o-user class="w-4 h-4 text-gray-400" />
                        </x-detail-item>
                        @endif

                        <!-- Status -->
                        <div>
                            <label class="text-sm font-medium leading-none text-gray-500">Status</label>
                            <div class="mt-1">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $sale->status->color() }}">
                                    {{ $sale->status->label() }}
                                </span>
                            </div>
                        </div>



                        <!-- Created By -->
                        <x-detail-item label="Created By" :value="$sale->creator->name ?? 'Unknown'">
                            <x-heroicon-o-user class="w-4 h-4 text-gray-400" />
                        </x-detail-item>
                    </div>

                    <!-- Notes -->
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <div class="space-y-1">
                            <label class="text-sm font-medium leading-none text-gray-500">
                                Notes
                            </label>
                            <div class="bg-gray-50 p-3 rounded-md border border-gray-100">
                                <p class="text-sm text-slate-700 italic leading-relaxed">{{ $sale->notes ?: 'No additional notes.' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table Section -->
                    <div class="mt-6 border-t overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3">SKU</th>
                                    <th class="px-6 py-3">Item Code IERP</th>
                                    <th class="px-6 py-3">Product</th>
                                    <th class="px-6 py-3">Batch Allocation</th>
                                    <th class="px-6 py-3">UOM</th>
                                    <th class="px-6 py-3 text-center">Qty</th>
                                    <th class="px-6 py-3 text-right">{{ $unitAmountLabel }}</th>
                                    <th class="px-6 py-3 text-right">{{ $adjustmentLabel }}</th>
                                    <th class="px-6 py-3 text-right">{{ $lineTotalLabel }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($sale->items as $item)
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $item->product->sku_display }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $item->product->item_code_ierp_display }}
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $item->product->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            @if($item->saleItemBatches->isNotEmpty())
                                                <div class="space-y-1">
                                                    @foreach($item->saleItemBatches as $allocation)
                                                        <div>
                                                            <span class="font-medium">{{ $allocation->batch?->batch_number ?? 'Batch deleted' }}</span>
                                                            <span class="text-xs text-gray-500">({{ $allocation->quantity }})</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-400">Legacy stock</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $item->product->unit->symbol ?? $item->product->unit->name ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            {{ number_format($item->quantity) }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @if($isMaterialUsage && !$canViewUsageValue)
                                                <span class="text-gray-400">Restricted</span>
                                            @else
                                                @money($isMaterialUsage ? ($item->cost_price ?: $item->unit_price) : $item->unit_price)
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right {{ $isMaterialUsage ? 'text-gray-700' : 'text-red-500' }}">
                                            @if($isMaterialUsage)
                                                @if($canViewUsageValue)
                                                    @money($item->total_cost)
                                                @else
                                                    <span class="text-gray-400">Restricted</span>
                                                @endif
                                            @else
                                                {!! $item->discount > 0 ? "- <span>" . format_money($item->discount) . "</span>" : '-' !!}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right font-medium">
                                            @if($isMaterialUsage && !$canViewUsageValue)
                                                <span class="text-gray-400">Restricted</span>
                                            @else
                                                @money($isMaterialUsage ? $item->total_cost : $item->subtotal)
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                @if($isMaterialUsage)
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-right">Total Issued Cost</td>
                                        <td class="px-6 py-4 text-right text-indigo-600 text-lg">
                                            @if($canViewUsageValue)
                                                @money($sale->items->sum('total_cost'))
                                            @else
                                                <span class="text-gray-400">Restricted</span>
                                            @endif
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-right">Subtotal</td>
                                        <td class="px-6 py-4 text-right text-gray-700">
                                            @money($sale->subtotal)
                                        </td>
                                    </tr>
                                    @if($sale->total_discount > 0)
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-right text-red-600">Total Discount (Items)</td>
                                            <td class="px-6 py-4 text-right text-red-600">
                                                - @money($sale->total_discount - $sale->global_discount)
                                            </td>
                                        </tr>
                                    @endif
                                    @if($sale->global_discount > 0)
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-right text-red-600">Global Discount (Transaction)</td>
                                            <td class="px-6 py-4 text-right text-red-600">
                                                - @money($sale->global_discount)
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-right">Total</td>
                                        <td class="px-6 py-4 text-right text-indigo-600 text-lg">
                                            @money($sale->total)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-right text-gray-600">Cash Received</td>
                                        <td class="px-6 py-4 text-right text-gray-800">
                                            @money($sale->cash_received)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-right text-gray-600">Change</td>
                                        <td class="px-6 py-4 text-right text-green-600">
                                            @money($sale->change)
                                        </td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Workflow -->
            <div x-data="{
                actionUrl: '',
                actionMethod: '',
                modalTitle: '',
                modalMessage: '',
                confirmButtonText: '',
                confirmButtonClass: '',

                confirmAction(url, method, title, message, btnText, btnClass) {
                    this.actionUrl = url;
                    this.actionMethod = method;
                    this.modalTitle = title;
                    this.modalMessage = message;
                    this.confirmButtonText = btnText;
                    this.confirmButtonClass = btnClass;
                    $dispatch('open-modal', { name: 'confirmation-modal' });
                }
            }" class="flex flex-col sm:flex-row justify-end gap-4">

                @if($sale->status === \App\Enums\SaleStatus::PENDING && $canCompleteUsage)
                    {{-- Complete / Pay Action --}}
                    <x-primary-button
                        class="!bg-green-600 hover:!bg-green-700 focus:!ring-green-500"
                        @click="confirmAction('{{ route($completeRoute ?? 'sales.complete', $sale) }}', 'PATCH', '{{ $isMaterialUsage ? 'Complete Material Usage' : 'Complete Legacy Sale' }}', '{{ $isMaterialUsage ? 'Mark this material usage as completed?' : 'Mark this legacy sale as completed? This confirms payment has been received.' }}', '{{ $isMaterialUsage ? 'Complete Usage' : 'Complete Legacy Sale' }}', '!bg-green-600 hover:!bg-green-700 focus:!ring-green-500')"
                    >
                        {{ $isMaterialUsage ? __('Complete Usage') : __('Complete Legacy Sale') }}
                    </x-primary-button>
                @endif

                @if($sale->status === \App\Enums\SaleStatus::PENDING && $canCancelUsage)
                    {{-- Cancel Pending Action (Modal) --}}
                    <div x-data="{ cancelOpen: false }">
                        <x-danger-button @click="cancelOpen = true">
                            {{ $isMaterialUsage ? __('Cancel Usage') : __('Cancel Legacy Sale') }}
                        </x-danger-button>

                        <!-- Cancel Modal -->
                        <div x-show="cancelOpen"
                             style="display: none;"
                             x-transition.opacity
                             class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-75 flex items-center justify-center p-4">

                            <div @click.outside="cancelOpen = false"
                                 x-transition.scale
                                 class="relative bg-white rounded-lg max-w-md w-full p-6 shadow-xl text-left">

                                <h3 class="text-lg font-medium text-gray-900 mb-2">
                                    {{ $isMaterialUsage ? __('Cancel Pending Usage') : __('Cancel Pending Sale') }}
                                </h3>
                                <p class="text-sm text-gray-500 mb-4">
                                    {{ $isMaterialUsage ? __('Are you sure you want to cancel this pending usage? Please provide a reason.') : __('Are you sure you want to cancel this pending sale? Please provide a reason.') }}
                                </p>

                                <form action="{{ route($destroyRoute ?? 'sales.destroy', $sale) }}" method="POST">
                                    @csrf
                                    @method('DELETE')

                                    <div class="mb-4">
                                        <x-input-label for="reason" :value="__('Reason')" />
                                        <textarea
                                            name="reason"
                                            id="reason"
                                            rows="3"
                                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            placeholder="{{ $isMaterialUsage ? 'Reason for cancelling this usage...' : 'Customer changed mind...' }}"
                                            required
                                        ></textarea>
                                    </div>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <x-secondary-button type="button" @click="cancelOpen = false">
                                            {{ __('Back') }}
                                        </x-secondary-button>
                                        <x-danger-button type="submit">
                                            {{ $isMaterialUsage ? __('Cancel Usage') : __('Cancel Legacy Sale') }}
                                        </x-danger-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if($sale->status === \App\Enums\SaleStatus::COMPLETED && $canCancelUsage)
                    {{-- Cancel Action --}}
                    <x-secondary-button
                        class="text-red-600 hover:bg-red-50 border-red-200"
                        @click="confirmAction('{{ route($destroyRoute ?? 'sales.destroy', $sale) }}', 'DELETE', '{{ $isMaterialUsage ? 'Cancel Material Usage' : 'Cancel Legacy Sale' }}', '{{ $isMaterialUsage ? 'Are you sure you want to cancel this material usage? Stock will be returned.' : 'Are you sure you want to cancel this legacy sale? Stock will be returned.' }}', '{{ $isMaterialUsage ? 'Yes, Cancel Usage' : 'Yes, Cancel Legacy Sale' }}', '!bg-red-600 hover:!bg-red-700 focus:!ring-red-500')"
                    >
                        {{ $isMaterialUsage ? __('Cancel Usage') : __('Cancel Legacy Sale') }}
                    </x-secondary-button>
                @endif

                @if($sale->status === \App\Enums\SaleStatus::CANCELLED && $canRestoreUsage)
                    {{-- Restore Action --}}
                    <x-secondary-button
                        class="bg-gray-800 text-white hover:bg-gray-700 focus:ring-gray-500"
                        @click="confirmAction('{{ route($restoreRoute ?? 'sales.restore', $sale) }}', 'PATCH', '{{ $isMaterialUsage ? 'Restore Material Usage' : 'Restore Legacy Sale' }}', '{{ $isMaterialUsage ? 'Restore this usage to pending status?' : 'Restore this legacy sale to pending status? You can then complete it again.' }}', 'Restore to Pending', '!bg-gray-800 hover:!bg-gray-700 text-white')"
                    >
                        {{ __('Restore to Pending') }}
                    </x-secondary-button>
                @endif

                <!-- Shared Confirmation Modal -->
                <x-modal name="confirmation-modal">
                    <div class="p-6" x-data="{ submitting: false }">
                        <h2 class="text-lg font-medium text-gray-900" x-text="modalTitle"></h2>

                        <p class="mt-1 text-sm text-gray-600" x-text="modalMessage"></p>

                        <div class="mt-6 flex justify-end">
                            <x-secondary-button x-on:click="$dispatch('close-modal', { name: 'confirmation-modal' })" x-bind:disabled="submitting">
                                {{ __('Back') }}
                            </x-secondary-button>

                            <form :action="actionUrl" method="POST" class="ml-3" @submit="submitting = true">
                                @csrf
                                <input type="hidden" name="_method" :value="actionMethod">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 text-white shadow-sm bg-primary"
                                    x-bind:class="confirmButtonClass + (submitting ? ' opacity-75 cursor-not-allowed' : '')"
                                    x-bind:disabled="submitting"
                                >
                                    <svg x-show="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="confirmButtonText"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                </x-modal>

            </div>
        </div>
    </div>
</x-app-layout>
