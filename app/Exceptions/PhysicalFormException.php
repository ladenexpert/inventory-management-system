<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class PhysicalFormException extends Exception
{
    public static function creationFailed(string $message, array $context = []): self
    {
        Log::error("Physical form creation failed: {$message}", $context);

        return new self("Failed to create physical form. {$message}");
    }

    public static function updateFailed(string $message, array $context = []): self
    {
        Log::error("Physical form update failed: {$message}", $context);

        return new self("Failed to update physical form. {$message}");
    }

    public static function deletionFailed(string $message, array $context = []): self
    {
        Log::error("Physical form deletion failed: {$message}", $context);

        return new self("Failed to delete physical form. {$message}");
    }
}
