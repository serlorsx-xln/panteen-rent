<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;
use App\Core\Upload;
use App\Services\Addresses;
use App\Services\Availability;
use App\Services\BookingException;
use App\Services\BookingService;
use App\Services\Catalog;
use App\Services\Fulfilment;
use App\Services\Inspection;
use App\Services\Photos;
use App\Services\Pricing;
use App\Services\PromptPay;
use App\Services\Receipts;
use App\Services\Settings;

final class BookingController extends Controller
{
    /** @return array<string, mixed> */
    private function bookable(string $code): array
    {
        $c = Catalog::byCode($code);
        if (!$c || $c['status'] === 'retired') {
            abort(404);
        }
        return $c;
    }

    /** Step 1: choose the window and see the itemised price before committing. */
    public function create(string $code): void
    {
        if (Auth::isStaff()) {
            redirect('/admin/bookings/new?dress=' . urlencode($code));
        }
        $c = $this->bookable($code);
        $event = Catalog::filters(['date' => $_GET['event'] ?? Session::get('event_date')])['date'];
        [$p, $r] = $event ? Pricing::defaultWindow($event) : [null, null];
        if ($p && $p < today()) {
            $p = today();
        }
        $pickup = $this->dateOr($_GET['pickup'] ?? null, $p);
        $return = $this->dateOr($_GET['return'] ?? null, $r);
        $state = null;
        if ($event && $pickup && $return) {
            $errors = BookingService::validateDates($c, $event, $pickup, $return);
            $state = $errors
                ? ['ok' => false, 'message' => reset($errors)]
                : ['ok' => Availability::isFree((int) $c['id'], $pickup, $return), 'quote' => Pricing::quote($c, $pickup, $return)];
        }
        $this->view('booking/create', [
            'title' => 'จอง ' . $c['name'],
            'c' => $c,
            'image' => Catalog::images((int) $c['id'])[0] ?? null,
            'event' => $event,
            'pickup' => $pickup,
            'return' => $return,
            'state' => $state,
            'fit' => Catalog::fit(Auth::user(), $c),
            'addresses' => Auth::id() ? Addresses::forUser(Auth::id()) : [],
        ]);
    }

