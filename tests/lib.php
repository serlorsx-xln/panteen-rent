<?php
declare(strict_types=1);

/** A tiny test harness: no Composer, runs anywhere PHP runs (XAMPP included). */
final class T
{
    public static int $pass = 0;
    /** @var list<string> */
    public static array $fail = [];
    public static string $group = '';

    public static function group(string $name): void
    {
        self::$group = $name;
        echo "\n\033[1m$name\033[0m\n";
    }

    public static function ok(bool $cond, string $label, string $detail = ''): void
    {
        if ($cond) {
            self::$pass++;
            echo "  \033[32m✓\033[0m $label\n";
        } else {
            self::$fail[] = self::$group . ' › ' . $label . ($detail !== '' ? " ($detail)" : '');
            echo "  \033[31m✗ $label\033[0m" . ($detail !== '' ? "  → $detail" : '') . "\n";
        }
    }

    public static function eq(mixed $actual, mixed $expected, string $label, string $detail = ''): void
    {
        $why = 'got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . ($detail !== '' ? ' · ' . $detail : '');
        self::ok($actual === $expected, $label, $actual === $expected ? '' : $why);
    }

    /** Assert that $fn throws; returns the exception message. */
    public static function throws(callable $fn, string $label, string $class = \Throwable::class): string
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            self::ok($e instanceof $class, $label, $e instanceof $class ? '' : get_class($e) . ': ' . $e->getMessage());
            return $e->getMessage();
        }
        self::ok(false, $label, 'no exception thrown');
        return '';
    }

    public static function summary(): int
    {
        $total = self::$pass + count(self::$fail);
        echo "\n" . str_repeat('─', 60) . "\n";
        echo self::$fail ? "\033[31m" : "\033[32m";
        echo self::$pass . '/' . $total . " passed\033[0m\n";
        foreach (self::$fail as $f) {
            echo "  ✗ $f\n";
        }
        return self::$fail ? 1 : 0;
    }
}
