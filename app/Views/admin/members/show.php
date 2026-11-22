<?php
/** @var array $u @var array $bookings @var array $stats */
use App\Core\Auth;
use App\Services\Status;
$isMember = $u['role'] === 'member';
$canEdit = $isMember || Auth::isAdmin();
$self = (int) $u['id'] === Auth::id();
$v = fn($k) => old($k, $u[$k] ?? '');
$num = fn($x) => $x === null || $x === '' ? '' : rtrim(rtrim((string) $x, '0'), '.');
?>
<nav class="crumbs"><a href="<?= e(url('/admin/members', $isMember ? [] : ['role' => 'team'])) ?>"><?= icon('arrow-left', 'ic ic-sm') ?> <?= $isMember ? 'สมาชิกทั้งหมด' : 'ทีมงาน' ?></a></nav>
<div class="page-head">
  <div class="cell-main" style="gap:14px">
    <span class="avatar" aria-hidden="true" style="width:56px;height:56px;font-size:20px"><?= e(initials($u)) ?></span>
    <div><h1><?= e(full_name($u)) ?><?= $u['nickname'] ? ' <span class="muted">(' . e($u['nickname']) . ')</span>' : '' ?></h1>
    <p class="muted"><?= e(Status::ROLE[$u['role']]) ?> · สมัครเมื่อ <?= e(thai_date(substr($u['created_at'], 0, 10))) ?><?= $u['last_login_at'] ? ' · เข้าใช้ล่าสุด ' . e(time_ago($u['last_login_at'])) : '' ?></p></div>
  </div>
  <div class="row"><?= Status::badge(Status::USER, $u['status']) ?><?php if ($isMember && $u['status'] === 'active'): ?><a class="btn" href="<?= e(url('/admin/bookings/new', ['member' => $u['id']])) ?>"><?= icon('calendar-plus') ?>จองให้ลูกค้านี้</a><?php endif; ?></div>
</div>
<?php if ($u['status'] === 'suspended'): ?><div class="conflict"><?= icon('ban') ?><span>บัญชีถูกระงับ: <?= e($u['suspended_reason'] ?: 'ไม่ระบุเหตุผล') ?></span></div><?php endif; ?>

<?php if ($isMember): ?>
<dl class="stats">
  <div><dt>การจองทั้งหมด</dt><dd><?= (int) $stats['total'] ?></dd></div>
  <div><dt>เช่าสำเร็จ</dt><dd><?= (int) $stats['done'] ?></dd></div>
  <div><dt>ยกเลิก</dt><dd><?= (int) $stats['cancelled'] ?></dd></div>
  <div><dt>คืนช้า</dt><dd><?= (int) $stats['late'] ?></dd></div>
  <div><dt>ค่าเช่ารวม (บาท)</dt><dd><?= e(number_format((float) $stats['spent'])) ?></dd></div>
</dl>
<?php endif; ?>

