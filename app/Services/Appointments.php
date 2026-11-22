<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Appointments: fitting (requested by members or entered by staff), plus pick-up and return slots that
 * are created automatically when a booking is confirmed.
 */
final class Appointments
{
    public const SLOT_MINUTES = 30;
    public const SLOT_CAPACITY = 2; // fittings per 30-minute slot (two fitting rooms)

    public static function syncForBooking(int $bookingId, ?int $actorId): void
    {
        $b = DB::one('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
        // a parcel leg has no visit to the shop, so no slot is kept for it
        $visits = [
            'pickup' => $b['delivery_method'] === 'pickup' ? [$b['pickup_date'], $b['pickup_time']] : null,
            'return' => $b['return_method'] === 'store' ? [$b['return_date'], $b['return_time']] : null,
        ];
        foreach ($visits as $type => $visit) {
            $existing = DB::one("SELECT id FROM appointments WHERE booking_id = ? AND type = ? AND status = 'scheduled'", [$bookingId, $type]);
            if ($visit === null) {
                if ($existing) {
                    DB::update('appointments', ['status' => 'cancelled'], 'id = :id', ['id' => $existing['id']]);
                }
                continue;
            }
            $starts = $visit[0] . ' ' . substr((string) $visit[1], 0, 5) . ':00';
            if ($existing) {
                DB::update('appointments', ['starts_at' => $starts], 'id = :id', ['id' => $existing['id']]);
            } else {
                DB::insert('appointments', ['user_id' => $b['user_id'], 'booking_id' => $bookingId, 'type' => $type, 'starts_at' => $starts, 'created_by' => $actorId]);
            }
        }
    }

    /**
     * Slots for a day: list of ['time' => '10:00', 'left' => n].
     * @return list<array{time: string, left: int}>
     */
    public static function fittingSlots(string $date): array
    {
        $open = Settings::get('open_time', '10:00');
        $close = Settings::get('close_time', '19:00');
        $taken = [];
        foreach (DB::all("SELECT DATE_FORMAT(starts_at, '%H:%i') AS t, COUNT(*) AS n FROM appointments
                           WHERE DATE(starts_at) = ? AND type = 'fitting' AND status = 'scheduled' GROUP BY t", [$date]) as $r) {
            $taken[$r['t']] = (int) $r['n'];
        }
        $slots = [];
        $now = time();
        for ($t = strtotime("$date $open"); $t + self::SLOT_MINUTES * 60 <= strtotime("$date $close"); $t += self::SLOT_MINUTES * 60) {
            $hm = date('H:i', $t);
            $slots[] = ['time' => $hm, 'left' => $t <= $now ? 0 : max(0, self::SLOT_CAPACITY - ($taken[$hm] ?? 0))];
        }
        return $slots;
    }

    public static function requestFitting(int $userId, string $date, string $time, ?string $note, ?int $actorId = null, ?int $costumeId = null): int
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $date < today() || $date > add_days(today(), 60)) {
            throw new BookingException('เลือกวันนัดภายใน 60 วันข้างหน้า');
        }
        // one booking of a day's slots at a time: without it two requests could both see the last seat free
        $lock = 'panteen:fitting:' . $date;
        if ((int) DB::value('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            throw new BookingException('ระบบนัดหมายไม่ว่างชั่วครู่ ลองใหม่อีกครั้ง');
        }
        try {
            return self::bookFitting($userId, $date, $time, $note, $actorId, $costumeId);
        } finally {
            DB::value('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private static function bookFitting(int $userId, string $date, string $time, ?string $note, ?int $actorId, ?int $costumeId): int
    {
        return DB::transaction(function () use ($userId, $date, $time, $note, $actorId, $costumeId) {
            $slot = null;
            foreach (self::fittingSlots($date) as $s) {
                if ($s['time'] === $time) {
                    $slot = $s;
                }
            }
            if (!$slot || $slot['left'] < 1) {
                throw new BookingException('ช่วงเวลานี้เต็มแล้ว เลือกเวลาอื่น');
            }
            $dup = DB::value("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND type = 'fitting' AND status = 'scheduled' AND starts_at >= NOW()", [$userId]);
            if ((int) $dup >= 2 && $actorId === null) {
                throw new BookingException('คุณมีนัดลองชุดที่ยังไม่ถึงกำหนด 2 นัดแล้ว');
            }
            $costumeNote = '';
            if ($costumeId) {
                $c = DB::one('SELECT code, name FROM costumes WHERE id = ?', [$costumeId]);
                $costumeNote = $c ? 'ชุดที่อยากลอง: ' . $c['code'] . ' ' . $c['name'] : '';
            }
            $fullNote = trim($costumeNote . ($note ? "\n" . $note : ''));
            $id = DB::insert('appointments', [
                'user_id' => $userId, 'type' => 'fitting', 'starts_at' => "$date $time:00",
                'note' => $fullNote !== '' ? mb_substr($fullNote, 0, 500) : null, 'created_by' => $actorId ?? $userId,
            ]);
            Notifier::user($userId, 'appointment', 'นัดลองชุด ' . thai_date($date) . ' เวลา ' . $time . ' น.', 'มาที่ร้านตามเวลานัด ถ้ามาไม่ได้ยกเลิกในหน้านัดหมาย', '/account/appointments', null, [
                'subject' => 'ยืนยันนัดลองชุด ' . thai_date($date) . ' ' . $time . ' น.',
                'data' => [
                    'heading' => 'นัดลองชุดของคุณ',
                    'lines' => ['เราเตรียมห้องลองชุดไว้ให้แล้ว', Settings::get('shop_address')],
                    'items' => ['วัน' => thai_date_full($date), 'เวลา' => $time . ' น.'] + ($costumeNote ? ['ชุด' => substr($costumeNote, strlen('ชุดที่อยากลอง: '))] : []),
                    'button' => ['ดูนัดหมาย', absolute_url('/account/appointments')],
                ],
            ]);
            Notifier::staff('appointment_new', 'นัดลองชุดใหม่ ' . thai_date($date) . ' ' . $time, $costumeNote, '/admin/appointments?date=' . $date);
            return $id;
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!isset(Status::APPOINTMENT[$status])) {
            throw new BookingException('สถานะไม่ถูกต้อง');
        }
        DB::update('appointments', ['status' => $status], 'id = :id', ['id' => $id]);
    }
}
