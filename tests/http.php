<?php
declare(strict_types=1);

/**
 * HTTP integration tests: a real PHP server (port 8089) on the test database,
 * driven with curl like a browser would — cookies, CSRF tokens, forms, uploads.
 */
use App\Core\DB;

final class Web
{
    public static string $base = 'http://127.0.0.1:8089';
    private string $jar;
    public int $status = 0;
    /** @var array<string, string> */
    public array $headers = [];
    public string $body = '';
    public ?string $location = null;

    /** @param string|null $host browse the test server under this name (resolved to 127.0.0.1), like a visitor from outside */
    public function __construct(private ?string $host = null)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'pt-jar');
    }

    /** @param list<string> $hdr */
    public function get(string $path, array $hdr = []): self
    {
        return $this->req('GET', $path, null, $hdr);
    }

    /**
     * POST with the CSRF token scraped from $tokenFrom (or the last page).
     * @param array<string, mixed> $data
     */
    public function post(string $path, array $data = [], ?string $tokenFrom = null, bool $csrf = true): self
    {
        if ($csrf && !isset($data['_token'])) {
            $page = $tokenFrom !== null ? (clone $this)->get($tokenFrom)->body : $this->body;
            $data['_token'] = self::token($page);
        }
        return $this->req('POST', $path, $data);
    }

    public static function token(string $html): string
    {
        return preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : (preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) ? $m[1] : '');
    }

    public function login(string $email, string $pw): self
    {
        $this->get('/login');
        return $this->post('/login', ['email' => $email, 'password' => $pw]);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<string> $hdr
     */
    public function req(string $method, string $path, ?array $data = null, array $hdr = []): self
    {
        $ch = curl_init(($this->host ? 'http://' . $this->host . ':8089' : self::$base) . $path);
        if ($this->host) {
            curl_setopt($ch, CURLOPT_RESOLVE, [$this->host . ':8089:127.0.0.1']);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $hdr, CURLOPT_TIMEOUT => 20, CURLOPT_NOBODY => $method === 'HEAD',
        ]);
        if ($data !== null) {
            $hasFile = (bool) array_filter($data, fn($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->body = substr($raw, $hs);
        $this->headers = [];
        foreach (explode("\r\n", substr($raw, 0, $hs)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->headers[strtolower(trim($k))] = trim($v);
            }
        }
        $this->location = $this->headers['location'] ?? null;
        return $this;
    }

    public function has(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }
}

// ---- start the server -----------------------------------------------------
$root = dirname(__DIR__);
$log = sys_get_temp_dir() . '/panteen-test-server.log';
$env = ['PANTEEN_DB_HOST' => (string) getenv('PANTEEN_DB_HOST'), 'PANTEEN_DB_PORT' => (string) getenv('PANTEEN_DB_PORT'), 'PANTEEN_DB_NAME' => getenv('PANTEEN_DB_NAME'), 'PANTEEN_MAIL_DRIVER' => 'log', 'PATH' => getenv('PATH'), 'TZ' => 'Asia/Bangkok'];
if ($probe = @fsockopen('127.0.0.1', 8089)) {
    fclose($probe);
    fwrite(STDERR, "Port 8089 is already in use (a test server from tests/serve-test.sh?). Stop it and run again.\n");
    exit(2);
}
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8089', '-t', "$root/public", "$root/public/index.php"], [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $p, $root, $env);
for ($i = 0; $i < 50; $i++) {
    usleep(100000);
    if (@fsockopen('127.0.0.1', 8089)) {
        break;
    }
}
register_shutdown_function(function () use ($server) {
    proc_terminate($server);
});

$code = (string) DB::value("SELECT code FROM costumes WHERE status = 'active' ORDER BY id LIMIT 1");
$cat = (string) DB::value('SELECT slug FROM categories WHERE is_active = 1 ORDER BY sort_order LIMIT 1');

/* ------------------------------------------------------------------ */
T::group('HTTP · หน้าร้านสาธารณะ');
$g = new Web();
$soon = add_days(today(), 20);
foreach (['/', "/?date=$soon", '/dresses', "/c/$cat", "/dress/$code", "/dress/$code?date=$soon", '/how-to-rent', '/credits', '/login', '/register', '/forgot',
          '/dresses?q=' . rawurlencode('ชุด'), "/dresses?size=M&date=$soon", "/dresses?free=1&date=$soon&sort=price_asc", "/c/$cat?free=1&date=$soon", '/dresses?new=1&max=1500'] as $u) {
    T::eq($g->get($u)->status, 200, "GET $u → 200");
}
// links made before the listing had its own address still land on it
$g->get('/?q=' . rawurlencode('ชุด'));
T::ok($g->status === 302 && str_contains((string) $g->location, '/dresses?q='), 'ลิงก์ค้นหาแบบเก่าที่หน้าแรกถูกส่งต่อไป /dresses', (string) $g->location);
$g->get("/?size=M&date=$soon");
T::ok($g->status === 302 && str_contains((string) $g->location, '/dresses?') && str_contains((string) $g->location, "date=$soon") && str_contains((string) $g->location, 'size=M'), 'ลิงก์กรองแบบเก่ายังพาวันงานและไซส์ไปด้วย', (string) $g->location);
$g->get("/dresses?category=$cat&size=M&date=$soon");
T::ok($g->status === 302 && str_contains((string) $g->location, "/c/$cat?") && !str_contains((string) $g->location, 'category='), 'ช่องค้นหาหน้าแรกส่งหมวดไปที่ /c/{slug}', (string) $g->location);
$g->get('/dresses?category=no-such-floor&size=M');
T::ok($g->status === 302 && str_contains((string) $g->location, '/dresses?size=M'), 'หมวดที่ไม่มีอยู่ถูกตัดออก ไม่พาไปหน้า 404', (string) $g->location);

$g->get('/dresses');
T::ok($g->has('<article class="vit') && $g->has('data-desk') && $g->has('id="filters"'), 'หน้ารายการชุดมีการ์ดชุด ช่องวันงาน และแผงตัวกรอง');
T::ok(substr_count($g->body, '<article class="vit') >= 12, 'หน้ารายการชุดแสดงชุดอย่างน้อย 12 ชุดในหน้าแรก');
$j = json_decode($g->get('/dresses?size=M', ['Accept: application/json'])->body, true);
T::ok(($j['ok'] ?? false) && isset($j['html'], $j['count'], $j['chips'], $j['empty'], $j['total'], $j['more']), 'รายการชุดคืน JSON สำหรับกรองโดยไม่โหลดหน้าใหม่');
T::ok(($j['total'] ?? 0) === (int) DB::value("SELECT COUNT(*) FROM costumes c JOIN categories g ON g.id = c.category_id WHERE c.status = 'active' AND g.is_active = 1 AND c.size_label = 'M'"), 'จำนวนชุดไซส์ M ใน JSON ตรงกับฐานข้อมูล');
$j = json_decode($g->get("/?date=$soon", ['Accept: application/json'])->body, true);
T::ok(($j['ok'] ?? false) && ($j['date'] ?? null) === $soon && count($j['floors'] ?? []) === (int) DB::value('SELECT COUNT(*) FROM categories WHERE is_active = 1'), 'หน้าแรกคืนจำนวนชุดว่างรายหมวดตามวันงานเป็น JSON');
$j = json_decode($g->get('/dresses?date=' . add_days(today(), -3), ['Accept: application/json'])->body, true);
T::ok(($j['ok'] ?? false) && array_key_exists('date', $j) && $j['date'] === null, 'วันงานที่ผ่านไปแล้วถูกละทิ้ง ไม่กรองด้วยวันในอดีต');

$g->get("/dress/$code");
T::ok($g->has('data-gallery-main') && $g->has('srcset=') && $g->has('/large/'), 'หน้าชุดมีรูปขนาดใหญ่สำหรับจอความละเอียดสูง (srcset)');
T::ok($g->has('id="availability"') && $g->has('id="details"') && $g->has('class="spec-table"'), 'หน้าชุดมีปฏิทินวันว่างและตารางรายละเอียด');
$g->get('/credits');
T::ok($g->has('ภาพประกอบเว็บไซต์') && $g->has('Xander Miadelko') && $g->has('Los Muertos Crew'), 'หน้าที่มาของภาพระบุช่างภาพของภาพประกอบเว็บไซต์ด้วย');
T::ok(substr_count($g->body, 'pexels.com') >= 30, 'หน้าที่มาของภาพมีลิงก์ต้นทางของภาพชุดทุกภาพ');
$g->get('/');
T::ok($g->has('<html lang="th"'), 'หน้าเว็บประกาศภาษาไทย');
T::eq($g->headers['x-content-type-options'] ?? '', 'nosniff', 'header X-Content-Type-Options');
T::eq($g->headers['x-frame-options'] ?? '', 'SAMEORIGIN', 'header X-Frame-Options (กัน clickjacking)');
T::ok(isset($g->headers['referrer-policy']), 'header Referrer-Policy');
$sc = (new Web())->get('/')->headers['set-cookie'] ?? '';
T::ok(stripos($sc, 'httponly') !== false && stripos($sc, 'samesite=lax') !== false, 'คุกกี้ session เป็น HttpOnly + SameSite', $sc);
foreach (['/dress/NOPE-999', '/c/no-such-category', '/no/such/page', '/bookings/abc'] as $u) {
    T::eq($g->get($u)->status, $u === '/bookings/abc' ? 302 : 404, "GET $u → " . ($u === '/bookings/abc' ? '302 (ต้องเข้าระบบ)' : '404'));
}
T::ok($g->get('/no/such/page')->has('ไม่พบ'), 'หน้า 404 เป็นภาษาไทยและอยู่ในดีไซน์ร้าน');

/* ------------------------------------------------------------------ */
T::group('HTTP · API ปฏิทินและราคา');
$cal = $g->get("/api/dress/$code/calendar?ym=" . date('Y-m'), ['Accept: application/json']);
T::ok($cal->status === 200 && (json_decode($cal->body, true)['ok'] ?? false), 'ปฏิทินคืน JSON');
T::eq($g->get("/api/dress/$code/calendar?ym=2026-13")->status, 200, 'เดือนผิดรูปแบบไม่ทำให้ระบบล่ม');
[$p, $e, $r] = [add_days(today(), 150), add_days(today(), 151), add_days(today(), 152)];
$q = json_decode($g->get("/api/dress/$code/quote?event=$e&pickup=$p&return=$r")->body, true);
T::ok(($q['ok'] ?? false) && $q['free'] === true && $q['quote']['days'] === 3, 'quote: ช่วงว่าง 3 วัน', json_encode($q['message'] ?? $q, JSON_UNESCAPED_UNICODE));
$q = json_decode($g->get("/api/dress/$code/quote?event=$e&pickup=$r&return=$p")->body, true);
T::ok(($q['ok'] ?? true) === false && isset($q['errors']['return_date']), 'quote: วันคืนก่อนวันรับ → error');
$busy = DB::one("SELECT c.code, b.pickup_date, b.return_date, b.event_date FROM bookings b JOIN costumes c ON c.id = b.costume_id WHERE b.status = 'confirmed' AND b.pickup_date > CURDATE() ORDER BY b.pickup_date LIMIT 1");
if ($busy) {
    $q = json_decode($g->get("/api/dress/{$busy['code']}/quote?event={$busy['event_date']}&pickup={$busy['pickup_date']}&return={$busy['return_date']}")->body, true);
    T::ok(($q['ok'] ?? false) && $q['free'] === false, 'quote: ช่วงที่มีคนจองแล้ว → ไม่ว่าง', $q['message'] ?? '');
}

/* ------------------------------------------------------------------ */
T::group('HTTP · สิทธิ์การเข้าถึง');
foreach (['/account', '/bookings', '/admin', '/admin/settings', "/book/$code"] as $u) {
    $g->get($u);
    T::ok($g->status === 302 && str_contains((string) $g->location, '/login'), "ผู้เยี่ยมชม $u → ไปหน้าเข้าสู่ระบบ");
}
$m = (new Web())->login('member@panteen.test', 'Member1234');
T::ok($m->status === 302, 'สมาชิกเข้าสู่ระบบได้');
T::eq($m->get('/account')->status, 200, 'สมาชิกเข้าหน้าบัญชีได้');
foreach (['/admin', '/admin/bookings', '/admin/reports/finance?format=csv', '/admin/settings'] as $u) {
    T::eq($m->get($u)->status, 403, "สมาชิกเข้า $u ไม่ได้ (403)");
}
$st = (new Web())->login('staff@panteen.test', 'Staff1234');
T::eq($st->get('/admin')->status, 200, 'พนักงานเข้าหลังร้านได้');
T::eq($st->get('/admin/settings')->status, 403, 'พนักงานเข้าหน้าตั้งค่าร้านไม่ได้ (เฉพาะผู้ดูแล)');
$sid = (int) DB::value("SELECT id FROM users WHERE email = 'member@panteen.test'");
T::eq($st->post("/admin/members/$sid/status", ['status' => 'suspended', 'reason' => 'x'], '/admin')->status, 403, 'พนักงานระงับบัญชีสมาชิกไม่ได้');
$ad = (new Web())->login('admin@panteen.test', 'Admin1234');
foreach (['/admin', '/admin/bookings', '/admin/bookings/new', '/admin/schedule', '/admin/payments', '/admin/payments?tab=done', '/admin/costumes', '/admin/costumes/new',
    '/admin/categories', '/admin/members', '/admin/members?tab=team', '/admin/members/new', '/admin/appointments', '/admin/reports/rentals',
    '/admin/reports/finance', '/admin/reports/appointments', '/admin/reports/rentals?format=print', '/admin/settings', '/admin/notifications'] as $u) {
    T::eq($ad->get($u)->status, 200, "ผู้ดูแล GET $u → 200");
}
$bid = (int) DB::value('SELECT id FROM bookings ORDER BY id DESC LIMIT 1');
$cid = (int) DB::value('SELECT id FROM costumes ORDER BY id LIMIT 1');
foreach (["/admin/bookings/$bid", "/admin/costumes/$cid", "/admin/members/$sid"] as $u) {
    T::eq($ad->get($u)->status, 200, "ผู้ดูแล GET $u → 200");
}
$ad->get('/admin/reports');
T::ok($ad->status === 302 && str_contains((string) $ad->location, '/admin/reports/'), '/admin/reports พาไปรายงานแรก');
T::eq($ad->get('/admin/reports/unknown')->status, 404, 'รายงานที่ไม่มี → 404');
T::eq($ad->get('/admin/bookings/999999')->status, 404, 'การจองที่ไม่มี → 404');

/* ------------------------------------------------------------------ */
T::group('HTTP · CSRF');
$x = (new Web())->login('member@panteen.test', 'Member1234');
$x->get('/account');
T::eq($x->post('/account/profile', ['first_name' => 'แฮก'], null, false)->status, 419, 'POST ไม่มี token → 419');
T::eq($x->post('/account/profile', ['first_name' => 'แฮก', '_token' => 'forged'])->status, 419, 'POST token ปลอม → 419');
T::eq($x->req('POST', '/account/notifications/read', ['_token' => 'bad'], ['Accept: application/json'])->status, 419, 'AJAX token ผิด → 419');
T::ok(json_decode($x->body, true) !== null, 'AJAX ได้ข้อความ error เป็น JSON');
T::ok(DB::value("SELECT first_name FROM users WHERE email = 'member@panteen.test'") !== 'แฮก', 'ข้อมูลไม่ถูกเปลี่ยน');
$x->post('/logout', [], '/account');
T::eq($x->get('/account')->status, 302, 'ออกจากระบบแล้วเข้าหน้าบัญชีไม่ได้');

/* ------------------------------------------------------------------ */
T::group('HTTP · สมัครสมาชิก → ยืนยันอีเมล');
$n = new Web();
$n->get('/register');
$email = 'newbie' . random_int(1000, 9999) . '@example.test';
$n->post('/register', ['first_name' => 'ทดลอง', 'last_name' => 'สมัครใหม่', 'email' => $email, 'phone' => '0899999999', 'password' => 'short', 'password_confirmation' => 'short']);
T::ok($n->status === 302 && !DB::value('SELECT 1 FROM users WHERE email = ?', [$email]), 'รหัสผ่านสั้นเกินไป สมัครไม่ผ่าน');
$n->get('/register');
T::ok($n->has('รหัสผ่าน'), 'แสดงข้อผิดพลาดที่ฟอร์ม');
$n->post('/register', ['first_name' => 'ทดลอง', 'last_name' => 'สมัครใหม่', 'email' => strtoupper($email), 'phone' => '089-999-9999', 'line_id' => 'tl', 'password' => 'Newbie1234', 'password_confirmation' => 'Newbie1234', 'accept' => '1']);
T::ok($n->status === 302, 'สมัครสำเร็จ', (string) $n->location);
$nu = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
T::ok($nu && $nu['role'] === 'member' && $nu['email_verified_at'] === null, 'สร้างบัญชีสมาชิก (อีเมลเก็บเป็นตัวเล็ก) ยังไม่ยืนยัน');
T::ok($nu && password_verify('Newbie1234', $nu['password_hash']) && !str_contains($nu['password_hash'], 'Newbie'), 'เก็บรหัสผ่านแบบ hash');
$n->get("/book/$code");
T::ok($n->status === 302 && str_contains((string) $n->location, '/account/verify'), 'ยังไม่ยืนยันอีเมล จองไม่ได้');
$mail = DB::one('SELECT * FROM mail_outbox WHERE to_email = ? ORDER BY id DESC LIMIT 1', [$email]);
T::ok($mail && preg_match('~/verify/([A-Za-z0-9_-]+)~', $mail['html'], $mm), 'ส่งอีเมลยืนยันพร้อมลิงก์');
T::ok($mail && trim($mail['text_body']) !== '', 'อีเมลมีเวอร์ชันข้อความล้วน');
$n->get('/verify/' . ($mm[1] ?? 'x'));
T::ok(DB::value('SELECT email_verified_at FROM users WHERE email = ?', [$email]) !== null, 'กดลิงก์แล้วยืนยันอีเมลสำเร็จ');
T::ok($n->get('/verify/' . ($mm[1] ?? 'x'))->has('ใช้ไม่ได้'), 'ลิงก์ยืนยันใช้ซ้ำไม่ได้');
T::eq($n->get("/book/$code")->status, 200, 'ยืนยันแล้วเปิดหน้าจองได้');
$dup = new Web();
$dup->get('/register');
$dup->post('/register', ['first_name' => 'ซ้ำ', 'last_name' => 'ซ้ำ', 'email' => $email, 'phone' => '0811111111', 'password' => 'Newbie1234', 'password_confirmation' => 'Newbie1234', 'accept' => '1']);
T::eq((int) DB::value('SELECT COUNT(*) FROM users WHERE email = ?', [$email]), 1, 'สมัครด้วยอีเมลซ้ำไม่ได้');

/* ------------------------------------------------------------------ */
T::group('HTTP · ลูกค้าจอง → แนบสลิป → พนักงานอนุมัติ → ใบเสร็จ');
$fresh = DB::one("SELECT c.code, c.id FROM costumes c WHERE c.status = 'active' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.costume_id = c.id AND b.pickup_date BETWEEN ? AND ?) ORDER BY c.id DESC LIMIT 1", [add_days(today(), 160), add_days(today(), 175)]);
[$p, $e, $r] = [add_days(today(), 165), add_days(today(), 166), add_days(today(), 167)];
$n->get("/book/{$fresh['code']}?event=$e&pickup=$p&return=$r");
T::eq($n->status, 200, 'เปิดฟอร์มจองพร้อมวันที่');
$n->post("/book/{$fresh['code']}", ['event_date' => $e, 'pickup_date' => $p, 'return_date' => $r, 'pickup_time' => '13:00', 'return_time' => '13:00']);
T::ok($n->status === 302 && !str_contains((string) $n->location, '/bookings/'), 'ไม่ติ๊กยอมรับเงื่อนไข → ไม่สร้างการจอง');
$n->get("/book/{$fresh['code']}?event=$e&pickup=$p&return=$r");
$n->post("/book/{$fresh['code']}", ['event_date' => $e, 'pickup_date' => $p, 'return_date' => $r, 'pickup_time' => '13:00', 'return_time' => '13:00', 'agree' => '1', 'note' => '<script>alert(1)</script>']);
T::ok($n->status === 302 && preg_match('~/bookings/(\d+)~', (string) $n->location, $bm), 'จองสำเร็จ ไปหน้ารายละเอียดการจอง', (string) $n->location);
$myId = (int) ($bm[1] ?? 0);
$n->get("/bookings/$myId");
T::ok($n->status === 200 && $n->has('000201'), 'หน้าการจองมี QR พร้อมเพย์ (payload EMVCo)');
T::ok(!$n->has('<script>alert(1)</script>') && $n->has('&lt;script&gt;'), 'หมายเหตุลูกค้าถูก escape (กัน XSS)');
$slipFile = "$root/database/seed-slips/slip-a.jpg";
$bad = tempnam(sys_get_temp_dir(), 'pt') . '.jpg';
file_put_contents($bad, '<?php echo "pwned"; ?>');
$n->post("/bookings/$myId/slip", ['slip' => new CURLFile($bad, 'image/jpeg', 'slip.jpg'), 'method' => 'promptpay']);
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$myId]), 'pending_payment', 'ไฟล์ปลอมนามสกุล .jpg ถูกปฏิเสธ');
$n->get("/bookings/$myId");
$n->post("/bookings/$myId/slip", ['slip' => new CURLFile($slipFile, 'image/jpeg', 'slip.jpg'), 'method' => 'promptpay']);
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$myId]), 'awaiting_verification', 'แนบสลิปจริงสำเร็จ → รอตรวจ');
$pay = DB::one('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$myId]);
T::ok($pay && !str_contains((string) $pay['slip_path'], '/') && is_file(STORAGE_PATH . '/slips/' . $pay['slip_path']), 'สลิปเก็บนอก public/ ด้วยชื่อไฟล์สุ่ม');
T::eq($g->get('/slips/' . $pay['id'])->status, 302, 'คนนอกเปิดสลิปไม่ได้');
$n->get('/slips/' . $pay['id']);
T::ok($n->status === 200 && str_starts_with($n->headers['content-type'] ?? '', 'image/'), 'เจ้าของเปิดสลิปตัวเองได้');
$st->get('/admin/payments');
T::ok($st->has($pay['id'] . '/approve'), 'สลิปขึ้นในหน้าตรวจสลิปของพนักงาน');
$st->post("/admin/payments/{$pay['id']}/reject", ['reason' => '']);
T::eq((string) DB::value('SELECT status FROM payments WHERE id = ?', [$pay['id']]), 'pending', 'ปฏิเสธสลิปโดยไม่ใส่เหตุผลไม่ได้');
$st->get('/admin/payments');
$st->post("/admin/payments/{$pay['id']}/approve");
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$myId]), 'confirmed', 'พนักงานอนุมัติ → การจองยืนยันแล้ว');
$rid = (int) DB::value("SELECT id FROM receipts WHERE booking_id = ? AND kind = 'booking'", [$myId]);
$n->get("/receipts/$rid");
T::ok($rid > 0 && $n->status === 200 && $n->has((string) DB::value('SELECT receipt_no FROM receipts WHERE id = ?', [$rid])), 'ลูกค้าเปิดใบเสร็จได้');
T::ok($n->has('ค่าเช่าชุด') && $n->has('เงินมัดจำ (คืนเมื่อคืนชุดตามเงื่อนไข)') && $n->has('รับชำระแล้ว'), 'ใบเสร็จแสดงรายการค่าเช่า เงินมัดจำ และยอดรับชำระครบทุกบรรทัด');
T::ok((int) DB::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type LIKE 'payment%'", [$nu['id']]) >= 1 || (int) DB::value("SELECT COUNT(*) FROM notifications WHERE user_id = ?", [$nu['id']]) >= 3, 'ลูกค้าได้รับแจ้งเตือนผลการตรวจ');
T::ok((bool) DB::value('SELECT 1 FROM mail_outbox WHERE to_email = ? AND subject LIKE ?', [$email, '%ยืนยัน%']), 'ส่งอีเมลยืนยันการจองถึงลูกค้า');
$n->get('/bookings');
T::ok($n->has((string) DB::value('SELECT booking_no FROM bookings WHERE id = ?', [$myId])), 'การจองอยู่ในรายการ “การจองของฉัน”');

/* ------------------------------------------------------------------ */
T::group('HTTP · กันเข้าถึงข้อมูลของคนอื่น (IDOR)');
$m = (new Web())->login('member@panteen.test', 'Member1234');
foreach (["/bookings/$myId", "/receipts/$rid", '/slips/' . $pay['id']] as $u) {
    T::eq($m->get($u)->status, 404, "สมาชิกคนอื่นเปิด $u ไม่ได้ (404)");
}
$m->get('/bookings');
$m->post("/bookings/$myId/cancel", ['reason' => 'แกล้ง']);
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$myId]), 'confirmed', 'สมาชิกคนอื่นยกเลิกการจองนี้ไม่ได้');
$m->post("/bookings/$myId/slip", ['slip' => new CURLFile($slipFile, 'image/jpeg', 's.jpg')]);
T::eq($m->status, 404, 'สมาชิกคนอื่นแนบสลิปให้การจองนี้ไม่ได้');
T::eq($st->get("/admin/bookings/$myId")->status, 200, 'แต่พนักงานเปิดดูได้');

