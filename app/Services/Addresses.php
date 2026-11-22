<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** A member's delivery addresses (up to MAX). Bookings copy the address, so editing it later changes no booking. */
final class Addresses
{
    public const MAX = 5;

    /** Form field names, as "addr_<field>" in forms */
    public const FIELDS = ['label', 'recipient', 'phone', 'line1', 'subdistrict', 'district', 'province', 'postcode', 'note'];

    /** @return list<array<string, mixed>> */
    public static function forUser(int $userId): array
    {
        return DB::all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id', [$userId]);
    }

    /** @return array<string, mixed>|null */
    public static function find(int $userId, int $id): ?array
    {
        return DB::one('SELECT * FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * The "addr_*" fields of a request, ready for Shipping::cleanAddress().
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function fromPost(array $post): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $v = $post['addr_' . $f] ?? '';
            $out[$f] = is_string($v) ? $v : '';
        }
        return $out;
    }

    /**
     * Field errors keyed the way the form names them.
     * @param array<string, string> $errors
     * @return array<string, string>
     */
    public static function formErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $k => $v) {
            $out['addr_' . $k] = $v;
        }
        return $out;
    }

    /**
     * Add or update an address. Returns [id, errors].
     * @param array<string, mixed> $input raw fields (without the addr_ prefix)
     * @return array{?int, array<string, string>}
     */
    public static function save(int $userId, array $input, ?int $id = null, bool $makeDefault = false): array
    {
        [$a, $errors] = Shipping::cleanAddress($input);
        if ($errors) {
            return [null, $errors];
        }
        return DB::transaction(function () use ($userId, $a, $id, $makeDefault) {
            DB::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            $count = (int) DB::value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$userId]);
            if ($id === null) {
                if ($count >= self::MAX) {
                    return [null, ['form' => 'เก็บที่อยู่ได้สูงสุด ' . self::MAX . ' ที่อยู่ ลบที่อยู่เดิมก่อนเพิ่มใหม่']];
                }
                $id = DB::insert('addresses', $a + ['user_id' => $userId, 'is_default' => $count === 0 ? 1 : 0]);
            } else {
                if (!self::find($userId, $id)) {
                    return [null, ['form' => 'ไม่พบที่อยู่นี้']];
                }
                DB::update('addresses', $a, 'id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
            }
            if ($makeDefault) {
                self::makeDefault($userId, $id);
            }
            return [$id, []];
        });
    }

    /**
     * The delivery part of a booking form: how the dress goes out and back, and to which address.
     * The address is a saved one (address_id) or typed in the form (address_id = "new", fields addr_*).
     * @param array<string, mixed> $post
     * @return array{delivery: string, return: string, address: ?array<string, mixed>, save: bool, errors: array<string, string>}
     */
    public static function choice(array $post, int $userId): array
    {
        $delivery = is_string($post['delivery_method'] ?? null) && isset(Shipping::DELIVERY[$post['delivery_method']]) ? $post['delivery_method'] : 'pickup';
        $return = is_string($post['return_method'] ?? null) && isset(Shipping::RETURN[$post['return_method']]) ? $post['return_method'] : 'store';
        $out = ['delivery' => $delivery, 'return' => $return, 'address' => null, 'save' => false, 'errors' => []];
        if ($delivery === 'pickup' && $return === 'store') {
            return $out;
        }
        $sel = (string) ($post['address_id'] ?? '');
        if (ctype_digit($sel)) {
            $row = self::find($userId, (int) $sel);
            if (!$row) {
                $out['errors'] = ['address_id' => 'เลือกที่อยู่จัดส่ง'];
                return $out;
            }
            $out['address'] = $row;
            return $out;
        }
        [$a, $errors] = Shipping::cleanAddress(self::fromPost($post));
        if ($errors) {
            $out['errors'] = self::formErrors($errors);
            return $out;
        }
        $out['address'] = $a;
        $out['save'] = !empty($post['save_address']);
        return $out;
    }

    public static function makeDefault(int $userId, int $id): bool
    {
        if (!self::find($userId, $id)) {
            return false;
        }
        DB::run('UPDATE addresses SET is_default = (id = ?) WHERE user_id = ?', [$id, $userId]);
        return true;
    }

    public static function delete(int $userId, int $id): bool
    {
        $a = self::find($userId, $id);
        if (!$a) {
            return false;
        }
        DB::run('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ((int) $a['is_default'] === 1) {
            DB::run('UPDATE addresses SET is_default = 1 WHERE user_id = ? ORDER BY id LIMIT 1', [$userId]);
        }
        return true;
    }
}
