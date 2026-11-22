<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\Settings;

/* ---------------------------------------------------------------- output */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Inline SVG icon from the Lucide sprite. */
function icon(string $name, string $class = 'ic', ?string $label = null): string
{
    $aria = $label ? 'role="img" aria-label="' . e($label) . '"' : 'aria-hidden="true"';
    return '<svg class="' . e($class) . '" ' . $aria . ' focusable="false"><use href="' . e(asset('img/icons.svg')) . '#i-' . e($name) . '"></use></svg>';
}

/* ---------------------------------------------------------------- urls */

/** Sub-folder the app is served from, e.g. "/panteen-rent" under XAMPP, "" at a domain root. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = (string) Config::get('app.url', '');
    if ($configured !== '') {
        return $base = rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
    }
    if (PHP_SAPI === 'cli' && !isset($_SERVER['SCRIPT_NAME'])) {
        return $base = '';
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($script), '/');
    // Served through the root .htaccess rewrite: /panteen-rent/public/index.php -> /panteen-rent
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }
    return $base = ($dir === '.' ? '' : $dir);
}

/** @param array<string, mixed> $query */
function url(string $path = '/', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/** The host asked for is this machine itself (localhost, 127.0.0.1 or [::1], any port). */
function is_loopback_host(string $host): bool
{
    return preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host) === 1;
}

/** The request comes from this machine and asks for it by a loopback name: a demo on the developer's own computer. */
function is_local_request(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && is_loopback_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
}

/**
 * The simulated mailbox holds every link the shop has mailed, password resets included,
 * so it opens for admins, or in dev mode for someone sitting at this machine, and for nobody else.
 */
function mailbox_open(): bool
{
    return Auth::isAdmin() || (Config::get('app.env') === 'dev' && is_local_request());
}

/**
 * Full URL for links that leave the site (e-mails). Built from app.url; without it, from the Host header
 * only when that host is this machine or listed in app.hosts, because a client can send any Host it likes
 * and a forged one would put its own domain into a password-reset link.
 * @param array<string, mixed> $query
 */
function absolute_url(string $path = '/', array $query = []): string
{
    $configured = (string) Config::get('app.url', '');
    if ($configured !== '') {
        $scheme_host = preg_replace('#^(https?://[^/]+).*$#', '$1', $configured);
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $allowed = array_map('strtolower', array_map('strval', (array) Config::get('app.hosts', [])));
        if (!is_loopback_host($host) && !in_array(strtolower($host), $allowed, true)) {
            $host = $allowed[0] ?? 'localhost';
        }
        $scheme_host = ($https ? 'https' : 'http') . '://' . $host;
    }
    return $scheme_host . url($path, $query);
}

function asset(string $path): string
{
    $file = PUBLIC_PATH . '/assets/' . $path;
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url('assets/' . $path) . '?v=' . $v;
}

function media(?string $path, string $fallback = 'img/placeholder.svg'): string
{
    if (!$path) {
        return asset($fallback);
    }
    return url('media/' . ltrim($path, '/'));
}

/** Larger copy for the dress page (media/costumes/large/...), falling back to the full image. */
function large(?string $path): string
{
    if ($path) {
        $l = dirname($path) . '/large/' . basename($path);
        if (is_file(PUBLIC_PATH . '/media/' . $l)) {
            return media($l);
        }
    }
    return media($path);
}

/**
 * Pixel size [width, height] of a file under public/media, read once per request; null when it cannot be read.
 * @return array{int, int}|null
 */
function media_size(?string $path): ?array
{
    static $seen = [];
    if (!$path) {
        return null;
    }
    if (!array_key_exists($path, $seen)) {
        $file = PUBLIC_PATH . '/media/' . $path;
        $size = is_file($file) ? @getimagesize($file) : false;
        $seen[$path] = $size ? [(int) $size[0], (int) $size[1]] : null;
    }
    return $seen[$path];
}

/**
 * srcset for a catalogue photograph in a grid: the small copy and the full copy, each described by its real
 * width so the browser picks the right one. Empty when the photograph has only one copy.
 */
function photo_srcset(?string $path): string
{
    if (!$path) {
        return '';
    }
    $small = media_size(dirname($path) . '/thumb/' . basename($path));
    $full = media_size($path);
    if (!$small || !$full) {
        return '';
    }
    return thumb($path) . ' ' . $small[0] . 'w, ' . media($path) . ' ' . $full[0] . 'w';
}