/* ------------------------------------------------------------------ */
T::group('HTTP · หลังร้าน: จองหน้าร้าน รับชุด คืนชุด');
$other = DB::one("SELECT c.code FROM costumes c WHERE c.status = 'active' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.costume_id = c.id AND b.status NOT IN ('cancelled','expired') AND b.block_until >= CURDATE() - INTERVAL 1 DAY AND b.pickup_date <= CURDATE() + INTERVAL 4 DAY) ORDER BY c.id LIMIT 1");
$st->get("/admin/bookings/new?code={$other['code']}");
T::eq($st->status, 200, 'เปิดฟอร์มจองหน้าร้าน');
$lk = json_decode($st->get('/admin/api/members?q=' . rawurlencode('ทดลอง'), ['Accept: application/json'])->body, true);
T::ok(is_array($lk) && str_contains(json_encode($lk, JSON_UNESCAPED_UNICODE), 'ทดลอง'), 'ค้นหาสมาชิกจากชื่อ');
$st->get("/admin/bookings/new?code={$other['code']}");
$st->post('/admin/bookings', ['costume_code' => $other['code'], 'user_id' => $nu['id'], 'event_date' => today(), 'pickup_date' => today(), 'return_date' => add_days(today(), 2),
    'pickup_time' => '10:00', 'return_time' => '18:00', 'pay_now' => 'cash']);
