<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Return inspection: a fixed checklist, damage priced from the shop's damage table, accessories counted back,
 * and what happens to the dress next. Then the customer may dispute the charge once, within a few days,
 * and staff may give part or all of it back.
 */
final class Inspection
{
    /** Points every returned dress is checked on, key => what "passed" means */
    public const CHECKS = [
        'fabric'   => 'เนื้อผ้าไม่ขาด ไม่มีรู ไม่มีรอยไหม้',
        'seams'    => 'ตะเข็บและซับในเรียบร้อย',
        'closures' => 'ซิป กระดุม ตะขอ ใช้งานได้ครบ',
        'beading'  => 'งานปัก ลูกไม้ เลื่อม ลูกปัด อยู่ครบ',
        'stains'   => 'ไม่มีคราบ ไม่มีกลิ่น',
        'shape'    => 'ทรงชุดไม่ยืด ไม่เสียรูป',
    ];

    public const OUTCOME = [
        'ready'       => 'พร้อมให้เช่าต่อ (ซักตามรอบปกติ)',
        'maintenance' => 'ส่งซ่อมหรือซักพิเศษ (ปิดรับจองไว้ก่อน)',
        'retired'     => 'เลิกให้เช่า (เสียหายถาวรหรือสูญหาย)',
    ];

    public const AREAS = ['เนื้อผ้า', 'ตะเข็บและซับใน', 'ซิป กระดุม ตะขอ', 'งานปักและลูกไม้', 'คราบและกลิ่น', 'ของที่มากับชุด', 'ทั้งชุด'];

    /** The area whose first active type prices a missing accessory */
    public const ACCESSORY_AREA = 'ของที่มากับชุด';

    /** Standard damage table installed with the shop; staff edit prices in the back office. [name, area, fee type, amount, hint] */
    public const DEFAULT_TYPES = [
        ['ผ้าขาดหรือไหม้ ซ่อมได้', 'เนื้อผ้า', 'fixed', 800, 'ต่อจุด ซ่อมแล้วมองไม่เห็นรอย'],
        ['ผ้ายืดหรือเสียทรง', 'เนื้อผ้า', 'fixed', 500, 'ต้องปรับทรงหรือเปลี่ยนชิ้นผ้า'],
        ['ชายชุดสกปรกหรือรุ่ย', 'เนื้อผ้า', 'fixed', 250, 'ลากพื้นจนชายรุ่ย ต้องเก็บชาย'],
        ['ตะเข็บแยก', 'ตะเข็บและซับใน', 'fixed', 150, 'ต่อจุด เย็บซ่อมได้'],
        ['ซับในขาด', 'ตะเข็บและซับใน', 'fixed', 300, 'เปลี่ยนซับในบางส่วน'],
        ['ซิปเสีย ต้องเปลี่ยน', 'ซิป กระดุม ตะขอ', 'fixed', 350, 'รวมค่าซิปและค่าแรง'],
        ['กระดุมหรือตะขอหลุดหาย', 'ซิป กระดุม ตะขอ', 'fixed', 50, 'ต่อชิ้น'],
        ['เลื่อมหรือลูกปัดหลุด', 'งานปักและลูกไม้', 'fixed', 300, 'ต่อจุดขนาดประมาณฝ่ามือ'],
        ['ลูกไม้หรืองานปักฉีก', 'งานปักและลูกไม้', 'fixed', 600, 'ต่อจุด'],
        ['คราบเล็กน้อย ขจัดได้', 'คราบและกลิ่น', 'fixed', 200, 'ขจัดคราบเฉพาะจุด เช่น เครื่องสำอาง อาหาร'],
        ['คราบฝังแน่น ต้องซักแห้งพิเศษ', 'คราบและกลิ่น', 'fixed', 500, 'ไวน์ หมึก น้ำมัน คราบเหงื่อเหลือง'],
        ['กลิ่นบุหรี่หรืออับชื้น', 'คราบและกลิ่น', 'fixed', 300, 'ซักและอบโอโซน'],
        ['ของที่มากับชุดหาย', 'ของที่มากับชุด', 'fixed', 300, 'ต่อชิ้น เช่น ผ้าคล้องไหล่ เข็มขัด สไบ'],
        ['เสียหายจนให้เช่าต่อไม่ได้', 'ทั้งชุด', 'percent', 100, 'ร้อยละของมูลค่าชุด'],
        ['ชุดสูญหาย ไม่ได้รับคืน', 'ทั้งชุด', 'percent', 100, 'ร้อยละของมูลค่าชุด'],
    ];

