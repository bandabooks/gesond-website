<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
if (!gr_is_installed()) { require_installed(); }
gr_start_session();
$m = current_member();
if (!$m) {
    // Public landing for /members/
    page_head('Gesond Rewards');
    ?>
<div class="memgrid">
  <section>
    <h1 class="display display--md">Gesond Rewards</h1>
    <p class="lead">Earn points every time you spend at Gesond Liquor Restaurant and turn them into vouchers for food and drinks. Free to join for everyone 18 and over.</p>
    <ul class="perks">
      <li><b>1 point for every R<?= gr_rand_per_point() ?></b> you spend</li>
      <li><b><?= gr_voucher_points() ?> points = <?= money(gr_voucher_value_cents()) ?> voucher</b></li>
      <li><b>Your card on your phone</b>, shown at the till</li>
    </ul>
    <div class="btnrow"><a class="btn btn--gold" href="join.php">Join free</a><a class="btn btn--line" href="login.php">Log in</a></div>
  </section>
  <section class="howto">
    <h2>How it works</h2>
    <ol>
      <li>Join with your cellphone number and a 6-digit PIN.</li>
      <li>At the till, show your member card or tell staff your member number.</li>
      <li>Staff add points for your bill. They appear on your card straight away.</li>
      <li>Swap <?= gr_voucher_points() ?> points for a <?= money(gr_voucher_value_cents()) ?> voucher and show the voucher code at the till.</li>
    </ol>
  </section>
</div>
<?php
    page_foot();
    exit;
}
if ($m['must_change_pin']) redirect('pin.php');
$id = (int)$m['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'redeem') {
        $count = max(1, min(10, (int)($_POST['count'] ?? 1)));
        $need = gr_voucher_points() * $count;
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('SELECT id FROM members WHERE id = ? FOR UPDATE')->execute([$id]); // serialise redemptions per member
        $bal = member_balance($id);
        if ($bal < $need) {
            $pdo->rollBack();
            flash('You need ' . $need . ' points for that. Your balance is ' . $bal . '.', 'err');
        } else {
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                do {
                    $code = '';
                    for ($j = 0; $j < 8; $j++) $code .= 'ACDEFGHJKLMNPQRTUVWXY34679'[random_int(0, 25)];
                    $chk = $pdo->prepare('SELECT 1 FROM vouchers WHERE code = ?');
                    $chk->execute([$code]);
                } while ($chk->fetch());
                $pdo->prepare("INSERT INTO vouchers (code, member_id, points, value_cents, status, created_at) VALUES (?,?,?,?,'active',NOW())")
                    ->execute([$code, $id, gr_voucher_points(), gr_voucher_value_cents()]);
                $vid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO points_ledger (member_id, type, points, reference, note, voucher_id, created_at) VALUES (?,'redeem',?,?,?,?,NOW())")
                    ->execute([$id, -gr_voucher_points(), $code, 'Voucher ' . money(gr_voucher_value_cents()), $vid]);
                $codes[] = $code;
            }
            $pdo->commit();
            audit('member', $id, 'redeem', $id, ['vouchers' => $codes]);
            flash('Done. Show the voucher code at the till to use it.');
        }
        redirect('index.php');
    }
    if ($action === 'marketing') {
        $on = !empty($_POST['marketing']) ? 1 : 0;
        db()->prepare('UPDATE members SET marketing_optin = ? WHERE id = ?')->execute([$on, $id]);
        audit('member', $id, $on ? 'marketing_on' : 'marketing_off', $id);
        flash($on ? 'You\'ll get Gesond news and specials.' : 'You won\'t get Gesond news and specials.');
        redirect('index.php');
    }
}

$bal = member_balance($id);
$st = db()->prepare("SELECT code, value_cents, created_at FROM vouchers WHERE member_id = ? AND status = 'active' ORDER BY created_at DESC");
$st->execute([$id]);
$vouchers = $st->fetchAll();
$st = db()->prepare('SELECT type, points, spend_cents, reference, note, created_at FROM points_ledger WHERE member_id = ? ORDER BY id DESC LIMIT 30');
$st->execute([$id]);
$history = $st->fetchAll();
$canRedeem = intdiv($bal, gr_voucher_points());
$toNext = $bal < 0 ? gr_voucher_points() - $bal : gr_voucher_points() - ($bal % gr_voucher_points());

