<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\AuthorizesComponentPermissions;
use App\Models\Customer;
use Livewire\Component;
use Livewire\Attributes\On;

class CustomerDetail extends Component
{
    use AuthorizesComponentPermissions;

    public ?Customer $customer = null;

    public function render()
    {
        return view('livewire.customers.customer-detail');
    }

    #[On('show-customer')]
    public function show(Customer $customer)
    {
        $this->customer = $customer;
        $this->dispatch('open-modal', name: 'customer-detail-modal');
    }

    public function edit()
    {
        $this->authorizePermission('master_data', 'update');

        if ($this->customer) {
            $this->dispatch('close-modal', name: 'customer-detail-modal');
            $this->dispatch('edit-customer', ['customer' => $this->customer->id]);
        }
    }
}
