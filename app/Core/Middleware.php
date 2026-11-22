<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Route guards.
 *   auth     : any logged-in user
 *   guest    : only when logged out
 *   verified : logged in and e-mail verified
 *   staff    : admin or staff
 *   admin    : admin only
 */
final class Middleware
{
    public static function handle(string $name): void
    {
        switch ($name) {
            case 'auth':
                if (!Auth::check()) {
                    Session::put('intended', current_path_with_query());
                    flash('info', 'กรุณาเข้าสู่ระบบก่อนดำเนินการต่อ');
                    redirect('/login');
                }
                break;
            case 'guest':
                if (Auth::check()) {
                    redirect(Auth::isStaff() ? '/admin' : '/');
                }
                break;
            case 'verified':
                self::handle('auth');
                if (!Auth::user()['email_verified_at']) {
                    flash('warning', 'ยืนยันอีเมลก่อนจองชุด เราส่งลิงก์ยืนยันไปที่ ' . Auth::user()['email'] . ' แล้ว');
                    redirect('/account/verify');
                }
                break;
            case 'staff':
                self::handle('auth');
                if (!Auth::isStaff()) {
                    abort(403);
                }
                break;
            case 'admin':
                self::handle('auth');
                if (!Auth::isAdmin()) {
                    abort(403);
                }
                break;
            default:
                throw new \RuntimeException("Unknown middleware $name");
        }
    }
}
