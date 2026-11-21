<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/ai.php';
require_installed();
$s = require_staff();
$sid = (int)$s['id'];
$fbReady = setting('fb_page_id') && setting('fb_page_token');
$aiOn = ai_provider() !== null;
$draft = ['title' => '', 'body' => '', 'notes' => '', 'tone' => 'hype'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'ai_draft') {
        $draft['notes'] = trim((string)($_POST['notes'] ?? ''));
        $draft['tone'] = in_array($_POST['tone'] ?? '', ['hype', 'friendly', 'formal'], true) ? $_POST['tone'] : 'hype';
        if (mb_strlen($draft['notes']) < 10) {
            flash('Give the AI some facts: event name, date, DJs, entry, specials.', 'err');
        } elseif (too_many_attempts('ai:' . $sid, 30, 60)) {
            flash('That\'s a lot of drafts this hour. Try again later.', 'err');
        } else {
            record_attempt('ai:' . $sid);
            try {
                $d = ai_draft_post(mb_substr($draft['notes'], 0, 1500), $draft['tone']);
                $draft['title'] = $d['title']; $draft['body'] = $d['body'];
                audit('staff', $sid, 'ai_draft', null, ['provider' => ai_provider()]);
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'err');
            }
        }
    }
    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $link = trim((string)($_POST['link_url'] ?? ''));
        $when = trim((string)($_POST['publish_at'] ?? ''));
        $toFb = !empty($_POST['to_facebook']) && $fbReady;
        $dt = $when === '' ? new DateTime() : DateTime::createFromFormat('Y-m-d\TH:i', $when);
        if ($title === '' || mb_strlen($title) > 120) { flash('Give the post a title (up to 120 characters).', 'err'); redirect('posts.php'); }
        if (mb_strlen($body) < 5 || mb_strlen($body) > 2000) { flash('Write the post text (up to 2000 characters).', 'err'); redirect('posts.php'); }
        if ($link !== '' && !preg_match('#^https://#i', $link) && !preg_match('#^/#', $link)) { flash('Links must start with https:// or /.', 'err'); redirect('posts.php'); }
        if (!$dt) { flash('Choose a valid date and time.', 'err'); redirect('posts.php'); }
        if ($link !== '' && $link[0] === '/') $link = GR_SITE . $link;
        db()->prepare("INSERT INTO posts (title, body, link_url, publish_at, status, to_facebook, fb_status, created_by, created_at) VALUES (?,?,?,?,'scheduled',?,?,?,NOW())")
            ->execute([$title, $body, $link ?: null, $dt->format('Y-m-d H:i:00'), $toFb ? 1 : 0, $toFb ? 'pending' : 'none', $sid]);
        $pid = (int)db()->lastInsertId();
        audit('staff', $sid, 'post_created', null, ['post' => $pid, 'facebook' => $toFb]);
        if ($toFb && $dt <= new DateTime()) publish_due_facebook_posts(1);
        flash($dt <= new DateTime() ? 'Published on the website' . ($toFb ? ' and sent to Facebook.' : '.') : 'Scheduled for ' . $dt->format('j M Y H:i') . '.');
        redirect('posts.php');
    }
    $pid = (int)($_POST['id'] ?? 0);
    if ($action === 'cancel') {
        db()->prepare("UPDATE posts SET status = 'cancelled', fb_status = IF(fb_status = 'pending', 'none', fb_status) WHERE id = ?")->execute([$pid]);
        audit('staff', $sid, 'post_cancelled', null, ['post' => $pid]);
        flash('Post removed from the website. A post already on Facebook stays there; delete it on Facebook if needed.');
        redirect('posts.php');
    }
    if ($action === 'retry_fb' && $fbReady) {
        db()->prepare("UPDATE posts SET fb_status = 'pending', fb_error = NULL WHERE id = ? AND fb_status = 'failed' AND status = 'scheduled'")->execute([$pid]);
        publish_due_facebook_posts(1);
        flash('Tried sending to Facebook again. Check the status below.');
        redirect('posts.php');
    }
}

$rows = db()->query("SELECT p.*, s.full_name FROM posts p LEFT JOIN staff s ON s.id = p.created_by ORDER BY p.publish_at DESC LIMIT 50")->fetchAll();
$fbLabel = ['none' => 'Website only', 'pending' => 'Facebook: waiting', 'sending' => 'Facebook: sending', 'posted' => 'Facebook: posted', 'failed' => 'Facebook: failed'];

