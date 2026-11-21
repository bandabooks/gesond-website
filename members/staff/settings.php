<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/ai.php';
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
    if ($action === 'ai') {
        $prov = (string)($_POST['ai_provider'] ?? '');
        if ($prov === 'none') {
            set_setting('ai_provider', null);
            audit('staff', $sid, 'ai_disconnected');
            flash('AI writing switched off.');
            redirect('settings.php');
        }
        if (!isset(AI_DEFAULT_MODELS[$prov])) { flash('Choose Anthropic or OpenAI.', 'err'); redirect('settings.php'); }
        $key = trim((string)($_POST['ai_key'] ?? ''));
        $model = trim((string)($_POST['ai_model'] ?? ''));
        if ($key !== '') set_setting('ai_key_' . $prov, $key);
        if (!setting('ai_key_' . $prov)) { flash('Paste the API key.', 'err'); redirect('settings.php'); }
        if ($model !== '' && !preg_match('/^[A-Za-z0-9._:-]{2,80}$/', $model)) { flash('That model name looks wrong.', 'err'); redirect('settings.php'); }
        set_setting('ai_model_' . $prov, $model ?: AI_DEFAULT_MODELS[$prov]);
        try {
            $msg = ai_test($prov);
            set_setting('ai_provider', $prov);
            audit('staff', $sid, 'ai_connected', null, ['provider' => $prov, 'model' => setting('ai_model_' . $prov)]);
            flash(($prov === 'anthropic' ? 'Anthropic (Claude)' : 'OpenAI') . ' connected. ' . $msg);
        } catch (RuntimeException $ex) {
            flash('Saved, but the test failed: ' . $ex->getMessage(), 'err');
        }
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
  <h2>AI writing for posts</h2>
  <p>Status: <b><?= ($ap = ai_provider()) ? ($ap === 'anthropic' ? 'Anthropic (Claude)' : 'OpenAI') . ', model ' . e(setting('ai_model_' . $ap)) : 'Not connected' ?></b></p>
  <p class="fine">Staff type a few notes on the Posts page and the AI drafts the post. Staff always check and edit it before it is saved or sent to Facebook. Each draft is charged to your AI account (usually well under R1 per draft).</p>
  <details>
    <summary>How to get an API key</summary>
    <ol class="steps">
      <li><b>Anthropic (Claude):</b> sign in at <a class="link" href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a>, add billing credit, then go to API keys and create a key.</li>
      <li><b>OpenAI:</b> sign in at <a class="link" href="https://platform.openai.com" target="_blank" rel="noopener">platform.openai.com</a>, add billing credit, then go to API keys and create a key.</li>
      <li>Set a monthly spending limit in that account. Paste the key below; it is stored only on the server and is never shown again.</li>
    </ol>
  </details>
  <form method="post" class="mform" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="ai">
    <label>Provider<select name="ai_provider">
      <option value="anthropic" <?= setting('ai_provider') === 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
      <option value="openai" <?= setting('ai_provider') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
      <option value="none">Switch AI writing off</option>
    </select></label>
    <label>API key<input name="ai_key" type="password" placeholder="Leave empty to keep the saved key"></label>
    <label>Model (optional)<input name="ai_model" placeholder="Default: <?= e(AI_DEFAULT_MODELS['anthropic']) ?> or <?= e(AI_DEFAULT_MODELS['openai']) ?>"></label>
    <button class="btn btn--gold" type="submit">Save and test</button>
  </form>
</section>
<section class="card-form postform">
  <h2>Rewards rules</h2>
  <p>1 point per R<?= gr_rand_per_point() ?> spent. <?= gr_voucher_points() ?> points = <?= money(gr_voucher_value_cents()) ?> voucher. To change these, update the settings file on the server and the published <a class="link" href="../rules.php">rewards rules</a> together, with 30 days' notice to members.</p>
</section>
<?php page_foot();
