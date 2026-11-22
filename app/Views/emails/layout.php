<?php
/**
 * Every email the shop sends. Mail clients load no web fonts and ignore most CSS, so the look is carried
 * by inline styles only: a white sheet on warm grey, black ink, the shop name spaced out in a system serif.
 * @var string $content @var ?string $subject
 */
$sans = "'Anuphan','Sukhumvit Set','Thonburi','Leelawadee UI',Tahoma,sans-serif";
$serif = "Didot,'Bodoni MT','Bodoni 72','Times New Roman',Georgia,serif";
?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($subject ?? '') ?></title></head>
<body style="margin:0;background:#F4F2EF;font-family:<?= $sans ?>;color:#131110">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F2EF"><tr><td align="center" style="padding:32px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #DEDAD4">
<tr><td style="padding:30px 32px 24px;border-bottom:1px solid #131110;font:400 19px/1.2 <?= $serif ?>;letter-spacing:5px;text-transform:uppercase;color:#131110"><?= e(setting('shop_name')) ?></td></tr>
<tr><td style="padding:28px 32px 32px;font-size:15px;line-height:1.75;color:#131110"><?= $content ?></td></tr>
<tr><td style="padding:18px 32px 22px;border-top:1px solid #DEDAD4;font-size:12px;line-height:1.7;color:#6E6964">
<?= e(setting('shop_name')) ?> · <?= e(setting('shop_address')) ?><br>โทร <?= e(setting('shop_phone')) ?> · LINE <?= e(setting('shop_line')) ?> · <?= e(setting('shop_hours')) ?><br>
อีเมลนี้ส่งอัตโนมัติจากระบบจองชุด<?= setting('is_demo') === '1' ? ' (ระบบสาธิต ข้อมูลทั้งหมดเป็นตัวอย่าง)' : '' ?>
</td></tr></table></td></tr></table></body></html>
