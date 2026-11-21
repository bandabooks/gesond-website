<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
gr_start_session();
$m = gr_is_installed() ? current_member() : null;
page_head('Privacy notice', '', $m);
?>
<div class="prose">
  <h1 class="display display--sm">Privacy notice: Gesond Rewards</h1>
  <p class="fine">Effective 22 November 2026. This notice explains how we handle your personal information under the Protection of Personal Information Act, 2013 (POPIA).</p>
  <h2>Who we are</h2>
  <p>Gesond Enterprise (Pty) Ltd (Reg. No. 2021/658589/07), trading as Gesond Liquor Restaurant, Stand No. 92, Mapela Road, Danisane Village, Mapela, Limpopo, 0610. Information Officer: Malesela Fanny Banda, <a href="mailto:admin@gesondlifestyle.co.za">admin@gesondlifestyle.co.za</a>, 082 643 9779.</p>
  <h2>What we collect</h2>
  <ul>
    <li>Your name, cellphone number, date of birth and, if you give it, your email address.</li>
    <li>Your PIN, stored only in scrambled (hashed) form that our staff can't read.</li>
    <li>Your points activity: the amount and slip number of bills you earn points on, and the vouchers you get and use.</li>
    <li>Basic security records, such as log-in times and the internet address used.</li>
  </ul>
  <h2>Why we collect it</h2>
  <ul>
    <li>To run your membership: add and show your points, and issue and check vouchers.</li>
    <li>To confirm you are 18 or older, because we are a licensed liquor venue.</li>
    <li>To prevent fraud and keep accurate records.</li>
    <li>To send you event news and specials, only if you opted in. You can switch this off on your card page at any time.</li>
  </ul>
  <h2>Who can see it</h2>
  <p>Only authorised Gesond staff, to serve you at the till, and our website host (Hostinger), which stores the data for us under its own security obligations. We don't sell your information or share it with other businesses for their marketing. We will disclose it only where the law requires us to.</p>
  <h2>How long we keep it</h2>
  <p>While your membership is active. If you close your membership, we delete or anonymise your details within 30 days, except points and voucher records that we must keep for accounting and tax purposes (generally 5 years).</p>
  <h2>Your rights</h2>
  <p>You can ask to see the information we hold about you, have it corrected, object to its use for marketing, or have your membership closed and your details deleted. Email the Information Officer above. If you're unhappy with how we handle your information, you may complain to the Information Regulator (South Africa) at <a href="https://inforegulator.org.za" target="_blank" rel="noopener">inforegulator.org.za</a>.</p>
  <h2>Security</h2>
  <p>The site uses an encrypted (https) connection, PINs are hashed, log-in attempts are limited, and staff actions are logged.</p>
</div>
<?php page_foot();