<div class="formgrid">
  <div class="stack" style="gap:14px">
    <?php if ($isMember): ?>
    <section class="box" aria-labelledby="h-bk">
      <div class="box-head"><h2 id="h-bk">ประวัติการเช่า</h2></div>
      <?php if (!$bookings): ?><p class="small muted">ยังไม่เคยจอง</p><?php else: ?>
      <div class="tablewrap" style="border:0">
      <table class="table">
        <thead><tr><th>เลขที่</th><th>ชุด</th><th>รับ – คืน</th><th class="r">ยอด</th><th>สถานะ</th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="num"><a href="<?= e(url('/admin/bookings/' . $b['id'])) ?>"><?= e($b['booking_no']) ?></a></td>
            <td><?= e($b['costume_code']) ?> <small class="faint"><?= e(str_limit($b['costume_name'], 24)) ?></small></td>
            <td class="num nowrap"><?= e(thai_date($b['pickup_date'], false, false)) ?> – <?= e(thai_date($b['return_date'], false, true)) ?><?= $b['late_days'] ? ' <span class="late small">ช้า ' . (int) $b['late_days'] . ' วัน</span>' : '' ?></td>
            <td class="r num"><?= e(number_format((float) $b['total_amount'])) ?></td>
            <td><?= Status::badge(Status::BOOKING, $b['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <form class="box" method="post" action="<?= e(url('/admin/members/' . $u['id'])) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="box-head"><h2>ข้อมูลติดต่อ</h2></div>
      <div class="grid-2">
        <?php foreach (['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'phone' => 'เบอร์โทร', 'email' => 'อีเมล', 'line_id' => 'LINE ID'] as $k => $l): ?>
        <div class="field"><label class="label" for="m-<?= $k ?>"><?= e($l) ?></label><input class="input<?= $k === 'phone' ? ' num' : '' ?>" id="m-<?= $k ?>" name="<?= $k ?>" value="<?= e($v($k)) ?>" <?= $k === 'email' ? 'type="email"' . (Auth::isAdmin() ? '' : ' readonly aria-describedby="m-email-hint"') : '' ?> <?= invalid_attr($k) ?>><?php if ($k === 'email' && !Auth::isAdmin()): ?><p class="hint" id="m-email-hint">อีเมลใช้เข้าระบบและรับลิงก์ตั้งรหัสผ่าน จึงให้ผู้ดูแลระบบหรือสมาชิกเองเป็นผู้แก้</p><?php endif; ?><?= field_error($k) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="box-head" style="margin-top:20px"><h2>สัดส่วน (นิ้ว)</h2></div>
      <div class="grid-3">
        <?php foreach (['bust_in' => 'รอบอก', 'waist_in' => 'รอบเอว', 'hip_in' => 'รอบสะโพก'] as $k => $l): ?>
        <div class="field"><label class="label" for="m-<?= $k ?>"><?= e($l) ?></label><input class="input num" id="m-<?= $k ?>" name="<?= $k ?>" inputmode="decimal" value="<?= e($num($v($k))) ?>" <?= invalid_attr($k) ?>><?= field_error($k) ?></div>
        <?php endforeach; ?>
      </div>
      <button class="btn" type="submit" style="margin-top:16px">บันทึก</button>
    </form>
    <?php endif; ?>
  </div>

  <aside class="stack" style="gap:14px">
    <div class="box">
      <div class="who">
        <span class="small muted">ติดต่อ</span>
        <span class="num"><a href="tel:<?= e(phone_fmt($u['phone'])) ?>"><?= e($u['phone'] ? phone_fmt($u['phone']) : '–') ?></a></span>
        <span><?= e($u['email']) ?></span>
        <?php if ($u['line_id']): ?><span>LINE <?= e($u['line_id']) ?></span><?php endif; ?>
        <span class="small <?= $u['email_verified_at'] ? 'muted' : 'late' ?>"><?= $u['email_verified_at'] ? 'ยืนยันอีเมลแล้ว' : 'ยังไม่ยืนยันอีเมล (จองออนไลน์ไม่ได้)' ?></span>
      </div>
    </div>

    <?php if (Auth::isAdmin() && !$self): ?>
    <form class="action" id="status" method="post" action="<?= e(url('/admin/members/' . $u['id'] . '/status')) ?>" style="border-width:1px;border-color:var(--line)">
      <?= csrf_field() ?>
      <?php if ($u['status'] === 'active'): ?>
      <h3>ระงับบัญชี</h3>
      <p class="small muted">ผู้ใช้จะเข้าระบบและจองไม่ได้ การจองเดิมยังอยู่</p>
      <input type="hidden" name="status" value="suspended">
      <div class="field" style="margin-top:8px"><label class="label" for="s-reason">เหตุผล</label><input class="input" id="s-reason" name="reason" maxlength="255" required <?= invalid_attr('reason') ?>><?= field_error('reason') ?></div>
      <button class="btn btn-danger btn-sm" type="submit" style="margin-top:10px">ระงับบัญชี</button>
      <?php else: ?>
      <h3>เปิดใช้งานบัญชีอีกครั้ง</h3>
      <input type="hidden" name="status" value="active">
      <button class="btn btn-sm" type="submit" style="margin-top:6px">เปิดใช้งาน</button>
      <?php endif; ?>
    </form>

    <form class="action" method="post" action="<?= e(url('/admin/members/' . $u['id'] . '/role')) ?>" style="border-width:1px;border-color:var(--line)" data-confirm="เปลี่ยนบทบาทของ <?= e(full_name($u)) ?>?">
      <?= csrf_field() ?>
      <h3>บทบาท</h3>
      <p class="small muted">พนักงานใช้หลังร้านได้ทั้งหมด ยกเว้นตั้งค่าร้านและจัดการบทบาท</p>
      <div class="row" style="margin-top:8px">
        <label class="sr-only" for="role">บทบาท</label>
        <select class="select" id="role" name="role" style="flex:1"><?php foreach (Status::ROLE as $k => $l): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <button class="btn btn-ghost btn-sm" type="submit">เปลี่ยน</button>
      </div>
    </form>
    <?php endif; ?>
  </aside>
</div>
