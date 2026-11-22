<?php
declare(strict_types=1);

/**
 * Create the database, load the schema and the demo data.
 *   php cli/install.php            -> database from config (panteen_rent)
 *   php cli/install.php --no-seed  -> schema only (admin account still created)
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;

$c = Config::get('db');
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']), $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "database {$c['name']} ready\n";

DB::reset();
$schema = file_get_contents(BASE_PATH . '/database/schema.sql');
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', preg_replace('/^--.*$/m', '', $schema)))) as $stmt) {
    DB::pdo()->exec($stmt);
}
echo "schema loaded\n";

if (!in_array('--no-seed', $_SERVER['argv'] ?? [], true)) {
    App\Services\DemoSeeder::run(fn($m) => print("  $m\n"));
    echo "demo data loaded\n";
} else {
    DB::insert('users', [
        'role' => 'admin', 'first_name' => 'ผู้ดูแล', 'last_name' => 'ระบบ', 'email' => 'admin@panteen.test',
        'password_hash' => password_hash('Admin1234', PASSWORD_DEFAULT), 'email_verified_at' => date('Y-m-d H:i:s'),
    ]);
    echo "admin account created\n";
    App\Services\Inspection::seedTypes();
    echo "standard damage price list added (edit it at /admin/damage-types)\n";
}
echo "\nเข้าสู่ระบบ:\n  ผู้ดูแลระบบ  admin@panteen.test   / Admin1234\n  พนักงาน      staff@panteen.test   / Staff1234\n  สมาชิก       member@panteen.test  / Member1234\n";
