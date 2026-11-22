<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\View;
use App\Services\Reports;
use App\Services\Status;

final class ReportsController extends Controller
{
    public const TYPES = ['rentals' => 'รายงานการเช่า', 'finance' => 'รายงานการเงิน', 'appointments' => 'รายงานการนัดหมาย'];

    public function index(): void
    {
        redirect('/admin/reports/rentals');
    }

    public function show(string $type): void
    {
        if (!isset(self::TYPES[$type])) {
            abort(404);
        }
        [$from, $to] = Reports::period($_GET['from'] ?? null, $_GET['to'] ?? null);
        $data = Reports::$type($from, $to);
        $format = $_GET['format'] ?? '';

        if ($format === 'csv') {
            $this->csv($type, $data, $from, $to);
        }
        $vars = ['title' => self::TYPES[$type], 'type' => $type, 'from' => $from, 'to' => $to, 'd' => $data, 'types' => self::TYPES];
        if ($format === 'print') {
            View::render('admin/reports/' . $type, $vars + ['print' => true, 'adminCss' => true], 'layouts/print');
            return;
        }
        $this->admin('reports/' . $type, $vars + ['print' => false]);
    }

    /** @param array<string, mixed> $d */
    private function csv(string $type, array $d, string $from, string $to): never
    {
        $name = "panteen-{$type}-{$from}-{$to}.csv";
        if ($type === 'rentals') {
            Reports::csv($name, ['เลขที่จอง', 'วันรับชุด', 'วันงาน', 'วันคืน', 'จำนวนวัน', 'รหัสชุด', 'ชื่อชุด', 'ประเภท', 'ลูกค้า', 'ช่องทาง', 'สถานะ', 'ค่าเช่า', 'ค่าวันเพิ่ม', 'มัดจำ', 'ค่าปรับคืนช้า', 'ค่าเสียหาย'],
                array_map(fn($r) => [
                    $r['booking_no'], $r['pickup_date'], $r['event_date'], $r['return_date'], $r['rental_days'], $r['costume_code'], $r['costume_name'],
                    $r['category_name'], full_name($r), $r['channel'] === 'online' ? 'ออนไลน์' : 'หน้าร้าน', Status::label(Status::BOOKING, $r['status']),
                    $r['rent_amount'], $r['extra_amount'], $r['deposit_amount'], $r['late_fee'], $r['damage_fee'],
                ], $d['rows']));
        }
        if ($type === 'finance') {
            Reports::csv($name, ['วันเวลา', 'เลขที่จอง', 'ลูกค้า', 'ประเภทรายการ', 'ช่องทาง', 'รับเข้า', 'จ่ายออก'],
                array_map(fn($t) => [
                    $t['reviewed_at'], $t['booking_no'], full_name($t), Status::PAYMENT_KIND[$t['kind']], Status::PAYMENT_METHOD[$t['method']],
                    $t['kind'] === 'refund' ? '' : $t['amount'], $t['kind'] === 'refund' ? $t['amount'] : '',
                ], $d['tx']));
        }
        Reports::csv($name, ['วันเวลา', 'ประเภทนัด', 'ลูกค้า', 'เบอร์โทร', 'การจอง', 'ชุด', 'สถานะ', 'หมายเหตุ'],
            array_map(fn($a) => [
                $a['starts_at'], Status::APPOINTMENT_TYPE[$a['type']], full_name($a), $a['phone'], $a['booking_no'] ?? '',
                $a['costume_code'] ? $a['costume_code'] . ' ' . $a['costume_name'] : '', Status::label(Status::APPOINTMENT, $a['status']), $a['note'] ?? '',
            ], $d['rows']));
    }
}
