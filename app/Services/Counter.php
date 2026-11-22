<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Gap-free yearly document numbers: PT69-0001 (booking), RC69-0001 (receipt). Buddhist-era year. */
final class Counter
{
    public static function next(string $name): int
    {
        $year = (int) date('Y');
        DB::run(
            'INSERT INTO counters (name, year, value) VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)',
            [$name, $year]
        );
        return (int) DB::value('SELECT LAST_INSERT_ID()');
    }

    public static function code(string $prefix, string $name): string
    {
        $be = ((int) date('Y') + 543) % 100;
        return sprintf('%s%02d-%04d', $prefix, $be, self::next($name));
    }
}
