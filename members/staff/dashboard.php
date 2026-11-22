<?php
/* Management dashboard: everything that needs tracking, at a glance. Admins only. */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/ai.php';
require_installed();
$s = require_staff(true);
$pdo = db();

$days = (int)($_GET['p'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;
$from = (new DateTime('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
$prevFrom = (new DateTime($from))->modify("-$days days")->format('Y-m-d');

function one(string $sql, array $p = []) { $st = db()->prepare($sql); $st->execute($p); return $st->fetchColumn(); }
function all(string $sql, array $p = []): array { $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll(); }
function rands(int $cents): string { return 'R' . number_format($cents / 100, 0, '.', ' '); }
function delta(float $now, float $before): string {
    if ($before <= 0) return $now > 0 ? '<span class="kpi__delta up">new</span>' : '';
    $pct = round(($now - $before) / $before * 100);
    return '<span class="kpi__delta ' . ($pct >= 0 ? 'up' : 'down') . '">' . ($pct >= 0 ? '+' : '') . $pct . '% vs previous ' . $GLOBALS['days'] . ' days</span>';
}

/* ---- headline figures ---- */
$membersTotal = (int)one('SELECT COUNT(*) FROM members');
$membersNew = (int)one('SELECT COUNT(*) FROM members WHERE created_at >= ?', [$from]);
$membersPrev = (int)one('SELECT COUNT(*) FROM members WHERE created_at >= ? AND created_at < ?', [$prevFrom, $from]);
$activeMembers = (int)one("SELECT COUNT(DISTINCT member_id) FROM points_ledger WHERE type='earn' AND created_at >= ?", [$from]);
$spend = (int)one("SELECT COALESCE(SUM(spend_cents),0) FROM points_ledger WHERE type='earn' AND created_at >= ?", [$from]);
$spendPrev = (int)one("SELECT COALESCE(SUM(spend_cents),0) FROM points_ledger WHERE type='earn' AND created_at >= ? AND created_at < ?", [$prevFrom, $from]);
$bills = (int)one("SELECT COUNT(*) FROM points_ledger WHERE type='earn' AND created_at >= ?", [$from]);
$ptsIssued = (int)one("SELECT COALESCE(SUM(points),0) FROM points_ledger WHERE type='earn' AND created_at >= ?", [$from]);
$ptsRedeemed = -(int)one("SELECT COALESCE(SUM(points),0) FROM points_ledger WHERE type='redeem' AND created_at >= ?", [$from]);
$ptsOutstanding = max(0, (int)one('SELECT COALESCE(SUM(b),0) FROM (SELECT GREATEST(SUM(points),0) b FROM points_ledger GROUP BY member_id) t'));
$liabilityCents = intdiv($ptsOutstanding * gr_voucher_value_cents(), gr_voucher_points());
$vActive = all("SELECT COUNT(*) n, COALESCE(SUM(value_cents),0) v FROM vouchers WHERE status='active'")[0];
$vUsed = all("SELECT COUNT(*) n, COALESCE(SUM(value_cents),0) v FROM vouchers WHERE status='used' AND used_at >= ?", [$from])[0];
$openMsgs = (int)one("SELECT COUNT(*) FROM enquiries WHERE status <> 'closed'");
$overdue = (int)one("SELECT COUNT(*) FROM enquiries WHERE status <> 'closed' AND ((kind='complaint' AND created_at < NOW() - INTERVAL 1 DAY) OR created_at < NOW() - INTERVAL 2 DAY)");
$reversals = all("SELECT COUNT(*) n, COALESCE(-SUM(points),0) p FROM points_ledger WHERE type='reversal' AND points < 0 AND created_at >= ?", [$from])[0];
$fbFailed = (int)one("SELECT COUNT(*) FROM posts WHERE status='scheduled' AND fb_status='failed'");
$postsUpcoming = (int)one("SELECT COUNT(*) FROM posts WHERE status='scheduled' AND publish_at > NOW()");
$postsLive = (int)one("SELECT COUNT(*) FROM posts WHERE status='scheduled' AND publish_at <= NOW() AND publish_at >= ?", [$from]);
$fbPosted = (int)one("SELECT COUNT(*) FROM posts WHERE fb_status='posted' AND publish_at >= ?", [$from]);
$optIn = (int)one('SELECT COUNT(*) FROM members WHERE marketing_optin = 1');

/* ---- daily series ---- */
$series = [];
for ($d = new DateTime($from); $d <= new DateTime('today'); $d->modify('+1 day')) $series[$d->format('Y-m-d')] = ['m' => 0, 's' => 0];
foreach (all('SELECT DATE(created_at) d, COUNT(*) n FROM members WHERE created_at >= ? GROUP BY DATE(created_at)', [$from]) as $r) if (isset($series[$r['d']])) $series[$r['d']]['m'] = (int)$r['n'];
foreach (all("SELECT DATE(created_at) d, SUM(spend_cents) c FROM points_ledger WHERE type='earn' AND created_at >= ? GROUP BY DATE(created_at)", [$from]) as $r) if (isset($series[$r['d']])) $series[$r['d']]['s'] = (int)$r['c'];

/* ---- tables ---- */
$inbox = all("SELECT kind, SUM(status='new') new_n, SUM(status='in_progress') prog_n, SUM(status <> 'closed' AND created_at < NOW() - INTERVAL 2 DAY) late_n, SUM(status='closed' AND created_at >= ?) closed_n FROM enquiries GROUP BY kind", [$from]);
$inboxBy = []; foreach ($inbox as $r) $inboxBy[$r['kind']] = $r;
$topMembers = all("SELECT m.member_no, m.first_name, m.last_name, COUNT(*) visits, SUM(l.spend_cents) spend FROM points_ledger l JOIN members m ON m.id = l.member_id WHERE l.type='earn' AND l.created_at >= ? GROUP BY l.member_id ORDER BY spend DESC LIMIT 10", [$from]);
$staffAct = all("SELECT s.full_name, s.role, s.last_login_at,
    (SELECT COUNT(*) FROM points_ledger l WHERE l.staff_id = s.id AND l.type='earn' AND l.created_at >= ?) bills,
    (SELECT COALESCE(SUM(spend_cents),0) FROM points_ledger l WHERE l.staff_id = s.id AND l.type='earn' AND l.created_at >= ?) spend,
    (SELECT COUNT(*) FROM vouchers v WHERE v.used_by_staff = s.id AND v.used_at >= ?) vouchers,
    (SELECT COUNT(*) FROM points_ledger l WHERE l.staff_id = s.id AND l.type='reversal' AND l.created_at >= ?) reversals,
    (SELECT COUNT(*) FROM audit_log a WHERE a.actor_type='staff' AND a.actor_id = s.id AND a.action='enquiry_update' AND a.created_at >= ?) inbox
    FROM staff s WHERE s.active = 1 ORDER BY spend DESC, s.full_name", [$from, $from, $from, $from, $from]);
$feed = all("SELECT a.*, s.full_name staff_name, m.member_no FROM audit_log a LEFT JOIN staff s ON a.actor_type='staff' AND s.id = a.actor_id LEFT JOIN members m ON m.id = a.member_id
    WHERE a.action NOT IN ('login','staff_login','staff_logout','member_lookup') ORDER BY a.id DESC LIMIT 20");

$labels = ['join' => 'New member joined', 'earn' => 'Points added', 'redeem' => 'Voucher created', 'voucher_used' => 'Voucher accepted', 'voucher_cancelled' => 'Voucher cancelled',
    'reversal' => 'Points reversed', 'pin_reset' => 'PIN reset', 'pin_changed' => 'Member changed PIN', 'member_suspended' => 'Member suspended', 'member_active' => 'Member reactivated',
    'enquiry_question' => 'New question', 'enquiry_booking' => 'New booking request', 'enquiry_complaint' => 'New complaint', 'enquiry_idea' => 'New idea', 'enquiry_update' => 'Message updated',
    'post_created' => 'Post saved', 'post_cancelled' => 'Post removed', 'fb_posted' => 'Posted to Facebook', 'fb_failed' => 'Facebook post failed', 'ai_draft' => 'AI draft written',
    'staff_added' => 'Staff log-in added', 'staff_toggled' => 'Staff log-in switched', 'staff_password_set' => 'Staff password set', 'fb_settings_saved' => 'Facebook settings saved',
    'ai_connected' => 'AI connected', 'ai_disconnected' => 'AI switched off', 'fb_disconnected' => 'Facebook disconnected', 'marketing_on' => 'Member opted in to news', 'marketing_off' => 'Member opted out of news'];

/* ---- attention list ---- */
$alerts = [];
if ($overdue) $alerts[] = ['crit', $overdue . ' message' . ($overdue > 1 ? 's' : '') . ' overdue (complaints over 1 day, others over 2 days)', 'inbox.php'];
if ($fbFailed) $alerts[] = ['crit', $fbFailed . ' post' . ($fbFailed > 1 ? 's' : '') . ' failed to reach Facebook', 'posts.php'];
if ($reversals['n'] > 0) $alerts[] = ['warn', $reversals['n'] . ' points reversal' . ($reversals['n'] > 1 ? 's' : '') . ' in this period (' . (int)$reversals['p'] . ' points). Check the reason for each', '#feed'];
if (setting('fb_page_id') && setting('fb_page_token') && setting('fb_health')) $alerts[] = ['crit', 'Facebook connection stopped working. Get a new Page token in Settings', 'settings.php'];
if (!(setting('fb_page_id') && setting('fb_page_token'))) $alerts[] = ['info', 'Facebook Page not connected', 'settings.php'];
if (!setting('notify_email')) $alerts[] = ['info', 'Message alerts by email are off', 'settings.php'];
if (!ai_provider()) $alerts[] = ['info', 'AI writing not connected', 'settings.php'];
if ($postsUpcoming === 0) $alerts[] = ['info', 'No posts scheduled ahead', 'posts.php'];

/* ---- small bar chart, server-drawn SVG, one series, hover via <title> ---- */
function bars(array $series, string $key, string $title, callable $fmt, int $floor = 2): string {
    $w = 640; $h = 170; $l = 44; $r = 8; $t = 12; $b = 26;
    $vals = array_map(fn($x) => $x[$key], $series);
    if (max($vals) <= 0) return '<p class="chart__empty">Nothing recorded in this period yet.</p>';
    $max = max($floor, max($vals));
    $step = pow(10, floor(log10($max))); $nice = ceil($max / $step) * $step; if ($nice / $step > 5 && $step > 1) $nice = ceil($max / ($step * 2)) * $step * 2;
    $n = count($series); $bw = ($w - $l - $r) / $n; $gap = $n > 40 ? 1 : 2;
    $o = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '">';
    foreach ([0, 0.5, 1] as $f) {
        if ($f === 0.5 && fmod($nice, 2) != 0) continue;   // no fractional tick labels
        $y = $t + ($h - $t - $b) * (1 - $f);
        $o .= '<line x1="' . $l . '" x2="' . ($w - $r) . '" y1="' . $y . '" y2="' . $y . '" class="chart__grid"/><text x="' . ($l - 6) . '" y="' . ($y + 4) . '" text-anchor="end" class="chart__tick">' . e($fmt((int)round($nice * $f))) . '</text>';
    }
    $i = 0; $every = max(1, (int)ceil($n / 6));
    foreach ($series as $date => $x) {
        $v = $x[$key]; $bh = ($h - $t - $b) * $v / $nice; $x0 = $l + $i * $bw + $gap / 2;
        $label = date('D j M', strtotime($date)) . ': ' . $fmt($v);
        $o .= '<g class="chart__bar"><rect x="' . round($x0, 1) . '" y="' . $t . '" width="' . round($bw - $gap, 1) . '" height="' . ($h - $t - $b) . '" class="chart__hit"/>';
        if ($v > 0) $o .= '<rect x="' . round($x0, 1) . '" y="' . round($t + ($h - $t - $b) - $bh, 1) . '" width="' . round(max(1, $bw - $gap), 1) . '" height="' . round(max(1, $bh), 1) . '" rx="' . ($bw > 10 ? 3 : 1) . '" class="chart__mark"/>';
        $o .= '<title>' . e($label) . '</title></g>';
        if ($i % $every === 0) $o .= '<text x="' . round($x0 + ($bw - $gap) / 2, 1) . '" y="' . ($h - 8) . '" text-anchor="middle" class="chart__tick">' . date('j M', strtotime($date)) . '</text>';
        $i++;
    }
    return $o . '</svg>';
}

page_head('Dashboard', '../', null, $s);
?>
<div class="dash__head">
  <div>
    <h1 class="display display--sm">Dashboard</h1>
    <p class="muted">Gesond Rewards, guest messages and posts. Last <?= $days ?> days (<?= e(date('j M', strtotime($from))) ?> to <?= e(date('j M Y')) ?>). Updated <?= e(date('H:i')) ?>. <a class="link" href="export.php">Download records for the bookkeeper</a></p>
  </div>
  <nav class="filters" aria-label="Period">
    <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $k => $lab): ?><a class="<?= $k === $days ? 'on' : '' ?>" href="?p=<?= $k ?>"><?= $lab ?></a><?php endforeach; ?>
  </nav>
</div>

<?php if ($alerts): ?>
<section class="alerts" aria-label="Needs attention">
  <h2 class="sub">Needs attention</h2>
  <ul>
    <?php foreach ($alerts as $a): ?><li class="alert alert--<?= $a[0] ?>"><span class="alert__tag"><?= $a[0] === 'crit' ? 'Act now' : ($a[0] === 'warn' ? 'Check' : 'Set up') ?></span><a href="<?= e($a[2]) ?>"><?= e($a[1]) ?></a></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="kpis" aria-label="Key figures">
  <div class="kpi"><p class="kpi__label">Members</p><p class="kpi__value"><?= number_format($membersTotal, 0, '.', ' ') ?></p><p class="kpi__sub">+<?= $membersNew ?> joined <?= delta($membersNew, $membersPrev) ?></p></div>
  <div class="kpi"><p class="kpi__label">Spend recorded on Rewards</p><p class="kpi__value"><?= rands($spend) ?></p><p class="kpi__sub"><?= $bills ?> bills, <?= $activeMembers ?> members <?= delta($spend, $spendPrev) ?></p></div>
  <div class="kpi"><p class="kpi__label">Points issued / redeemed</p><p class="kpi__value"><?= number_format($ptsIssued, 0, '.', ' ') ?> <span class="kpi__of">/ <?= number_format($ptsRedeemed, 0, '.', ' ') ?></span></p><p class="kpi__sub"><?= $vUsed['n'] ?> voucher<?= $vUsed['n'] == 1 ? '' : 's' ?> used, <?= rands((int)$vUsed['v']) ?> discount given</p></div>
  <div class="kpi"><p class="kpi__label">Points outstanding (liability)</p><p class="kpi__value"><?= rands($liabilityCents) ?></p><p class="kpi__sub"><?= number_format($ptsOutstanding, 0, '.', ' ') ?> points, plus <?= $vActive['n'] ?> unused voucher<?= $vActive['n'] == 1 ? '' : 's' ?> (<?= rands((int)$vActive['v']) ?>)</p></div>
  <div class="kpi"><p class="kpi__label">Open guest messages</p><p class="kpi__value"><?= $openMsgs ?></p><p class="kpi__sub <?= $overdue ? 'bad' : '' ?>"><?= $overdue ? $overdue . ' overdue' : 'None overdue' ?></p></div>
  <div class="kpi"><p class="kpi__label">Posts</p><p class="kpi__value"><?= $postsLive ?> <span class="kpi__of">live</span></p><p class="kpi__sub"><?= $postsUpcoming ?> scheduled, <?= $fbPosted ?> on Facebook<?= $fbFailed ? ', ' . $fbFailed . ' failed' : '' ?></p></div>
</section>

<div class="dashgrid">
  <section class="panel">
    <h2 class="panel__title">New members per day</h2>
    <?= bars($series, 'm', 'New members per day', fn($v) => (string)$v) ?>
    <p class="fine"><?= $optIn ?> of <?= $membersTotal ?> members agreed to receive event news.</p>
  </section>
  <section class="panel">
    <h2 class="panel__title">Spend recorded on Rewards per day</h2>
    <?= bars($series, 's', 'Spend recorded on Rewards per day', fn($v) => rands($v), 10000) ?>
    <p class="fine">Only bills where a member's points were added. This is not total sales; use the POS for those.</p>
  </section>
</div>

<div class="dashgrid">
  <section class="panel">
    <h2 class="panel__title">Guest messages</h2>
    <div class="tablewrap"><table class="tbl">
      <thead><tr><th>Type</th><th class="num">New</th><th class="num">In progress</th><th class="num">Overdue</th><th class="num">Closed (period)</th></tr></thead>
      <tbody>
      <?php foreach (['complaint' => 'Complaints', 'booking' => 'Bookings', 'question' => 'Questions', 'idea' => 'Ideas'] as $k => $lab): $r = $inboxBy[$k] ?? ['new_n' => 0, 'prog_n' => 0, 'late_n' => 0, 'closed_n' => 0]; ?>
        <tr><td><a class="link" href="inbox.php?k=<?= $k ?>"><?= $lab ?></a></td><td class="num"><?= (int)$r['new_n'] ?></td><td class="num"><?= (int)$r['prog_n'] ?></td><td class="num <?= (int)$r['late_n'] ? 'neg' : '' ?>"><?= (int)$r['late_n'] ?></td><td class="num"><?= (int)$r['closed_n'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="panel">
    <h2 class="panel__title">Top members (period)</h2>
    <div class="tablewrap"><table class="tbl">
      <thead><tr><th>Member</th><th class="num">Bills</th><th class="num">Spend</th></tr></thead>
      <tbody>
      <?php foreach ($topMembers as $t): ?><tr><td><?= e($t['first_name'] . ' ' . $t['last_name']) ?> <span class="muted"><?= e($t['member_no']) ?></span></td><td class="num"><?= (int)$t['visits'] ?></td><td class="num"><?= rands((int)$t['spend']) ?></td></tr><?php endforeach; ?>
      <?php if (!$topMembers): ?><tr><td colspan="3" class="muted">No member bills in this period yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</div>

<section class="panel">
  <h2 class="panel__title">Staff activity (period)</h2>
  <div class="tablewrap"><table class="tbl">
    <thead><tr><th>Staff member</th><th>Role</th><th class="num">Bills captured</th><th class="num">Spend captured</th><th class="num">Vouchers accepted</th><th class="num">Reversals</th><th class="num">Messages handled</th><th>Last log-in</th></tr></thead>
    <tbody>
    <?php foreach ($staffAct as $a): ?><tr><td><?= e($a['full_name']) ?></td><td><?= e($a['role']) ?></td><td class="num"><?= (int)$a['bills'] ?></td><td class="num"><?= rands((int)$a['spend']) ?></td><td class="num"><?= (int)$a['vouchers'] ?></td><td class="num <?= (int)$a['reversals'] ? 'neg' : '' ?>"><?= (int)$a['reversals'] ?></td><td class="num"><?= (int)$a['inbox'] ?></td><td><?= $a['last_login_at'] ? e(date('j M H:i', strtotime($a['last_login_at']))) : '<span class="muted">Never</span>' ?></td></tr><?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="panel" id="feed">
  <h2 class="panel__title">Latest activity</h2>
  <ul class="feed">
  <?php foreach ($feed as $f): $d = json_decode((string)$f['details'], true) ?: []; ?>
    <li><span class="feed__time"><?= e(date('j M H:i', strtotime($f['created_at']))) ?></span>
      <span class="feed__what"><?= e($labels[$f['action']] ?? str_replace('_', ' ', $f['action'])) ?><?php
        if (isset($d['points'])) echo ': ' . (int)$d['points'] . ' pts';
        if (isset($d['spend_cents'])) echo ' on ' . e(money((int)$d['spend_cents']));
        if (isset($d['ref'])) echo ', ' . e((string)$d['ref']);
        if (isset($d['reason'])) echo ' (' . e((string)$d['reason']) . ')';
        if (isset($d['error'])) echo ': ' . e(mb_strimwidth((string)$d['error'], 0, 60, '...'));
      ?></span>
      <span class="feed__who muted"><?= e($f['staff_name'] ?? ($f['actor_type'] === 'member' ? 'Member' : 'System')) ?><?= $f['member_no'] ? ' · ' . e($f['member_no']) : '' ?></span></li>
  <?php endforeach; ?>
  <?php if (!$feed): ?><li class="muted">No activity yet.</li><?php endif; ?>
  </ul>
</section>
<?php page_foot();
