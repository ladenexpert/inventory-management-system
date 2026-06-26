<?php

namespace App\Services;

use App\DTOs\PhysicalFormData;
use App\Exceptions\PhysicalFormException;
use App\Models\PhysicalForm;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PhysicalFormService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {
    }

    public function createPhysicalForm(PhysicalFormData $data): PhysicalForm
    {
        return DB::transaction(function () use ($data) {
            try {
                return PhysicalForm::create([
                    'code' => Str::lower($data->code),
                    'name' => $data->name,
                    'description' => $data->description,
                    'is_active' => $data->is_active,
                ]);
            } catch (Exception $e) {
                throw PhysicalFormException::creationFailed($e->getMessage(), ['data' => (array) $data]);
            }
        });
    }

    public function updatePhysicalForm(PhysicalForm $physicalForm, PhysicalFormData $data): PhysicalForm
    {
        return DB::transaction(function () use ($physicalForm, $data) {
            try {
                $physicalForm->update([
                    'code' => Str::lower($data->code),
                    'name' => $data->name,
                    'description' => $data->description,
                    'is_active' => $data->is_active,
                ]);

                return $physicalForm->refresh();
            } catch (Exception $e) {
                throw PhysicalFormException::updateFailed($e->getMessage(), ['id' => $physicalForm->id]);
            }
        });
    }

    public function deletePhysicalForm(PhysicalForm $physicalForm): void
    {
        DB::transaction(function () use ($physicalForm) {
            try {
                $physicalForm->delete();
                $this->auditLogService->logDeletion($physicalForm);
            } catch (Exception $e) {
                throw PhysicalFormException::deletionFailed($e->getMessage(), ['id' => $physicalForm->id]);
            }
        });
    }

    public function resolve(?string $value): ?PhysicalForm
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $normalized = Str::of($raw)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        return PhysicalForm::query()
            ->where('code', $normalized)
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($raw)])
            ->first();
    }
}