$cb = DB::one("SELECT * FROM bookings WHERE user_id = ? AND channel = 'counter' ORDER BY id DESC LIMIT 1", [$nu['id']]);
T::ok($cb && $cb['status'] === 'confirmed', 'จองหน้าร้าน + รับเงินสด → ยืนยันทันที', (string) $st->location);
$st->get("/admin/bookings/{$cb['id']}");
$st->post("/admin/bookings/{$cb['id']}/pickup");
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$cb['id']]), 'picked_up', 'บันทึกลูกค้ารับชุด');
$st->get("/admin/bookings/{$cb['id']}");
$st->post("/admin/bookings/{$cb['id']}/return", ['returned_on' => today(), 'damage_fee' => '0']);
$rb = DB::one('SELECT * FROM bookings WHERE id = ?', [$cb['id']]);
T::ok($rb['status'] === 'returned' && (float) $rb['deposit_refund'] === (float) $rb['deposit_amount'], 'คืนชุดตรงเวลา คืนมัดจำเต็ม');
$st->get("/admin/bookings/{$cb['id']}");
T::ok($st->has('ใบสรุป') || $st->has('settlement') || $st->has('/receipts/'), 'หน้าการจองมีลิงก์เอกสารการคืนชุด');

/* ------------------------------------------------------------------ */
T::group('HTTP · จองแบบส่งพัสดุ → ส่ง → ได้รับ → ส่งคืน → ตรวจ (ผ่านหน้าเว็บ)');
$pc = DB::one("SELECT c.code, c.id FROM costumes c WHERE c.status = 'active' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.costume_id = c.id AND b.status NOT IN ('cancelled','expired') AND b.block_until >= ? AND b.block_from <= ?) ORDER BY c.id DESC LIMIT 1", [add_days(today(), 116), add_days(today(), 136)]);
[$p, $e, $r] = [add_days(today(), 125), add_days(today(), 126), add_days(today(), 127)];
$form = ['event_date' => $e, 'pickup_date' => $p, 'return_date' => $r, 'pickup_time' => '13:00', 'return_time' => '13:00', 'agree' => '1',
    'delivery_method' => 'post', 'return_method' => 'post', 'address_id' => 'new'];
