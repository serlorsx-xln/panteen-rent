<?php
use App\Core\Auth;
use App\Core\DB;
use App\Services\Notifier;

$adminCss = true;
$user = Auth::user();
$unread = Notifier::unreadCount((int) $user['id']);
$pendingSlips = (int) DB::value("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$overdue = (int) DB::value("SELECT COUNT(*) FROM bookings WHERE status = 'picked_up' AND return_date < ?", [today()]);
// parcels due out today or late, returns waiting at the shop, and open disputes
$shipWork = (int) DB::value(
    "SELECT COUNT(*) FROM bookings b WHERE (b.status = 'confirmed' AND b.delivery_method <> 'pickup' AND b.ship_by_date <= ?)
        OR (b.status = 'returning' AND EXISTS (SELECT 1 FROM shipments s WHERE s.booking_id = b.id AND s.direction = 'back' AND s.status IN ('received','lost')))
        OR (b.dispute_at IS NOT NULL AND b.dispute_resolved_at IS NULL)",
    [today()]
);
$nav = [
    ['/admin', 'layout-dashboard', 'วันนี้', null, true],
    ['/admin/bookings', 'list-checks', 'การจอง', $overdue ? ['n' => $overdue, 'label' => 'เลยกำหนดคืน'] : null],
    ['/admin/payments', 'wallet', 'ตรวจสลิป', $pendingSlips ? ['n' => $pendingSlips, 'label' => 'สลิปรอตรวจ'] : null],
    ['/admin/shipping', 'truck', 'จัดส่งและรับคืน', $shipWork ? ['n' => $shipWork, 'label' => 'งานจัดส่งที่ต้องทำวันนี้'] : null],
    ['/admin/scan', 'scan-line', 'สแกนชุด', null],
    ['/admin/schedule', 'calendar-days', 'ตารางชุด', null],
    ['/admin/appointments', 'calendar-clock', 'นัดหมาย', null],
    ['/admin/costumes', 'dress', 'ชุด', null],
    ['/admin/categories', 'tag', 'หมวดชุด', null],
    ['/admin/damage-types', 'scale', 'ค่าเสียหาย', null],
    ['/admin/members', 'users', 'สมาชิก', null],
    ['/admin/reports', 'chart-column', 'รายงาน', null],
];
if (Auth::isAdmin()) {
    $nav[] = ['/admin/settings', 'settings', 'ตั้งค่าร้าน', null];
}
$here = request_path();
$isHere = fn(string $href, bool $exact = false) => $exact ? $here === $href : ($here === $href || str_starts_with($here, $href . '/'));
include __DIR__ . '/head.php';
?>
<body class="admin">
<a class="skip" href="#main">ข้ามไปที่เนื้อหา</a>
<div class="shell">
  <aside class="side" id="side-nav" aria-label="แถบเมนูหลังร้าน">
    <div class="side-head">
      <a class="wordmark" href="<?= e(url('/admin')) ?>"><?= e(setting('shop_name')) ?></a>
      <span class="side-tag">หลังร้าน</span>
    </div>
    <a class="btn btn-block side-new" href="<?= e(url('/admin/bookings/new')) ?>"><?= icon('calendar-plus') ?>จองหน้าร้าน</a>
    <nav aria-label="เมนูหลังร้าน">
      <?php foreach ($nav as $n): $on = $isHere($n[0], !empty($n[4])); ?>
      <a class="side-link<?= $on ? ' is-on' : '' ?>" href="<?= e(url($n[0])) ?>" <?= $on ? 'aria-current="page"' : '' ?>>
        <?= icon($n[1]) ?><span><?= e($n[2]) ?></span>
        <?php if ($n[3]): ?><span class="side-count" aria-label="<?= e($n[3]['label'] . ' ' . $n[3]['n']) ?>"><?= (int) $n[3]['n'] ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a class="side-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?><span>ดูหน้าร้าน</span></a>
      <?php if (mailbox_open()): ?>
      <a class="side-link" href="<?= e(url('/dev/mailbox')) ?>"><?= icon('inbox') ?><span>กล่องจดหมายจำลอง</span></a>
      <?php endif; ?>
      <div class="side-user">
        <span class="avatar" aria-hidden="true"><?= e(initials($user)) ?></span>
        <span><b><?= e($user['nickname'] ?: $user['first_name']) ?></b><small><?= e(\App\Services\Status::ROLE[$user['role']]) ?></small></span>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="iconbtn" type="submit" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= icon('log-out') ?></button></form>
      </div>
    </div>
  </aside>
  <div class="main-wrap">
    <header class="adminbar">
      <button class="iconbtn nav-toggle" type="button" data-nav-toggle aria-controls="side-nav" aria-expanded="false" aria-label="เปิดเมนู"><?= icon('menu') ?></button>
      <form class="adminsearch" method="get" action="<?= e(url('/admin/bookings')) ?>" role="search">
        <?= icon('search', 'ic ic-sm') ?>
        <label class="sr-only" for="global-q">ค้นหาการจอง</label>
        <input id="global-q" name="q" type="search" placeholder="ค้นเลขจอง ชื่อลูกค้า เบอร์โทร รหัสชุด" value="<?= e(is_path('/admin/bookings') ? input_str('q') : '') ?>">
      </form>
      <a class="iconbtn" href="<?= e(url('/admin/notifications')) ?>" aria-label="การแจ้งเตือน<?= $unread ? ' ยังไม่อ่าน ' . $unread : '' ?>">
        <?= icon('bell') ?><?php if ($unread): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
      </a>
    </header>
    <main id="main" class="admin-main" tabindex="-1">
      <?php \App\Core\View::partial('flashes'); ?>
      <?= $content ?>
    </main>
  </div>
</div>
<div class="nav-scrim" data-nav-toggle aria-hidden="true"></div>
</body>
</html>