    private function dateOr(mixed $v, ?string $default): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
    }

    /** Step 2: commit. This is the point of no return for the price; payment comes next. */
    public function store(string $code): void
    {
        if (Auth::isStaff()) {
            abort(403, 'พนักงานสร้างการจองให้ลูกค้าได้ที่หน้า “จองหน้าร้าน” ในระบบหลังร้าน');
        }
        $c = $this->bookable($code);
        $event = input_str('event_date');
        $pickup = input_str('pickup_date');
        $return = input_str('return_date');
        $back = '/book/' . rawurlencode($code) . '?' . http_build_query(['event' => $event, 'pickup' => $pickup, 'return' => $return]);
        if (empty($_POST['agree'])) {
            back_with_errors(['agree' => 'อ่านและยอมรับเงื่อนไขการเช่าก่อนยืนยัน'], $back);
        }
        $ship = Addresses::choice($_POST, Auth::id());
        if ($ship['errors']) {
            back_with_errors($ship['errors'] + ['form' => 'กรอกที่อยู่จัดส่งให้ครบก่อนยืนยัน'], $back);
        }
        try {
            $b = BookingService::create([
                'user_id' => Auth::id(),
                'costume_id' => (int) $c['id'],
                'event_date' => $event,
                'pickup_date' => $pickup,
                'return_date' => $return,
                'pickup_time' => input_str('pickup_time'),
                'return_time' => input_str('return_time'),
                'note' => input_str('note'),
                'delivery_method' => $ship['delivery'],
                'return_method' => $ship['return'],
                'address' => $ship['address'],
            ]);
        } catch (BookingException $e) {
            $errors = $e->errors;
            if (isset($errors['address'])) {
                $errors['address_id'] = $errors['address'];
            }
            back_with_errors($errors + ['form' => $e->getMessage()], $back);
        }
        if ($ship['save'] && $ship['address']) {
            Addresses::save(Auth::id(), $ship['address']); // a full address book only skips saving; the booking keeps its copy
        }
        Session::put('event_date', $event);
        flash('success', 'จองไว้ให้แล้ว ชำระเงินภายใน ' . thai_datetime($b['hold_expires_at']));
        redirect('/bookings/' . $b['id']);
    }

    public function index(): void
    {
        $tab = in_array($_GET['tab'] ?? '', ['active', 'past'], true) ? $_GET['tab'] : 'active';
        $statuses = $tab === 'active' ? BookingService::ACTIVE_SQL : "('returned','cancelled','expired')";
        $items = DB::all(
            "SELECT b.*, c.code AS costume_code, c.name AS costume_name, c.size_label,
                    (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image
               FROM bookings b JOIN costumes c ON c.id = b.costume_id
              WHERE b.user_id = ? AND b.status IN $statuses
              ORDER BY " . ($tab === 'active' ? 'b.pickup_date ASC' : 'b.pickup_date DESC') . ', b.id DESC',
            [Auth::id()]
        );
        $counts = DB::one(
            "SELECT SUM(status IN " . BookingService::ACTIVE_SQL . ") AS active,
                    SUM(status IN ('returned','cancelled','expired')) AS past FROM bookings WHERE user_id = ?",
            [Auth::id()]
        );
        $this->view('booking/index', ['title' => 'การจองของฉัน', 'items' => $items, 'tab' => $tab, 'counts' => $counts]);
    }

    public function show(string $id): void
    {
        $b = $this->ownedBooking($this->intId($id));
        if (Auth::isStaff() && (int) $b['user_id'] !== Auth::id()) {
            redirect('/admin/bookings/' . $b['id']);
        }
        // An expired hold is closed the moment anyone looks at it.
        if ($b['status'] === 'pending_payment' && $b['hold_expires_at'] && strtotime($b['hold_expires_at']) <= time()) {
            BookingService::expireHolds();
            $b = BookingService::find((int) $b['id']);
        }
        $payload = null;
        if ($b['status'] === 'pending_payment') {
            try {
                $payload = PromptPay::payload(Settings::get('promptpay_id'), (float) $b['total_amount']);
            } catch (\InvalidArgumentException) {
                $payload = null;
            }
        }
        $this->view('booking/show', [
            'title' => 'การจอง ' . $b['booking_no'],
            'b' => $b,
            'payload' => $payload,
            'payments' => DB::all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$b['id']]),
            'receipts' => DB::all('SELECT * FROM receipts WHERE booking_id = ? ORDER BY id', [$b['id']]),
            'events' => DB::all('SELECT * FROM booking_events WHERE booking_id = ? ORDER BY created_at, id', [$b['id']]),
            'cancel' => BookingService::memberCanCancel($b),
            'shipments' => Fulfilment::shipments((int) $b['id']),
            'photos' => Photos::forBooking((int) $b['id']),
            'damage' => Inspection::items((int) $b['id']),
            'dispute' => Inspection::canDispute($b),
            'accessories' => Inspection::accessories($b['accessories']),
        ]);
    }

    /** @return array<string, mixed> the signed-in member's own booking (staff use the back office) */
    private function mine(string $id): array
    {
        $b = $this->ownedBooking($this->intId($id));
        if ((int) $b['user_id'] !== Auth::id()) {
            abort(404);
        }
        return $b;
    }

    /**
     * Run a customer step, then keep the photos sent with it. A step that fails keeps no photo.
     * @param array<string, mixed> $b
     * @param callable(): mixed $step
     */
    private function step(array $b, callable $step, string $stage, string $done, string $anchor): never
    {
        $back = '/bookings/' . $b['id'];
        $files = Photos::fromRequest('photos');
        if ($err = Photos::tooMany($files)) {
            back_with_errors(['photos' => $err], $back . $anchor);
        }
        try {
            $step();
        } catch (BookingException $e) {
            back_with_errors($e->errors + ['form' => $e->getMessage()], $back . $anchor);
        }
        $saved = $files ? Photos::store((int) $b['id'], $stage, $files, Auth::id()) : ['saved' => 0, 'errors' => []];
        flash('success', $done . ($saved['saved'] ? ' · แนบรูป ' . $saved['saved'] . ' รูป' : ''));
        if ($saved['errors']) {
            flash('error', 'บางรูปแนบไม่ได้: ' . implode(' / ', $saved['errors']));
        }
        redirect($back);
    }

    public function received(string $id): void
    {
        $b = $this->mine($id);
        try {
            Fulfilment::markDelivered((int) $b['id'], Auth::id(), true);
            flash('success', 'ขอบคุณที่ยืนยัน ถ่ายรูปสภาพชุดส่งให้ร้านได้ภายใน ' . Settings::int('arrival_report_hours') . ' ชั่วโมง');
        } catch (BookingException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/bookings/' . $b['id'] . '#arrival');
    }

    public function arrival(string $id): void
    {
        $b = $this->mine($id);
        $note = input_str('arrival_note');
        if ($note === '' && !Photos::fromRequest('photos')) {
            back_with_errors(['photos' => 'แนบรูปสภาพชุด หรือเขียนปัญหาที่พบ'], '/bookings/' . $b['id'] . '#arrival');
        }
        $this->step($b, fn() => Fulfilment::reportArrival((int) $b['id'], Auth::id(), $note), 'arrival',
            $note !== '' ? 'แจ้งปัญหาให้ร้านแล้ว ร้านจะติดต่อกลับโดยเร็ว' : 'ส่งรูปสภาพชุดให้ร้านแล้ว', '#arrival');
    }

    public function postBack(string $id): void
    {
        $b = $this->mine($id);
        $this->step($b, fn() => Fulfilment::postBack((int) $b['id'], Auth::id(), input_str('carrier'), input_str('tracking_no'), input_str('posted_on')),
            'dropoff', 'แจ้งส่งชุดคืนแล้ว ร้านจะตรวจสภาพเมื่อได้รับพัสดุ', '#return');
    }

    public function dispute(string $id): void
    {
        $b = $this->mine($id);
        $this->step($b, fn() => Inspection::dispute((int) $b['id'], Auth::id(), input_str('dispute_note')), 'dispute',
            'ส่งข้อโต้แย้งให้ร้านแล้ว ร้านจะตอบภายใน 2 วันทำการ', '#dispute');
    }

    /** Condition photos live outside the web root and are only served to the booking's customer and staff. */
    public function photo(string $id): void
    {
        $p = Photos::find($this->intId($id));
        if (!$p || (!Auth::isStaff() && (int) $p['user_id'] !== Auth::id())) {
            abort(404);
        }
        $this->sendFile(STORAGE_PATH . '/condition/' . basename($p['path']));
    }

    private function sendFile(string $file): void
    {
        if (!is_file($file)) {
            abort(404);
        }
        header('Content-Type: ' . (new \finfo(FILEINFO_MIME_TYPE))->file($file));
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=3600');
        readfile($file);
    }

    public function uploadSlip(string $id): void
    {
        $b = $this->mine($id);
        $up = Upload::image($_FILES['slip'] ?? null, STORAGE_PATH . '/slips', 1600);
        if (!$up['ok']) {
            back_with_errors(['slip' => $up['error']], '/bookings/' . $b['id'] . '#pay');
        }
        try {
            BookingService::submitSlip((int) $b['id'], Auth::id(), $up['path'], input_str('method') === 'transfer' ? 'transfer' : 'promptpay');
        } catch (BookingException $e) {
            @unlink(STORAGE_PATH . '/slips/' . $up['path']);
            flash('error', $e->getMessage());
            redirect('/bookings/' . $b['id']);
        }
        flash('success', 'ได้รับสลิปแล้ว ร้านจะตรวจและยืนยันให้เร็วที่สุด');
        redirect('/bookings/' . $b['id']);
    }

    public function cancel(string $id): void
    {
        $b = $this->mine($id);
        try {
            BookingService::cancel((int) $b['id'], Auth::id(), input_str('reason'));
            flash('success', 'ยกเลิกการจองแล้ว');
        } catch (BookingException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/bookings/' . $b['id']);
    }

    public function receipt(string $id): void
    {
        $r = Receipts::find($this->intId($id));
        if (!$r || (!Auth::isStaff() && (int) $r['user_id'] !== Auth::id())) {
            abort(404);
        }
        DB::run('UPDATE receipts SET print_count = print_count + 1 WHERE id = ?', [$r['id']]);
        $b = BookingService::find((int) $r['booking_id']);
        $issuer = $r['issued_by'] ? DB::one('SELECT first_name, nickname FROM users WHERE id = ?', [$r['issued_by']]) : null;
        $this->view('booking/receipt', [
            'title' => 'ใบเสร็จ ' . $r['receipt_no'],
            'r' => $r,
            'b' => $b,
            'data' => json_decode($r['items_json'], true),
            'issuer' => $issuer,
        ], 'layouts/print');
    }

    /** Slip images live outside the web root and are only served to the owner and staff. */
    public function slip(string $id): void
    {
        $p = DB::one('SELECT p.*, b.user_id FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.id = ?', [$this->intId($id)]);
        if (!$p || !$p['slip_path'] || (!Auth::isStaff() && (int) $p['user_id'] !== Auth::id())) {
            abort(404);
        }
        $this->sendFile(STORAGE_PATH . '/slips/' . basename($p['slip_path']));
    }
}
