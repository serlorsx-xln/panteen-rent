<?php
declare(strict_types=1);

namespace App\Services;

/** Labels and display tone for every status in the system (one vocabulary everywhere). */
final class Status
{
    public const BOOKING = [
        'pending_payment'       => ['รอชำระเงิน', 'wait'],
        'awaiting_verification' => ['รอร้านตรวจสลิป', 'wait'],
        'confirmed'             => ['ยืนยันแล้ว รอรับชุด', 'ok'],
        'shipped'               => ['ร้านส่งชุดแล้ว ระหว่างขนส่ง', 'live'],
        'picked_up'             => ['ชุดอยู่กับลูกค้า (กำลังเช่า)', 'live'],
        'returning'             => ['ส่งชุดคืนแล้ว รอร้านตรวจรับ', 'live'],
        'returned'              => ['คืนชุดแล้ว', 'done'],
        'cancelled'             => ['ยกเลิกแล้ว', 'off'],
        'expired'               => ['หมดเวลาชำระ', 'off'],
    ];

    public const PAYMENT = [
        'pending'  => ['รอตรวจสอบ', 'wait'],
        'approved' => ['อนุมัติแล้ว', 'ok'],
        'rejected' => ['ไม่ผ่าน', 'bad'],
    ];

    public const PAYMENT_KIND = ['booking' => 'ค่าเช่า + มัดจำ', 'fine' => 'ค่าปรับส่วนเกินมัดจำ', 'refund' => 'คืนเงิน'];

    public const RECEIPT = ['booking' => 'ใบเสร็จค่าเช่าและมัดจำ', 'settlement' => 'ใบสรุปการคืนชุด', 'adjustment' => 'ใบปรับปรุงค่าเสียหาย'];

    public const SHIPMENT = [
        'in_transit' => ['ระหว่างขนส่ง', 'live'],
        'delivered'  => ['ถึงลูกค้าแล้ว', 'ok'],
        'failed'     => ['ส่งไม่สำเร็จ', 'bad'],
        'received'   => ['ร้านได้รับคืนแล้ว', 'ok'],
        'lost'       => ['สูญหายระหว่างขนส่ง', 'bad'],
    ];

    public const PHOTO_STAGE = [
        'handover' => 'สภาพก่อนส่งมอบ',
        'arrival'  => 'ลูกค้าถ่ายตอนได้รับ',
        'dropoff'  => 'ใบรับฝากพัสดุคืน',
        'return'   => 'สภาพตอนรับคืน',
        'dispute'  => 'หลักฐานโต้แย้งของลูกค้า',
    ];

    public const DISPUTE = [
        'open'     => ['รอร้านทบทวน', 'wait'],
        'resolved' => ['ร้านตอบแล้ว', 'done'],
    ];
    public const PAYMENT_METHOD = ['promptpay' => 'พร้อมเพย์', 'transfer' => 'โอนผ่านบัญชี', 'cash' => 'เงินสด'];

    public const COSTUME = [
        'active'      => ['เปิดให้เช่า', 'ok'],
        'maintenance' => ['ซ่อม/ซักพิเศษ', 'wait'],
        'retired'     => ['เลิกให้เช่า', 'off'],
    ];

    /** Where a dress is today, derived by Availability::inventoryState() for the back office */
    public const INVENTORY = [
        'available'   => ['ว่างตอนนี้', 'ok'],
        'booked'      => ['ติดจองวันนี้', 'wait'],
        'on_rent'     => ['อยู่กับลูกค้า', 'live'],
        'in_transit'  => ['อยู่ระหว่างขนส่ง', 'live'],
        'cleaning'    => ['รอซักหลังคืน', 'done'],
        'maintenance' => ['ซ่อม/ซักพิเศษ', 'wait'],
        'retired'     => ['เลิกให้เช่า', 'off'],
    ];

    public const APPOINTMENT_TYPE = ['fitting' => 'นัดลองชุด', 'pickup' => 'นัดรับชุด', 'return' => 'นัดคืนชุด'];
    public const APPOINTMENT = [
        'scheduled' => ['นัดไว้', 'wait'],
        'done'      => ['เสร็จแล้ว', 'done'],
        'cancelled' => ['ยกเลิก', 'off'],
        'no_show'   => ['ไม่มาตามนัด', 'bad'],
    ];

    public const ROLE = ['admin' => 'ผู้ดูแลระบบ', 'staff' => 'พนักงาน', 'member' => 'สมาชิก'];
    public const USER = ['active' => ['ใช้งานได้', 'ok'], 'suspended' => ['ถูกระงับ', 'bad']];

    /** @param array<string, array{string, string}> $map key => [label, tone] */
    public static function badge(array $map, ?string $key): string
    {
        [$label, $tone] = $map[$key] ?? [$key ?? '–', 'off'];
        return '<span class="status status-' . e($tone) . '">' . e($label) . '</span>';
    }

    /** @param array<string, array{string, string}|string> $map key => [label, tone] or key => label */
    public static function label(array $map, ?string $key): string
    {
        $v = $map[$key] ?? $key;
        return is_array($v) ? $v[0] : (string) $v;
    }
}