/** Smaller copy for grids (media/costumes/thumb/...), falling back to the full image. */
function thumb(?string $path): string
{
    if ($path) {
        $t = dirname($path) . '/thumb/' . basename($path);
        if (is_file(PUBLIC_PATH . '/media/' . $t)) {
            return media($t);
        }
    }
    return media($path);
}

/** Small copy for list rows and side panels (media/costumes/mini/..., 320px on the long side), falling back to thumb(). */
function mini(?string $path): string
{
    if ($path) {
        $t = dirname($path) . '/mini/' . basename($path);
        if (is_file(PUBLIC_PATH . '/media/' . $t)) {
            return media($t);
        }
    }
    return thumb($path);
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    if (str_starts_with($uri, '/public/')) {
        $uri = substr($uri, 7);
    }
    return '/' . ltrim($uri, '/');
}

function current_path_with_query(): string
{
    $q = $_SERVER['QUERY_STRING'] ?? '';
    return request_path() . ($q !== '' ? '?' . $q : '');
}

function is_path(string $prefix): bool
{
    $p = request_path();
    return $prefix === '/' ? $p === '/' : ($p === $prefix || str_starts_with($p, rtrim($prefix, '/') . '/'));
}

/* ---------------------------------------------------------------- responses */

function redirect(string $path, int $code = 302): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    if (defined('PANTEEN_TESTING')) {
        throw new \App\Core\HttpRedirect($target);
    }
    header('Location: ' . $target, true, $code);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && $host && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
        redirect($ref);
    }
    redirect($fallback);
}

function abort(int $code, ?string $message = null): never
{
    http_response_code($code);
    $titles = [
        403 => 'หน้านี้สำหรับเจ้าหน้าที่ร้าน',
        404 => 'ไม่พบหน้าที่คุณหา',
        419 => 'หน้านี้หมดอายุแล้ว',
        500 => 'ระบบขัดข้องชั่วคราว',
    ];
    $messages = [
        403 => 'บัญชีของคุณไม่มีสิทธิ์เปิดหน้านี้ ถ้าคิดว่าเป็นความผิดพลาด ลองออกจากระบบแล้วเข้าใหม่ด้วยบัญชีที่ถูกต้อง',
        404 => 'ลิงก์อาจพิมพ์ผิด หรือชุดนี้ถูกนำออกจากร้านไปแล้ว',
        500 => 'ขออภัย ลองใหม่อีกครั้งในอีกสักครู่',
    ];
    if (wants_json()) {
        json_response(['ok' => false, 'error' => $message ?? ($titles[$code] ?? 'Error')], $code);
    }
    View::render('errors/error', [
        'code' => $code,
        'title' => $titles[$code] ?? 'เกิดข้อผิดพลาด',
        'message' => $message ?? ($messages[$code] ?? ''),
    ], 'layouts/shop');
    exit;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** @param array<string, mixed> $data */
function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------------------------------------------------------- forms */

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    $all = Session::get('_flash', []);
    $all[] = ['type' => $type, 'message' => $message];
    Session::put('_flash', $all);
}

/** @return list<array{type: string, message: string}> */
function take_flashes(): array
{
    return Session::pull('_flash', []);
}

/**
 * Store old input + errors and go back to the form.
 * @param array<string, string> $errors field => message
 */
function back_with_errors(array $errors, string $fallback): never
{
    $input = $_POST;
    unset($input['_token'], $input['password'], $input['password_confirmation'], $input['current_password']);
    Session::put('_old', $input);
    Session::put('_errors', $errors);
    redirect($fallback);
}

function old(string $key, mixed $default = ''): mixed
{
    static $old = null;
    if ($old === null) {
        $old = Session::pull('_old', []);
    }
    return $old[$key] ?? $default;
}

/** @return array<string, string> */
function form_errors(): array
{
    static $errors = null;
    if ($errors === null) {
        $errors = Session::pull('_errors', []);
    }
    return $errors;
}

function field_error(string $key): string
{
    $err = form_errors()[$key] ?? null;
    return $err ? '<p class="field-error" id="err-' . e($key) . '">' . icon('circle-alert', 'ic ic-sm') . e($err) . '</p>' : '';
}

function invalid_attr(string $key): string
{
    return isset(form_errors()[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}

function input_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

/* ---------------------------------------------------------------- money & dates */

function money(float|int|string|null $amount, bool $withUnit = true): string
{
    $amount = (float) ($amount ?? 0);
    $s = number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2);
    return $withUnit ? $s . ' บาท' : $s;
}

/** Short Thai price used in captions: "590.-" */
function price_tag(float|int|string $amount): string
{
    return number_format((float) $amount) . '.-';
}

const THAI_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const THAI_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const THAI_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
const THAI_DAYS_SHORT = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];

