<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Services\BookingException;
use App\Services\BookingService;
use App\Services\Catalog;
use App\Services\Fulfilment;
use App\Services\Inspection;
use App\Services\Photos;

/**
 * The back office side of rentals that travel: sending parcels, taking them back, the return inspection
 * with the damage table, disputes, printed labels and dress tags, the shipping queue and the scan desk.
 */
final class FulfilmentController extends Controller
{
    /** @return array<string, mixed> */
    private function load(string $id): array
    {
        $b = BookingService::find($this->intId($id));
        if (!$b) {
            abort(404);
        }
        return $b;
    }

    /**
     * Run a step for a booking, keep the photos sent with it, and go back to the booking.
     * @param callable(int): mixed $step
     */
    private function step(string $id, callable $step, string $ok, ?string $stage = null, string $anchor = ''): never
    {
        $b = $this->load($id);
        $back = '/admin/bookings/' . $b['id'];
        $files = $stage ? Photos::fromRequest('photos') : [];
        if ($err = Photos::tooMany($files)) {
            back_with_errors(['photos' => $err], $back);
        }
        try {
            $step((int) $b['id']);
        } catch (BookingException $e) {
            back_with_errors($e->errors + ['form' => $e->getMessage()], $back . $anchor);
        }
        $saved = $files ? Photos::store((int) $b['id'], (string) $stage, $files, Auth::id()) : ['saved' => 0, 'errors' => []];
        flash('success', $ok . ($saved['saved'] ? ' · รูป ' . $saved['saved'] . ' รูป' : ''));
        if ($saved['errors']) {
            flash('error', 'บางรูปบันทึกไม่ได้: ' . implode(' / ', $saved['errors']));
        }
        redirect($back . $anchor);
    }

    public function ship(string $id): void
    {
        $this->step($id, fn($bid) => Fulfilment::ship($bid, Auth::id(), input_str('carrier'), input_str('tracking_no'), input_str('note')),
            'บันทึกส่งพัสดุและแจ้งเลขพัสดุให้ลูกค้าแล้ว', 'handover');
    }

    public function delivered(string $id): void
    {
        $this->step($id, fn($bid) => Fulfilment::markDelivered($bid, Auth::id(), false), 'บันทึกว่าพัสดุถึงลูกค้าแล้ว');
    }

    public function failed(string $id): void
    {
        $this->step($id, fn($bid) => Fulfilment::deliveryFailed($bid, Auth::id(), input_str('reason'), !empty($_POST['lost'])),
            'บันทึกแล้ว การจองกลับเป็น “ยืนยันแล้ว” ส่งใหม่ เปลี่ยนเป็นรับที่ร้าน หรือยกเลิกได้');
    }

    public function toPickup(string $id): void
    {
        $this->step($id, fn($bid) => Fulfilment::switchToPickup($bid, Auth::id()), 'เปลี่ยนเป็นรับชุดที่ร้านแล้ว');
    }

    public function postBack(string $id): void
    {
        $received = !empty($_POST['received']);
        $this->step($id, function ($bid) use ($received) {
            if ($received) {
                return Fulfilment::receiveBack($bid, Auth::id(), input_str('posted_on'), input_str('carrier'), input_str('tracking_no'));
            }
            return Fulfilment::postBack($bid, Auth::id(), input_str('carrier'), input_str('tracking_no'), input_str('posted_on'), true);
        }, $received ? 'บันทึกรับพัสดุคืนแล้ว ตรวจสภาพต่อได้เลย' : 'บันทึกการส่งคืนแทนลูกค้าแล้ว');
    }

    public function receive(string $id): void
    {
        $this->step($id, fn($bid) => Fulfilment::receiveBack($bid, Auth::id(), input_str('posted_on') ?: null), 'รับพัสดุคืนแล้ว ตรวจสภาพต่อได้เลย');
    }

    public function returnLost(string $id): void
    {
        $this->step($id, fn($bid) => Fulfilment::returnLost($bid, Auth::id(), input_str('reason')), 'บันทึกพัสดุคืนสูญหายแล้ว แจ้งลูกค้าเรียบร้อย');
    }

