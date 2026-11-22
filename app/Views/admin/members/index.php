<?php
/** @var array $items @var array $pg @var string $q @var string $role @var string $status */
use App\Services\Status;
$qs = fn(array $o = []) => url('/admin/members', array_filter(array_merge(['role' => $role, 'q' => $q, 'status' => $status], $o), fn($v) => $v !== '' && $v !== null));
?>
<div class="page-head">
  <div><h1><?= $role === 'team' ? 'ทีมงาน' : 'สมาชิก' ?></h1><p class="muted"><?= (int) $pg['total'] ?> บัญชี</p></div>
  <a class="btn" href="<?= e(url('/admin/members/new')) ?>"><?= icon('user-plus') ?>สมัครให้ลูกค้า</a>
</div>
<nav class="tabs" aria-label="กลุ่มผู้ใช้">
  <a href="<?= e(url('/admin/members')) ?>" <?= $role === 'member' ? 'aria-current="page"' : '' ?>>ลูกค้า</a>
  <a href="<?= e(url('/admin/members', ['role' => 'team'])) ?>" <?= $role === 'team' ? 'aria-current="page"' : '' ?>>ทีมงาน</a>
</nav>
<form class="filterbar" method="get" action="<?= e(url('/admin/members')) ?>">
  <input type="hidden" name="role" value="<?= e($role) ?>">
  <div class="field"><label class="label" for="mq">ค้นหา</label><input class="input" id="mq" type="search" name="q" value="<?= e($q) ?>" placeholder="ชื่อ ชื่อเล่น อีเมล เบอร์ LINE" style="width:260px"></div>
  <div class="field"><label class="label" for="ms">สถานะ</label><select class="select" id="ms" name="status" data-autosubmit><option value="">ทั้งหมด</option><?php foreach (Status::USER as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ghost" type="submit"><?= icon('search', 'ic ic-sm') ?>ค้นหา</button>
</form>
<?php if (!$items): ?>
<div class="empty"><?= icon('users', 'ic ic-lg') ?><h2>ไม่พบบัญชี</h2><p>ลองค้นด้วยเบอร์โทรหรืออีเมล</p></div>
<?php else: ?>
<div class="tablewrap">
  <table class="table">
    <thead><tr><th>ชื่อ</th><th>ติดต่อ</th><?php if ($role === 'team'): ?><th>บทบาท</th><?php endif; ?><th class="r">การจอง</th><th>จองล่าสุด</th><th>อีเมล</th><th>สถานะ</th></tr></thead>
    <tbody>
      <?php foreach ($items as $u): ?>
      <tr>
        <td><a class="cell-main" href="<?= e(url('/admin/members/' . $u['id'])) ?>"><span class="avatar" aria-hidden="true"><?= e(initials($u)) ?></span><span><b><?= e(full_name($u)) ?></b><small><?= e($u['nickname'] ?: '') ?></small></span></a></td>
        <td><span class="num"><?= e($u['phone'] ? phone_fmt($u['phone']) : '–') ?></span><br><small class="faint"><?= e($u['email']) ?></small></td>
        <?php if ($role === 'team'): ?><td><?= e(Status::ROLE[$u['role']]) ?></td><?php endif; ?>
        <td class="r num"><?= (int) $u['bookings'] ?><?= $u['active_bookings'] ? ' <small class="faint">(ค้าง ' . (int) $u['active_bookings'] . ')</small>' : '' ?></td>
        <td class="num nowrap"><?= $u['last_booking'] ? e(thai_date(substr($u['last_booking'], 0, 10), false, false)) : '<span class="faint">–</span>' ?></td>
        <td><?= $u['email_verified_at'] ? '<span class="small">ยืนยันแล้ว</span>' : '<span class="status status-wait">ยังไม่ยืนยัน</span>' ?></td>
        <td><?= Status::badge(Status::USER, $u['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php \App\Core\View::partial('pager', ['pg' => $pg, 'make' => fn($p) => $qs(['page' => $p])]); ?>
<?php endif; ?>
