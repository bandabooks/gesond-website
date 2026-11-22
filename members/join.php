<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_installed();
gr_start_session();
if (current_member()) redirect('index.php');

$err = [];
$v = ['first_name' => '', 'last_name' => '', 'phone' => '', 'birth_date' => '', 'email' => '', 'marketing' => false];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['first_name', 'last_name', 'phone', 'birth_date', 'email'] as $k) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $v['marketing'] = !empty($_POST['marketing']);
    $pin = (string)($_POST['pin'] ?? '');
    $pin2 = (string)($_POST['pin2'] ?? '');

    if ($v['first_name'] === '' || mb_strlen($v['first_name']) > 60) $err['first_name'] = 'Enter your first name.';
    if ($v['last_name'] === '' || mb_strlen($v['last_name']) > 60) $err['last_name'] = 'Enter your surname.';
    $phone = normalise_phone($v['phone']);
    if (!$phone) $err['phone'] = 'Enter a South African cellphone number, for example 082 123 4567.';
    $bd = DateTime::createFromFormat('!Y-m-d', $v['birth_date']);
    if (!$bd || $bd->format('Y-m-d') !== $v['birth_date']) {
        $err['birth_date'] = 'Enter your date of birth.';
    } elseif ($bd > new DateTime('-18 years')) {
        $err['birth_date'] = 'You need to be 18 or older to join Gesond Rewards.';
    } elseif ($bd < new DateTime('-110 years')) {
        $err['birth_date'] = 'Check your date of birth.';
    }
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Check your email address, or leave it blank.';
    if (!preg_match('/^\d{6}$/', $pin)) $err['pin'] = 'Choose a 6-digit PIN.';
    elseif (preg_match('/^(\d)\1{5}$/', $pin) || in_array($pin, ['123456', '654321', '012345'], true)) $err['pin'] = 'That PIN is too easy to guess. Choose another.';
    elseif ($pin !== $pin2) $err['pin2'] = 'The two PINs don\'t match.';
    if (empty($_POST['popia'])) $err['popia'] = 'Tick the box to agree to the privacy notice.';
    // Stop scripted sign-ups. The limit allows a busy night of guests joining on the venue Wi-Fi.
    $joinKey = 'join:' . client_ip();
    if (($_POST['website'] ?? '') !== '') $err['first_name'] = 'Something went wrong. Please try again.';
    elseif (too_many_attempts($joinKey, 30, 60)) $err['first_name'] = 'Many people joined from this connection in the last hour. Please try again later, or use your mobile data.';

    if (!$err && $phone) {
        $st = db()->prepare('SELECT id FROM members WHERE phone = ?');
        $st->execute([$phone]);
        if ($st->fetch()) $err['phone'] = 'This number is already a member. Log in instead.';
    }
    if (!$err) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO members (first_name, last_name, phone, birth_date, email, pin_hash, marketing_optin, popia_consent_at, status, created_at, last_login_at) VALUES (?,?,?,?,?,?,?,NOW(),\'active\',NOW(),NOW())')
            ->execute([$v['first_name'], $v['last_name'], $phone, $v['birth_date'], $v['email'] ?: null, password_hash($pin, PASSWORD_DEFAULT), $v['marketing'] ? 1 : 0]);
        $id = (int)$pdo->lastInsertId();
        $no = 'GL' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE members SET member_no = ? WHERE id = ?')->execute([$no, $id]);
        $pdo->commit();
        record_attempt($joinKey);
        audit('member', $id, 'join', $id, ['marketing_optin' => $v['marketing']]);
        session_regenerate_id(true);
        $_SESSION['member_id'] = $id;
        flash('Welcome to Gesond Rewards! Show your card at the till to earn points.');
        redirect('index.php');
    }
}

function ferr(array $err, string $k): string { return isset($err[$k]) ? '<span class="ferr">' . e($err[$k]) . '</span>' : ''; }

page_head('Join free');
?>
<div class="memgrid">
  <section>
    <h1 class="display display--md">Join Gesond Rewards</h1>
    <p class="lead">Free for everyone 18 and over. Earn points every time you spend at Gesond and turn them into vouchers.</p>
    <ul class="perks">
      <li><b>1 point for every R<?= gr_rand_per_point() ?></b> you spend at Gesond</li>
      <li><b><?= gr_voucher_points() ?> points = <?= money(gr_voucher_value_cents()) ?> voucher</b> to use on food or drinks</li>
      <li><b>Your card on your phone.</b> Show it at the till, no plastic needed</li>
      <li><b>First to hear</b> about new events and specials, if you choose</li>
    </ul>
    <p class="fine">Already a member? <a href="login.php">Log in</a>. See the <a href="rules.php">rewards rules</a>.</p>
  </section>
  <form method="post" class="mform card-form" novalidate>
    <?= csrf_field() ?>
    <label class="hp" aria-hidden="true">Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label>
    <div class="row2">
      <label>First name<input name="first_name" autocomplete="given-name" required value="<?= e($v['first_name']) ?>"><?= ferr($err, 'first_name') ?></label>
      <label>Surname<input name="last_name" autocomplete="family-name" required value="<?= e($v['last_name']) ?>"><?= ferr($err, 'last_name') ?></label>
    </div>
    <label>Cellphone number<input name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="082 123 4567" required value="<?= e($v['phone']) ?>"><small>You'll use this to log in.</small><?= ferr($err, 'phone') ?></label>
    <label>Date of birth<input name="birth_date" type="date" required value="<?= e($v['birth_date']) ?>"><small>Members must be 18 or older.</small><?= ferr($err, 'birth_date') ?></label>
    <label>Email (optional)<input name="email" type="email" autocomplete="email" value="<?= e($v['email']) ?>"><?= ferr($err, 'email') ?></label>
    <div class="row2">
      <label>Choose a 6-digit PIN<input name="pin" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="new-password" required><?= ferr($err, 'pin') ?></label>
      <label>Repeat your PIN<input name="pin2" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="new-password" required><?= ferr($err, 'pin2') ?></label>
    </div>
    <label class="check"><input type="checkbox" name="marketing" value="1" <?= $v['marketing'] ? 'checked' : '' ?>><span>Send me Gesond event news and specials by WhatsApp or SMS. I can stop this at any time.</span></label>
    <label class="check"><input type="checkbox" name="popia" value="1" required><span>I am 18 or older and I agree to the <a href="privacy.php" target="_blank">privacy notice</a> and <a href="rules.php" target="_blank">rewards rules</a>.</span></label>
    <?= ferr($err, 'popia') ?>
    <button class="btn btn--gold" type="submit">Join Gesond Rewards</button>
  </form>
</div>
<?php page_foot();
