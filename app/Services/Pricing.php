<?php
declare(strict_types=1);

namespace App\Services;

/**
 * All money rules in one place, so screen, email and receipt itemise identically.
 *
 * Rental days are counted inclusively: pick up 13 Dec, return 15 Dec = 3 days.
 * The rent price covers `base_days` days; every further day costs extra_day_price.
 * Total due at booking = rent + extra days + deposit + delivery fee + return-label fee
 * (the deposit is refunded at return minus fines).
 */
final class Pricing
{
    public static function rentalDays(string $pickup, string $return): int
    {
        return days_between($pickup, $return) + 1;
    }

    /**
     * @param array<string, mixed> $costume
     * @return array{days:int, base_days:int, extra_days:int, rent:float, extra:float, deposit:float, delivery_fee:float, return_fee:float,
     *               subtotal:float, total:float, late_fee_per_day:float}
     */
    public static function quote(array $costume, string $pickup, string $return, ?int $baseDays = null, float $deliveryFee = 0.0, float $returnFee = 0.0): array
    {
        $baseDays ??= Settings::int('base_days');
        $days = self::rentalDays($pickup, $return);
        $extraDays = max(0, $days - $baseDays);
        $rent = round((float) $costume['rent_price'], 2);
        $extra = round($extraDays * (float) $costume['extra_day_price'], 2);
        $deposit = round((float) $costume['deposit'], 2);
        $deliveryFee = round(max(0.0, $deliveryFee), 2);
        $returnFee = round(max(0.0, $returnFee), 2);
        return [
            'days'             => $days,
            'base_days'        => $baseDays,
            'extra_days'       => $extraDays,
            'rent'             => $rent,
            'extra'            => $extra,
            'deposit'          => $deposit,
            'delivery_fee'     => $deliveryFee,
            'return_fee'       => $returnFee,
            'subtotal'         => $rent + $extra,
            'total'            => $rent + $extra + $deposit + $deliveryFee + $returnFee,
            'late_fee_per_day' => (float) $costume['late_fee_per_day'],
        ];
    }

    /**
     * Settlement at return.
     * @return array{late_days:int, late_fee:float, damage_fee:float, fines:float, deposit:float, deposit_refund:float, extra_charge:float}
     */
    public static function settle(float $deposit, float $lateFeePerDay, string $dueDate, string $actualReturnDate, float $damageFee = 0.0): array
    {
        $lateDays = max(0, days_between($dueDate, $actualReturnDate));
        $lateFee = round($lateDays * $lateFeePerDay, 2);
        $damageFee = round(max(0.0, $damageFee), 2);
        $fines = $lateFee + $damageFee;
        return [
            'late_days'      => $lateDays,
            'late_fee'       => $lateFee,
            'damage_fee'     => $damageFee,
            'fines'          => $fines,
            'deposit'        => $deposit,
            'deposit_refund' => round(max(0.0, $deposit - $fines), 2),
            'extra_charge'   => round(max(0.0, $fines - $deposit), 2),
        ];
    }

    /**
     * Default rental window around an event: the day before to the day after.
     * @return array{string, string} [pickup, return]
     */
    public static function defaultWindow(string $eventDate): array
    {
        // Pick up the day before, but never in the past: an event today means pick up today.
        $pickup = max(add_days($eventDate, -1), min(today(), $eventDate));
        return [$pickup, add_days($eventDate, 1)];
    }
}
