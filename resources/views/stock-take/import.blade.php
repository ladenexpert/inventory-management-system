<x-app-layout title="Stock Take">
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">Stock Take</h2>
                <p class="text-sm text-muted-foreground mt-1">Import counted stock for existing batches, review the variance, then post a traceable stock take adjustment.</p>
            </div>
            @if(auth()->user()?->hasPermission('stock_take', 'import'))
                <x-secondary-button :href="route('stock-take.template')">
                    Download Template
                </x-secondary-button>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(auth()->user()?->hasPermission('stock_take', 'import'))
                <div class="bg-white border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-foreground">Import Stock Take File</h3>
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        SKU, Batch No, and Counted Qty are required. Item Code, Material, Expiry, Storage Location, Reference Number, and Notes are optional. Stock Take in v0.4.8 only reconciles existing matched batches and does not create new batches.
                    </div>

                    <form action="{{ route('stock-take.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="file" :value="'Upload Stock Take File'" required />
                            <input id="file" type="file" name="file" accept=".xlsx,.csv,.ods" class="mt-2 block w-full text-sm text-gray-900 border border-input rounded-md cursor-pointer bg-background focus:outline-none" required />
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button type="submit">Create Preview Session</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white border border-border rounded-lg overflow-hidden">
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-base font-semibold text-foreground">Recent Stock Take Sessions</h3>
                    <p class="text-sm text-muted-foreground mt-1">Review import evidence, recalculate variances when stock has changed, and post or close sessions from the detail page.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Session Code</th>
                                <th class="px-4 py-3 text-left">Imported At</th>
                                <th class="px-4 py-3 text-left">Imported By</th>
                                <th class="px-4 py-3 text-right">Rows</th>
                                <th class="px-4 py-3 text-right">Errors</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Reference</th>
                                <th class="px-4 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($sessions as $session)
                                @php
                                    $badgeClass = match ($session->status) {
                                        'imported' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'reviewed' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'stale' => 'bg-red-50 text-red-700 border-red-200',
                                        'posted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'closed' => 'bg-zinc-100 text-zinc-700 border-zinc-200',
                                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $session->session_code }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $session->imported_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $session->importedByUser?->name ?? 'System' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">{{ number_format($session->row_count) }}</td>
                                    <td class="px-4 py-3 text-right {{ $session->error_count > 0 ? 'text-red-600 font-medium' : 'text-gray-700' }}">{{ number_format($session->error_count) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                            {{ Str::headline($session->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $session->reference ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('stock-take.show', $session) }}" class="text-primary hover:underline">Open Session</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">No stock take sessions have been created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t bg-gray-50 px-4 py-3">
                    {{ $sessions->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