$addrForm = ['addr_recipient' => 'ผู้รับทดสอบ', 'addr_phone' => '0812345678', 'addr_line1' => '9 ถนนทดสอบ (ที่อยู่ตัวอย่าง)', 'addr_subdistrict' => 'สามเสนใน',
    'addr_district' => 'พญาไท', 'addr_province' => 'กรุงเทพมหานคร', 'addr_postcode' => '10400', 'save_address' => '1'];
$before = (int) DB::value('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$nu['id']]);
$n->get("/book/{$pc['code']}?event=$e&pickup=$p&return=$r");
T::ok($n->has('name="delivery_method"') && $n->has('name="addr_province"'), 'ฟอร์มจองมีตัวเลือกส่งพัสดุและช่องที่อยู่');
$n->post("/book/{$pc['code']}", $form + ['addr_recipient' => '']);
T::eq((int) DB::value('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$nu['id']]), $before, 'เลือกส่งพัสดุแต่กรอกที่อยู่ไม่ครบ → ยังไม่สร้างการจอง');
$n->get("/book/{$pc['code']}?event=$e&pickup=$p&return=$r");
T::ok($n->has('กรอกชื่อผู้รับ'), 'บอกช่องที่อยู่ที่ยังขาด');
$n->post("/book/{$pc['code']}", $form + $addrForm);
T::ok($n->status === 302 && preg_match('~/bookings/(\d+)~', (string) $n->location, $sm), 'จองแบบส่งพัสดุสำเร็จ', (string) $n->location);
$sid = (int) ($sm[1] ?? 0);
$sb = DB::one('SELECT * FROM bookings WHERE id = ?', [$sid]);
T::eq([$sb['delivery_method'], $sb['return_method'], $sb['ship_zone'], $sb['ship_postcode']], ['post', 'post', 'metro', '10400'], 'การจองเก็บวิธีส่ง เขต และที่อยู่');
T::ok((bool) DB::value('SELECT 1 FROM addresses WHERE user_id = ? AND postcode = ?', [$nu['id'], '10400']), 'ติ๊กบันทึกที่อยู่ → เก็บลงสมุดที่อยู่');
$quote = json_decode($n->get("/api/dress/{$pc['code']}/quote?event=" . add_days($e, 30) . '&pickup=' . add_days($p, 30) . '&return=' . add_days($r, 30) . '&delivery=post&back=post&province=' . rawurlencode('เชียงใหม่'))->body, true);
T::ok(($quote['ok'] ?? false) && str_contains((string) ($quote['html'] ?? ''), money(setting('ship_fee_upcountry'))), 'API ราคาคิดค่าส่งตามจังหวัด');
$stUser = DB::one("SELECT id FROM users WHERE email = 'staff@panteen.test'");
App\Services\BookingService::recordCounterPayment($sid, (int) $stUser['id'], 'transfer');

$st->get("/admin/bookings/$sid");
T::ok($st->has("/admin/bookings/$sid/ship") && $st->has('ต้องส่งออก'), 'หน้าการจองหลังร้านมีฟอร์มส่งพัสดุและวันต้องส่ง');
$st->post("/admin/bookings/$sid/ship", ['carrier' => 'thaipost', 'tracking_no' => 'EF100000099TH']);
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$sid]), 'shipped', 'พนักงานบันทึกส่งพัสดุ');
foreach (['received' => [], 'arrival' => ['arrival_note' => 'แกล้ง'], 'post-back' => ['carrier' => 'thaipost', 'tracking_no' => 'EF100000098TH', 'posted_on' => today()],
          'dispute' => ['dispute_note' => 'แกล้งโต้แย้งค่าเสียหายของคนอื่น']] as $act => $data) {
    $m->get('/bookings');
    $m->post("/bookings/$sid/$act", $data);
    T::eq($m->status, 404, "สมาชิกคนอื่นกด $act ให้การจองนี้ไม่ได้ (404)");
}
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$sid]), 'shipped', 'สถานะไม่เปลี่ยนจากคำขอของคนอื่น');
$n->get("/bookings/$sid");
T::ok($n->has('EF100000099TH') && $n->has('track.thailandpost.co.th'), 'ลูกค้าเห็นเลขพัสดุและลิงก์ติดตาม');
$n->post("/bookings/$sid/received");
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$sid]), 'picked_up', 'ลูกค้ากด “ได้รับชุดแล้ว”');
$n->get("/bookings/$sid");
$n->post("/bookings/$sid/arrival", ['arrival_note' => 'ชายกระโปรงมีรอยเปื้อนเล็กน้อยตั้งแต่เปิดกล่อง']);
T::ok(DB::value('SELECT arrival_reported_at FROM bookings WHERE id = ?', [$sid]) !== null, 'ลูกค้าแจ้งสภาพตอนได้รับ');
$n->get("/bookings/$sid");
$n->post("/bookings/$sid/post-back", ['carrier' => 'thaipost', 'tracking_no' => 'EF100000097TH', 'posted_on' => today()]);
T::eq((string) DB::value('SELECT status FROM bookings WHERE id = ?', [$sid]), 'returning', 'ลูกค้าแจ้งเลขพัสดุส่งคืน');
$st->get("/admin/bookings/$sid");
$st->post("/admin/bookings/$sid/receive");
T::eq((string) DB::value("SELECT status FROM shipments WHERE booking_id = ? AND direction = 'back'", [$sid]), 'received', 'พนักงานรับพัสดุคืน');
$st->get("/admin/bookings/$sid/inspect");
T::ok($st->status === 200 && $st->has('ชายกระโปรงมีรอยเปื้อน') && $st->has('name="rows[0][type_id]"'), 'หน้าตรวจสภาพแสดงสิ่งที่ลูกค้าแจ้งไว้และตารางค่าเสียหาย');
$stain = (int) DB::value("SELECT id FROM damage_types WHERE name = 'คราบเล็กน้อย ขจัดได้'");
$st->post("/admin/bookings/$sid/inspect", ['checks' => array_keys(App\Services\Inspection::CHECKS), 'outcome' => 'ready',
    'rows' => [['type_id' => (string) $stain, 'qty' => '1', 'amount' => '', 'note' => 'ปกเสื้อ']]]);
