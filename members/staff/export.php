<?php
/* Admin: download points and voucher records as CSV for the bookkeeper (month-end, VAT, POS reconciliation). */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_installed();
$s = require_staff(true);
$sid = (int)$s['id'];

$from = (string)($_GET['from'] ?? date('Y-m-01', strtotime('first day of last month')));
$to = (string)($_GET['to'] ?? date('Y-m-t', strtotime('last day of last month')));
$valid = fn($d) => ($x = DateTime::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
$err = null;
if (!$valid($from) || !$valid($to)) { $err = 'Choose both dates.'; $from = date('Y-m-01'); $to = date('Y-m-d'); }
elseif ($from > $to) $err = 'The start date is after the end date.';

/* Spreadsheet-safe cell: stop text being run as a formula when the file is opened. */
function cell($v): string {
    $v = (string)($v ?? '');
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v)) $v = "'" . $v;
    return $v;
}
function send_csv(string $name, array $head, iterable $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                        // so Excel reads the rand sign and names correctly
    fputcsv($out, $head);
    foreach ($rows as $r) fputcsv($out, array_map('cell', $r));
    fclose($out);
    exit;
}

$dl = (string)($_GET['dl'] ?? '');
if ($dl !== '' && !$err) {
    $a = $from . ' 00:00:00'; $b = $to . ' 23:59:59';
    $tag = $from . '_to_' . $to;
    audit('staff', $sid, 'export_' . $dl, null, ['from' => $from, 'to' => $to]);
    $rv = gr_voucher_value_cents(); $vp = gr_voucher_points();

    if ($dl === 'ledger') {
        $st = db()->prepare("SELECT l.id, l.created_at, m.member_no, l.type, l.points, l.spend_cents, l.reference, v.code, l.reverses_id, l.note, st.full_name
            FROM points_ledger l JOIN members m ON m.id = l.member_id LEFT JOIN vouchers v ON v.id = l.voucher_id LEFT JOIN staff st ON st.id = l.staff_id
            WHERE l.created_at BETWEEN ? AND ? ORDER BY l.id");
        $st->execute([$a, $b]);
        $rows = (function () use ($st) { foreach ($st as $r) yield [$r['id'], $r['created_at'], $r['member_no'], $r['type'], $r['points'],
            $r['spend_cents'] !== null ? number_format($r['spend_cents'] / 100, 2, '.', '') : '', $r['reference'], $r['code'], $r['reverses_id'], $r['note'], $r['full_name'] ?? ($r['type'] === 'redeem' ? 'Member' : '')]; })();
        send_csv('gesond_points_ledger_' . $tag . '.csv', ['Entry no', 'Date and time', 'Member no', 'Type', 'Points', 'Bill amount (R)', 'Slip / reference', 'Voucher code', 'Reverses entry no', 'Note', 'Captured by'], $rows);
    }
    if ($dl === 'vouchers') {
        // Every voucher issued, used or cancelled in the period
        $st = db()->prepare("SELECT v.*, m.member_no, st.full_name,
              (SELECT MIN(r.created_at) FROM points_ledger r WHERE r.voucher_id = v.id AND r.type = 'reversal') AS cancelled_at
            FROM vouchers v JOIN members m ON m.id = v.member_id LEFT JOIN staff st ON st.id = v.used_by_staff
            HAVING (created_at BETWEEN ? AND ?) OR (used_at BETWEEN ? AND ?) OR (cancelled_at BETWEEN ? AND ?)
            ORDER BY v.id");
        $st->execute([$a, $b, $a, $b, $a, $b]);
        $rows = (function () use ($st) { foreach ($st as $r) yield [$r['code'], $r['member_no'], $r['created_at'], $r['points'], number_format($r['value_cents'] / 100, 2, '.', ''),
            $r['status'], $r['used_at'], $r['used_reference'], $r['full_name'], $r['cancelled_at']]; })();
        send_csv('gesond_vouchers_' . $tag . '.csv', ['Voucher code', 'Member no', 'Issued', 'Points', 'Value (R)', 'Status now', 'Used on', 'Used on slip', 'Accepted by', 'Cancelled on'], $rows);
    }
    if ($dl === 'summary') {
        $one = function (string $sql, array $p) { $q = db()->prepare($sql); $q->execute($p); return $q->fetch(PDO::FETCH_NUM); };
        [$earnPts, $earnSpend, $bills] = $one("SELECT COALESCE(SUM(points),0), COALESCE(SUM(spend_cents),0), COUNT(*) FROM points_ledger WHERE type='earn' AND created_at BETWEEN ? AND ?", [$a, $b]);
        [$revPts] = $one("SELECT COALESCE(SUM(points),0) FROM points_ledger WHERE type='reversal' AND created_at BETWEEN ? AND ?", [$a, $b]);
        [$vIss, $vIssVal] = $one("SELECT COUNT(*), COALESCE(SUM(value_cents),0) FROM vouchers WHERE created_at BETWEEN ? AND ?", [$a, $b]);
        [$vUsed, $vUsedVal] = $one("SELECT COUNT(*), COALESCE(SUM(value_cents),0) FROM vouchers WHERE used_at BETWEEN ? AND ?", [$a, $b]);
        [$vCan, $vCanVal] = $one("SELECT COUNT(*), COALESCE(SUM(v.value_cents),0) FROM vouchers v WHERE v.status='cancelled' AND (SELECT MIN(r.created_at) FROM points_ledger r WHERE r.voucher_id=v.id AND r.type='reversal') BETWEEN ? AND ?", [$a, $b]);
        // Balances at the end date
        [$ptsOut] = $one("SELECT COALESCE(SUM(points),0) FROM points_ledger WHERE created_at <= ?", [$b]);
        [$vOpen, $vOpenVal] = $one("SELECT COUNT(*), COALESCE(SUM(v.value_cents),0) FROM vouchers v WHERE v.created_at <= ?
            AND (v.used_at IS NULL OR v.used_at > ?)
            AND NOT EXISTS (SELECT 1 FROM points_ledger r WHERE r.voucher_id = v.id AND r.type='reversal' AND r.created_at <= ?)", [$b, $b, $b]);
        $r = fn(int $c) => number_format($c / 100, 2, '.', '');
        $rows = [
            ['Period', $from . ' to ' . $to, ''],
            ['Bills with points added', $bills, ''],
            ['Spend recorded on those bills (R)', $r((int)$earnSpend), 'Not total sales; use the POS for sales'],
            ['Points earned', $earnPts, ''],
            ['Points reversed or returned', $revPts, 'Negative = corrections; positive = points returned from cancelled vouchers'],
            ['Vouchers issued', $vIss, $r((int)$vIssVal)],
            ['Vouchers used (discount given, R)', $vUsed, $r((int)$vUsedVal)],
            ['Vouchers cancelled', $vCan, $r((int)$vCanVal)],
            ['Points outstanding at ' . $to, $ptsOut, 'Value R' . $r(intdiv((int)$ptsOut * $rv, $vp)) . ' at ' . $vp . ' points = R' . $r($rv)],
            ['Unused vouchers at ' . $to, $vOpen, $r((int)$vOpenVal)],
            ['Total potential obligation at ' . $to . ' (R)', $r(intdiv((int)$ptsOut * $rv, $vp) + (int)$vOpenVal), 'Before any expected non-redemption; for the bookkeeper to assess'],
            ['Prepared', date('Y-m-d H:i'), 'by ' . $s['full_name'] . ' from Gesond Rewards'],
        ];
        send_csv('gesond_rewards_summary_' . $tag . '.csv', ['Item', 'Value', 'Note'], $rows);
    }
    $err = 'Choose a download.';
}

page_head('Download records', '../', null, $s);
?>
<h1 class="display display--sm">Download records</h1>
<p class="lead">Points and voucher records as spreadsheet files (CSV, opens in Excel) for month-end, the VAT return and checking vouchers against POS discounts. Members appear by member number only.</p>
<?php if ($err): ?><p class="notice notice--err"><?= e($err) ?></p><?php endif; ?>
<form method="get" class="mform card-form postform">
  <div class="row2">
    <label>From<input type="date" name="from" value="<?= e($from) ?>" required></label>
    <label>To<input type="date" name="to" value="<?= e($to) ?>" required></label>
  </div>
  <p class="fine">Defaults to last month. Every download is recorded in the activity log.</p>
  <div class="btnrow">
    <button class="btn btn--gold" name="dl" value="summary" type="submit">Month-end summary</button>
    <button class="btn btn--line" name="dl" value="ledger" type="submit">Points ledger</button>
    <button class="btn btn--line" name="dl" value="vouchers" type="submit">Vouchers</button>
  </div>
</form>
<section class="card-form postform">
  <h2>What each file is for</h2>
  <ul class="steps">
    <li><b>Month-end summary:</b> points earned, reversed and outstanding, vouchers issued, used and unused, and the total potential obligation at the end date. Give this to the bookkeeper.</li>
    <li><b>Points ledger:</b> every points entry in the period with slip number and who captured it. Use it to review reversals.</li>
    <li><b>Vouchers:</b> every voucher issued, used or cancelled in the period. Match "Used on slip" to the R<?= number_format(gr_voucher_value_cents() / 100, 0) ?> discounts on the POS.</li>
  </ul>
</section>
<?php page_foot();
