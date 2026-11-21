<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
gr_start_session();
$m = gr_is_installed() ? current_member() : null;
page_head('Rewards rules', '', $m);
?>
<div class="prose">
  <h1 class="display display--sm">Gesond Rewards rules</h1>
  <p class="fine">Effective 22 November 2026. Run by Gesond Enterprise (Pty) Ltd, trading as Gesond Liquor Restaurant.</p>
  <ol>
    <li><b>Who can join.</b> Membership is free and open to anyone aged 18 or older. One membership per person and per cellphone number.</li>
    <li><b>Earning points.</b> You earn 1 point for every full R<?= gr_rand_per_point() ?> on a paid Gesond bill (for example, a R250 bill earns 25 points). Staff add points at the till when you show your card or give your member number. Points must be added on the day of purchase and need the slip number.</li>
    <li><b>Using points.</b> <?= gr_voucher_points() ?> points can be swapped for a <?= money(gr_voucher_value_cents()) ?> voucher in your account. Show the voucher code at the till to use it against food or drinks at Gesond. A voucher is used once, in full; change is not given.</li>
    <li><b>What points are not.</b> Points and vouchers have no cash value, can't be exchanged for cash and can't be transferred to another person.</li>
    <li><b>Corrections.</b> If points are added in error, or a purchase is refunded, Gesond may reverse those points. Every change is recorded in your points history.</li>
    <li><b>Responsible drinking.</b> Vouchers can't be used to buy alcohol for anyone under 18 or for anyone who is intoxicated. Staff may refuse service at any time.</li>
    <li><b>Misuse.</b> Gesond may suspend a membership that is used fraudulently, and cancel points or vouchers gained that way.</li>
    <li><b>Changes.</b> Gesond may change these rules or end the programme with at least 30 days' notice on this page. Points earned before a change keep their value until the notice period ends.</li>
    <li><b>Questions.</b> Email <a href="mailto:admin@gesondlifestyle.co.za">admin@gesondlifestyle.co.za</a> or call 082 643 9779.</li>
  </ol>
</div>
<?php page_foot();
