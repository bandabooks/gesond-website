<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_installed();
$s = require_staff();
$sid = (int)$s['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $note = trim((string)($_POST['staff_note'] ?? ''));
    if (in_array($status, ['new', 'in_progress', 'closed'], true)) {
        db()->prepare('UPDATE enquiries SET status = ?, staff_note = ?, handled_by = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$status, mb_substr($note, 0, 2000) ?: null, $sid, $id]);
        audit('staff', $sid, 'enquiry_update', null, ['enquiry' => $id, 'status' => $status]);
        flash('Saved.');
    }
    redirect('inbox.php?' . http_build_query(['f' => $_POST['f'] ?? 'open', 'k' => $_POST['k'] ?? '']) . '#e' . $id);
}

$f = (string)($_GET['f'] ?? 'open');
$k = (string)($_GET['k'] ?? '');
$where = $f === 'closed' ? "e.status = 'closed'" : ($f === 'all' ? '1=1' : "e.status <> 'closed'");
$params = [];
if (in_array($k, ['question', 'booking', 'complaint', 'idea'], true)) { $where .= ' AND e.kind = ?'; $params[] = $k; }
$st = db()->prepare("SELECT e.*, s.full_name AS handler, m.member_no FROM enquiries e LEFT JOIN staff s ON s.id = e.handled_by LEFT JOIN members m ON m.id = e.member_id WHERE $where ORDER BY (e.status = 'new') DESC, e.created_at DESC LIMIT 100");
$st->execute($params);
$rows = $st->fetchAll();
$counts = db()->query("SELECT kind, SUM(status <> 'closed') AS open_n FROM enquiries GROUP BY kind")->fetchAll(PDO::FETCH_KEY_PAIR);
$labels = ['question' => 'Questions', 'booking' => 'Bookings', 'complaint' => 'Complaints', 'idea' => 'Ideas'];
$statusLabel = ['new' => 'New', 'in_progress' => 'In progress', 'closed' => 'Closed'];

page_head('Inbox', '../', null, $s);
?>
<div class="tillhead">
  <h1 class="display display--sm">Inbox</h1>
  <p class="muted">Messages from the website's Talk to us page. Reply by WhatsApp, SMS or email, then record what you did.</p>
</div>
<nav class="filters" aria-label="Filter messages">
  <a class="<?= $k === '' ? 'on' : '' ?>" href="?f=<?= e($f) ?>">All types</a>
  <?php foreach ($labels as $key => $lab): ?><a class="<?= $k === $key ? 'on' : '' ?>" href="?f=<?= e($f) ?>&k=<?= $key ?>"><?= $lab ?><?= !empty($counts[$key]) ? ' (' . (int)$counts[$key] . ')' : '' ?></a><?php endforeach; ?>
  <span class="filters__sep"></span>
  <a class="<?= $f === 'open' ? 'on' : '' ?>" href="?f=open&k=<?= e($k) ?>">Open</a>
  <a class="<?= $f === 'closed' ? 'on' : '' ?>" href="?f=closed&k=<?= e($k) ?>">Closed</a>
  <a class="<?= $f === 'all' ? 'on' : '' ?>" href="?f=all&k=<?= e($k) ?>">Everything</a>
</nav>
<?php if (!$rows): ?><p class="muted">Nothing here.</p><?php endif; ?>
<?php foreach ($rows as $r):
    $wa = $r['phone'] ? 'https://wa.me/' . $r['phone'] . '?text=' . rawurlencode('Hi ' . strtok($r['name'], ' ') . ', this is Gesond about your message ' . $r['ref'] . '. ') : null; ?>
<article class="enq enq--<?= e($r['status']) ?>" id="e<?= (int)$r['id'] ?>">
  <header class="enq__head">
    <span class="tag tag--<?= e($r['kind']) ?>"><?= e(ucfirst($r['kind'])) ?></span>
    <b><?= e($r['name']) ?></b><?php if ($r['member_no']): ?> <span class="tag"><?= e($r['member_no']) ?></span><?php endif; ?>
    <span class="muted"><?= e($r['ref']) ?> · <?= e(date('j M Y H:i', strtotime($r['created_at']))) ?></span>
    <span class="enq__status"><?= e($statusLabel[$r['status']]) ?><?= $r['handler'] ? ' · ' . e($r['handler']) : '' ?></span>
  </header>
  <p class="enq__msg"><?= nl2br(e($r['message'])) ?></p>
  <p class="enq__contact">
    <?php if ($r['phone']): ?><a class="btn btn--sm btn--gold" href="<?= e($wa) ?>" target="_blank" rel="noopener">Reply on WhatsApp</a> <a class="btn btn--sm btn--line" href="tel:+<?= e($r['phone']) ?>">Call <?= e(format_phone($r['phone'])) ?></a><?php endif; ?>
    <?php if ($r['email']): ?><a class="btn btn--sm btn--line" href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Gesond: your message ' . $r['ref']) ?>">Email <?= e($r['email']) ?></a><?php endif; ?>
  </p>
  <form method="post" class="enq__form">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="hidden" name="k" value="<?= e($k) ?>">
    <label>What was done<textarea name="staff_note" rows="2" maxlength="2000"><?= e($r['staff_note']) ?></textarea></label>
    <label>Status<select name="status"><?php foreach ($statusLabel as $sk => $sl): ?><option value="<?= $sk ?>" <?= $sk === $r['status'] ? 'selected' : '' ?>><?= $sl ?></option><?php endforeach; ?></select></label>
    <button class="btn btn--sm btn--dark" type="submit">Save</button>
  </form>
</article>
<?php endforeach; ?>
<?php page_foot();
