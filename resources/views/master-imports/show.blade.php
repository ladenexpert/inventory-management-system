<x-app-layout :title="$definition['label'] . ' Import'">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">{{ $definition['label'] }} Import</h2>
                <p class="text-sm text-muted-foreground mt-1">Download the system template, fill it in, and upload the Excel file here.</p>
            </div>
            <x-secondary-button :href="route('master-imports.template', $resource)">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                Download Template
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Template columns</h3>
                    <p class="text-sm text-gray-500 mt-1">Columns must stay in the same order as the generated template.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach($definition['headers'] as $header)
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $header }}</span>
                    @endforeach
                </div>

                <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
                    <li>Use `.xlsx`, `.csv`, or `.ods` files up to 20 MB.</li>
                    <li>Rows starting with `#` are ignored.</li>
                    <li>Imports create new master data records and reject duplicate keys already in the system.</li>
                </ul>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-6">
                <form action="{{ route('master-imports.store', $resource) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="space-y-2">
                        <x-input-label for="file" :value="'Upload ' . $definition['label'] . ' file'" required />
                        <input
                            id="file"
                            type="file"
                            name="file"
                            accept=".xlsx,.csv,.ods"
                            class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-md file:border-0
                                file:text-sm file:font-semibold
                                file:bg-indigo-50 file:text-indigo-700
                                hover:file:bg-indigo-100"
                        />
                        <x-input-error :messages="$errors->get('file')" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>
                            <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                            Import Excel
                        </x-primary-button>
                    </div>
                </form>
            </div>

            @if(session('import_report'))
                @php($report = session('import_report'))
                <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Import Summary</h3>
                        <p class="text-sm text-gray-500 mt-1">Review created, skipped, and failed rows before continuing.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Processed</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $report['processed_rows'] }}</p>
                        </div>
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-green-700">Created</p>
                            <p class="mt-2 text-2xl font-semibold text-green-800">{{ $report['created_rows'] }}</p>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Skipped</p>
                            <p class="mt-2 text-2xl font-semibold text-amber-800">{{ $report['skipped_rows'] }}</p>
                        </div>
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-red-700">Failed</p>
                            <p class="mt-2 text-2xl font-semibold text-red-800">{{ $report['failed_rows'] }}</p>
                        </div>
                    </div>

                    @if(!empty($report['errors']))
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <h4 class="text-sm font-semibold text-red-800">Row errors</h4>
                            <ul class="mt-3 space-y-2 text-sm text-red-700">
                                @foreach($report['errors'] as $error)
                                    <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
