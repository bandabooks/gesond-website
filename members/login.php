<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_installed();
gr_start_session();
if (current_member()) redirect('index.php');

$error = null;
$phoneIn = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $phoneIn = trim((string)($_POST['phone'] ?? ''));
    $pin = (string)($_POST['pin'] ?? '');
    $phone = normalise_phone($phoneIn);
    $ipKey = 'm-ip:' . client_ip();
    $phKey = 'm-ph:' . ($phone ?? $phoneIn);
    if (too_many_attempts($ipKey, 20) || too_many_attempts($phKey)) {
        $error = 'Too many attempts. Wait 15 minutes and try again, or ask Gesond staff to reset your PIN.';
    } else {
        $m = null;
        if ($phone) {
            $st = db()->prepare('SELECT * FROM members WHERE phone = ?');
            $st->execute([$phone]);
            $m = $st->fetch() ?: null;
        }
        if ($m && $m['status'] === 'active' && password_verify($pin, $m['pin_hash'])) {
            clear_attempts($phKey);
            session_regenerate_id(true);
            $_SESSION['member_id'] = (int)$m['id'];
            db()->prepare('UPDATE members SET last_login_at = NOW() WHERE id = ?')->execute([$m['id']]);
            audit('member', (int)$m['id'], 'login', (int)$m['id']);
            redirect($m['must_change_pin'] ? 'pin.php' : 'index.php');
        }
        record_attempt($ipKey);
        record_attempt($phKey);
        $error = ($m && $m['status'] !== 'active')
            ? 'This membership is suspended. Please speak to Gesond staff.'
            : 'That number and PIN don\'t match. Check them and try again.';
    }
}
page_head('Log in');
?>
<div class="narrowbox">
  <h1 class="display display--md">Log in</h1>
  <p class="lead">Use the cellphone number and 6-digit PIN you joined with.</p>
  <?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="mform card-form">
    <?= csrf_field() ?>
    <label>Cellphone number<input name="phone" type="tel" inputmode="tel" autocomplete="tel" required value="<?= e($phoneIn) ?>"></label>
    <label>PIN<input name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="current-password" required></label>
    <button class="btn btn--gold" type="submit">Log in</button>
  </form>
  <p class="fine">Forgot your PIN? Ask Gesond staff at the till to reset it; they'll check your ID first. Not a member yet? <a href="join.php">Join free</a>.</p>
</div>
<?php page_foot();
