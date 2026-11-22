<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_installed();
gr_start_session();
$s = current_staff();

/* ---------- Staff log-in ---------- */
if (!$s) {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $u = strtolower(trim((string)($_POST['username'] ?? '')));
        $key = 's:' . $u;
        if (too_many_attempts($key) || too_many_attempts('s-ip:' . client_ip(), 20)) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            $st = db()->prepare('SELECT * FROM staff WHERE username = ? AND active = 1');
            $st->execute([$u]);
            $row = $st->fetch();
            if ($row && password_verify((string)($_POST['password'] ?? ''), $row['password_hash'])) {
                clear_attempts($key);
                session_regenerate_id(true);
                $_SESSION['staff_id'] = (int)$row['id'];
                db()->prepare('UPDATE staff SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
                audit('staff', (int)$row['id'], 'staff_login');
                redirect($row['role'] === 'admin' ? 'dashboard.php' : 'index.php');
            }
            record_attempt($key);
            record_attempt('s-ip:' . client_ip());
            $error = 'Username or password is not right.';
        }
    }
    page_head('Staff log-in', '../');
    ?>
<div class="narrowbox">
  <h1 class="display display--sm">Staff log-in</h1>
  <?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="mform card-form">
    <?= csrf_field() ?>
    <label>Username<input name="username" autocomplete="username" required></label>
    <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
    <button class="btn btn--gold" type="submit">Log in</button>
  </form>
</div>
<?php
    page_foot();
    exit;
}

$sid = (int)$s['id'];
$isAdmin = $s['role'] === 'admin';

