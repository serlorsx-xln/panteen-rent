<?php
declare(strict_types=1);

/**
 * Test runner.
 *   php tests/run.php          -> unit + HTTP tests
 *   php tests/run.php unit     -> unit/service tests only
 *   php tests/run.php http     -> HTTP integration tests only
 *
 * Uses a separate database (panteen_test) that is rebuilt from database/schema.sql
 * and the demo data on every run, so your real data is never touched.
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
$db = getenv('PANTEEN_DB_NAME') ?: 'panteen_test';
if ($db === 'panteen_rent') {
    exit("Refusing to run tests against the real database.\n");
}
putenv("PANTEEN_DB_NAME=$db");
putenv('PANTEEN_MAIL_DRIVER=log');
define('PANTEEN_TESTING', true);

$root = dirname(__DIR__);
echo "Rebuilding test database `$db`…\n";
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/cli/install.php") . ' 2>&1', $out, $code);
if ($code !== 0) {
    echo implode("\n", $out), "\n";
    exit(1);
}

require "$root/app/bootstrap.php";
require __DIR__ . '/lib.php';

$which = $argv[1] ?? 'all';
$started = microtime(true);
if ($which === 'all' || $which === 'unit') {
    require __DIR__ . '/unit.php';
    require __DIR__ . '/shipping.php';
}
if ($which === 'all' || $which === 'http') {
    require __DIR__ . '/http.php';
}
printf("\n%.1f s\n", microtime(true) - $started);
exit(T::summary());
