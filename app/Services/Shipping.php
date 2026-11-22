<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Getting a dress to the customer and back.
 *
 * Two legs, chosen separately:
 *   out  : pickup (at the shop) | post (parcel, 1–2 days by zone) | messenger (same day, Bangkok and nearby only)
 *   back : store (bring it back) | post (the shop packs a prepaid return label in the box)
 *
 * The rental window stays "the day the customer has the dress" to "the day she hands it back"
 * (pickup_date … return_date), so rent and late fees work the same for every method. Shipping only
 * moves the days the dress is away from the rail: it leaves `out days` before pickup_date and is
 * back `back days` after return_date, then needs its cleaning days.
 */
final class Shipping
{
    public const DELIVERY = [
        'pickup'    => 'รับชุดที่ร้าน',
        'post'      => 'ส่งพัสดุถึงบ้าน',
        'messenger' => 'เมสเซนเจอร์ส่งด่วนในวัน',
    ];

    public const RETURN = [
        'store' => 'นำชุดมาคืนที่ร้าน',
        'post'  => 'ส่งพัสดุคืน (ใช้ใบปะหน้าที่ร้านแนบไว้)',
    ];

    public const ZONE = ['metro' => 'กรุงเทพฯ และปริมณฑล', 'upcountry' => 'ต่างจังหวัด'];

    /** Bangkok and the five provinces around it */
    public const METRO = ['กรุงเทพมหานคร', 'นนทบุรี', 'ปทุมธานี', 'สมุทรปราการ', 'สมุทรสาคร', 'นครปฐม'];

    /** All 77 provinces, Bangkok first, the rest in Thai alphabetical order */
    public const PROVINCES = [
        'กรุงเทพมหานคร', 'กระบี่', 'กาญจนบุรี', 'กาฬสินธุ์', 'กำแพงเพชร', 'ขอนแก่น', 'จันทบุรี', 'ฉะเชิงเทรา', 'ชลบุรี', 'ชัยนาท',
        'ชัยภูมิ', 'ชุมพร', 'เชียงราย', 'เชียงใหม่', 'ตรัง', 'ตราด', 'ตาก', 'นครนายก', 'นครปฐม', 'นครพนม', 'นครราชสีมา',
        'นครศรีธรรมราช', 'นครสวรรค์', 'นนทบุรี', 'นราธิวาส', 'น่าน', 'บึงกาฬ', 'บุรีรัมย์', 'ปทุมธานี', 'ประจวบคีรีขันธ์', 'ปราจีนบุรี',
        'ปัตตานี', 'พระนครศรีอยุธยา', 'พะเยา', 'พังงา', 'พัทลุง', 'พิจิตร', 'พิษณุโลก', 'เพชรบุรี', 'เพชรบูรณ์', 'แพร่', 'ภูเก็ต',
        'มหาสารคาม', 'มุกดาหาร', 'แม่ฮ่องสอน', 'ยโสธร', 'ยะลา', 'ร้อยเอ็ด', 'ระนอง', 'ระยอง', 'ราชบุรี', 'ลพบุรี', 'ลำปาง',
        'ลำพูน', 'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สงขลา', 'สตูล', 'สมุทรปราการ', 'สมุทรสงคราม', 'สมุทรสาคร', 'สระแก้ว', 'สระบุรี',
        'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี', 'สุราษฎร์ธานี', 'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู', 'อ่างทอง', 'อำนาจเจริญ',
        'อุดรธานี', 'อุตรดิตถ์', 'อุทัยธานี', 'อุบลราชธานี',
    ];

    /**
     * key => [name, tracking page, tracking number pattern or null]. The tracking pages take the number
     * typed in (none of them documents a link that fills it in), so screens show the number with a copy button.
     */
    public const CARRIERS = [
        'thaipost'  => ['ไปรษณีย์ไทย (EMS / ลงทะเบียน)', 'https://track.thailandpost.co.th/', '/^[A-Z]{2}\d{9}TH$/'],
        'kex'       => ['KEX (Kerry Express)', 'https://th.kex-express.com/th/track/', '/^[A-Z0-9]{8,20}$/'],
        'flash'     => ['Flash Express', 'https://www.flashexpress.co.th/fle/tracking', '/^[A-Z0-9]{8,20}$/'],
        'jnt'       => ['J&T Express', 'https://www.jtexpress.co.th/service/track', '/^\d{12}$/'],
        'messenger' => ['เมสเซนเจอร์ (Lalamove / Grab)', null, null],
        'other'     => ['ขนส่งอื่น', null, '/^[A-Z0-9-]{6,30}$/'],
    ];

