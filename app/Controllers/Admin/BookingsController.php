<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\Addresses;
use App\Services\Availability;
use App\Services\BookingException;
use App\Services\BookingService;
use App\Services\Catalog;
use App\Services\Fulfilment;
use App\Services\Inspection;
use App\Services\Photos;
use App\Services\Status;

final class BookingsController extends Controller
{
    public function index(): void
    {
        $status = $_GET['status'] ?? '';
        $q = input_str('q');
        $from = input_str('from');
        $to = input_str('to');
        $where = ['1=1'];
        $params = [];
        if ($status === 'active') {
            $where[] = "b.status IN " . BookingService::ACTIVE_SQL;
        } elseif ($status === 'overdue') {
            $where[] = "b.status = 'picked_up' AND b.return_date < ?";
            $params[] = today();
        } elseif (isset(Status::BOOKING[$status])) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(b.booking_no LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.nickname LIKE ? OR u.phone LIKE ? OR c.code LIKE ? OR c.name LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'b.pickup_date >= ?';
            $params[] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'b.pickup_date <= ?';
            $params[] = $to;
        }
        $base = ' FROM bookings b JOIN costumes c ON c.id = b.costume_id JOIN users u ON u.id = b.user_id WHERE ' . implode(' AND ', $where);
        $pg = $this->paginate('SELECT COUNT(*)' . $base, $params, 30);
        $items = DB::all(
            "SELECT b.*, c.code AS costume_code, c.name AS costume_name, u.first_name, u.last_name, u.nickname, u.phone,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image"
            . $base . " ORDER BY FIELD(b.status,'awaiting_verification','pending_payment','returning','picked_up','shipped','confirmed','returned','cancelled','expired'), b.pickup_date DESC, b.id DESC
              LIMIT {$pg['limit']} OFFSET {$pg['offset']}",
            $params
        );
        $counts = [];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM bookings GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $counts['overdue'] = (int) DB::value("SELECT COUNT(*) FROM bookings WHERE status = 'picked_up' AND return_date < ?", [today()]);
        $this->admin('bookings/index', compact('items', 'pg', 'status', 'q', 'from', 'to', 'counts') + ['title' => 'การจองทั้งหมด']);
    }

    public function create(): void
    {
        $costume = isset($_GET['dress']) ? Catalog::byCode((string) $_GET['dress']) : null;
        $member = isset($_GET['member']) ? DB::one("SELECT * FROM users WHERE id = ? AND role = 'member'", [(int) $_GET['member']]) : null;
        $costumes = DB::all(
            "SELECT c.id, c.code, c.name, c.size_label, c.rent_price, cat.name AS category_name FROM costumes c
               JOIN categories cat ON cat.id = c.category_id WHERE c.status = 'active' ORDER BY c.code"
        );
        $this->admin('bookings/create', ['title' => 'จองหน้าร้าน', 'costume' => $costume, 'member' => $member, 'costumes' => $costumes,
            'addresses' => $member ? Addresses::forUser((int) $member['id']) : []]);
    }

    public function store(): void
    {
        $back = '/admin/bookings/new?' . http_build_query(['dress' => input_str('costume_code'), 'member' => input_int('user_id')]);
        $c = Catalog::byCode(input_str('costume_code'));
        $u = DB::one("SELECT * FROM users WHERE id = ? AND role = 'member'", [input_int('user_id')]);
        $errors = [];
        if (!$c) {
            $errors['costume_code'] = 'เลือกชุดที่จะเช่า';
        }
        if (!$u) {
            $errors['user_id'] = 'เลือกลูกค้า (ค้นหาจากชื่อหรือเบอร์โทร หรือเพิ่มลูกค้าใหม่)';
        }
        $ship = $u ? Addresses::choice($_POST, (int) $u['id']) : null;
        if ($ship && $ship['errors']) {
            $errors += $ship['errors'] + ['form' => 'กรอกที่อยู่จัดส่งของลูกค้าให้ครบ'];
        }
        if ($errors) {
            back_with_errors($errors, $back);
        }
        try {
            $b = BookingService::create([
                'user_id' => (int) $u['id'], 'costume_id' => (int) $c['id'], 'channel' => 'counter', 'actor_id' => Auth::id(),
                'event_date' => input_str('event_date'), 'pickup_date' => input_str('pickup_date'), 'return_date' => input_str('return_date'),
                'pickup_time' => input_str('pickup_time'), 'return_time' => input_str('return_time'), 'note' => input_str('note'),
                'delivery_method' => $ship['delivery'], 'return_method' => $ship['return'], 'address' => $ship['address'],
            ]);
            $pay = input_str('pay_now');
            if (in_array($pay, ['cash', 'transfer', 'promptpay'], true)) {
                $b = BookingService::recordCounterPayment((int) $b['id'], Auth::id(), $pay);
            }
        } catch (BookingException $e) {
            $errors = $e->errors;
            if (isset($errors['address'])) {
                $errors['address_id'] = $errors['address'];
            }
            back_with_errors($errors + ['form' => $e->getMessage()], $back);
        }
        if ($ship['save'] && $ship['address']) {
            Addresses::save((int) $u['id'], $ship['address']);
        }
        flash('success', 'สร้างการจอง ' . $b['booking_no'] . ' แล้ว' . ($b['status'] === 'confirmed' ? ' และรับชำระเรียบร้อย' : ' (รอชำระเงิน)'));
        redirect('/admin/bookings/' . $b['id']);
    }