$sb = DB::one('SELECT * FROM bookings WHERE id = ?', [$sid]);
T::eq([$sb['status'], (float) $sb['damage_fee']], ['returned', 200.0], 'ตรวจสภาพแล้วปิดการเช่า คิดค่าเสียหายตามตาราง');
$n->get("/bookings/$sid");
T::ok($n->has('ปกเสื้อ') && $n->has("/bookings/$sid/dispute"), 'ลูกค้าเห็นรายการค่าเสียหายและปุ่มโต้แย้ง');

T::group('HTTP · รูปสภาพชุด สมุดที่อยู่ และหน้าหลังร้านใหม่');
@mkdir(STORAGE_PATH . '/condition', 0775, true);
copy($slipFile, STORAGE_PATH . '/condition/test-photo.jpg');
$photo = DB::insert('booking_photos', ['booking_id' => $sid, 'stage' => 'return', 'path' => 'test-photo.jpg', 'created_by' => $stUser['id']]);
$n->get("/condition/$photo");
T::ok($n->status === 200 && str_starts_with($n->headers['content-type'] ?? '', 'image/'), 'เจ้าของการจองเปิดรูปสภาพชุดได้');
T::eq($m->get("/condition/$photo")->status, 404, 'สมาชิกคนอื่นเปิดรูปสภาพชุดไม่ได้');
T::eq($g->get("/condition/$photo")->status, 302, 'คนนอกเปิดรูปสภาพชุดไม่ได้');
T::eq($st->get("/condition/$photo")->status, 200, 'พนักงานเปิดรูปสภาพชุดได้');
$aid = (int) DB::value('SELECT id FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$nu['id']]);
$n->get('/account/addresses');
T::ok($n->status === 200 && $n->has('ผู้รับทดสอบ'), 'สมาชิกเห็นสมุดที่อยู่ของตัวเอง');
$m->get('/account/addresses');
T::ok(!$m->has('ผู้รับทดสอบ'), 'สมาชิกคนอื่นไม่เห็นที่อยู่นี้');
$m->post("/account/addresses/$aid/delete");
T::ok($m->status === 404 && (bool) DB::value('SELECT 1 FROM addresses WHERE id = ?', [$aid]), 'สมาชิกคนอื่นลบที่อยู่นี้ไม่ได้');
$m->get('/account/addresses');
$m->post('/account/addresses', ['id' => (string) $aid] + $addrForm + ['addr_recipient' => 'ถูกแก้']);
T::eq((string) DB::value('SELECT recipient FROM addresses WHERE id = ?', [$aid]), 'ผู้รับทดสอบ', 'สมาชิกคนอื่นแก้ที่อยู่นี้ไม่ได้');
foreach (['/admin/shipping', '/admin/shipping?tab=road', '/admin/shipping?tab=due', '/admin/shipping?tab=back', '/admin/shipping?tab=check', '/admin/shipping?tab=dispute',
          '/admin/scan', '/admin/scan?q=' . $pc['code'], '/admin/damage-types', "/admin/bookings/$sid/label", "/admin/costumes/{$pc['id']}/tag"] as $u) {
    T::eq($st->get($u)->status, 200, "พนักงานเปิด $u ได้");
}
$st->get('/admin/scan?q=' . rawurlencode(strtolower((string) $sb['booking_no'])));
T::ok($st->status === 302 && str_ends_with((string) $st->location, "/admin/bookings/$sid"), 'สแกนเลขจอง → พาไปหน้าการจอง');
T::eq($m->get('/admin/shipping')->status, 403, 'สมาชิกเปิดหน้าจัดส่งหลังร้านไม่ได้');
$st->get('/admin/damage-types');
$st->post('/admin/damage-types', ['name' => 'พนักงานแอบเพิ่ม', 'area' => 'ทั้งชุด', 'fee_type' => 'fixed', 'amount' => '1']);
T::eq($st->status, 403, 'พนักงานแก้ตารางค่าเสียหายไม่ได้ (เฉพาะผู้ดูแล)');

/* ------------------------------------------------------------------ */
T::group('HTTP · รายงานและ CSV');
$ad->get('/admin/reports/rentals?format=csv&from=' . date('Y-m-01') . '&to=' . date('Y-m-t'));
T::ok(str_starts_with($ad->headers['content-type'] ?? '', 'text/csv'), 'CSV ส่ง content-type text/csv', $ad->headers['content-type'] ?? '');
T::ok(str_starts_with($ad->body, "\xEF\xBB\xBF"), 'CSV มี BOM ให้ Excel อ่านภาษาไทยถูก');
T::ok(str_contains($ad->headers['content-disposition'] ?? '', 'attachment'), 'CSV ดาวน์โหลดเป็นไฟล์');
T::ok(str_contains($ad->body, 'เลขที่จอง'), 'CSV มีหัวคอลัมน์ภาษาไทย');
foreach (['finance', 'appointments'] as $t) {
    $ad->get("/admin/reports/$t?format=csv");
    T::ok(str_starts_with($ad->body, "\xEF\xBB\xBF") && substr_count($ad->body, "\n") >= 1, "CSV $t ดาวน์โหลดได้");
}

/* ------------------------------------------------------------------ */
T::group('HTTP · ล็อกอินผิดซ้ำ และบัญชีที่ถูกระงับ');
$bf = new Web();
for ($i = 0; $i < 5; $i++) {
    $bf->get('/login');
    $bf->post('/login', ['email' => 'staff@panteen.test', 'password' => 'wrong' . $i]);
}
$bf->get('/login');
$bf->post('/login', ['email' => 'staff@panteen.test', 'password' => 'Staff1234']);
$bf->get('/admin');
T::ok($bf->status !== 200, 'ผิด 5 ครั้ง → ล็อกชั่วคราว แม้รหัสถูกก็เข้าไม่ได้');
$bf->get('/login');
T::ok($bf->has('นาที') || $bf->has('ล็อก') || $bf->has('ลองใหม่'), 'บอกให้รอแล้วลองใหม่');
DB::run('DELETE FROM login_attempts');
$sus = (int) DB::value("SELECT id FROM users WHERE role = 'member' AND email <> 'member@panteen.test' AND status = 'active' ORDER BY id DESC LIMIT 1");
$ad->get("/admin/members/$sus");
$ad->post("/admin/members/$sus/status", ['status' => 'suspended', 'reason' => 'ทดสอบระงับ']);
T::eq((string) DB::value('SELECT status FROM users WHERE id = ?', [$sus]), 'suspended', 'ผู้ดูแลระงับบัญชีได้');
DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash('Suspend1234', PASSWORD_DEFAULT), $sus]);
$s = (new Web())->login((string) DB::value('SELECT email FROM users WHERE id = ?', [$sus]), 'Suspend1234');
T::ok($s->get('/account')->status === 302, 'บัญชีที่ถูกระงับเข้าสู่ระบบไม่ได้');

