<?php
declare(strict_types=1);

namespace App\Services;

/** A business-rule failure with a message that can be shown to the user. */
final class BookingException extends \RuntimeException
{
    /** @param array<string, string> $errors field => message */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
