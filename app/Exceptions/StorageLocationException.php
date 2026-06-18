<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class StorageLocationException extends Exception
{
    public static function creationFailed(string $message, array $context = []): self
    {
        Log::error("Storage location creation failed: {$message}", $context);

        return new self("Failed to create storage location: {$message}");
    }

    public static function updateFailed(string $message, array $context = []): self
    {
        Log::error("Storage location update failed: {$message}", $context);

        return new self("Failed to update storage location: {$message}");
    }

    public static function deletionFailed(string $message, array $context = []): self
    {
        Log::error("Storage location deletion failed: {$message}", $context);

        return new self("Failed to delete storage location: {$message}");
    }
}