/* ------------------------------------------------------------------ */
T::group('HTTP · ลืมรหัสผ่าน');
$f = new Web();
$f->get('/forgot');
$f->post('/forgot', ['email' => 'member@panteen.test']);
$strip = fn(string $h) => preg_replace(['/name="(_token|csrf-token)" (value|content)="[^"]+"/', '/[a-z0-9.]+@[a-z0-9.]+\.test/'], ['', '@'], $h);
$known = [$f->status, $strip($f->body)];
T::eq($f->status, 200, 'ส่งคำขอรีเซ็ตรหัสผ่าน');
$f->get('/forgot');
$f->post('/forgot', ['email' => 'nobody@nowhere.test']);
T::ok([$f->status, $strip($f->body)] === $known, 'อีเมลที่ไม่มีในระบบได้หน้าตอบเหมือนกันทุกตัวอักษร (ไม่เปิดเผยว่ามีบัญชีหรือไม่)');
$rm = DB::one("SELECT html FROM mail_outbox WHERE to_email = 'member@panteen.test' AND html LIKE '%/reset/%' ORDER BY id DESC LIMIT 1");
T::ok($rm && preg_match('~/reset/([A-Za-z0-9_-]+)~', $rm['html'], $tm), 'ส่งลิงก์รีเซ็ตทางอีเมล');
$f->get('/reset/' . ($tm[1] ?? 'x'));
$f->post('/reset/' . ($tm[1] ?? 'x'), ['password' => 'Member5678', 'password_confirmation' => 'Member5678']);
T::ok(password_verify('Member5678', (string) DB::value("SELECT password_hash FROM users WHERE email = 'member@panteen.test'")), 'ตั้งรหัสผ่านใหม่สำเร็จ');
T::eq((new Web())->login('member@panteen.test', 'Member5678')->get('/account')->status, 200, 'เข้าสู่ระบบด้วยรหัสใหม่ได้');

