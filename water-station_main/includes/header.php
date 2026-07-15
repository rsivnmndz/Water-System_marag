<?php
// Expects: $title (string), $active (current filename). Both optional.
$title  = $title  ?? BRAND_NAME;
$active = $active ?? basename($_SERVER['PHP_SELF']);
$nav = [
    ['index.php',      'Dashboard'],
    ['order-new.php',  'Bagong Order'],
    ['orders.php',     'Orders'],
    ['customers.php',  'Customers'],
    ['utang.php',      'Utang'],
    ['inventory.php',  'Inventory'],
    ['expenses.php',   'Gastos'],
];
$u = current_user();
?>
<!doctype html>
<html lang="fil">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(BRAND_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&family=Figtree:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>:root{--brand:<?= BRAND_COLOR ?>;--ink:<?= BRAND_INK ?>;}</style>
</head>
<body>
<div class="shell">

  <aside class="sidebar">
    <div class="brand">
      <div class="brand-drop" aria-hidden="true"></div>
      <div>
        <div class="brand-name"><?= e(BRAND_NAME) ?></div>
        <div class="brand-tag"><?= e(BRAND_TAGLINE) ?></div>
      </div>
    </div>
    <nav class="nav">
      <?php foreach ($nav as [$href, $label]): ?>
        <a href="<?= e($href) ?>" class="nav-link<?= $active === $href ? ' is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <span class="who"><?= e($u['full_name'] ?? $u['username'] ?? '') ?></span>
      <a class="logout" href="logout.php">Logout</a>
    </div>
  </aside>

  <main class="main">
    <?php if ($f = get_flash()): ?>
      <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endif; ?>
