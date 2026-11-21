<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_installed();
$s = require_staff(true);
$sid = (int)$s['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'facebook') {
        $page = trim((string)($_POST['fb_page_id'] ?? ''));
        $token = trim((string)($_POST['fb_page_token'] ?? ''));
        if (!preg_match('/^\d{5,25}$/', $page)) { flash('The Page ID is a number, found in your Page\'s About or Page transparency section.', 'err'); redirect('settings.php'); }
        if ($token === '' && !setting('fb_page_token')) { flash('Paste the Page access token.', 'err'); redirect('settings.php'); }
        set_setting('fb_page_id', $page);
        if ($token !== '') set_setting('fb_page_token', $token);
        // Check the connection by reading the Page name with the token
        $ok = false; $name = '';
        if (function_exists('curl_init')) {
            $ch = curl_init(GR_GRAPH . '/' . rawurlencode($page) . '?fields=name&access_token=' . rawurlencode((string)setting('fb_page_token')));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
            $res = json_decode((string)curl_exec($ch), true) ?: [];
            curl_close($ch);
            $ok = !empty($res['name']); $name = $res['name'] ?? ($res['error']['message'] ?? 'no response');
        }
        audit('staff', $sid, 'fb_settings_saved', null, ['page' => $page, 'check' => $ok]);
        flash($ok ? 'Connected to the Facebook Page "' . $name . '".' : 'Saved, but Facebook said: ' . $name, $ok ? 'ok' : 'err');
        redirect('settings.php');
    }
    if ($action === 'facebook_off') {
        set_setting('fb_page_token', null);
        audit('staff', $sid, 'fb_disconnected');
        flash('Facebook disconnected.');
        redirect('settings.php');
    }
}
$fbPage = setting('fb_page_id');
$fbOn = $fbPage && setting('fb_page_token');
page_head('Settings', '../', null, $s);
?>
<h1 class="display display--sm">Settings</h1>
<section class="card-form postform">
  <h2>Facebook Page</h2>
  <p>Status: <b><?= $fbOn ? 'Connected (Page ID ' . e($fbPage) . ')' : 'Not connected' ?></b></p>
  <details>
    <summary>How to get the Page ID and access token</summary>
    <ol class="steps">
      <li>Log in to <a class="link" href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> with the Facebook account that is an admin of the Gesond Page, and create an app of type Business.</li>
      <li>Open the Graph API Explorer, choose your app, then choose "Get Page Access Token" and select the Gesond Page. Grant <i>pages_manage_posts</i>, <i>pages_read_engagement</i> and <i>pages_show_list</i>.</li>
      <li>Swap it for a long-lived token with the Access Token Debugger ("Extend Access Token"), then request the Page token again so it doesn't expire.</li>
      <li>Paste the Page ID and that Page token below. The token is stored only on the server and is never shown again.</li>
    </ol>
  </details>
  <form method="post" class="mform" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="facebook">
    <label>Facebook Page ID<input name="fb_page_id" inputmode="numeric" value="<?= e($fbPage) ?>" required></label>
    <label>Page access token<input name="fb_page_token" type="password" placeholder="<?= $fbOn ? 'Saved. Paste a new one only to replace it.' : '' ?>"></label>
    <button class="btn btn--gold" type="submit">Save and test</button>
  </form>
  <?php if ($fbOn): ?>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="facebook_off"><button class="linkbtn" type="submit" onclick="return confirm('Stop posting to Facebook?')">Disconnect Facebook</button></form>
  <?php endif; ?>
</section>
<section class="card-form postform">
  <h2>Rewards rules</h2>
  <p>1 point per R<?= gr_rand_per_point() ?> spent. <?= gr_voucher_points() ?> points = <?= money(gr_voucher_value_cents()) ?> voucher. To change these, update the settings file on the server and the published <a class="link" href="../rules.php">rewards rules</a> together, with 30 days' notice to members.</p>
</section>
<?php page_foot();
