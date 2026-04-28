<x-app-layout title="Import Stok Awal">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Import Stok Awal Product') }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    Upload file Excel untuk migrasi product beserta opening balance batch.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-secondary-button :href="route('products.index')">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    {{ __('Kembali ke Products') }}
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
                <h3 class="text-base font-semibold text-foreground">Panduan Singkat</h3>
                <ul class="list-disc pl-5 text-sm text-muted-foreground space-y-1">
                    <li>Gunakan file dari tombol <strong>Download Template</strong> agar format kolom sesuai.</li>
                    <li>Kolom wajib: <code>name</code>, <code>category</code>, <code>unit</code>, <code>purchase_price</code>, <code>selling_price</code>, <code>opening_quantity</code>.</li>
                    <li><code>category</code> bisa isi nama/slug/id category yang sudah ada.</li>
                    <li><code>unit</code> bisa isi nama/simbol/id unit yang sudah ada.</li>
                    <li>Baris yang diawali <code>#</code> akan diabaikan saat import.</li>
                </ul>
            </div>

            <div class="bg-white border border-border rounded-lg p-6">
                <form action="{{ route('products.import-opening-stock.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <x-input-label for="file" :value="__('File Import (.xlsx / .csv / .ods)')" required />
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
                            {{ __('Import Sekarang') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>

            @if(session('import_report'))
                @php($report = session('import_report'))
                <div class="bg-white border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-foreground">Ringkasan Import Terakhir</h3>

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

