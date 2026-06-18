<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Services\DashboardStatsService;

class Dashboard extends Component
{
    public array $stats = [];
    public array $lowStockProducts = [];
    public array $urgentBatches = [];
    public array $batchValuation = [];
    public array $recentUsage = [];

    public function mount(DashboardStatsService $service)
    {
        $this->loadStats($service);
    }

    public function loadStats(DashboardStatsService $service)
    {
        $this->stats = $service->getRniOverviewStats();
        $this->lowStockProducts = $service->getLowStockProducts(5);
        $this->urgentBatches = $service->getUrgentBatches(5);
        $this->batchValuation = $service->getTopBatchValuations(5);
        $this->recentUsage = $service->getRecentMaterialUsage(8);
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard');
    }
}