    public function photos(string $id): void
    {
        $stage = input_str('stage');
        if (!in_array($stage, ['handover', 'return'], true)) {
            $stage = 'return';
        }
        if (!Photos::fromRequest('photos')) {
            back_with_errors(['photos' => 'เลือกรูปก่อนบันทึก'], '/admin/bookings/' . $this->intId($id) . '#parcels');
        }
        $this->step($id, fn($bid) => null, 'บันทึกรูปสภาพชุดแล้ว', $stage, '#parcels');
    }

    public function dispute(string $id): void
    {
        $waive = (float) str_replace(',', '', input_str('waive', '0'));
        $this->step($id, fn($bid) => Inspection::resolve($bid, Auth::id(), input_str('reply'), $waive),
            $waive > 0 ? 'ลดค่าเสียหาย คืนเงิน และออกใบปรับปรุงแล้ว' : 'ตอบข้อโต้แย้งแล้ว', null, '#dispute');
    }

    /** The return inspection: checklist, accessories, damage rows from the shop's table, outcome. */
    public function inspectForm(string $id): void
    {
        $b = $this->load($id);
        $parcel = $b['status'] === 'returning' ? Fulfilment::lastShipment((int) $b['id'], 'back') : null;
        if (!($b['status'] === 'picked_up' || ($parcel && in_array($parcel['status'], ['received', 'lost'], true)))) {
            flash('error', $b['status'] === 'returning' ? 'ยังไม่ได้รับพัสดุคืน กด “ได้รับพัสดุคืนแล้ว” ก่อน' : 'การจองนี้ยังไม่อยู่ในขั้นรับคืน');
            redirect('/admin/bookings/' . $b['id']);
        }
        $this->admin('bookings/inspect', [
            'title' => 'ตรวจสภาพชุดคืน ' . $b['booking_no'],
            'b' => $b,
            'types' => Inspection::types(),
            'photos' => Photos::forBooking((int) $b['id']),
            'accessories' => Inspection::accessories($b['accessories']),
            'parcel' => $parcel,
            // a parcel is late by the day it was posted; at the counter, by the day it is handed in
            'lateFrom' => $parcel ? (string) $parcel['shipped_on'] : today(),
            'fixedDate' => $parcel !== null,
        ]);
    }

    public function inspect(string $id): void
    {
        $b = $this->load($id);
        $back = '/admin/bookings/' . $b['id'];
        $files = Photos::fromRequest('photos');
        if ($err = Photos::tooMany($files)) {
            back_with_errors(['form' => $err], $back . '/inspect');
        }
        $rows = [];
        foreach (is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [] as $r) {
            if (is_array($r)) {
                $rows[] = array_map(fn($v) => is_string($v) ? trim($v) : '', $r);
            }
        }
        $list = static fn(string $k): array => array_values(array_filter(is_array($_POST[$k] ?? null) ? $_POST[$k] : [], 'is_string'));
        try {
            Inspection::complete((int) $b['id'], Auth::id(), [
                'returned_on' => input_str('returned_on') ?: today(),
                'rows' => $rows,
                'missing' => $list('missing'),
                'checks' => $list('checks'),
                'outcome' => input_str('outcome', 'ready'),
                'note' => input_str('note'),
            ]);
        } catch (BookingException $e) {
            back_with_errors($e->errors + ['form' => $e->getMessage()], $back . '/inspect');
        }
        $saved = $files ? Photos::store((int) $b['id'], 'return', $files, Auth::id()) : ['saved' => 0, 'errors' => []];
        flash('success', 'ปิดการเช่าแล้ว ส่งใบสรุปการคืนชุดให้ลูกค้าเรียบร้อย' . ($saved['saved'] ? ' · รูป ' . $saved['saved'] . ' รูป' : ''));
        if ($saved['errors']) {
            flash('error', 'บางรูปบันทึกไม่ได้: ' . implode(' / ', $saved['errors']));
        }
        redirect($back);
    }

    /** Printable parcel label, return label and packing list for one booking. */
    public function label(string $id): void
    {
        $b = $this->load($id);
        $this->view('admin/bookings/label', [
            'title' => 'ใบปะหน้า ' . $b['booking_no'],
            'b' => $b,
            'accessories' => Inspection::accessories($b['accessories']),
            'out' => Fulfilment::lastShipment((int) $b['id'], 'out'),
        ], 'layouts/print');
    }

