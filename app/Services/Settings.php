<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Shop settings stored as key/value rows. DEFAULTS documents every key.
 * All shop details are demonstration values until the owner edits them.
 */
final class Settings
{
    public const DEFAULTS = [
        'shop_name'            => 'Panteen Rent',
        'shop_bio'             => 'ร้านเช่าชุดผู้หญิง · รับปริญญา · ออกงาน · ชุดไทย · รังสิต',
        'shop_phone'           => '090-000-0000',
        'shop_line'            => '@panteenrent',
        'shop_instagram'       => 'panteen.rent',
        'shop_address'         => 'ที่อยู่ร้านตัวอย่าง ต.หลักหก อ.เมืองปทุมธานี จ.ปทุมธานี 12000',
        'shop_hours'           => 'เปิดทุกวัน 10:00–19:00 น.',
        'open_time'            => '10:00',
        'close_time'           => '19:00',
        'promptpay_id'         => '0900000000',
        'promptpay_name'       => 'ร้าน Panteen Rent (ตัวอย่าง)',
        'bank_name'            => 'ธนาคารตัวอย่าง',
        'bank_account'         => '000-0-00000-0',
        'base_days'            => '3',
        'max_days'             => '7',
        'cleaning_days'        => '1',
        'hold_hours'           => '24',
        'cancel_before_days'   => '3',
        'min_lead_days'        => '0',
        'booking_horizon_days' => '180',
        'default_pickup_time'  => '13:00',
        'default_return_time'  => '13:00',
        'remind_before_days'   => '1',
        'ship_enabled'         => '1',
        'ship_fee_metro'       => '80',
        'ship_fee_upcountry'   => '120',
        'ship_days_metro'      => '1',
        'ship_days_upcountry'  => '2',
        'ship_cutoff'          => '14:00',
        'messenger_enabled'    => '1',
        'messenger_fee'        => '250',
        'return_post_enabled'  => '1',
        'arrival_report_hours' => '24',
        'dispute_days'         => '3',
        'is_demo'              => '1',
        'rental_terms'         => "ค่าเช่ารวมการใช้ชุด 3 วัน (วันรับ วันงาน วันคืน) วันต่อไปคิดตามราคาวันเพิ่มของแต่ละชุด\nเงินมัดจำคืนเต็มจำนวนเมื่อคืนชุดตรงเวลาและสภาพสมบูรณ์\nคืนช้าคิดค่าปรับรายวันตามที่ระบุในแต่ละชุด และหักจากเงินมัดจำ\nยกเลิกได้ก่อนวันรับชุดอย่างน้อย 3 วัน ร้านคืนเงินเต็มจำนวน\nส่งพัสดุ: ร้านส่งให้ถึงก่อนวันรับชุด ลูกค้าตรวจและถ่ายรูปชุดภายใน 24 ชั่วโมงหลังได้รับ ถ้าพบปัญหาแจ้งร้านทันที\nส่งคืนทางพัสดุ: ฝากส่งภายในวันคืนชุดด้วยใบปะหน้าที่แนบไว้ในกล่อง ร้านนับวันฝากส่งเป็นวันคืน\nค่าเสียหายคิดตามตารางค่าเสียหายของร้าน ไม่เกินมูลค่าชุด และโต้แย้งได้ภายใน 3 วันหลังได้รับใบสรุป",
    ];

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /** @return array<string, string|null> */
    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = [];
            try {
                foreach (DB::all('SELECT `key`, `value` FROM settings') as $r) {
                    $rows[$r['key']] = $r['value'];
                }
            } catch (\Throwable) {
                // Database not installed yet: fall back to defaults.
            }
            self::$cache = $rows + self::DEFAULTS;
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key, self::DEFAULTS[$key] ?? 0);
    }

    /** @param array<string, mixed> $values key => value */
    public static function set(array $values): void
    {
        foreach ($values as $k => $v) {
            DB::run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, (string) $v]);
        }
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
