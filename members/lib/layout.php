<?php
declare(strict_types=1);

/* $base: path from the current page to /members/ ("" or "../") */
function page_head(string $title, string $base = '', ?array $member = null, ?array $staff = null): void {
    $flash = flash();
    $v = '20261122d';
    ?><!DOCTYPE html>
<html lang="en-ZA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> | Gesond Rewards</title>
<meta name="theme-color" content="#14110D">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= $v ?>">
<link rel="stylesheet" href="/members/assets/members.css?v=<?= $v ?>">
</head>
<body class="mem">
<header class="nav">
  <div class="wrap nav__in">
    <a href="/" class="nav__logo"><img src="/assets/brand/gesond-logo-600.webp" alt="Gesond Liquor Restaurant home" width="150" height="50"></a>
    <nav class="mem__nav" aria-label="Rewards">
<?php if ($staff): ?>
      <span class="mem__who"><?= e($staff['full_name']) ?> (<?= e($staff['role']) ?>)</span>
<?php if ($staff['role'] === 'admin'): ?>      <a href="<?= $base ?>staff/dashboard.php">Dashboard</a>
<?php endif; ?>      <a href="<?= $base ?>staff/index.php">Till</a>
      <a href="<?= $base ?>staff/inbox.php">Inbox</a>
      <a href="<?= $base ?>staff/posts.php">Posts</a>
<?php if ($staff['role'] === 'admin'): ?>      <a href="<?= $base ?>staff/users.php">Staff</a>
      <a href="<?= $base ?>staff/settings.php">Settings</a>
<?php endif; ?>
      <form method="post" action="<?= $base ?>staff/logout.php"><?= csrf_field() ?><button class="linkbtn" type="submit">Log out</button></form>
<?php elseif ($member): ?>
      <a href="<?= $base ?>index.php">My card</a>
      <a href="/events.html">What's on</a>
      <a href="<?= $base ?>contact.php">Talk to us</a>
      <form method="post" action="<?= $base ?>logout.php"><?= csrf_field() ?><button class="linkbtn" type="submit">Log out</button></form>
<?php else: ?>
      <a href="/events.html">What's on</a>
      <a href="<?= $base ?>contact.php">Talk to us</a>
      <a href="<?= $base ?>login.php">Log in</a>
      <a class="btn btn--gold btn--sm" href="<?= $base ?>join.php">Join free</a>
<?php endif; ?>
    </nav>
  </div>
</header>
<main id="main" class="mem__main">
<div class="wrap">
<?php if ($flash): ?>
<p class="notice notice--<?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></p>
<?php endif;
}

function page_foot(): void {
    ?>
</div>
</main>
<footer class="foot">
  <div class="wrap foot__in">
    <p class="foot__links"><a href="/members/contact.php">Talk to us</a> <a href="/members/privacy.php">Privacy notice</a> <a href="/members/rules.php">Rewards rules</a> <a href="/">Gesond website</a></p>
    <p>Gesond Enterprise (Pty) Ltd, trading as Gesond Liquor Restaurant. Reg. No. 2021/658589/07.</p>
    <p class="foot__warn">Not for sale to persons under the age of 18. Drink responsibly.</p>
  </div>
</footer>
<script src="/assets/js/pwa.js?v=<?= '20261122d' ?>" defer></script>
</body>
</html>
<?php
}