    public static function enabled(): bool
    {
        return Settings::get('ship_enabled', '1') === '1';
    }

    public static function messengerEnabled(): bool
    {
        return self::enabled() && Settings::get('messenger_enabled', '1') === '1';
    }

    public static function returnPostEnabled(): bool
    {
        return self::enabled() && Settings::get('return_post_enabled', '1') === '1';
    }

    public static function zone(?string $province): ?string
    {
        if ($province === null || !in_array($province, self::PROVINCES, true)) {
            return null;
        }
        return in_array($province, self::METRO, true) ? 'metro' : 'upcountry';
    }

    /** Days a parcel takes one way. Messenger and the counter are same day. */
    public static function transitDays(string $method, ?string $zone): int
    {
        if ($method !== 'post') {
            return 0;
        }
        return Settings::int($zone === 'metro' ? 'ship_days_metro' : 'ship_days_upcountry');
    }

    /** @return array{delivery: float, return: float} */
    public static function fees(string $delivery, string $return, ?string $zone): array
    {
        $post = (float) Settings::get($zone === 'metro' ? 'ship_fee_metro' : 'ship_fee_upcountry', 0);
        return [
            'delivery' => match ($delivery) {
                'post' => $post,
                'messenger' => (float) Settings::get('messenger_fee', 0),
                default => 0.0,
            },
            'return' => $return === 'post' ? $post : 0.0,
        ];
    }

    /**
     * Everything the booking needs to know about moving the dress.
     * @return array{delivery: string, return: string, zone: ?string, out_days: int, back_days: int, ship_by: ?string,
     *               block_from: string, block_until: string, delivery_fee: float, return_fee: float}
     */
    public static function plan(string $pickup, string $return, string $delivery = 'pickup', string $returnMethod = 'store', ?string $zone = null): array
    {
        $delivery = isset(self::DELIVERY[$delivery]) ? $delivery : 'pickup';
        $returnMethod = isset(self::RETURN[$returnMethod]) ? $returnMethod : 'store';
        $out = self::transitDays($delivery, $zone);
        $back = self::transitDays($returnMethod, $zone);
        $fees = self::fees($delivery, $returnMethod, $zone);
        return [
            'delivery' => $delivery,
            'return' => $returnMethod,
            'zone' => $delivery === 'pickup' && $returnMethod === 'store' ? null : $zone,
            'out_days' => $out,
            'back_days' => $back,
            'ship_by' => $delivery === 'pickup' ? null : add_days($pickup, -$out),
            'block_from' => add_days($pickup, -$out),
            'block_until' => Availability::blockUntil($return, $back),
            'delivery_fee' => $fees['delivery'],
            'return_fee' => $fees['return'],
        ];
    }

    /** Has today's dispatch cut-off passed? Parcels confirmed after it go out tomorrow. */
    public static function pastCutoff(?int $now = null): bool
    {
        return date('H:i', $now ?? time()) >= (string) Settings::get('ship_cutoff', '14:00');
    }

    /** First day a parcel booked now can reach the customer. */
    public static function earliestArrival(string $delivery, ?string $zone): string
    {
        $first = add_days(today(), self::pastCutoff() ? 1 : 0);
        return add_days($first, self::transitDays($delivery, $zone));
    }

    /**
     * Problems with a delivery choice, field => message (empty when it can be done).
     * Staff at the counter may still send a parcel today after the cut-off: they can see the courier.
     * @param array<string, mixed> $plan from plan()
     * @return array<string, string>
     */
    public static function check(array $plan, bool $staff = false): array
    {
        $errors = [];
        $usesShipping = $plan['delivery'] !== 'pickup' || $plan['return'] === 'post';
        if ($usesShipping && !self::enabled()) {
            return ['delivery_method' => 'ตอนนี้ร้านงดบริการจัดส่ง เลือกรับและคืนชุดที่ร้าน'];
        }
        if ($plan['delivery'] === 'messenger' && !self::messengerEnabled()) {
            $errors['delivery_method'] = 'ตอนนี้ร้านงดบริการเมสเซนเจอร์';
        }
        if ($plan['return'] === 'post' && !self::returnPostEnabled()) {
            $errors['return_method'] = 'ตอนนี้ร้านรับคืนเฉพาะที่ร้าน';
        }
        if ($usesShipping && $plan['zone'] === null) {
            $errors['address'] = 'เลือกที่อยู่จัดส่งให้ครบ';
            return $errors;
        }
        if ($plan['delivery'] === 'messenger' && $plan['zone'] !== 'metro') {
            $errors['delivery_method'] = 'เมสเซนเจอร์ส่งได้เฉพาะ' . self::ZONE['metro'] . ' ต่างจังหวัดเลือกส่งพัสดุ';
        }
        if ($plan['ship_by'] !== null && !$errors) {
            $today = today();
            $late = $plan['ship_by'] < $today || ($plan['ship_by'] === $today && !$staff && self::pastCutoff());
            if ($late) {
                $first = self::earliestArrival($plan['delivery'], $plan['zone']);
                $errors['pickup_date'] = 'ส่ง' . ($plan['delivery'] === 'messenger' ? 'เมสเซนเจอร์' : 'พัสดุ') . 'ไม่ทันวันนั้น ได้รับชุดเร็วที่สุดวันที่ '
                    . thai_date($first) . ' หรือเลือกรับที่ร้าน';
            }
        }
        return $errors;
    }

