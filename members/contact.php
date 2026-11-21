<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_installed();
gr_start_session();
$m = current_member();

$kinds = ['question' => 'A question', 'booking' => 'A booking or function', 'idea' => 'An idea to make Gesond better', 'complaint' => 'A complaint'];
$kind = (string)($_GET['type'] ?? $_POST['kind'] ?? 'question');
if (!isset($kinds[$kind])) $kind = 'question';
$v = ['name' => $m ? $m['first_name'] . ' ' . $m['last_name'] : '', 'phone' => $m ? format_phone($m['phone']) : '', 'email' => $m['email'] ?? '', 'message' => ''];
$err = [];
$sentRef = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'phone', 'email', 'message'] as $k) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $ipKey = 'enq:' . client_ip();
    if (($_POST['website'] ?? '') !== '') {             // honeypot: real people never fill this in
        $sentRef = 'GQ-000000';
    } elseif (too_many_attempts($ipKey, 5, 60)) {
        $err['message'] = 'You\'ve sent several messages in the last hour. Please WhatsApp us on 082 643 9779 instead.';
    } else {
        if ($v['name'] === '' || mb_strlen($v['name']) > 100) $err['name'] = 'Enter your name.';
        $phone = $v['phone'] !== '' ? normalise_phone($v['phone']) : null;
        if ($v['phone'] !== '' && !$phone) $err['phone'] = 'Enter a South African cellphone number, or leave it blank.';
        if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Check your email address, or leave it blank.';
        if (!$phone && $v['email'] === '' && !isset($err['phone']) && !isset($err['email'])) $err['phone'] = 'Give a cellphone number or email so we can reply.';
        if (mb_strlen($v['message']) < 5 || mb_strlen($v['message']) > 2000) $err['message'] = 'Write your message (up to 2000 characters).';
        if (empty($_POST['consent'])) $err['consent'] = 'Tick the box so we may use your details to reply.';
        if (!$err) {
            $pdo = db();
            $pdo->prepare("INSERT INTO enquiries (kind, name, phone, email, message, member_id, status, created_at) VALUES (?,?,?,?,?,?,'new',NOW())")
                ->execute([$kind, $v['name'], $phone, $v['email'] ?: null, $v['message'], $m ? (int)$m['id'] : null]);
            $id = (int)$pdo->lastInsertId();
            $sentRef = 'GQ-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE enquiries SET ref = ? WHERE id = ?')->execute([$sentRef, $id]);
            record_attempt($ipKey);
            audit($m ? 'member' : 'system', $m ? (int)$m['id'] : null, 'enquiry_' . $kind, $m ? (int)$m['id'] : null, ['ref' => $sentRef]);
        }
    }
}
function ferr2(array $err, string $k): string { return isset($err[$k]) ? '<span class="ferr">' . e($err[$k]) . '</span>' : ''; }

page_head('Talk to us', '', $m);
?>
<?php if ($sentRef): ?>
<div class="narrowbox">
  <h1 class="display display--md">Thank you</h1>
  <p class="lead"><?= $kind === 'idea' ? 'We read every idea and use the good ones to make Gesond better for everyone.' : 'We\'ve received your message and will get back to you, usually within one working day.' ?></p>
  <p>Your reference is <b class="refno"><?= e($sentRef) ?></b>. Keep it in case you need to follow up.</p>
  <p>Urgent? WhatsApp us on <a class="link" href="https://wa.me/27826439779?text=<?= rawurlencode('Hi Gesond, about my message ' . $sentRef) ?>">082 643 9779</a>.</p>
  <p><a class="btn btn--line" href="/">Back to the website</a></p>
</div>
<?php else: ?>
<div class="memgrid">
  <section>
    <h1 class="display display--md">Talk to us</h1>
    <p class="lead">Ask a question, plan a function, tell us something went wrong, or share an idea that would make Gesond a better place for everyone.</p>
    <ul class="perks">
      <li><b>Questions and bookings:</b> we reply by WhatsApp, SMS or email, usually within one working day.</li>
      <li><b>Complaints:</b> handled by the management team and followed up personally.</li>
      <li><b>Ideas:</b> music, food, events, comfort, safety. Good ideas get tried out at Gesond.</li>
    </ul>
    <p class="fine">For something urgent, WhatsApp or call <a href="tel:+27826439779">082 643 9779</a>.</p>
  </section>
  <form method="post" class="mform card-form" novalidate>
    <?= csrf_field() ?>
    <fieldset>
      <legend>What's this about?</legend>
      <div class="chips">
        <?php foreach ($kinds as $k => $label): ?>
        <label><input type="radio" name="kind" value="<?= e($k) ?>" <?= $k === $kind ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label>Your name<input name="name" autocomplete="name" required value="<?= e($v['name']) ?>"><?= ferr2($err, 'name') ?></label>
    <div class="row2">
      <label>Cellphone<input name="phone" type="tel" inputmode="tel" autocomplete="tel" value="<?= e($v['phone']) ?>"><?= ferr2($err, 'phone') ?></label>
      <label>Email<input name="email" type="email" autocomplete="email" value="<?= e($v['email']) ?>"><?= ferr2($err, 'email') ?></label>
    </div>
    <label>Your message<textarea name="message" rows="6" maxlength="2000" required><?= e($v['message']) ?></textarea><?= ferr2($err, 'message') ?></label>
    <label class="hp" aria-hidden="true">Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label>
    <label class="check"><input type="checkbox" name="consent" value="1" required><span>Gesond may use these details to reply to me, as set out in the <a href="privacy.php" target="_blank">privacy notice</a>.</span></label>
    <?= ferr2($err, 'consent') ?>
    <button class="btn btn--gold" type="submit">Send message</button>
  </form>
</div>
<?php endif; ?>
<?php page_foot();
