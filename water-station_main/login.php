<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    try {
        $rows = db()->select('staff_users', ['username' => 'eq.' . $username], '*', '', 1);
        $user = $rows[0] ?? null;

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
                'role'      => $user['role'],
            ];
            header('Location: index.php');
            exit;
        }
        $error = 'Mali ang username o password.';
    } catch (Throwable $t) {
        $error = 'Hindi maka-connect sa database. Check ang Supabase keys sa config.php.';
    }
}
?>
<!doctype html>
<html lang="fil">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= e(BRAND_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&family=Figtree:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>:root{--brand:<?= BRAND_COLOR ?>;--ink:<?= BRAND_INK ?>;}</style>
</head>
<body>
<div class="hero-wrap">
  <div class="hero-card">
    <div class="card">
      <div class="hero-brand">
        <div class="brand-drop" aria-hidden="true"></div>
        <div>
          <h1 style="font-size:20px;margin:0;"><?= e(BRAND_NAME) ?></h1>
          <div class="muted" style="font-size:13px;"><?= e(BRAND_TAGLINE) ?></div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="flash flash-err"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>
        <button class="btn btn-block" type="submit">Pasok sa istasyon</button>
      </form>
    </div>
    <p class="muted" style="text-align:center;font-size:12.5px;">
      Staff access lang ito. Gusto mag-order?
      <a href="order-online.php">Order online dito</a>.
    </p>
  </div>
</div>
</body>
</html>
