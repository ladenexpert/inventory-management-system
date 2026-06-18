<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductDetail extends Component
{
    public ?Product $product = null;

    public function render()
    {
        return view('livewire.products.product-detail');
    }

    #[On('show-product')]
    public function show(Product $product): void
    {
        $this->product = $product->load([
            'category',
            'unit',
            'supplier',
            'batches' => fn($query) => $query
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy('received_at')
                ->orderBy('id'),
        ]);
        $this->dispatch('open-modal', name: 'product-detail-modal');
    }

    public function closeModal(): void
    {
        $this->product = null;
    }
}
