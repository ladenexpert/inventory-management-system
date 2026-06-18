<?php

namespace App\Services;

use App\Models\Setting;

class ModuleService
{
    public const DEFAULT_MODULES = [
        'rni' => true,
        'sales' => true,
        'purchases' => true,
        'finance' => true,
        'reports' => true,
        'users' => true,
        'materials' => true,
    ];

    public function isEnabled(string $module): bool
    {
        $default = self::DEFAULT_MODULES[$module] ?? true;
        $value = Setting::get($this->settingKey($module), $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function settingKey(string $module): string
    {
        return "module_{$module}_enabled";
    }

    public function all(): array
    {
        return collect(self::DEFAULT_MODULES)
            ->mapWithKeys(fn (bool $default, string $module) => [$module => $this->isEnabled($module)])
            ->all();
    }
}
