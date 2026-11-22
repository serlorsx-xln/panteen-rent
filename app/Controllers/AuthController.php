<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Tokens;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->view('auth/login', ['title' => 'เข้าสู่ระบบ']);
    }

    public function login(): void
    {
        $email = input_str('email');
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            back_with_errors(['email' => 'กรอกอีเมลและรหัสผ่าน'], '/login');
        }
        $res = Auth::attempt($email, $password, client_ip());
        if (!$res['ok']) {
            back_with_errors(['email' => $res['error']], '/login');
        }
        $user = $res['user'];
        flash('success', 'ยินดีต้อนรับ คุณ' . ($user['nickname'] ?: $user['first_name']));
        $intended = Session::pull('intended');
        if (in_array($user['role'], ['admin', 'staff'], true)) {
            redirect($intended && str_starts_with($intended, '/admin') ? $intended : '/admin');
        }
        redirect($intended && !str_starts_with($intended, '/admin') ? $intended : '/');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('info', 'ออกจากระบบแล้ว');
        redirect('/');
    }

    public function registerForm(): void
    {
        $this->view('auth/register', ['title' => 'สมัครสมาชิก']);
    }

    public function register(): void
    {
        $_POST['email'] = mb_strtolower(input_str('email'));
        $v = Validator::make($_POST, [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'nickname' => 'nullable|max:50',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'required|phone',
            'line_id' => 'nullable|max:50',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
        ], [
            'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'email' => 'อีเมล',
            'phone' => 'เบอร์โทร', 'line_id' => 'LINE ID', 'password' => 'รหัสผ่าน', 'password_confirmation' => 'ยืนยันรหัสผ่าน',
        ]);
        if (empty($_POST['accept'])) {
            $v->addError('accept', 'กรุณายอมรับเงื่อนไขการเช่าชุด');
        }
        if ($v->fails()) {
            back_with_errors($v->errors(), '/register');
        }
        $d = $v->data();
        $id = DB::insert('users', [
            'role' => 'member',
            'first_name' => $d['first_name'],
            'last_name' => $d['last_name'],
            'nickname' => $d['nickname'],
            'email' => $d['email'],
            'phone' => preg_replace('/\D/', '', $d['phone']),
            'line_id' => $d['line_id'],
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
        ]);
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
        Tokens::sendVerification($user);
        Auth::login($id);
        redirect('/account/verify');
    }

    public function verifyNotice(): void
    {
        $user = Auth::user();
        if ($user['email_verified_at']) {
            redirect('/');
        }
        $this->view('auth/verify', ['title' => 'ยืนยันอีเมล', 'user' => $user]);
    }

    public function resendVerification(): void
    {
        $user = Auth::user();
        if (!$user['email_verified_at']) {
            $recent = (int) DB::value("SELECT COUNT(*) FROM email_tokens WHERE user_id = ? AND type = 'verify' AND created_at > (NOW() - INTERVAL 1 MINUTE)", [$user['id']]);
            if ($recent) {
                flash('warning', 'เพิ่งส่งไปเมื่อสักครู่ รอ 1 นาทีแล้วค่อยขอใหม่');
            } else {
                Tokens::sendVerification($user);
                flash('success', 'ส่งลิงก์ยืนยันใหม่ไปที่ ' . $user['email'] . ' แล้ว');
            }
        }
        redirect('/account/verify');
    }

    public function verify(string $token): void
    {
        $row = Tokens::consume($token, 'verify');
        if (!$row) {
            $this->view('auth/message', [
                'title' => 'ลิงก์ใช้ไม่ได้',
                'heading' => 'ลิงก์ยืนยันนี้หมดอายุหรือถูกใช้ไปแล้ว',
                'text' => 'เข้าสู่ระบบแล้วกด “ส่งลิงก์ใหม่” เพื่อรับลิงก์ยืนยันอีกครั้ง',
                'action' => ['เข้าสู่ระบบ', '/login'],
            ]);
            return;
        }
        DB::run('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [$row['user_id']]);
        if (!Auth::check()) {
            Auth::login((int) $row['user_id']);
        }
        Auth::refresh();
        flash('success', 'ยืนยันอีเมลเรียบร้อย เริ่มจองชุดได้เลย');
        redirect('/');
    }

    public function forgotForm(): void
    {
        $this->view('auth/forgot', ['title' => 'ลืมรหัสผ่าน']);
    }

    public function forgot(): void
    {
        $email = mb_strtolower(input_str('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            back_with_errors(['email' => 'รูปแบบอีเมลไม่ถูกต้อง'], '/forgot');
        }
        $user = DB::one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
        if ($user) {
            $recent = (int) DB::value("SELECT COUNT(*) FROM email_tokens WHERE user_id = ? AND type = 'reset' AND created_at > (NOW() - INTERVAL 1 MINUTE)", [$user['id']]);
            if (!$recent) {
                Tokens::sendReset($user);
            }
        }
        // Same answer whether or not the e-mail exists (no account enumeration).
        $this->view('auth/message', [
            'title' => 'ตรวจอีเมลของคุณ',
            'heading' => 'ถ้ามีบัญชีนี้ในระบบ เราส่งลิงก์ไปที่ ' . $email . ' แล้ว',
            'text' => 'ลิงก์ตั้งรหัสผ่านใหม่ใช้ได้ภายใน 1 ชั่วโมง ถ้าไม่เห็นอีเมล ลองดูในโฟลเดอร์จดหมายขยะ',
            'action' => ['กลับไปหน้าเข้าสู่ระบบ', '/login'],
        ]);
    }

    public function resetForm(string $token): void
    {
        if (!Tokens::consume($token, 'reset', false)) {
            $this->view('auth/message', [
                'title' => 'ลิงก์ใช้ไม่ได้',
                'heading' => 'ลิงก์ตั้งรหัสผ่านนี้หมดอายุหรือถูกใช้ไปแล้ว',
                'text' => 'ขอลิงก์ใหม่ได้ที่หน้า “ลืมรหัสผ่าน”',
                'action' => ['ขอลิงก์ใหม่', '/forgot'],
            ]);
            return;
        }
        $this->view('auth/reset', ['title' => 'ตั้งรหัสผ่านใหม่', 'token' => $token]);
    }

    public function reset(string $token): void
    {
        $v = Validator::make($_POST, ['password' => 'required|password', 'password_confirmation' => 'required|same:password'], ['password' => 'รหัสผ่าน']);
        if ($v->fails()) {
            back_with_errors($v->errors(), '/reset/' . $token);
        }
        $row = Tokens::consume($token, 'reset');
        if (!$row) {
            flash('error', 'ลิงก์หมดอายุแล้ว ขอลิงก์ใหม่อีกครั้ง');
            redirect('/forgot');
        }
        DB::update('users', [
            'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'email_verified_at' => DB::value('SELECT COALESCE(email_verified_at, NOW()) FROM users WHERE id = ?', [$row['user_id']]),
        ], 'id = :id', ['id' => $row['user_id']]);
        DB::run('DELETE FROM login_attempts WHERE email = (SELECT email FROM users WHERE id = ?)', [$row['user_id']]);
        Auth::logout();
        flash('success', 'ตั้งรหัสผ่านใหม่แล้ว เข้าสู่ระบบด้วยรหัสผ่านใหม่ได้เลย');
        redirect('/login');
    }
}
