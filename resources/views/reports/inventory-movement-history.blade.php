<x-app-layout title="Inventory Movement History">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">{{ __('Inventory Movement History') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">Unified stock in, stock out, adjustment, receipt, and restore activity from the inventory ledger.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('reports.inventory-movement-history') }}" class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2">
                        <x-input-label for="from_date" :value="__('From Date')" />
                        <x-text-input id="from_date" name="from_date" type="date" :value="$filters['from_date'] ?? ''" class="block w-full" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="to_date" :value="__('To Date')" />
                        <x-text-input id="to_date" name="to_date" type="date" :value="$filters['to_date'] ?? ''" class="block w-full" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="user_id" :value="__('User')" />
                        <select id="user_id" name="user_id" class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="transaction_type" :value="__('Transaction Type')" />
                        <select id="transaction_type" name="transaction_type" class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All transaction types</option>
                            @foreach($transactionTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['transaction_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="rm_code" :value="__('Item Code IERP / SKU')" />
                        <x-text-input id="rm_code" name="rm_code" type="text" :value="$filters['rm_code'] ?? ''" class="block w-full" placeholder="Search IERP code or SKU" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="rm_name" :value="__('Material / Product Name')" />
                        <x-text-input id="rm_name" name="rm_name" type="text" :value="$filters['rm_name'] ?? ''" class="block w-full" placeholder="Material or product name" />
                    </div>

                    <div class="space-y-2">
                        <x-input-label for="lot_number" :value="__('Lot Number')" />
                        <x-text-input id="lot_number" name="lot_number" type="text" :value="$filters['lot_number'] ?? ''" class="block w-full" placeholder="Batch / lot number" />
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <x-primary-button type="submit">Apply Filters</x-primary-button>
                        <x-secondary-button :href="route('reports.inventory-movement-history')">Reset</x-secondary-button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-secondary-button :href="route('reports.inventory-movement-history.export', array_merge(['format' => 'xlsx'], $filters))">
                            Export XLSX
                        </x-secondary-button>
                        <x-secondary-button :href="route('reports.inventory-movement-history.export', array_merge(['format' => 'csv'], $filters))">
                            Export CSV
                        </x-secondary-button>
                    </div>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Date & Time</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Transaction Type</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Material / Product Name</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">SKU</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Item Code IERP</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Lot Number</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Expiry Date</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Storage Location</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Quantity</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Unit</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Remaining Stock</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Reference</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['date_time'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['user'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['transaction_type'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row['material_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['sku'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['item_code_ierp'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['lot_number'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['expiry_date'] }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['storage_location'] }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ $row['quantity'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($row['quantity']) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['unit'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">{{ number_format($row['remaining_stock']) }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $row['reference'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $row['notes'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-4 py-10 text-center text-gray-500">No inventory movement history found for the selected filters.</td>
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