page_head('My card', '', $m);
?>
<h1 class="display display--md">Hi <?= e($m['first_name']) ?></h1>
<div class="memgrid">
  <section class="mcard" aria-label="Your Gesond Rewards card">
    <img class="mcard__logo" src="/assets/brand/gesond-logo-600.webp" alt="Gesond Liquor Restaurant" width="200" height="67">
    <p class="mcard__label">Gesond Rewards member</p>
    <p class="mcard__name"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></p>
    <div class="mcard__qr" id="qr" data-code="<?= e($m['member_no']) ?>" role="img" aria-label="QR code for member number <?= e($m['member_no']) ?>"></div>
    <p class="mcard__no"><?= e($m['member_no']) ?></p>
    <p class="mcard__hint">Show this card at the till to earn points.</p>
  </section>
  <section>
    <div class="balance">
      <p class="balance__label">Your points</p>
      <p class="balance__pts"><?= number_format($bal, 0, '.', ' ') ?></p>
      <p class="balance__note"><?= $canRedeem > 0
          ? 'Enough for ' . $canRedeem . ' voucher' . ($canRedeem > 1 ? 's' : '') . ' of ' . money(gr_voucher_value_cents()) . '.'
          : $toNext . ' more points to your first ' . money(gr_voucher_value_cents()) . ' voucher.' ?></p>
      <?php if ($canRedeem > 0): ?>
      <form method="post" class="redeem">
        <?= csrf_field() ?><input type="hidden" name="action" value="redeem">
        <label>Vouchers
          <select name="count"><?php for ($i = 1; $i <= min($canRedeem, 10); $i++): ?><option value="<?= $i ?>"><?= $i ?> × <?= money(gr_voucher_value_cents()) ?> (<?= $i * gr_voucher_points() ?> points)</option><?php endfor; ?></select>
        </label>
        <button class="btn btn--gold" type="submit">Get voucher</button>
      </form>
      <?php endif; ?>
    </div>
    <?php if ($vouchers): ?>
    <h2 class="sub">Your vouchers</h2>
    <ul class="vlist">
      <?php foreach ($vouchers as $vc): ?>
      <li><span class="vlist__code"><?= e($vc['code']) ?></span><span><?= money((int)$vc['value_cents']) ?> voucher</span><span class="fine">Show this code at the till</span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<h2 class="sub">Points history</h2>
<?php if (!$history): ?>
<p class="muted">No points yet. Show your card at the till next time you're at Gesond.</p>
<?php else: ?>
<div class="tablewrap"><table class="tbl">
  <thead><tr><th>Date</th><th>What</th><th class="num">Points</th></tr></thead>
  <tbody>
  <?php foreach ($history as $h):
      $what = $h['type'] === 'earn' ? 'Spent ' . money((int)$h['spend_cents']) . ($h['reference'] ? ' (slip ' . $h['reference'] . ')' : '')
          : ($h['type'] === 'redeem' ? ($h['note'] ?: 'Voucher') . ' ' . $h['reference'] : 'Correction' . ($h['note'] ? ': ' . $h['note'] : '')); ?>
    <tr><td><?= e(date('j M Y', strtotime($h['created_at']))) ?></td><td><?= e($what) ?></td><td class="num <?= $h['points'] < 0 ? 'neg' : 'pos' ?>"><?= $h['points'] > 0 ? '+' : '' ?><?= (int)$h['points'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<h2 class="sub">Settings</h2>
<div class="settings">
  <form method="post" class="inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="marketing">
    <label class="check"><input type="checkbox" name="marketing" value="1" <?= $m['marketing_optin'] ? 'checked' : '' ?> onchange="this.form.submit()"><span>Send me Gesond event news and specials</span></label>
    <noscript><button class="btn btn--sm btn--line" type="submit">Save</button></noscript>
  </form>
  <p><a class="link" href="pin.php">Change my PIN</a></p>
  <p class="fine">Registered number: <?= e(format_phone($m['phone'])) ?>. To change your number or close your membership, email <a href="mailto:admin@gesondlifestyle.co.za">admin@gesondlifestyle.co.za</a>.</p>
</div>
<script src="assets/qrcode.js"></script>
<script>
(function () {
  var el = document.getElementById('qr');
  if (!el || typeof qrcode !== 'function') return;
  var q = qrcode(0, 'M'); q.addData(el.getAttribute('data-code')); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
})();
</script>
<?php page_foot();