    public const MAX_ROWS = 20;

    /** Install the standard damage table (kept: rows the shop already has). */
    public static function seedTypes(): void
    {
        foreach (self::DEFAULT_TYPES as $i => [$name, $area, $type, $amount, $hint]) {
            DB::run('INSERT IGNORE INTO damage_types (name, area, fee_type, amount, hint, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $area, $type, $amount, $hint, ($i + 1) * 10]);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function types(bool $activeOnly = true): array
    {
        return DB::all('SELECT * FROM damage_types' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
    }

    /** @return list<string> */
    public static function accessories(?string $list): array
    {
        $out = [];
        foreach (preg_split('/\s*[,\n]\s*/u', (string) $list) ?: [] as $a) {
            $a = trim($a);
            if ($a !== '' && !in_array($a, $out, true)) {
                $out[] = mb_substr($a, 0, 60);
            }
        }
        return $out;
    }

    /** Damage is never more than the dress is worth (when the shop has set its value). */
    public static function cap(float $fee, mixed $replacementValue): float
    {
        $fee = max(0.0, $fee);
        $value = (float) $replacementValue;
        return $value > 0 ? min($fee, $value) : $fee;
    }

    /**
     * One unit of a damage type for this dress, or null when a percentage has no dress value to work from.
     * @param array<string, mixed> $type
     */
    public static function unitPrice(array $type, mixed $replacementValue): ?float
    {
        if ($type['fee_type'] === 'percent') {
            $value = (float) $replacementValue;
            return $value > 0 ? round($value * (float) $type['amount'] / 100, 2) : null;
        }
        return (float) $type['amount'];
    }

    /**
     * Price what the inspection found.
     * $rows: list of [type_id, qty, amount ('' = standard price; any number = staff's own price for the line), note];
     * $missing: accessories that did not come back.
     * @param array<string, mixed> $costume needs replacement_value and accessories
     * @param list<array<string, mixed>> $rows
     * @param list<string> $missing
     * @return array{items: list<array{type_id: ?int, label: string, qty: int, amount: float, note: string}>, subtotal: float, total: float, capped: bool, errors: list<string>}
     */
    public static function price(array $costume, array $rows, array $missing): array
    {
        $types = [];
        foreach (self::types(false) as $t) {
            $types[(int) $t['id']] = $t;
        }
        $items = [];
        $errors = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $r) {
            $id = (int) ($r['type_id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $t = $types[$id] ?? null;
            if (!$t) {
                $errors[] = 'ไม่พบรายการค่าเสียหายที่เลือก';
                continue;
            }
            $qty = max(1, min(99, (int) ($r['qty'] ?? 1)));
            $note = mb_substr(trim((string) ($r['note'] ?? '')), 0, 255);
            $own = trim((string) ($r['amount'] ?? ''));
            if ($own !== '') {
                if (!is_numeric($own) || (float) $own < 0) {
                    $errors[] = $t['name'] . ': จำนวนเงินไม่ถูกต้อง';
                    continue;
                }
                $amount = round((float) $own, 2);
            } else {
                $unit = self::unitPrice($t, $costume['replacement_value'] ?? null);
                if ($unit === null) {
                    $errors[] = $t['name'] . ': ชุดนี้ยังไม่ได้ตั้งมูลค่าชุด ใส่จำนวนเงินเอง หรือตั้งมูลค่าในหน้าแก้ไขชุด';
                    continue;
                }
                $amount = round($unit * $qty, 2);
            }
            $items[] = ['type_id' => $id, 'label' => $t['name'], 'qty' => $qty, 'amount' => $amount, 'note' => $note];
        }
        $known = self::accessories($costume['accessories'] ?? null);
        $missing = array_values(array_intersect($known, $missing));
        if ($missing) {
            $acc = null;
            foreach ($types as $t) {
                if ($t['area'] === self::ACCESSORY_AREA && (int) $t['is_active'] === 1 && $t['fee_type'] === 'fixed') {
                    $acc = $t;
                    break;
                }
            }
            foreach ($missing as $m) {
                $items[] = ['type_id' => $acc ? (int) $acc['id'] : null, 'label' => 'ของที่มากับชุดหาย: ' . $m, 'qty' => 1,
                    'amount' => $acc ? (float) $acc['amount'] : 0.0, 'note' => ''];
            }
        }
        $subtotal = round(array_sum(array_column($items, 'amount')), 2);
        $total = self::cap($subtotal, $costume['replacement_value'] ?? null);
        return ['items' => $items, 'subtotal' => $subtotal, 'total' => $total, 'capped' => $total < $subtotal, 'errors' => $errors];
    }

    /**
     * "ตะเข็บแยก ×2 · ซิปเสีย ต้องเปลี่ยน"
     * @param list<array<string, mixed>> $items
     */
    public static function summary(array $items): string
    {
        return implode(' · ', array_map(fn($i) => $i['label'] . ($i['qty'] > 1 ? ' ×' . $i['qty'] : '') . ($i['note'] !== '' ? ' (' . $i['note'] . ')' : ''), $items));
    }

    /**
     * Finish the inspection and settle the rental.
     * @param array{returned_on?: string, rows?: list<array<string, mixed>>, missing?: list<string>, checks?: list<string>, outcome?: string, note?: string} $in
     * @return array<string, mixed>
     */
    public static function complete(int $bookingId, int $staffId, array $in): array
    {
        $b = BookingService::find($bookingId);
        if (!$b) {
            throw new BookingException('ไม่พบการจองนี้');
        }
        $outcome = $in['outcome'] ?? 'ready';
        if (!isset(self::OUTCOME[$outcome])) {
            throw new BookingException('เลือกสภาพชุดหลังตรวจ', ['outcome' => 'เลือกสภาพชุดหลังตรวจ']);
        }
        $p = self::price($b, $in['rows'] ?? [], $in['missing'] ?? []);
        if ($p['errors']) {
            throw new BookingException($p['errors'][0], ['damage' => implode(' ', $p['errors'])]);
        }
        $checks = array_values(array_intersect(array_keys(self::CHECKS), $in['checks'] ?? []));
        $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 300);
        if ($p['items'] === [] && count($checks) < count(self::CHECKS) && $note === '') {
            throw new BookingException('มีจุดตรวจที่ยังไม่ผ่าน ให้เพิ่มรายการค่าเสียหาย หรือเขียนหมายเหตุว่าพบอะไร (เช่น ร่องรอยเดิมก่อนเช่า)', ['checks' => 'ตรวจไม่ครบ']);
        }
        $damageNote = self::summary($p['items']) . ($p['capped'] ? ' (คิดไม่เกินมูลค่าชุด ' . money($b['replacement_value']) . ')' : '');
        return BookingService::markReturned($bookingId, $staffId, $in['returned_on'] ?? today(), $p['total'], $damageNote, [
            'items' => $p['items'], 'checks' => $checks, 'missing' => array_values(array_intersect(self::accessories($b['accessories']), $in['missing'] ?? [])),
            'outcome' => $outcome, 'note' => $note,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function items(int $bookingId): array
    {
        return DB::all('SELECT * FROM damage_items WHERE booking_id = ? ORDER BY id', [$bookingId]);
    }

    /** @param array<string, mixed> $b */
    public static function disputeDeadline(array $b): ?string
    {
        $from = $b['inspected_at'] ?? $b['returned_at'] ?? null;
        return $from ? date('Y-m-d H:i:s', strtotime((string) $from) + Settings::int('dispute_days') * 86400) : null;
    }

    /**
     * Can the customer still dispute the damage charge? [bool, reason]
     * @param array<string, mixed> $b
     * @return array{bool, string}
     */
    public static function canDispute(array $b): array
    {
        if ($b['status'] !== 'returned' || (float) $b['damage_fee'] <= 0) {
            return [false, 'การจองนี้ไม่มีค่าเสียหายให้โต้แย้ง'];
        }
        if ($b['dispute_at'] !== null) {
            return [false, 'คุณแจ้งโต้แย้งไปแล้ว'];
        }
        $deadline = self::disputeDeadline($b);
        if ($deadline === null || time() > strtotime($deadline)) {
            return [false, 'เลยเวลาโต้แย้งค่าเสียหายแล้ว (ภายใน ' . Settings::int('dispute_days') . ' วันหลังตรวจสภาพ)'];
        }
        return [true, ''];
    }

    /** @return array<string, mixed> */
    public static function dispute(int $bookingId, int $userId, string $note): array
    {
        $note = trim($note);
        if (mb_strlen($note) < 10) {
            throw new BookingException('อธิบายเหตุผลที่ไม่เห็นด้วยอย่างน้อย 10 ตัวอักษร', ['dispute_note' => 'อธิบายเหตุผล']);
        }
        return DB::transaction(function () use ($bookingId, $userId, $note) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b) {
                throw new BookingException('ไม่พบการจองนี้');
            }
            [$ok, $why] = self::canDispute($b);
            if (!$ok) {
                throw new BookingException($why);
            }
            DB::update('bookings', ['dispute_note' => mb_substr($note, 0, 500), 'dispute_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $bookingId]);
            BookingService::log($bookingId, $userId, 'dispute', 'ลูกค้าโต้แย้งค่าเสียหาย: ' . $note);
            $full = BookingService::find($bookingId);
            Notifier::staff('dispute', 'ลูกค้าโต้แย้งค่าเสียหาย ' . $full['booking_no'], money($full['damage_fee']) . ' · ' . mb_substr($note, 0, 80), '/admin/bookings/' . $bookingId . '#dispute');
            return $full;
        });
    }

    /**
     * Staff answer a dispute and may give back part or all of the damage charge (refunded, with an adjustment receipt).
     * @return array<string, mixed>
     */
    public static function resolve(int $bookingId, int $staffId, string $reply, float $waive): array
    {
        $reply = trim($reply);
        if ($reply === '') {
            throw new BookingException('เขียนคำตอบถึงลูกค้า', ['dispute_reply' => 'เขียนคำตอบ']);
        }
        return DB::transaction(function () use ($bookingId, $staffId, $reply, $waive) {
            $b = DB::one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if (!$b || $b['dispute_at'] === null || $b['dispute_resolved_at'] !== null) {
                throw new BookingException('ไม่มีข้อโต้แย้งที่รอตอบ');
            }
            $waive = round($waive, 2);
            if ($waive < 0 || $waive > (float) $b['damage_fee']) {
                throw new BookingException('ยอดที่ลดให้ต้องอยู่ระหว่าง 0 ถึง ' . money($b['damage_fee']), ['waive' => 'ยอดไม่ถูกต้อง']);
            }
            DB::update('bookings', ['dispute_reply' => mb_substr($reply, 0, 500), 'dispute_resolved_at' => date('Y-m-d H:i:s'), 'damage_waived' => $waive],
                'id = :id', ['id' => $bookingId]);
            $receipt = null;
            if ($waive > 0) {
                DB::insert('payments', ['booking_id' => $bookingId, 'kind' => 'refund', 'method' => 'transfer', 'amount' => $waive,
                    'status' => 'approved', 'reviewed_by' => $staffId, 'reviewed_at' => date('Y-m-d H:i:s'), 'created_by' => $staffId]);
                $receipt = Receipts::issueAdjustment($bookingId, $staffId);
            }
            BookingService::log($bookingId, $staffId, 'dispute_resolved', 'ตอบข้อโต้แย้ง' . ($waive > 0 ? ' · ลดค่าเสียหายและคืนเงิน ' . money($waive) : ' · คงค่าเสียหายเดิม') . ': ' . $reply);
            $full = BookingService::find($bookingId);
            $lines = ['คำตอบจากร้าน: ' . $reply, $waive > 0 ? 'ร้านลดค่าเสียหายให้ ' . money($waive) . ' และโอนคืนภายใน 3 วันทำการ' : 'ร้านคงค่าเสียหายตามเดิม'];
            Notifier::user((int) $full['user_id'], 'dispute_resolved', 'ร้านตอบข้อโต้แย้งค่าเสียหายแล้ว', end($lines), '/bookings/' . $bookingId, null, [
                'subject' => 'ผลการทบทวนค่าเสียหาย ' . $full['booking_no'],
                'data' => BookingService::mailData($full, 'ผลการทบทวนค่าเสียหาย', $lines,
                    $receipt ? ['ดูใบปรับปรุงยอด', absolute_url('/receipts/' . $receipt['id'])] : ['ดูการจอง', absolute_url('/bookings/' . $bookingId)]),
            ]);
            return $full;
        });
    }
}
