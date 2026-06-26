<x-app-layout title="Stock Take Import">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">Stock Take Import</h2>
                <p class="text-sm text-muted-foreground mt-1">Preview counted stock variances first, then apply traceable adjustment transactions.</p>
            </div>
            <x-secondary-button :href="route('stock-take.template')">Download Template</x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-6">
                <form action="{{ route('stock-take.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="file" :value="'Upload Stock Take File'" required />
                        <input id="file" type="file" name="file" accept=".xlsx,.csv,.ods" class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button type="submit">Preview Variance</x-primary-button>
                    </div>
                </form>
            </div>

            @if($preview)
                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-6 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Processed</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $preview['summary']['processed_rows'] }}</div>
                        </div>
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-green-700">Valid</div>
                            <div class="mt-2 text-2xl font-semibold text-green-800">{{ $preview['summary']['valid_rows'] }}</div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-amber-700">Needs Adjustment</div>
                            <div class="mt-2 text-2xl font-semibold text-amber-800">{{ $preview['summary']['adjustment_rows'] }}</div>
                        </div>
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-red-700">Errors</div>
                            <div class="mt-2 text-2xl font-semibold text-red-800">{{ $preview['summary']['error_rows'] }}</div>
                        </div>
                    </div>

                    @if(!empty($preview['errors']))
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <h3 class="text-sm font-semibold text-red-800">Validation Errors</h3>
                            <ul class="mt-3 space-y-2 text-sm text-red-700">
                                @foreach($preview['errors'] as $error)
                                    <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Item Code</th>
                                    <th class="px-4 py-3 text-left">Material</th>
                                    <th class="px-4 py-3 text-left">Batch No</th>
                                    <th class="px-4 py-3 text-left">Expiry</th>
                                    <th class="px-4 py-3 text-left">Storage Location</th>
                                    <th class="px-4 py-3 text-right">Current Qty</th>
                                    <th class="px-4 py-3 text-right">Counted Qty</th>
                                    <th class="px-4 py-3 text-right">Variance</th>
                                    <th class="px-4 py-3 text-left">Reference Number</th>
                                    <th class="px-4 py-3 text-left">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($preview['rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3">{{ $row['item_code'] }}</td>
                                        <td class="px-4 py-3">{{ $row['material_name'] }}</td>
                                        <td class="px-4 py-3">{{ $row['batch_number'] }}</td>
                                        <td class="px-4 py-3">{{ $row['expiry_date'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $row['storage_location'] }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($row['current_qty']) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($row['counted_qty']) }}</td>
                                        <td class="px-4 py-3 text-right {{ $row['variance'] < 0 ? 'text-red-600' : ($row['variance'] > 0 ? 'text-emerald-600' : 'text-gray-600') }}">{{ number_format($row['variance']) }}</td>
                                        <td class="px-4 py-3">{{ $row['reference'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $row['notes'] ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">No preview rows available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <form action="{{ route('stock-take.apply') }}" method="POST">
                            @csrf
                            <x-primary-button type="submit" :disabled="!empty($preview['errors'])">Apply Stock Take</x-primary-button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
