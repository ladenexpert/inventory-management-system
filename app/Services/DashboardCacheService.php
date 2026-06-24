<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private const VERSION_KEY = 'dashboard_cache_version';

    public function versionedKey(string $key): string
    {
        return sprintf('dashboard:v%s:%s', $this->currentVersion(), $key);
    }

    public function forgetDashboardData(): void
    {
        Cache::forever(self::VERSION_KEY, $this->currentVersion() + 1);
    }

    private function currentVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
