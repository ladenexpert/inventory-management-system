<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Services\DashboardStatsService;
use Livewire\Attributes\Url;

class Dashboard extends Component
{
    #[Url(as: 'view')]
    public string $view = 'rni-operations';

    public array $stats = [];
    public array $lowStockProducts = [];
    public array $urgentBatches = [];
    public array $batchValuation = [];
    public array $recentReceipts = [];
    public array $recentUsage = [];
    public array $topUsedMaterials = [];
    public array $nearExpiryRisks = [];
    public array $physicalFormBreakdown = [];
    public array $businessStats = [];
    public array $topSuppliers = [];
    public array $topCustomers = [];
    public array $fastMovingMaterials = [];
    public array $slowMovingMaterials = [];
    public array $deadStock = [];
    public array $purchaseTrend = [];
    public array $salesTrend = [];
    public array $inboundTrend = [];
    public array $outboundTrend = [];
    public array $materialConsumptionTrend = [];

    public function mount(DashboardStatsService $service)
    {
        if (!in_array($this->view, ['rni-operations', 'business-insights'], true)) {
            $this->view = 'rni-operations';
        }

        $this->loadStats($service);
    }

    public function loadStats(DashboardStatsService $service)
    {
        $endDate = now()->endOfDay();
        $startDate = now()->subDays(29)->startOfDay();

        $this->stats = $service->getRniOverviewStats();
        $this->lowStockProducts = $service->getLowStockProducts(5);
        $this->urgentBatches = $service->getUrgentBatches(5);
        $this->batchValuation = $service->getTopBatchValuations(5);
        $this->recentReceipts = $service->getRecentReceipts(8);
        $this->recentUsage = $service->getRecentMaterialUsage(8);
        $this->topUsedMaterials = $service->getTopUsedMaterialsThisMonth(5);
        $this->nearExpiryRisks = $service->getNearExpiryMaterialRisks(5);
        $this->physicalFormBreakdown = $service->getPhysicalFormBreakdown();
        $this->businessStats = $service->getBusinessInsightStats($startDate, $endDate);
        $this->topSuppliers = $service->getTopSuppliers($startDate, $endDate, 5);
        $this->topCustomers = $service->getTopCustomers($startDate, $endDate, 5);
        $this->fastMovingMaterials = $service->getFastMovingMaterials($startDate, $endDate, 5);
        $this->slowMovingMaterials = $service->getSlowMovingMaterials($startDate, $endDate, 5);
        $this->deadStock = $service->getDeadStock(90, 5);
        $this->purchaseTrend = $service->getPurchaseTrend($startDate, $endDate);
        $this->salesTrend = $service->getSalesTrend($startDate, $endDate);
        $this->inboundTrend = $service->getInboundTrend($startDate, $endDate);
        $this->outboundTrend = $service->getOutboundTrend($startDate, $endDate);
        $this->materialConsumptionTrend = $service->getMaterialConsumptionTrend($startDate, $endDate);
    }

    public function setView(string $view): void
    {
        if (!in_array($view, ['rni-operations', 'business-insights'], true)) {
            return;
        }

        $this->view = $view;
    }

    public function sumTrend(array $trend): int
    {
        return (int) array_sum($trend);
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard');
    }
}
