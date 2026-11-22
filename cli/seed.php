<?php
declare(strict_types=1);

/** Reset all data to fresh demo data dated around today: php cli/seed.php */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
$r = App\Services\DemoSeeder::run(fn($m) => print("  $m\n"));
echo "done\n";