T::group('HTTP · ช่องโหว่ที่ปิดไว้');
// the server is on this machine, so browsing it by another name stands in for a visitor from outside
T::eq((new Web('rent.example.com'))->get('/dev/mailbox')->status, 404, 'กล่องจดหมายจำลองไม่เปิดให้คนนอกเครื่องที่ยังไม่เข้าระบบ (ลิงก์รีเซ็ตรหัสผ่านอยู่ในนั้น)');
T::eq((new Web())->get('/dev/mailbox')->status, 200, 'โหมดทดสอบบนเครื่องตัวเองยังเปิดกล่องจดหมายได้');
T::ok(!(new Web('rent.example.com'))->get('/')->has('/dev/mailbox'), 'หน้าร้านที่เปิดจากที่อื่นไม่มีลิงก์ไปกล่องจดหมาย');
// a reset was asked for a moment ago and the form allows one a minute: let that minute pass
DB::run("UPDATE email_tokens SET created_at = created_at - INTERVAL 2 MINUTE WHERE type = 'reset'");
$lastMail = (int) DB::value('SELECT COALESCE(MAX(id), 0) FROM mail_outbox');
$h = new Web('attacker.example');
$h->get('/forgot');
$h->post('/forgot', ['email' => 'member@panteen.test']);
$hm = (string) DB::value("SELECT html FROM mail_outbox WHERE to_email = 'member@panteen.test' AND html LIKE '%/reset/%' AND id > ? ORDER BY id DESC LIMIT 1", [$lastMail]);
T::ok($hm !== '' && !str_contains($hm, 'attacker.example') && preg_match('~https?://(localhost|127\.0\.0\.1)[^/]*/reset/([A-Za-z0-9_-]+)~', $hm, $hl), 'Host ปลอมไม่ไปอยู่ในลิงก์รีเซ็ตรหัสผ่านที่ส่งทางอีเมล');
$before = (new Web())->login('member@panteen.test', 'Member5678');
$h = new Web();
$h->get('/reset/' . ($hl[2] ?? 'x'));
$h->post('/reset/' . ($hl[2] ?? 'x'), ['password' => 'Member9012', 'password_confirmation' => 'Member9012']);
T::ok($before->get('/account')->status === 302 && str_contains((string) $before->location, '/login'), 'ตั้งรหัสผ่านใหม่แล้ว เครื่องที่เข้าระบบไว้ก่อนด้วยรหัสเดิมถูกออกจากระบบ');
$other = (new Web())->login('member@panteen.test', 'Member9012');
$self = (new Web())->login('member@panteen.test', 'Member9012');
$self->get('/account');
$self->post('/account/password', ['current_password' => 'Member9012', 'password' => 'Member3456', 'password_confirmation' => 'Member3456']);
T::eq($self->get('/account')->status, 200, 'เปลี่ยนรหัสผ่านเองแล้ว เครื่องที่ใช้อยู่ยังเข้าระบบอยู่');
T::eq($other->get('/account')->status, 302, 'เปลี่ยนรหัสผ่านเองแล้ว เครื่องอื่นที่เข้าระบบไว้ถูกออกจากระบบ');
$self->get('/account');
$self->post('/account/measurements', ['bust_in' => '34', 'waist_in' => '27', 'hip_in' => '36', 'back' => '/\\evil.example/x']);
T::ok($self->status === 302 && !str_contains((string) $self->location, 'evil'), 'ลิงก์กลับ /\\host ไม่พาออกไปเว็บอื่น', (string) $self->location);
$mid = (int) DB::value("SELECT id FROM users WHERE email = 'member@panteen.test'");
$mu = DB::one('SELECT * FROM users WHERE id = ?', [$mid]);
$st = (new Web())->login('staff@panteen.test', 'Staff1234');
$st->get('/admin/members/' . $mid);
$st->post('/admin/members/' . $mid, ['first_name' => $mu['first_name'], 'last_name' => $mu['last_name'], 'nickname' => (string) $mu['nickname'], 'phone' => $mu['phone'], 'email' => 'staff-owned@panteen.test', 'line_id' => (string) $mu['line_id']]);
T::eq((string) DB::value('SELECT email FROM users WHERE id = ?', [$mid]), 'member@panteen.test', 'พนักงานเปลี่ยนอีเมลของสมาชิกไม่ได้ (กันยึดบัญชีผ่านลืมรหัสผ่าน)');
T::eq((new Web())->get('/dresses?page=999999999999999999999')->status, 200, 'เลขหน้าใหญ่ผิดปกติไม่ทำให้ระบบขัดข้อง');
$head = (new Web())->req('HEAD', '/');
T::ok($head->status === 200 && !isset($head->headers['x-powered-by']), 'HEAD ได้ 200 และไม่บอกเวอร์ชัน PHP');

$errs = trim((string) @file_get_contents($log));
$phpErr = preg_grep('/(Fatal|Warning|Notice|Deprecated|Uncaught)/i', explode("\n", $errs));
T::ok(!$phpErr, 'ไม่มี PHP warning/error ใน log ของเซิร์ฟเวอร์', implode(' | ', array_slice($phpErr, 0, 3)));