    /** Printable QR tag sewn or tied to a dress: scanning it opens the dress at the scan desk. */
    public function tag(string $id): void
    {
        $c = DB::one('SELECT c.*, cat.name AS category_name FROM costumes c JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?', [$this->intId($id)]);
        if (!$c) {
            abort(404);
        }
        $this->view('admin/costumes/tag', ['title' => 'ป้ายชุด ' . $c['code'], 'c' => $c, 'accessories' => Inspection::accessories($c['accessories'])], 'layouts/print');
    }

    /** What has to go out, what is on the road, and what is coming back. */
    public function queue(): void
    {
        $tab = input_str('tab', 'out');
        $base = "SELECT b.*, c.code AS costume_code, c.name AS costume_name, u.first_name, u.last_name, u.nickname, u.phone,
                        (SELECT path FROM costume_images i WHERE i.costume_id = c.id ORDER BY sort_order, id LIMIT 1) AS image,
                        s.carrier, s.tracking_no, s.shipped_on, s.status AS parcel_status, s.arrived_at
                   FROM bookings b JOIN costumes c ON c.id = b.costume_id JOIN users u ON u.id = b.user_id
                   LEFT JOIN shipments s ON s.id = (SELECT MAX(id) FROM shipments x WHERE x.booking_id = b.id%s) ";
        $tabs = [
            'out' => [sprintf($base, " AND x.direction = 'out'") . "WHERE b.status = 'confirmed' AND b.delivery_method <> 'pickup' ORDER BY b.ship_by_date, b.id", 'ต้องส่งออก'],
            'road' => [sprintf($base, " AND x.direction = 'out'") . "WHERE b.status = 'shipped' ORDER BY b.pickup_date, b.id", 'กำลังไปหาลูกค้า'],
            'due' => [sprintf($base, " AND x.direction = 'out'") . "WHERE b.status = 'picked_up' AND b.return_method = 'post' ORDER BY b.return_date, b.id", 'รอลูกค้าส่งคืน'],
            'back' => [sprintf($base, " AND x.direction = 'back'") . "WHERE b.status = 'returning' AND s.status = 'in_transit' ORDER BY s.shipped_on, b.id", 'พัสดุคืนกำลังมา'],
            'check' => [sprintf($base, " AND x.direction = 'back'") . "WHERE b.status = 'returning' AND s.status IN ('received','lost') ORDER BY s.arrived_at, b.id", 'รอตรวจสภาพ'],
            'dispute' => [sprintf($base, " AND x.direction = 'back'") . 'WHERE b.dispute_at IS NOT NULL AND b.dispute_resolved_at IS NULL ORDER BY b.dispute_at', 'ข้อโต้แย้งรอตอบ'],
        ];
        if (!isset($tabs[$tab])) {
            $tab = 'out';
        }
        $counts = [];
        foreach ($tabs as $k => [$sql]) {
            $counts[$k] = count(DB::all($sql));
        }
        $this->admin('shipping/index', [
            'title' => 'จัดส่งและรับคืน', 'tab' => $tab, 'tabs' => $tabs, 'counts' => $counts, 'items' => DB::all($tabs[$tab][0]),
        ]);
    }

    /** The scan desk: a barcode gun types into the box (and presses Enter), or a phone camera reads the QR. */
    public function scan(): void
    {
        $q = strtoupper(trim(input_str('q')));
        $found = $q !== '' ? self::lookup($q) : null;
        if ($found && isset($found['go'])) {
            redirect($found['go']);
        }
        $this->admin('scan/index', ['title' => 'สแกนชุด', 'q' => $q, 'found' => $found]);
    }

