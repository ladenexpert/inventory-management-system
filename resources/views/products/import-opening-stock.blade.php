<x-app-layout title="Upload Opening Stock">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Upload Opening Stock') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    Upload raw material opening stock and opening batch balances from Excel.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-secondary-button :href="route('products.index')">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    {{ __('Back to Materials') }}
                </x-secondary-button>
                <x-primary-button :href="route('products.import-opening-stock.template')">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                    {{ __('Download Template') }}
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-border rounded-lg p-6 space-y-4">
                <h3 class="text-base font-semibold text-foreground">Quick Guide</h3>
                <ul class="list-disc pl-5 text-sm text-muted-foreground space-y-1">
                    <li>Use the <strong>Download Template</strong> file so the import columns stay valid.</li>
                    <li>Required columns: <code>name</code>, <code>category</code>, <code>unit</code>, <code>purchase_price</code>, <code>selling_price</code>, <code>opening_quantity</code>.</li>
                    <li><code>category</code> may use an existing category name, slug, or ID.</li>
                    <li><code>unit</code> may use an existing unit name, symbol, or ID.</li>
                    <li>Rows starting with <code>#</code> are ignored during import.</li>
                </ul>
            </div>

            <div class="bg-white border border-border rounded-lg p-6">
                <form action="{{ route('products.import-opening-stock.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <x-input-label for="file" :value="__('Import File (.xlsx / .csv / .ods)')" required />
                        <input
                            id="file"
                            name="file"
                            type="file"
                            accept=".xlsx,.csv,.ods"
                            class="block w-full text-sm text-gray-900 border border-input rounded-md cursor-pointer bg-background focus:outline-none"
                            required
                        />
                        @error('file')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button type="submit">
                            <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                            {{ __('Upload Now') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>

            @if(session('import_report'))
                @php($report = session('import_report'))
                <div class="bg-white border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-foreground">Latest Import Summary</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">Processed Rows</p>
                            <p class="text-lg font-semibold">{{ $report['processed_rows'] ?? 0 }}</p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">Created</p>
                            <p class="text-lg font-semibold text-green-600">{{ $report['created_rows'] ?? 0 }}</p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">Failed</p>
                            <p class="text-lg font-semibold text-red-600">{{ $report['failed_rows'] ?? 0 }}</p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">Skipped</p>
                            <p class="text-lg font-semibold">{{ $report['skipped_rows'] ?? 0 }}</p>
                        </div>
                    </div>

                    @if(!empty($report['errors']))
                        <div class="overflow-x-auto rounded-md border border-red-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-red-50 text-red-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Row</th>
                                        <th class="px-3 py-2 text-left font-medium">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-100 bg-white">
                                    @foreach($report['errors'] as $error)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-red-700">{{ $error['row'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-red-700">{{ $error['message'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
