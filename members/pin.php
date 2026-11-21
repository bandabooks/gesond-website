<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_installed();
$m = require_member();
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cur = (string)($_POST['current'] ?? '');
    $pin = (string)($_POST['pin'] ?? '');
    $pin2 = (string)($_POST['pin2'] ?? '');
    if (!password_verify($cur, $m['pin_hash'])) $err = 'Your current PIN is not right.';
    elseif (!preg_match('/^\d{6}$/', $pin)) $err = 'Choose a 6-digit PIN.';
    elseif (preg_match('/^(\d)\1{5}$/', $pin) || in_array($pin, ['123456', '654321', '012345'], true)) $err = 'That PIN is too easy to guess. Choose another.';
    elseif ($pin === $cur) $err = 'Choose a PIN that is different from your current one.';
    elseif ($pin !== $pin2) $err = 'The two new PINs don\'t match.';
    else {
        db()->prepare('UPDATE members SET pin_hash = ?, must_change_pin = 0 WHERE id = ?')->execute([password_hash($pin, PASSWORD_DEFAULT), $m['id']]);
        audit('member', (int)$m['id'], 'pin_changed', (int)$m['id']);
        session_regenerate_id(true);
        flash('Your PIN is changed.');
        redirect('index.php');
    }
}
page_head('Change PIN', '', $m);
?>
<div class="narrowbox">
  <h1 class="display display--sm"><?= $m['must_change_pin'] ? 'Choose your new PIN' : 'Change my PIN' ?></h1>
  <?php if ($m['must_change_pin']): ?><p class="lead">Staff gave you a temporary PIN. Choose your own 6-digit PIN to continue.</p><?php endif; ?>
  <?php if ($err): ?><p class="notice notice--err" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="mform card-form">
    <?= csrf_field() ?>
    <label><?= $m['must_change_pin'] ? 'Temporary PIN' : 'Current PIN' ?><input name="current" type="password" inputmode="numeric" maxlength="6" autocomplete="current-password" required></label>
    <label>New 6-digit PIN<input name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" required></label>
    <label>Repeat new PIN<input name="pin2" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" required></label>
    <button class="btn btn--gold" type="submit">Save new PIN</button>
  </form>
</div>
<?php page_foot();
