<?php
// Helper for the concurrency test: try to book the same costume/dates and report the outcome.
// usage: php tests/race.php <costumeId> <userId> <pickup> <event> <return> <startAtMicrotime>
define('PANTEEN_TESTING', true);
require dirname(__DIR__) . '/app/bootstrap.php';
[, $cid, $uid, $p, $e, $r, $at] = $_SERVER['argv'];
while (microtime(true) < (float) $at) {
    usleep(500);
}
try {
    $b = App\Services\BookingService::create(['user_id' => (int) $uid, 'costume_id' => (int) $cid, 'event_date' => $e, 'pickup_date' => $p, 'return_date' => $r]);
    echo 'OK ' . $b['booking_no'];
} catch (App\Services\BookingException $ex) {
    echo 'BUSY';
} catch (Throwable $ex) {
    echo 'ERR ' . $ex->getMessage();
}