function find_member(string $q): ?array {
    $q = trim($q);
    if ($q === '') return null;
    $no = strtoupper(preg_replace('/\s+/', '', $q));
    if (preg_match('/^GL\d{6}$/', $no)) {
        $st = db()->prepare('SELECT * FROM members WHERE member_no = ?');
        $st->execute([$no]);
    } else {
        $p = normalise_phone($q);
        if (!$p) return null;
        $st = db()->prepare('SELECT * FROM members WHERE phone = ?');
        $st->execute([$p]);
    }
    return $st->fetch() ?: null;
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $mid = (int)($_POST['member_id'] ?? 0);
    $back = 'index.php' . ($mid ? '?m=' . $mid : '');
    $st = db()->prepare('SELECT * FROM members WHERE id = ?');
    $st->execute([$mid]);
    $mem = $st->fetch();

    if ($action === 'earn' && $mem) {
        $amount = str_replace([' ', ','], ['', '.'], (string)($_POST['amount'] ?? ''));
        $ref = strtoupper(trim((string)($_POST['reference'] ?? '')));
        if ($mem['status'] !== 'active') { flash('This membership is suspended. Points can\'t be added.', 'err'); redirect($back); }
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $amount) || (float)$amount <= 0 || (float)$amount > 50000) { flash('Enter the bill amount in rand, for example 250 or 250.50.', 'err'); redirect($back); }
        if (!preg_match('/^[A-Z0-9\/\-]{2,40}$/', $ref)) { flash('Enter the slip or receipt number from the till.', 'err'); redirect($back); }
        $cents = (int)round((float)$amount * 100);
        $pts = intdiv($cents, gr_rand_per_point() * 100);
        if ($pts < 1) { flash('Bills under R' . gr_rand_per_point() . ' don\'t earn points.', 'err'); redirect($back); }
        try {
            db()->prepare("INSERT INTO points_ledger (member_id, type, points, spend_cents, reference, staff_id, created_at) VALUES (?,'earn',?,?,?,?,NOW())")
                ->execute([$mid, $pts, $cents, $ref, $sid]);
            audit('staff', $sid, 'earn', $mid, ['points' => $pts, 'spend_cents' => $cents, 'ref' => $ref]);
            flash('Added ' . $pts . ' points for ' . money($cents) . ' (slip ' . $ref . ').');
        } catch (PDOException $ex) {
            flash('Slip ' . $ref . ' already has points on it. Each slip can earn points only once.', 'err');
        }
        redirect($back);
    }

    if ($action === 'use_voucher') {
        $code = strtoupper(preg_replace('/\s+/', '', (string)($_POST['code'] ?? '')));
        $ref = strtoupper(trim((string)($_POST['reference'] ?? '')));
        if (!preg_match('/^[A-Z0-9\/\-]{2,40}$/', $ref)) { flash('Enter the slip number the voucher is used on.', 'err'); redirect($back); }
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT v.*, m.member_no, m.first_name, m.last_name FROM vouchers v JOIN members m ON m.id = v.member_id WHERE v.code = ? FOR UPDATE');
        $st->execute([$code]);
        $vc = $st->fetch();
        if (!$vc) { $pdo->rollBack(); flash('No voucher with code ' . $code . '.', 'err'); redirect($back); }
        if ($vc['status'] !== 'active') { $pdo->rollBack(); flash('Voucher ' . $code . ' was already ' . $vc['status'] . ($vc['used_at'] ? ' on ' . date('j M Y H:i', strtotime($vc['used_at'])) : '') . '.', 'err'); redirect($back); }
        $pdo->prepare("UPDATE vouchers SET status = 'used', used_at = NOW(), used_by_staff = ?, used_reference = ? WHERE id = ?")->execute([$sid, $ref, $vc['id']]);
        $pdo->commit();
        audit('staff', $sid, 'voucher_used', (int)$vc['member_id'], ['code' => $code, 'ref' => $ref]);
        flash('Voucher ' . $code . ' accepted: take ' . money((int)$vc['value_cents']) . ' off slip ' . $ref . ' for ' . $vc['first_name'] . ' ' . $vc['last_name'] . ' (' . $vc['member_no'] . ').');
        redirect('index.php?m=' . (int)$vc['member_id']);
    }

    if ($action === 'reset_pin' && $mem) {
        $tmp = (string)random_int(100000, 999999);
        db()->prepare('UPDATE members SET pin_hash = ?, must_change_pin = 1 WHERE id = ?')->execute([password_hash($tmp, PASSWORD_DEFAULT), $mid]);
        clear_attempts('m-ph:' . $mem['phone']);
        audit('staff', $sid, 'pin_reset', $mid);
        flash('Temporary PIN for ' . $mem['first_name'] . ': ' . $tmp . '. Give it to the member in person only, after checking their ID. They must choose a new PIN when they log in.');
        redirect($back);
    }

    if ($action === 'reverse' && $mem && $isAdmin) {
        $lid = (int)($_POST['ledger_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $st = db()->prepare("SELECT * FROM points_ledger WHERE id = ? AND member_id = ? AND type = 'earn'");
        $st->execute([$lid, $mid]);
        $row = $st->fetch();
        if (!$row || mb_strlen($reason) < 3) { flash('Choose the entry and give a reason for the correction.', 'err'); redirect($back); }
        try {
            db()->prepare("INSERT INTO points_ledger (member_id, type, points, reference, note, reverses_id, staff_id, created_at) VALUES (?,'reversal',?,NULL,?,?,?,NOW())")
                ->execute([$mid, -(int)$row['points'], mb_substr($reason, 0, 255), $lid, $sid]);
            audit('staff', $sid, 'reversal', $mid, ['ledger_id' => $lid, 'points' => -(int)$row['points'], 'reason' => $reason]);
            flash('Reversed ' . (int)$row['points'] . ' points.');
        } catch (PDOException $ex) {
            flash('That entry was already reversed.', 'err');
        }
        redirect($back);
    }

    if ($action === 'cancel_voucher' && $mem && $isAdmin) {
        $vid = (int)($_POST['voucher_id'] ?? 0);
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM vouchers WHERE id = ? AND member_id = ? AND status = 'active' FOR UPDATE");
        $st->execute([$vid, $mid]);
        $vc = $st->fetch();
        if ($vc) {
            $pdo->prepare("UPDATE vouchers SET status = 'cancelled' WHERE id = ?")->execute([$vid]);
            $st = $pdo->prepare("SELECT id FROM points_ledger WHERE voucher_id = ? AND type = 'redeem'");
            $st->execute([$vid]);
            $redeemId = (int)$st->fetchColumn();
            $pdo->prepare("INSERT INTO points_ledger (member_id, type, points, note, voucher_id, reverses_id, staff_id, created_at) VALUES (?,'reversal',?,?,?,?,?,NOW())")
                ->execute([$mid, (int)$vc['points'], 'Voucher ' . $vc['code'] . ' cancelled, points returned', $vid, $redeemId ?: null, $sid]);
            $pdo->commit();
            audit('staff', $sid, 'voucher_cancelled', $mid, ['code' => $vc['code']]);
            flash('Voucher ' . $vc['code'] . ' cancelled and ' . (int)$vc['points'] . ' points returned.');
        } else {
            $pdo->rollBack();
            flash('That voucher is no longer active.', 'err');
        }
        redirect($back);
    }

    if ($action === 'status' && $mem && $isAdmin) {
        $new = $mem['status'] === 'active' ? 'suspended' : 'active';
        db()->prepare('UPDATE members SET status = ? WHERE id = ?')->execute([$new, $mid]);
        audit('staff', $sid, 'member_' . $new, $mid);
        flash('Membership is now ' . $new . '.');
        redirect($back);
    }
    redirect('index.php');
}

/* ---------- Page ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$mem = null;
if (isset($_GET['m'])) {
    $st = db()->prepare('SELECT * FROM members WHERE id = ?');
    $st->execute([(int)$_GET['m']]);
    $mem = $st->fetch() ?: null;
} elseif ($q !== '') {
    $mem = find_member($q);
    if ($mem) audit('staff', $sid, 'member_lookup', (int)$mem['id']);
}
$newEnq = (int)db()->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
$memberCount = (int)db()->query("SELECT COUNT(*) FROM members")->fetchColumn();

page_head('Till', '../', null, $s);
?>
<div class="tillhead">
  <h1 class="display display--sm">Till</h1>
  <p class="muted"><?= $memberCount ?> members<?php if ($newEnq): ?> · <a class="link" href="inbox.php"><?= $newEnq ?> new message<?= $newEnq > 1 ? 's' : '' ?></a><?php endif; ?></p>
</div>

<div class="tillgrid">
  <form method="get" class="mform card-form">
    <h2>Find a member</h2>
    <label>Member number or cellphone<input name="q" value="<?= e($q) ?>" placeholder="GL000123 or 082 123 4567" autofocus required></label>
    <button class="btn btn--gold" type="submit">Find</button>
    <?php if ($q !== '' && !$mem): ?><p class="notice notice--err">No member found for "<?= e($q) ?>".</p><?php endif; ?>
  </form>
  <form method="post" class="mform card-form">
    <h2>Accept a voucher</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="use_voucher"><input type="hidden" name="member_id" value="<?= $mem ? (int)$mem['id'] : 0 ?>">
    <label>Voucher code<input name="code" required autocomplete="off" style="text-transform:uppercase"></label>
    <label>Slip number it's used on<input name="reference" required autocomplete="off"></label>
    <button class="btn btn--line" type="submit">Check and accept voucher</button>
  </form>
</div>

<?php if ($mem):
    $mid = (int)$mem['id'];
    $bal = member_balance($mid);
    $st = db()->prepare("SELECT * FROM vouchers WHERE member_id = ? AND status = 'active' ORDER BY created_at");
    $st->execute([$mid]);
    $vouchers = $st->fetchAll();
    $st = db()->prepare('SELECT l.*, s.full_name AS staff_name, (SELECT COUNT(*) FROM points_ledger r WHERE r.reverses_id = l.id) AS reversed FROM points_ledger l LEFT JOIN staff s ON s.id = l.staff_id WHERE l.member_id = ? ORDER BY l.id DESC LIMIT 25');
    $st->execute([$mid]);
    $hist = $st->fetchAll();
    $age = (new DateTime($mem['birth_date']))->diff(new DateTime())->y;
?>
<section class="memberpanel">
  <div class="memberpanel__head">
    <div>
      <h2><?= e($mem['first_name'] . ' ' . $mem['last_name']) ?> <span class="tag"><?= e($mem['member_no']) ?></span> <?php if ($mem['status'] !== 'active'): ?><span class="tag tag--bad">Suspended</span><?php endif; ?></h2>
      <p class="muted"><?= e(format_phone($mem['phone'])) ?> · Age <?= $age ?> · Member since <?= e(date('j M Y', strtotime($mem['created_at']))) ?></p>
    </div>
    <div class="balance balance--sm"><p class="balance__label">Points</p><p class="balance__pts"><?= $bal ?></p></div>
  </div>

  <div class="tillgrid">
    <form method="post" class="mform card-form">
      <h3>Add points for a bill</h3>
      <?= csrf_field() ?><input type="hidden" name="action" value="earn"><input type="hidden" name="member_id" value="<?= $mid ?>">
      <div class="row2">
        <label>Bill amount (R)<input name="amount" inputmode="decimal" required autocomplete="off" placeholder="250.00"></label>
        <label>Slip number<input name="reference" required autocomplete="off"></label>
      </div>
      <p class="fine">1 point per full R<?= gr_rand_per_point() ?>. Each slip can earn points once.</p>
      <button class="btn btn--gold" type="submit" <?= $mem['status'] !== 'active' ? 'disabled' : '' ?>>Add points</button>
    </form>
    <div class="card-form">
      <h3>Active vouchers</h3>
      <?php if (!$vouchers): ?><p class="muted">None.</p><?php endif; ?>
      <ul class="vlist">
      <?php foreach ($vouchers as $vc): ?>
        <li><span class="vlist__code"><?= e($vc['code']) ?></span><span><?= money((int)$vc['value_cents']) ?></span>
        <?php if ($isAdmin): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_voucher"><input type="hidden" name="member_id" value="<?= $mid ?>"><input type="hidden" name="voucher_id" value="<?= (int)$vc['id'] ?>"><button class="linkbtn" type="submit" onclick="return confirm('Cancel this voucher and return the points?')">Cancel</button></form><?php endif; ?></li>
      <?php endforeach; ?>
      </ul>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="reset_pin"><input type="hidden" name="member_id" value="<?= $mid ?>"><button class="btn btn--sm btn--line" type="submit" onclick="return confirm('Check the member\'s ID first. Reset their PIN?')">Reset PIN</button></form>
      <?php if ($isAdmin): ?>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="member_id" value="<?= $mid ?>"><button class="btn btn--sm btn--line" type="submit" onclick="return confirm('Change this membership\'s status?')"><?= $mem['status'] === 'active' ? 'Suspend membership' : 'Reactivate membership' ?></button></form>
      <?php endif; ?>
    </div>
  </div>

  <h3 class="sub">Points history</h3>
  <div class="tablewrap"><table class="tbl">
    <thead><tr><th>Date</th><th>Type</th><th>Detail</th><th>By</th><th class="num">Points</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($hist as $h): ?>
      <tr>
        <td><?= e(date('j M Y H:i', strtotime($h['created_at']))) ?></td>
        <td><?= e(ucfirst($h['type'])) ?></td>
        <td><?= e($h['type'] === 'earn' ? money((int)$h['spend_cents']) . ', slip ' . $h['reference'] : trim(($h['note'] ?? '') . ' ' . ($h['type'] === 'redeem' ? $h['reference'] : ''))) ?></td>
        <td><?= e($h['staff_name'] ?? ($h['type'] === 'redeem' ? 'Member' : '')) ?></td>
        <td class="num <?= $h['points'] < 0 ? 'neg' : 'pos' ?>"><?= $h['points'] > 0 ? '+' : '' ?><?= (int)$h['points'] ?></td>
        <?php if ($isAdmin): ?><td><?php if ($h['type'] === 'earn' && !$h['reversed']): ?>
          <form method="post" class="inline rev"><?= csrf_field() ?><input type="hidden" name="action" value="reverse"><input type="hidden" name="member_id" value="<?= $mid ?>"><input type="hidden" name="ledger_id" value="<?= (int)$h['id'] ?>"><input name="reason" placeholder="Reason" required maxlength="255"><button class="linkbtn" type="submit">Reverse</button></form>
        <?php elseif ($h['reversed']): ?><span class="muted">Reversed</span><?php endif; ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$hist): ?><tr><td colspan="6" class="muted">No points yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
<?php page_foot();
