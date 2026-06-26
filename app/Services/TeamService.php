<?php

namespace App\Services;

use App\DTOs\TeamData;
use App\Exceptions\TeamException;
use App\Models\Team;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {
    }

    public function createTeam(TeamData $data): Team
    {
        return DB::transaction(function () use ($data) {
            try {
                return Team::create([
                    'code' => Str::upper($data->code),
                    'name' => $data->name,
                    'description' => $data->description,
                    'is_active' => $data->is_active,
                ]);
            } catch (Exception $e) {
                throw TeamException::creationFailed($e->getMessage(), ['data' => (array) $data]);
            }
        });
    }

    public function updateTeam(Team $team, TeamData $data): Team
    {
        return DB::transaction(function () use ($team, $data) {
            try {
                $team->update([
                    'code' => Str::upper($data->code),
                    'name' => $data->name,
                    'description' => $data->description,
                    'is_active' => $data->is_active,
                ]);

                return $team->refresh();
            } catch (Exception $e) {
                throw TeamException::updateFailed($e->getMessage(), ['id' => $team->id]);
            }
        });
    }

    public function deleteTeam(Team $team): void
    {
        DB::transaction(function () use ($team) {
            try {
                $team->delete();
                $this->auditLogService->logDeletion($team);
            } catch (Exception $e) {
                throw TeamException::deletionFailed($e->getMessage(), ['id' => $team->id]);
            }
        });
    }
}