    /**
     * Validate an address typed by a member or staff. Returns [clean values, errors].
     * @param array<string, mixed> $in
     * @return array{array<string, string|null>, array<string, string>}
     */
    public static function cleanAddress(array $in): array
    {
        $s = fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? '')) ?? ''), 0, $max);
        $a = [
            'label' => $s('label', 40) ?: null,
            'recipient' => $s('recipient', 150),
            'phone' => preg_replace('/\D/', '', (string) ($in['phone'] ?? '')) ?? '',
            'line1' => $s('line1', 255),
            'subdistrict' => $s('subdistrict', 100),
            'district' => $s('district', 100),
            'province' => $s('province', 60),
            'postcode' => preg_replace('/\D/', '', (string) ($in['postcode'] ?? '')) ?? '',
            'note' => $s('note', 255) ?: null,
        ];
        $errors = [];
        $need = ['recipient' => 'ชื่อผู้รับ', 'line1' => 'บ้านเลขที่และถนน', 'subdistrict' => 'แขวง/ตำบล', 'district' => 'เขต/อำเภอ'];
        foreach ($need as $k => $label) {
            if ($a[$k] === '') {
                $errors[$k] = 'กรอก' . $label;
            }
        }
        if (!preg_match('/^0\d{8,9}$/', $a['phone'])) {
            $errors['phone'] = 'เบอร์โทรผู้รับ 9–10 หลัก ขึ้นต้นด้วย 0';
        }
        if (!in_array($a['province'], self::PROVINCES, true)) {
            $errors['province'] = 'เลือกจังหวัด';
        }
        if (!preg_match('/^[1-9]\d{4}$/', $a['postcode'])) {
            $errors['postcode'] = 'รหัสไปรษณีย์ 5 หลัก';
        }
        return [$a, $errors];
    }

    /** @param array<string, mixed> $a an address row or cleaned input */
    public static function addressText(array $a): string
    {
        $bkk = ($a['province'] ?? '') === 'กรุงเทพมหานคร';
        return trim($a['line1'] . ' ' . ($bkk ? 'แขวง' : 'ต.') . $a['subdistrict'] . ' ' . ($bkk ? 'เขต' : 'อ.') . $a['district']
            . ' ' . ($bkk ? '' : 'จ.') . $a['province'] . ' ' . $a['postcode'])
            . (!empty($a['note']) ? "\nจุดสังเกต: " . $a['note'] : '');
    }

    /**
     * Normalise and check a tracking number for a carrier. Returns [number or null, error or null].
     * @return array{?string, ?string}
     */
    public static function cleanTracking(string $carrier, string $no): array
    {
        if (!isset(self::CARRIERS[$carrier])) {
            return [null, 'เลือกบริษัทขนส่ง'];
        }
        $no = strtoupper(preg_replace('/[\s-]+/', '', $no) ?? '');
        $pattern = self::CARRIERS[$carrier][2];
        if ($pattern === null) {
            return [$no !== '' ? mb_substr($no, 0, 40) : null, null];
        }
        if ($no === '') {
            return [null, 'กรอกเลขพัสดุ'];
        }
        if (!preg_match($pattern, $no)) {
            return [null, 'เลขพัสดุ ' . self::CARRIERS[$carrier][0] . ' ไม่ถูกรูปแบบ' . ($carrier === 'thaipost' ? ' (ตัวอย่าง EF123456789TH)' : ($carrier === 'jnt' ? ' (ตัวเลข 12 หลัก)' : ''))];
        }
        return [$no, null];
    }

    public static function carrierName(?string $carrier): string
    {
        return self::CARRIERS[$carrier ?? ''][0] ?? (string) $carrier;
    }

    public static function trackingPage(?string $carrier): ?string
    {
        return self::CARRIERS[$carrier ?? ''][1] ?? null;
    }
}
