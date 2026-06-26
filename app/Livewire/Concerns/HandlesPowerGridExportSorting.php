<?php

namespace App\Livewire\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PowerComponents\LivewirePowerGrid\DataSource\DataTransformer;
use PowerComponents\LivewirePowerGrid\DataSource\ProcessDataSource;
use PowerComponents\LivewirePowerGrid\DataSource\Processors\Database\Handlers\FilterHandler;
use PowerComponents\LivewirePowerGrid\DataSource\Processors\Database\Handlers\SearchHandlerContract;

trait HandlesPowerGridExportSorting
{
    /**
     * @return array<string, string>
     */
    protected function legacyPowerGridSortFieldMap(): array
    {
        return [];
    }

    protected function normalizePowerGridSortingState(): void
    {
        if (filled($this->sortField)) {
            $this->sortField = $this->normalizePowerGridSortField($this->sortField);
        }

        if (! empty($this->sortArray)) {
            $normalized = [];

            foreach ($this->sortArray as $field => $direction) {
                $normalized[$this->normalizePowerGridSortField($field)] = $direction;
            }

            $this->sortArray = $normalized;
        }
    }

    /**
     * @throws Exception
     */
    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        $this->normalizePowerGridSortingState();

        $processDataSource = tap(ProcessDataSource::make($this), fn ($datasource) => $datasource->get());

        $filtered = $processDataSource->component->filtered;

        if ($selected && filled($processDataSource->component->checkboxValues)) {
            $filtered = $processDataSource->component->checkboxValues;
        }

        if ($processDataSource->datasource instanceof Collection) {
            if ($filtered) {
                $results = $processDataSource->get(isExport: true)['results']
                    ->whereIn($this->primaryKey, $filtered);

                $dataTransformer = new DataTransformer($processDataSource->component);

                return $dataTransformer->transform($results)->collection;
            }

            $dataTransformer = new DataTransformer($processDataSource->component);

            return $dataTransformer->transform($processDataSource->datasource)->collection;
        }

        /** @phpstan-ignore-next-line */
        $currentTable = $processDataSource->component->currentTable;

        $results = $processDataSource->datasource
            ->where(function ($query) {
                app()->makeWith(SearchHandlerContract::class, [
                    'component' => $this,
                ])->apply($query);
                (new FilterHandler($this))->apply($query);
            })
            ->when($filtered, function ($query, $filtered) use ($currentTable) {
                $primaryKey = $this->qualifyPowerGridSortField($this->primaryKey, $currentTable);

                return $query->whereIn($primaryKey, $filtered);
            });

        if ($processDataSource->component->multiSort && ! empty($processDataSource->component->sortArray)) {
            foreach ($processDataSource->component->sortArray as $sortField => $direction) {
                $this->applyPowerGridExportSort($results, $sortField, $direction, $currentTable);
            }
        } elseif (filled($processDataSource->component->sortField)) {
            $this->applyPowerGridExportSort(
                $results,
                $processDataSource->component->sortField,
                $processDataSource->component->sortDirection,
                $currentTable,
            );
        }

        $dataTransformer = new DataTransformer($processDataSource->component);

        return $dataTransformer->transform($results->get())->collection;
    }

    protected function normalizePowerGridSortField(string $sortField): string
    {
        return $this->legacyPowerGridSortFieldMap()[$sortField] ?? $sortField;
    }

    protected function qualifyPowerGridSortField(string $sortField, string $currentTable): string
    {
        if (Str::of($sortField)->contains('.') || $this->ignoreTablePrefix) {
            return $sortField;
        }

        return $currentTable . '.' . $sortField;
    }

    protected function applyPowerGridExportSort(mixed $query, string $sortField, string $direction, string $currentTable): void
    {
        $sortField = $this->normalizePowerGridSortField($sortField);
        $sortCallback = $this->getSortCallback($sortField);

        if ($sortCallback !== null) {
            $sortCallback($query, $direction);

            return;
        }

        $query->orderBy($this->qualifyPowerGridSortField($sortField, $currentTable), $direction);
    }
}