function to_ts(string|int|\DateTimeInterface|null $date): ?int
{
    if ($date === null || $date === '') {
        return null;
    }
    if ($date instanceof \DateTimeInterface) {
        return $date->getTimestamp();
    }
    return is_int($date) ? $date : (strtotime($date) ?: null);
}

/** 14 ธ.ค. 2569 */
function thai_date(string|int|\DateTimeInterface|null $date, bool $long = false, bool $withYear = true): string
{
    $ts = to_ts($date);
    if ($ts === null) {
        return '–';
    }
    $m = (int) date('n', $ts);
    $s = date('j', $ts) . ' ' . ($long ? THAI_MONTHS[$m] : THAI_MONTHS_SHORT[$m]);
    if ($withYear) {
        $s .= ' ' . ((int) date('Y', $ts) + 543);
    }
    return $s;
}

/** ส. 14 ธ.ค. 2569 : the short form the date desk speaks in */
function thai_date_dow(string|int|null $date): string
{
    $ts = to_ts($date);
    return $ts === null ? '–' : THAI_DAYS_SHORT[(int) date('w', $ts)] . '. ' . thai_date($ts);
}

/** วันอาทิตย์ที่ 14 ธันวาคม 2569 */
function thai_date_full(string|int|null $date): string
{
    $ts = to_ts($date);
    return $ts === null ? '–' : 'วัน' . THAI_DAYS[(int) date('w', $ts)] . 'ที่ ' . thai_date($ts, true);
}

function thai_datetime(string|int|null $date): string
{
    $ts = to_ts($date);
    return $ts === null ? '–' : thai_date($ts) . ' ' . date('H:i', $ts) . ' น.';
}

function thai_month_year(int $year, int $month): string
{
    return THAI_MONTHS[$month] . ' ' . ($year + 543);
}

function today(): string
{
    return date('Y-m-d');
}

function days_between(string $from, string $to): int
{
    return (int) round((strtotime($to . ' 12:00:00') - strtotime($from . ' 12:00:00')) / 86400);
}

function add_days(string $date, int $days): string
{
    return date('Y-m-d', strtotime($date . ' 12:00:00 ' . ($days >= 0 ? '+' : '') . $days . ' days'));
}

/** "อีก 3 วัน" / "พรุ่งนี้" / "วันนี้" / "เมื่อวาน" */
function relative_day(string $date): string
{
    $d = days_between(today(), $date);
    return match (true) {
        $d === 0  => 'วันนี้',
        $d === 1  => 'พรุ่งนี้',
        $d === -1 => 'เมื่อวาน',
        $d > 1    => "อีก $d วัน",
        default   => abs($d) . ' วันก่อน',
    };
}

function time_ago(string $datetime): string
{
    $diff = time() - (int) strtotime($datetime);
    return match (true) {
        $diff < 60     => 'เมื่อสักครู่',
        $diff < 3600   => floor($diff / 60) . ' นาทีที่แล้ว',
        $diff < 86400  => floor($diff / 3600) . ' ชั่วโมงที่แล้ว',
        $diff < 604800 => floor($diff / 86400) . ' วันที่แล้ว',
        default        => thai_date($datetime),
    };
}

/* ---------------------------------------------------------------- misc */

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function str_limit(string $s, int $len): string
{
    return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
}

/** @param array<string, mixed> $u */
function full_name(array $u): string
{
    return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}

/** @param array<string, mixed> $u */
function initials(array $u): string
{
    $name = (string) ($u['nickname'] ?: ($u['first_name'] ?? '?'));
    // Thai leading vowels (เ แ โ ใ ไ) are written before the consonant: keep the consonant too.
    $n = preg_match('/^[\x{0E40}-\x{0E44}]/u', $name) ? 2 : 1;
    return mb_strtoupper(mb_substr($name, 0, $n));
}


/** 0812345678 -> 081-234-5678 (Thai mobile), 021234567 -> 02-123-4567 (Bangkok landline). */
function phone_fmt(?string $p): string
{
    $d = preg_replace('/\D/', '', (string) $p);
    if (strlen($d) === 10) {
        return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
    }
    if (strlen($d) === 9 && str_starts_with($d, '02')) {
        return '02-' . substr($d, 2, 3) . '-' . substr($d, 5);
    }
    return (string) $p;
}
