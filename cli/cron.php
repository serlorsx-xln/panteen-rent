<?php
declare(strict_types=1);

/**
 * Scheduled jobs: expire unpaid bookings and send reminders.
 * Windows Task Scheduler (every 15 minutes):  C:\xampp\php\php.exe C:\xampp\htdocs\panteen-rent\cli\cron.php
 * Linux cron:  *\/15 * * * * php /path/to/panteen-rent/cli/cron.php
 * (The website also runs these jobs by itself at most every 10 minutes, so this is optional.)
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
$out = App\Services\Reminders::run();
@file_put_contents(STORAGE_PATH . '/cache/last-cron.txt', (string) time());
foreach ($out as $k => $v) {
    echo str_pad($k, 10) . $v . "\n";
}