page_head('Posts', '../', null, $s);
?>
<div class="tillhead">
  <h1 class="display display--sm">Posts</h1>
  <p class="muted">Posts appear on the website's Updates page at the time you choose<?= $fbReady ? ', and on the Gesond Facebook Page if you tick the box' : '' ?>.</p>
</div>
<?php if (!$aiOn && $s['role'] === 'admin'): ?><p class="notice notice--info">AI writing is off. <a class="link" href="settings.php">Connect Anthropic or OpenAI</a> to draft posts from short notes.</p><?php endif; ?>
<?php if (!$fbReady): ?><p class="notice notice--info">Facebook posting is not connected yet. <?= $s['role'] === 'admin' ? '<a class="link" href="settings.php">Connect the Facebook Page</a>.' : 'Ask an admin to connect it in Settings.' ?></p><?php endif; ?>
<?php if ($aiOn): ?>
<form method="post" class="mform card-form postform">
  <h2>Draft with AI</h2>
  <?= csrf_field() ?><input type="hidden" name="action" value="ai_draft">
  <label>Notes for the AI<textarea name="notes" rows="3" maxlength="1500" required placeholder="Pens Down Party, Sat 28 Nov. DJs: DJ Tumi, Mash Nick, Mr Faraz... MC Maghezi. Stance Crew Mokopane cars. Free entrance, cooler box R50."><?= e($draft['notes']) ?></textarea><small>Only these facts are used. Check the draft before saving.</small></label>
  <label>Style<select name="tone"><option value="hype" <?= $draft['tone'] === 'hype' ? 'selected' : '' ?>>Party hype</option><option value="friendly" <?= $draft['tone'] === 'friendly' ? 'selected' : '' ?>>Friendly</option><option value="formal" <?= $draft['tone'] === 'formal' ? 'selected' : '' ?>>Formal notice</option></select></label>
  <button class="btn btn--line" type="submit">Write draft</button>
</form>
<?php endif; ?>
<form method="post" class="mform card-form postform" id="post-form">
  <?php if ($draft['body'] !== ''): ?><p class="notice notice--info">AI draft below. Check every name, date and price before saving.</p><?php endif; ?>
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label>Title<input name="title" maxlength="120" required placeholder="Pens Down Party this Saturday" value="<?= e($draft['title']) ?>"></label>
  <label>Text<textarea name="body" rows="8" maxlength="2000" required placeholder="Who's playing, date, entry, cooler box..."><?= e($draft['body']) ?></textarea></label>
  <div class="row2">
    <label>Link (optional)<input name="link_url" placeholder="/events.html#pens-down-party-2026"></label>
    <label>Publish at<input type="datetime-local" name="publish_at"><small>Leave empty to publish now.</small></label>
  </div>
  <?php if ($fbReady): ?><label class="check"><input type="checkbox" name="to_facebook" value="1" checked><span>Also post to the Gesond Facebook Page</span></label><?php endif; ?>
  <button class="btn btn--gold" type="submit">Save post</button>
</form>

<h2 class="sub">Scheduled and published</h2>
<div class="tablewrap"><table class="tbl">
  <thead><tr><th>Publish</th><th>Post</th><th>Where</th><th>By</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): $live = $p['status'] === 'scheduled' && strtotime($p['publish_at']) <= time(); ?>
    <tr class="<?= $p['status'] === 'cancelled' ? 'dim' : '' ?>">
      <td><?= e(date('j M Y H:i', strtotime($p['publish_at']))) ?><br><span class="muted"><?= $p['status'] === 'cancelled' ? 'Removed' : ($live ? 'Live' : 'Scheduled') ?></span></td>
      <td><b><?= e($p['title']) ?></b><br><span class="muted"><?= e(mb_strimwidth($p['body'], 0, 120, '...')) ?></span></td>
      <td><?= e($fbLabel[$p['fb_status']]) ?><?php if ($p['fb_status'] === 'failed'): ?><br><span class="neg"><?= e($p['fb_error']) ?></span><?php endif; ?></td>
      <td><?= e($p['full_name']) ?></td>
      <td>
        <?php if ($p['status'] === 'scheduled'): ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="linkbtn" type="submit" onclick="return confirm('Remove this post from the website?')">Remove</button></form>
        <?php endif; ?>
        <?php if ($p['fb_status'] === 'failed' && $fbReady && $p['status'] === 'scheduled'): ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="retry_fb"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="linkbtn" type="submit">Retry Facebook</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted">No posts yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php page_foot();
