<x-modal name="product-detail-modal" focusable>
    @if($product)
        <div class="p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border pb-4">
                <div>
                    <h3 class="text-xl font-bold text-foreground tracking-tight">{{ $product->name }}</h3>
                    <p class="text-sm text-muted-foreground font-mono">{{ $product->sku }}</p>
                    <p class="text-xs text-muted-foreground">Item Code IERP: {{ $product->item_code_ierp ?? '-' }}</p>
                </div>
                <div>
                    @if($product->is_active)
                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                            Inactive
                        </span>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <!-- Details -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Category') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $product->category->name ?? '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Unit') }}</label>
                        <p class="text-sm text-foreground font-medium">
                            @if($product->unit)
                                {{ $product->unit->name }} <span class="text-muted-foreground">({{ $product->unit->symbol }})</span>
                            @else
                                -
                            @endif
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Selling Price') }}</label>
                        <p class="text-sm text-foreground font-medium">@money($product->selling_price)</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Purchase Price') }}</label>
                        <p class="text-sm text-foreground font-medium">@money($product->purchase_price)</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Stock') }}</label>
                        <p class="text-sm text-foreground font-medium {{ $product->quantity <= $product->min_stock ? 'text-red-500' : '' }}">
                            {{ $product->quantity . ' ' . ($product->unit->symbol ?? '') }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Min Stock Alert') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $product->min_stock }}</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Description') }}</label>
                    <p class="text-sm text-foreground font-medium">
                        {{ $product->description ?: 'No description provided.' }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Internal Notes') }}</label>
                    <div class="bg-gray-50 border border-secondary p-3 rounded-md">
                        <p class="text-sm text-foreground font-mono whitespace-pre-wrap leading-relaxed">{{ $product->notes ?: 'No notes.' }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Active Batches') }}</label>
                        <p class="text-xs text-muted-foreground mt-1">Batch with the nearest expiry will be consumed first during sales.</p>
                    </div>

                    <div class="overflow-x-auto rounded-md border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Batch</th>
                                    <th class="px-3 py-2 text-left font-medium">Expiry</th>
                                    <th class="px-3 py-2 text-right font-medium">Available</th>
                                    <th class="px-3 py-2 text-right font-medium">Cost</th>
                                    <th class="px-3 py-2 text-left font-medium">Source</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($product->batches->where('available_quantity', '>', 0) as $batch)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-gray-900">{{ $batch->batch_number }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $batch->expiry_date?->format('d M Y') ?? '-' }}</td>
                                        <td class="px-3 py-2 text-right text-gray-700">{{ $batch->available_quantity }}</td>
                                        <td class="px-3 py-2 text-right text-gray-700">@money($batch->unit_cost)</td>
                                        <td class="px-3 py-2 text-gray-500">{{ str($batch->source)->headline() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500">No active batch available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Meta -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Created At') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $product->created_at?->format('d M Y, H:i') ?? '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Last Updated') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $product->updated_at?->format('d M Y, H:i') ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-x-2 pt-4 border-t border-border">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-detail-modal' })">
                    {{ __('Close') }}
                </x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-detail-modal' }); $dispatch('edit-product', { product: {{ $product->id }} })">
                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-2" />
                    {{ __('Edit Product') }}
                </x-primary-button>
            </div>
        </div>
    @else
        <div class="p-8 text-center flex flex-col items-center justify-center space-y-3">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            <span class="text-sm text-muted-foreground">{{ __('Loading details...') }}</span>
        </div>
    @endif
</x-modal>
