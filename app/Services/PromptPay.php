<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Thai PromptPay QR payload (EMVCo merchant-presented QR, BOT specification).
 * Supports mobile numbers (10 digits), national/tax IDs (13 digits) and e-wallet IDs (15 digits).
 */
final class PromptPay
{
    private const AID = 'A000000677010111';

    public static function payload(string $target, ?float $amount = null): string
    {
        $digits = preg_replace('/\D/', '', $target);
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $tag = '01';
            $value = '0066' . substr($digits, 1);
        } elseif (strlen($digits) === 13) {
            $tag = '02';
            $value = $digits;
        } elseif (strlen($digits) === 15) {
            $tag = '03';
            $value = $digits;
        } else {
            throw new \InvalidArgumentException('PromptPay ID must be a 10-digit phone number, 13-digit ID or 15-digit e-wallet');
        }

        $merchant = self::f('00', self::AID) . self::f($tag, $value);
        $data = self::f('00', '01')
            . self::f('01', $amount !== null ? '12' : '11')
            . self::f('29', $merchant)
            . self::f('58', 'TH')
            . self::f('53', '764');
        if ($amount !== null) {
            $data .= self::f('54', number_format($amount, 2, '.', ''));
        }
        $data .= '6304';
        return $data . self::crc16($data);
    }

    private static function f(string $id, string $value): string
    {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    /** CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF), upper-case hex. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /** Display form of the ID: 090-000-0000 */
    public static function display(string $target): string
    {
        $d = preg_replace('/\D/', '', $target);
        if (strlen($d) === 10) {
            return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
        }
        if (strlen($d) === 13) {
            return $d[0] . '-' . substr($d, 1, 4) . '-' . substr($d, 5, 5) . '-' . substr($d, 10, 2) . '-' . $d[12];
        }
        return $d;
    }
}