    /** @return array<string, mixed> */
    private function load(string $id): array
    {
        $b = BookingService::find($this->intId($id));
        if (!$b) {
            abort(404);
        }
        return $b;
    }

    public function show(string $id): void
    {
        $b = $this->load($id);
        $events = DB::all(
            'SELECT e.*, u.first_name, u.nickname, u.role FROM booking_events e LEFT JOIN users u ON u.id = e.actor_id WHERE e.booking_id = ? ORDER BY e.created_at, e.id',
            [$b['id']]
        );
        $this->admin('bookings/show', [
            'title' => 'การจอง ' . $b['booking_no'],
            'b' => $b,
            'payments' => DB::all('SELECT p.*, r.first_name AS reviewer_first, r.nickname AS reviewer_nick FROM payments p LEFT JOIN users r ON r.id = p.reviewed_by WHERE p.booking_id = ? ORDER BY p.id', [$b['id']]),
            'receipts' => DB::all('SELECT * FROM receipts WHERE booking_id = ? ORDER BY id', [$b['id']]),
            'events' => $events,
            'preview' => $b['status'] === 'picked_up' ? BookingService::previewReturn($b, today()) : null,
            'conflicts' => in_array($b['status'], ['pending_payment', 'awaiting_verification', 'confirmed'], true)
                ? Availability::conflicts((int) $b['costume_id'], $b['pickup_date'], $b['return_date'], (int) $b['id'],
                    max(0, days_between((string) $b['block_from'], $b['pickup_date'])), max(0, days_between(Availability::blockUntil($b['return_date']), (string) $b['block_until']))) : [],
            'shipments' => Fulfilment::shipments((int) $b['id']),
            'photos' => Photos::forBooking((int) $b['id']),
            'damage' => Inspection::items((int) $b['id']),
            'inspection' => $b['inspection_json'] ? json_decode((string) $b['inspection_json'], true) : null,
            'paidTotal' => (float) DB::value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = ? AND kind = 'booking' AND status = 'approved'", [$b['id']]),
        ]);
    }

    private function act(string $id, callable $fn, string $ok): never
    {
        $b = $this->load($id);
        try {
            $fn((int) $b['id']);
            flash('success', $ok);
        } catch (BookingException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/bookings/' . $b['id']);
    }

    public function payCounter(string $id): void
    {
        $method = input_str('method');
        $this->act($id, fn($bid) => BookingService::recordCounterPayment($bid, Auth::id(), in_array($method, ['cash', 'transfer', 'promptpay'], true) ? $method : 'cash'), 'บันทึกรับชำระและออกใบเสร็จแล้ว');
    }

    public function pickup(string $id): void
    {
        $this->act($id, fn($bid) => BookingService::markPickedUp($bid, Auth::id()), 'บันทึกว่าลูกค้ารับชุดแล้ว');
    }

    public function markReturned(string $id): void
    {
        $date = input_str('returned_on') ?: today();
        $fee = (float) str_replace(',', '', input_str('damage_fee', '0'));
        $this->act($id, fn($bid) => BookingService::markReturned($bid, Auth::id(), $date, $fee, input_str('damage_note')), 'รับคืนชุดและออกใบสรุปการคืนแล้ว');
    }

    public function cancel(string $id): void
    {
        $refund = input_str('refund') !== '' ? (float) str_replace(',', '', input_str('refund')) : null;
        $this->act($id, fn($bid) => BookingService::cancel($bid, Auth::id(), input_str('reason'), true, $refund), 'ยกเลิกการจองแล้ว');
    }

    public function reschedule(string $id): void
    {
        $this->act($id, fn($bid) => BookingService::reschedule(
            $bid, Auth::id(), input_str('event_date'), input_str('pickup_date'), input_str('return_date'),
            input_str('pickup_time'), input_str('return_time'), input_str('staff_note')
        ), 'บันทึกการแก้ไขแล้ว แจ้งลูกค้าเรียบร้อย');
    }

    /** Occupancy board: costumes × days. */
    public function schedule(): void
    {
        $start = input_str('start');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = add_days(today(), -2);
        }
        $span = 21;
        $end = add_days($start, $span - 1);
        $cat = input_int('category');
        $costumes = DB::all(
            "SELECT c.id, c.code, c.name, c.size_label, c.status, cat.color AS category_color FROM costumes c JOIN categories cat ON cat.id = c.category_id
              WHERE c.status <> 'retired'" . ($cat ? ' AND c.category_id = ' . $cat : '') . ' ORDER BY cat.sort_order, c.code'
        );
        $bookings = DB::all(
            'SELECT b.id, b.costume_id, b.booking_no, b.status, b.pickup_date, b.return_date, b.block_until, ' . Availability::endSql() . " AS busy_until,
                    u.nickname, u.first_name
               FROM bookings b JOIN users u ON u.id = b.user_id
              WHERE " . Availability::blockingSql() . " AND b.pickup_date <= '$end' AND " . Availability::endSql() . " >= '$start'"
        );
        $byCostume = [];
        foreach ($bookings as $bk) {
            $byCostume[$bk['costume_id']][] = $bk;
        }
        $this->admin('bookings/schedule', [
            'title' => 'ตารางชุด',
            'start' => $start, 'span' => $span, 'costumes' => $costumes, 'byCostume' => $byCostume,
            'categories' => Catalog::categories(false), 'cat' => $cat,
        ]);
    }
}
