<?php /** @var string $heading @var array $lines @var ?array $items @var ?array $button @var ?string $note @var ?array $user */ ?>
<?php if (!empty($user)): ?><p style="margin:0 0 12px;color:#4A4643">สวัสดีคุณ<?= e($user['nickname'] ?: $user['first_name']) ?></p><?php endif; ?>
<h1 style="margin:0 0 14px;font-size:24px;line-height:1.35;font-weight:300;color:#131110"><?= e($heading ?? '') ?></h1>
<?php foreach ($lines ?? [] as $l): ?><p style="margin:0 0 8px"><?= e($l) ?></p><?php endforeach; ?>
<?php if (!empty($items)): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0 4px;border-top:1px solid #131110;font-size:14px">
<?php foreach ($items as $k => $v): ?>
<tr><td style="padding:10px 16px 10px 0;color:#6E6964;border-bottom:1px solid #ECE9E4;white-space:nowrap;vertical-align:top"><?= e($k) ?></td><td style="padding:10px 0;border-bottom:1px solid #ECE9E4;font-weight:500;text-align:right"><?= e($v) ?></td></tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php if (!empty($button)): ?>
<p style="margin:24px 0 8px"><a href="<?= e($button[1]) ?>" style="display:inline-block;background:#131110;color:#ffffff;text-decoration:none;font-weight:500;font-size:15px;padding:14px 30px;border:1px solid #131110"><?= e($button[0]) ?></a></p>
<p style="margin:8px 0 0;font-size:12px;line-height:1.6;color:#6E6964;word-break:break-all">ถ้าปุ่มกดไม่ได้ คัดลอกลิงก์นี้: <?= e($button[1]) ?></p>
<?php endif; ?>
<?php if (!empty($note)): ?><p style="margin:18px 0 0;font-size:13px;line-height:1.65;color:#4A4643"><?= e($note) ?></p><?php endif; ?>
