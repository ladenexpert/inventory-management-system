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
    public array $topUsedMaterials = [];
    public array $nearExpiryRisks = [];
    public array $physicalFormBreakdown = [];

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
        $this->topUsedMaterials = $service->getTopUsedMaterialsThisMonth(5);
        $this->nearExpiryRisks = $service->getNearExpiryMaterialRisks(5);
        $this->physicalFormBreakdown = $service->getPhysicalFormBreakdown();
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard');
    }
}
