<?php
/** @var string|null $title */
$shop = setting('shop_name');
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? null) ? $title . ' · ' . $shop : $shop . ' · เช่าชุดตามวันงานของคุณ') ?></title>
<meta name="description" content="<?= e(setting('shop_bio')) ?>">
<meta name="theme-color" content="#FFFFFF">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-path" content="<?= e(base_path()) ?>">
<link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="icon" href="<?= e(asset('img/favicon-192.png')) ?>" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/anuphan-thai-300-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/anuphan-thai-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/libre-caslon-display-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php if (!empty($overHero)): ?><link rel="preload" href="<?= e(url('assets/fonts/anuphan-thai-200-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (!empty($adminCss)): ?><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"><?php endif; ?>
<script>document.documentElement.classList.add('js')</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
