<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class TeamException extends Exception
{
    public static function creationFailed(string $message, array $context = []): self
    {
        Log::error("Team creation failed: {$message}", $context);

        return new self("Failed to create team. {$message}");
    }

    public static function updateFailed(string $message, array $context = []): self
    {
        Log::error("Team update failed: {$message}", $context);

        return new self("Failed to update team. {$message}");
    }

    public static function deletionFailed(string $message, array $context = []): self
    {
        Log::error("Team deletion failed: {$message}", $context);

        return new self("Failed to delete team. {$message}");
    }
}
