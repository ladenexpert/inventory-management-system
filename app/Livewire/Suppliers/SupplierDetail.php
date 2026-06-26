<?php

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\Attributes\On;

class SupplierDetail extends Component
{
    use AuthorizesComponentPermissions;

    public ?Supplier $supplier = null;

    public function render()
    {
        return view('livewire.suppliers.supplier-detail');
    }

    #[On('show-supplier')]
    public function show(Supplier $supplier)
    {
        $this->supplier = $supplier;
        $this->dispatch('open-modal', name: 'supplier-detail-modal');
    }

    public function edit()
    {
        $this->authorizePermission('master_data', 'update');

        if ($this->supplier) {
            $this->dispatch('close-modal', name: 'supplier-detail-modal');
            $this->dispatch('edit-supplier', ['supplier' => $this->supplier->id]);
        }
    }
}