    /**
     * Where a scanned code leads: a booking number opens the booking; a dress code opens the rental that dress
     * is in right now, at the step that is due (inspect, receive, ship), or the dress itself when it is on the rail.
     * @return array<string, mixed>
     */
    public static function lookup(string $code): array
    {
        $code = preg_replace('#^.*/(?:dress|tag|admin/costumes)/#i', '', $code) ?? $code;
        $code = strtoupper(trim($code, " /\t"));
        $b = DB::one('SELECT id FROM bookings WHERE booking_no = ?', [$code]);
        if ($b) {
            return ['go' => '/admin/bookings/' . $b['id']];
        }
        $c = Catalog::byCode($code);
        if (!$c) {
            return ['none' => true];
        }
        $live = DB::all(
            "SELECT b.*, u.first_name, u.last_name, u.nickname FROM bookings b JOIN users u ON u.id = b.user_id
              WHERE b.costume_id = ? AND b.status IN ('confirmed','shipped','picked_up','returning')
              ORDER BY FIELD(b.status,'returning','picked_up','shipped','confirmed'), b.pickup_date",
            [$c['id']]
        );
        $next = [];
        foreach ($live as $b) {
            $path = '/admin/bookings/' . $b['id'];
            $parcel = $b['status'] === 'returning' ? Fulfilment::lastShipment((int) $b['id'], 'back') : null;
            [$label, $href] = match (true) {
                $b['status'] === 'returning' && $parcel && $parcel['status'] === 'in_transit' => ['รับพัสดุคืน', $path . '#return'],
                in_array($b['status'], ['returning', 'picked_up'], true) => ['ตรวจสภาพและรับคืน', $path . '/inspect'],
                $b['status'] === 'confirmed' && BookingService::ships($b) => ['แพ็กและส่งพัสดุ', $path],
                $b['status'] === 'confirmed' => ['ส่งมอบให้ลูกค้าที่ร้าน', $path],
                default => ['ดูการจอง', $path],
            };
            $next[] = ['b' => $b, 'label' => $label, 'href' => $href];
        }
        return ['costume' => $c, 'next' => $next, 'state' => \App\Services\Availability::inventoryState($c)];
    }

    public function damageTypes(): void
    {
        $this->admin('damage-types/index', ['title' => 'ตารางค่าเสียหาย', 'types' => Inspection::types(false)]);
    }

    public function damageTypeSave(): void
    {
        $id = input_int('id');
        $in = [
            'name' => mb_substr(input_str('name'), 0, 120),
            'area' => in_array(input_str('area'), Inspection::AREAS, true) ? input_str('area') : '',
            'fee_type' => input_str('fee_type') === 'percent' ? 'percent' : 'fixed',
            'amount' => input_str('amount'),
            'hint' => mb_substr(input_str('hint'), 0, 200) ?: null,
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];
        $errors = [];
        if ($in['name'] === '') {
            $errors['name'] = 'ใส่ชื่อรายการ';
        }
        if ($in['area'] === '') {
            $errors['area'] = 'เลือกหมวด';
        }
        if (!is_numeric($in['amount']) || (float) $in['amount'] < 0 || ($in['fee_type'] === 'percent' && (float) $in['amount'] > 100)) {
            $errors['amount'] = $in['fee_type'] === 'percent' ? 'ใส่ร้อยละ 0–100' : 'ใส่จำนวนเงิน';
        }
        $dupe = DB::value('SELECT id FROM damage_types WHERE name = ? AND id <> ?', [$in['name'], $id]);
        if ($dupe) {
            $errors['name'] = 'มีรายการชื่อนี้แล้ว';
        }
        if ($errors) {
            back_with_errors($errors, '/admin/damage-types' . ($id ? '?edit=' . $id : '') . '#form');
        }
        $in['amount'] = round((float) $in['amount'], 2);
        if ($id && DB::one('SELECT id FROM damage_types WHERE id = ?', [$id])) {
            DB::update('damage_types', $in, 'id = :id', ['id' => $id]);
            flash('success', 'บันทึก “' . $in['name'] . '” แล้ว');
        } else {
            $in['sort_order'] = (int) DB::value('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM damage_types');
            DB::insert('damage_types', $in);
            flash('success', 'เพิ่ม “' . $in['name'] . '” แล้ว');
        }
        redirect('/admin/damage-types');
    }

    /**
     * Where the address on a label comes from: the booking's own copy.
     * @param array<string, mixed> $b
     */
    public static function shipTo(array $b): string
    {
        return trim($b['ship_recipient'] . ' ' . phone_fmt((string) $b['ship_phone']) . "\n" . $b['ship_address']);
    }

    /** The shop's own return address, for the prepaid return label. */
    public static function shopAddress(): string
    {
        return trim(setting('shop_name') . ' ' . setting('shop_phone') . "\n" . setting('shop_address'));
    }
}
