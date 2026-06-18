<?php

namespace App\Services;

use App\DTOs\StorageLocationData;
use App\Exceptions\StorageLocationException;
use App\Models\StorageLocation;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorageLocationService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {
    }

    public function createLocation(StorageLocationData $data): StorageLocation
    {
        return DB::transaction(function () use ($data) {
            try {
                return StorageLocation::create([
                    'code' => Str::upper($data->code),
                    'name' => $data->name,
                    'type' => $data->type,
                    'parent_id' => $data->parent_id,
                    'description' => $data->description,
                    'is_active' => $data->is_active,
                ]);
            } catch (Exception $e) {
                throw StorageLocationException::creationFailed($e->getMessage(), ['data' => (array) $data]);
            }
        });
    }

    public function updateLocation(StorageLocation $location, StorageLocationData $data): StorageLocation
    {
        return DB::transaction(function () use ($location, $data) {
            try {
                $location->update([
                    'code' => Str::upper($data->code),
                    'name' => $data->name,
                    'type' => $data->type,
                    'parent_id' => $data->parent_id,
                    'description' => $data->description,
                    'is_active' => $data->is_active,
                ]);

                return $location->refresh();
            } catch (Exception $e) {
                throw StorageLocationException::updateFailed($e->getMessage(), ['id' => $location->id]);
            }
        });
    }

    public function deleteLocation(StorageLocation $location): void
    {
        DB::transaction(function () use ($location) {
            try {
                $location->delete();
                $this->auditLogService->logDeletion($location);
            } catch (Exception $e) {
                throw StorageLocationException::deletionFailed($e->getMessage(), ['id' => $location->id]);
            }
        });
    }

    public function resolveOrCreate(?string $rawValue): ?StorageLocation
    {
        $value = trim((string) $rawValue);

        if ($value === '') {
            return null;
        }

        $location = StorageLocation::query()
            ->where('code', $value)
            ->orWhere('name', $value)
            ->orWhereRaw('LOWER(code) = ?', [Str::lower($value)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($value)])
            ->first();

        if ($location) {
            return $location;
        }

        $generatedCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?: 'LOC', 0, 50));

        if ($generatedCode === '') {
            $generatedCode = 'LOC-' . Str::upper(Str::random(6));
        }

        if (StorageLocation::where('code', $generatedCode)->exists()) {
            $generatedCode = 'LOC-' . Str::upper(Str::random(6));
        }

        return StorageLocation::create([
            'code' => $generatedCode,
            'name' => $value,
            'type' => 'other',
            'is_active' => true,
        ]);
    }
}
