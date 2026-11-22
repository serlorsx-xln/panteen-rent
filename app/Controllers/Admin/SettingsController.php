<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Mailer;
use App\Core\Validator;
use App\Services\PromptPay;
use App\Services\Settings;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->admin('settings', [
            'title' => 'ตั้งค่าร้าน',
            's' => Settings::all(),
            'mail' => ['driver' => Config::get('mail.driver'), 'host' => Config::get('mail.smtp.host'), 'user' => Config::get('mail.smtp.username'), 'from' => Config::get('mail.from_email')],
        ]);
    }

    public function update(): void
    {
        $v = Validator::make($_POST, [
            'shop_name' => 'required|max:100', 'shop_bio' => 'required|max:160', 'shop_phone' => 'required|max:30',
            'shop_line' => 'nullable|max:50', 'shop_instagram' => 'nullable|max:50', 'shop_address' => 'required|max:300',
            'shop_hours' => 'required|max:100', 'open_time' => 'required|max:5', 'close_time' => 'required|max:5',
            'promptpay_id' => 'required|max:20', 'promptpay_name' => 'required|max:100',
            'bank_name' => 'nullable|max:100', 'bank_account' => 'nullable|max:30',
            'base_days' => 'required|between:1,14', 'max_days' => 'required|between:1,30', 'cleaning_days' => 'required|between:0,7',
            'hold_hours' => 'required|between:1,168', 'cancel_before_days' => 'required|between:0,30', 'min_lead_days' => 'required|between:0,30',
            'booking_horizon_days' => 'required|between:7,730', 'default_pickup_time' => 'required|max:5', 'default_return_time' => 'required|max:5',
            'remind_before_days' => 'required|between:0,7', 'rental_terms' => 'required|max:3000',
            'ship_fee_metro' => 'required|between:0,2000', 'ship_fee_upcountry' => 'required|between:0,2000', 'messenger_fee' => 'required|between:0,5000',
            'ship_days_metro' => 'required|between:1,7', 'ship_days_upcountry' => 'required|between:1,7', 'ship_cutoff' => 'required|max:5',
            'arrival_report_hours' => 'required|between:1,168', 'dispute_days' => 'required|between:1,30',
        ], [
            'shop_name' => 'ชื่อร้าน', 'shop_bio' => 'คำอธิบายร้าน', 'shop_phone' => 'เบอร์โทรร้าน', 'shop_address' => 'ที่อยู่', 'shop_hours' => 'เวลาเปิดปิด',
            'promptpay_id' => 'พร้อมเพย์', 'promptpay_name' => 'ชื่อบัญชีพร้อมเพย์', 'base_days' => 'จำนวนวันในแพ็กเกจ', 'max_days' => 'จำนวนวันเช่าสูงสุด',
            'cleaning_days' => 'วันซักชุด', 'hold_hours' => 'เวลาจองรอชำระ', 'cancel_before_days' => 'ยกเลิกล่วงหน้า', 'min_lead_days' => 'จองล่วงหน้าขั้นต่ำ',
            'booking_horizon_days' => 'จองล่วงหน้าได้สูงสุด', 'remind_before_days' => 'เตือนล่วงหน้า', 'rental_terms' => 'เงื่อนไขการเช่า',
            'ship_fee_metro' => 'ค่าส่งกรุงเทพฯ', 'ship_fee_upcountry' => 'ค่าส่งต่างจังหวัด', 'messenger_fee' => 'ค่าเมสเซนเจอร์',
            'ship_days_metro' => 'ระยะขนส่งกรุงเทพฯ', 'ship_days_upcountry' => 'ระยะขนส่งต่างจังหวัด', 'ship_cutoff' => 'เวลาตัดรอบส่ง',
            'arrival_report_hours' => 'เวลาแจ้งสภาพตอนได้รับ', 'dispute_days' => 'เวลาโต้แย้ง',
        ]);
        $d = $v->data();
        foreach (['open_time', 'close_time', 'default_pickup_time', 'default_return_time', 'ship_cutoff'] as $t) {
            if (!$v->fails() && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $d[$t])) {
                $v->addError($t, 'เวลาต้องอยู่ในรูปแบบ 13:00');
            }
        }
        if (!$v->fails()) {
            try {
                PromptPay::payload($d['promptpay_id'], 1);
            } catch (\InvalidArgumentException) {
                $v->addError('promptpay_id', 'พร้อมเพย์ต้องเป็นเบอร์มือถือ 10 หลัก หรือเลขประจำตัว 13 หลัก');
            }
            if ((int) $d['max_days'] < (int) $d['base_days']) {
                $v->addError('max_days', 'จำนวนวันเช่าสูงสุดต้องไม่น้อยกว่าจำนวนวันในแพ็กเกจ');
            }
            if ($d['close_time'] <= $d['open_time']) {
                $v->addError('close_time', 'เวลาปิดต้องหลังเวลาเปิด');
            }
        }
        if ($v->fails()) {
            back_with_errors($v->errors(), '/admin/settings');
        }
        $d['promptpay_id'] = preg_replace('/\D/', '', $d['promptpay_id']);
        foreach (['is_demo', 'ship_enabled', 'messenger_enabled', 'return_post_enabled'] as $flag) {
            $d[$flag] = !empty($_POST[$flag]) ? '1' : '0';
        }
        Settings::set($d);
        flash('success', 'บันทึกการตั้งค่าแล้ว');
        redirect('/admin/settings');
    }

    public function testMail(): void
    {
        $u = Auth::user();
        $id = Mailer::send($u['email'], 'ทดสอบการส่งอีเมล · ' . Settings::get('shop_name'), 'notice', [
            'user' => $u,
            'heading' => 'อีเมลทดสอบ',
            'lines' => ['ถ้าคุณเห็นข้อความนี้ แปลว่าระบบส่งอีเมลได้ตามปกติ', 'โหมดการส่ง: ' . Config::get('mail.driver')],
        ]);
        $row = \App\Core\DB::one('SELECT status, error FROM mail_outbox WHERE id = ?', [$id]);
        if ($row['status'] === 'failed') {
            flash('error', 'ส่งไม่สำเร็จ: ' . $row['error']);
        } else {
            flash('success', $row['status'] === 'sent' ? 'ส่งอีเมลทดสอบไปที่ ' . $u['email'] . ' แล้ว' : 'บันทึกอีเมลทดสอบไว้ในกล่องจดหมายจำลองแล้ว (โหมด log)');
        }
        redirect('/admin/settings#mail');
    }

    /** Demo only: wipe everything and rebuild the sample shop around today's date. */
    public function reseed(): void
    {
        if (Settings::get('is_demo') !== '1') {
            abort(403);
        }
        @set_time_limit(120);
        \App\Services\DemoSeeder::run();
        \App\Core\Session::destroy();
        \App\Core\Session::start();
        flash('success', 'สร้างข้อมูลตัวอย่างใหม่แล้ว เข้าสู่ระบบอีกครั้งด้วยบัญชีทดลอง');
        redirect('/login');
    }
}
