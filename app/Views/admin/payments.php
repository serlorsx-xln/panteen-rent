<?php
/** @var array $items @var string $tab */
use App\Services\Status;
$presets = ['ยอดเงินไม่ตรงกับยอดที่ต้องชำระ', 'ชื่อบัญชีผู้รับไม่ใช่ของร้าน', 'สลิปไม่ชัด อ่านรายละเอียดไม่ได้', 'ไม่พบยอดเงินเข้าบัญชีร้าน', 'สลิปซ้ำกับรายการอื่น'];
?>
<div class="page-head">
  <div><h1>ตรวจสลิป</h1><p class="muted">เทียบยอดและชื่อบัญชีในสลิปกับยอดเงินเข้าแอปธนาคารของร้านก่อนกดอนุมัติ</p></div>
</div>
<nav class="tabs" aria-label="สถานะสลิป">
  <a href="<?= e(url('/admin/payments')) ?>" <?= $tab === 'pending' ? 'aria-current="page"' : '' ?>>รอตรวจ<?php if ($tab === 'pending'): ?><span class="n num"><?= count($items) ?></span><?php endif; ?></a>
  <a href="<?= e(url('/admin/payments', ['tab' => 'done'])) ?>" <?= $tab === 'done' ? 'aria-current="page"' : '' ?>>ตรวจแล้ว</a>
</nav>

<?php if (!$items): ?>
  <div class="empty"><?= icon('circle-check', 'ic ic-lg') ?><h2><?= $tab === 'pending' ? 'ไม่มีสลิปรอตรวจ' : 'ยังไม่มีสลิปที่ตรวจแล้ว' ?></h2><p><?= $tab === 'pending' ? 'สลิปใหม่จะขึ้นที่นี่ และมีแจ้งเตือนที่กระดิ่งด้านบน' : '' ?></p></div>
<?php elseif ($tab === 'pending'): ?>
<div class="slips">
  <?php foreach ($items as $p): $match = abs((float) $p['amount'] - (float) $p['total_amount']) < 0.01; ?>
  <article class="slipcard" aria-labelledby="sl-<?= (int) $p['id'] ?>">
    <a href="<?= e(url('/slips/' . $p['id'])) ?>" target="_blank" title="เปิดสลิปขนาดเต็ม"><img src="<?= e(url('/slips/' . $p['id'])) ?>" alt="สลิปของการจอง <?= e($p['booking_no']) ?>" loading="lazy"></a>
    <div>
      <h2 id="sl-<?= (int) $p['id'] ?>" style="font-size:15px"><a href="<?= e(url('/admin/bookings/' . $p['booking_id'])) ?>"><?= e($p['booking_no']) ?></a></h2>
      <p class="small"><?= e(full_name($p)) ?><?= $p['nickname'] ? ' (' . e($p['nickname']) . ')' : '' ?> · <span class="num"><?= e(phone_fmt($p['phone'])) ?></span></p>
      <p class="small muted"><?= e($p['costume_code']) ?> · งาน <?= e(thai_date($p['event_date'], false, false)) ?> · ส่ง <?= e(time_ago($p['created_at'])) ?></p>
      <div class="match">
        ต้องชำระ <b class="num"><?= e(money($p['total_amount'])) ?></b> · ลูกค้าแจ้ง <b class="num"><?= e(money($p['amount'])) ?></b>
        <br><?= $match ? 'ยอดตรงกัน' : '<span class="late">ยอดไม่ตรง ตรวจสลิปให้ดี</span>' ?> · <?= e(Status::PAYMENT_METHOD[$p['method']] ?? $p['method']) ?>
      </div>
      <form method="post" action="<?= e(url('/admin/payments/' . $p['id'] . '/approve')) ?>" data-confirm="อนุมัติสลิป <?= e($p['booking_no']) ?> ยืนยันการจองและออกใบเสร็จ?">
        <?= csrf_field() ?><button class="btn btn-block" type="submit"><?= icon('check') ?>ยอดเข้าแล้ว · อนุมัติ</button>
      </form>
      <details>
        <summary>สลิปไม่ผ่าน</summary>
        <form method="post" action="<?= e(url('/admin/payments/' . $p['id'] . '/reject')) ?>" class="stack" style="gap:8px">
          <?= csrf_field() ?>
          <label class="sr-only" for="rp-<?= (int) $p['id'] ?>">เหตุผล</label>
          <select class="select" id="rp-<?= (int) $p['id'] ?>" name="reason_preset">
            <?php foreach ($presets as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
          </select>
          <label class="sr-only" for="rr-<?= (int) $p['id'] ?>">เหตุผลอื่น</label>
          <input class="input" id="rr-<?= (int) $p['id'] ?>" name="reason" maxlength="200" placeholder="หรือพิมพ์เหตุผลเอง">
          <button class="btn btn-danger btn-sm" type="submit">แจ้งลูกค้าให้ส่งสลิปใหม่</button>
        </form>
      </details>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="tablewrap">
  <table class="table">
    <thead><tr><th>การจอง</th><th>ลูกค้า</th><th class="r">ยอด</th><th>ผล</th><th>ตรวจโดย</th><th>เมื่อ</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $p): ?>
      <tr>
        <td class="num"><a href="<?= e(url('/admin/bookings/' . $p['booking_id'])) ?>"><b><?= e($p['booking_no']) ?></b></a></td>
        <td><?= e(full_name($p)) ?></td>
        <td class="r num"><?= e(number_format((float) $p['amount'])) ?></td>
        <td><?= Status::badge(Status::PAYMENT, $p['status']) ?><?= $p['reject_reason'] ? '<br><small class="faint">' . e($p['reject_reason']) . '</small>' : '' ?></td>
        <td><?= e($p['reviewer_nick'] ?: ($p['reviewer_first'] ?? '–')) ?></td>
        <td class="num nowrap"><?= $p['reviewed_at'] ? e(thai_datetime($p['reviewed_at'])) : '–' ?></td>
        <td><a href="<?= e(url('/slips/' . $p['id'])) ?>" target="_blank">ดูสลิป</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
