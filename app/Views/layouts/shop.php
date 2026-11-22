<?php
use App\Core\Auth;
use App\Core\View;
use App\Services\Catalog;
use App\Services\Notifier;

$user = Auth::user();
$staff = Auth::isStaff();
$unread = $user ? Notifier::unreadCount((int) $user['id']) : 0;
$floors = Catalog::directory();
$allCount = array_sum(array_column($floors, 'costume_count'));
$path = request_path();
$shopName = setting('shop_name');
$here = fn(string $p): string => $path === $p ? ' aria-current="page"' : '';
$notifUrl = url($staff ? '/admin/notifications' : '/account/notifications');
$flashes = trim(View::make('partials/flashes'));
// The bar floats clear over the opening photograph only when nothing has to be said above it.
$over = !empty($overHero) && $flashes === '';
include __DIR__ . '/head.php';
?>
<body<?= !empty($overHero) ? ' class="home"' : '' ?>>
<a class="skip" href="#main">ข้ามไปที่เนื้อหา</a>
<?php if (setting('is_demo') === '1'): ?>
<aside class="demo-note" aria-label="หมายเหตุเว็บไซต์สาธิต">เว็บไซต์สาธิต · ชุด ราคา และการจองทั้งหมดเป็นข้อมูลตัวอย่าง<?php if (mailbox_open()): ?> · อีเมลทั้งหมดอยู่ใน <a href="<?= e(url('/dev/mailbox')) ?>">กล่องจดหมายจำลอง</a><?php endif; ?></aside>
<?php endif; ?>
<header class="topbar<?= $over ? ' is-over' : '' ?>" data-topbar>
  <div class="wrap topbar-in">
    <div class="topbar-side">
      <details class="navsheet" data-sheet>
        <summary class="iconbtn" aria-label="เปิดเมนู"><?= icon('menu') ?></summary>
        <div class="navsheet-panel" role="dialog" aria-label="เมนูร้าน">
          <div class="navsheet-top">
            <button class="iconbtn" type="button" data-sheet-close aria-label="ปิดเมนู"><?= icon('x') ?></button>
            <span class="wordmark" aria-hidden="true"><?= e($shopName) ?></span>
          </div>
          <ul class="navdir">
            <li><a href="<?= e(url('/dresses')) ?>"<?= $here('/dresses') ?>><span>ชุดทั้งหมด</span><small><?= (int) $allCount ?> ชุด</small></a></li>
            <?php foreach ($floors as $fl): ?>
            <li><a href="<?= e(url('/c/' . $fl['slug'])) ?>"<?= $here('/c/' . $fl['slug']) ?>><span><?= e($fl['name']) ?></span><small><?= (int) $fl['costume_count'] ?> ชุด</small></a></li>
            <?php endforeach; ?>
          </ul>
          <ul class="navlinks">
            <li><a href="<?= e(url('/how-to-rent')) ?>"><?= icon('info') ?>วิธีเช่าและเงื่อนไข</a></li>
            <?php if ($user && !$staff): ?>
            <li><a href="<?= e(url('/bookings')) ?>"><?= icon('shopping-bag') ?>การจองของฉัน</a></li>
            <li><a href="<?= e(url('/account/appointments')) ?>"><?= icon('calendar-clock') ?>นัดลองชุด</a></li>
            <li><a href="<?= e(url('/account/favorites')) ?>"><?= icon('heart') ?>ชุดที่ถูกใจ</a></li>
            <?php elseif ($staff): ?>
            <li><a href="<?= e(url('/admin')) ?>"><?= icon('layout-dashboard') ?>ระบบหลังร้าน</a></li>
            <?php else: ?>
            <li><a href="<?= e(url('/login')) ?>"><?= icon('log-in') ?>เข้าสู่ระบบ</a></li>
            <li><a href="<?= e(url('/register')) ?>"><?= icon('user-plus') ?>สมัครสมาชิก</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </details>
      <nav class="mainnav" aria-label="หมวดชุด">
        <a href="<?= e(url('/dresses')) ?>"<?= $here('/dresses') ?>>ชุดทั้งหมด</a>
        <?php foreach ($floors as $fl): ?>
        <a href="<?= e(url('/c/' . $fl['slug'])) ?>"<?= $here('/c/' . $fl['slug']) ?>><?= e($fl['name']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <a class="wordmark" href="<?= e(url('/')) ?>" aria-label="<?= e($shopName) ?> หน้าแรก"><?= e($shopName) ?></a>
    <div class="topnav">
      <nav class="mainnav" aria-label="ช่วยเหลือ">
        <a href="<?= e(url('/how-to-rent')) ?>"<?= $here('/how-to-rent') ?>>วิธีเช่า</a>
      </nav>
      <?php if ($user): ?>
        <?php if ($staff): ?>
          <a class="textlink" href="<?= e(url('/admin')) ?>">หลังร้าน</a>
        <?php else: ?>
          <a class="iconbtn hide-sm" href="<?= e(url('/account/favorites')) ?>" aria-label="ชุดที่ถูกใจ" title="ชุดที่ถูกใจ"><?= icon('heart') ?></a>
          <a class="iconbtn hide-sm" href="<?= e(url('/bookings')) ?>" aria-label="การจองของฉัน" title="การจองของฉัน"><?= icon('shopping-bag') ?></a>
        <?php endif; ?>
        <a class="iconbtn" href="<?= e($notifUrl) ?>" aria-label="การแจ้งเตือน<?= $unread ? ' ยังไม่อ่าน ' . $unread : '' ?>" title="การแจ้งเตือน">
          <?= icon('bell') ?><?php if ($unread): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </a>
        <details class="menu">
          <summary class="iconbtn" aria-label="บัญชีของฉัน"><span class="avatar" aria-hidden="true"><?= e(initials($user)) ?></span></summary>
          <div class="menu-pop">
            <div class="menu-who"><b><?= e(full_name($user)) ?></b><br><span class="small faint"><?= e($user['email']) ?></span></div>
            <hr>
            <?php if (!$staff): ?>
            <a href="<?= e(url('/bookings')) ?>"><?= icon('shopping-bag') ?>การจองของฉัน</a>
            <a href="<?= e(url('/account/appointments')) ?>"><?= icon('calendar-clock') ?>นัดลองชุด</a>
            <a href="<?= e(url('/account/favorites')) ?>"><?= icon('heart') ?>ชุดที่ถูกใจ</a>
            <?php else: ?>
            <a href="<?= e(url('/admin')) ?>"><?= icon('layout-dashboard') ?>ระบบหลังร้าน</a>
            <?php endif; ?>
            <a href="<?= e(url('/account')) ?>"><?= icon('user') ?>ข้อมูลส่วนตัวและสัดส่วน</a>
            <hr>
            <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('log-out') ?>ออกจากระบบ</button></form>
          </div>
        </details>
      <?php else: ?>
        <a class="textlink" href="<?= e(url('/login')) ?>">เข้าสู่ระบบ</a>
        <a class="textlink hide-sm" href="<?= e(url('/register')) ?>">สมัครสมาชิก</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main id="main" tabindex="-1">
  <?php if ($flashes !== ''): ?><div class="<?= !empty($wide) ? 'wrap' : 'col' ?>"><?= $flashes ?></div><?php endif; ?>
  <?= $content ?>
</main>
<footer class="foot">
  <div class="wrap">
    <a class="foot-mark" href="<?= e(url('/')) ?>"><?= e($shopName) ?></a>
    <div class="foot-grid">
      <div>
        <h2>หน้าร้าน</h2>
        <p><?= e(setting('shop_address')) ?></p>
        <p style="margin-top:8px"><?= e(setting('shop_hours')) ?></p>
      </div>
      <div>
        <h2>ติดต่อร้าน</h2>
        <ul>
          <li><a href="tel:<?= e(preg_replace('/\D/', '', (string) setting('shop_phone'))) ?>">โทร <?= e(setting('shop_phone')) ?></a></li>
          <?php if (setting('shop_line')): ?><li>LINE <?= e(setting('shop_line')) ?></li><?php endif; ?>
          <?php if (setting('shop_instagram')): ?><li>Instagram <?= e(setting('shop_instagram')) ?></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h2>หมวดชุด</h2>
        <ul>
          <li><a href="<?= e(url('/dresses')) ?>">ชุดทั้งหมด</a></li>
          <?php foreach ($floors as $fl): ?>
          <li><a href="<?= e(url('/c/' . $fl['slug'])) ?>"><?= e($fl['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h2>ช่วยเหลือ</h2>
        <ul>
          <li><a href="<?= e(url('/how-to-rent')) ?>">วิธีเช่าและเงื่อนไข</a></li>
          <li><a href="<?= e(url('/account/appointments')) ?>">นัดลองชุดที่ร้าน</a></li>
          <li><a href="<?= e(url('/bookings')) ?>">การจองของฉัน</a></li>
          <li><a href="<?= e(url('/credits')) ?>">ที่มาของภาพ</a></li>
        </ul>
      </div>
    </div>
    <p class="foot-legal"><span>© <?= date('Y') + 543 ?> <?= e($shopName) ?></span><span>ระบบเช่าชุดออนไลน์ โครงงานพัฒนาเว็บแอปพลิเคชันร้านเช่าชุด</span></p>
  </div>
</footer>
</body>
</html>
