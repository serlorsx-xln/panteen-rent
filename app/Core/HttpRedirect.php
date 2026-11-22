<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown instead of exit() when redirect() is called inside tests. */
final class HttpRedirect extends \RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct('Redirect to ' . $location);
    }
}
