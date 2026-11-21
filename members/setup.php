<?php
/*
 * One-time setup for Gesond Rewards.
 * Connects only to the local database server (no host field), creates the tables,
 * creates the first admin, and writes the settings file outside the web root.
 * Once that file exists this page refuses to run again.
 */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

if (gr_is_installed()) { http_response_code(404); exit('Not found.'); }

$error = null;
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminUser = strtolower(trim((string)($_POST['admin_user'] ?? '')));
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    try {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbUser)) throw new RuntimeException('Enter the database name and user exactly as hPanel shows them.');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $adminUser)) throw new RuntimeException('Admin username: 3 to 40 letters, numbers, dots, dashes or underscores.');
        if (strlen($adminPass) < 10) throw new RuntimeException('Admin password must be at least 10 characters.');
        if ($adminName === '') throw new RuntimeException('Enter the admin\'s full name.');
        $pdo = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/lib/schema.sql'))))) as $sql) {
            $pdo->exec($sql);
        }
        if ((int)$pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO staff (username, full_name, password_hash, role, active, created_at) VALUES (?,?,?,'admin',1,NOW())")
                ->execute([$adminUser, $adminName, password_hash($adminPass, PASSWORD_DEFAULT)]);
        }
        $cfg = "<?php\n// Gesond Rewards settings. Written by members/setup.php. Keep private; never commit.\nreturn " . var_export([
            'db_host' => 'localhost',
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'rand_per_point' => 10,          // 1 point for every R10 spent
            'voucher_points' => 100,         // 100 points ...
            'voucher_value_cents' => 1000,   // ... = R10 voucher
            'installed_at' => date('c'),
        ], true) . ";\n";
        if (@file_put_contents(GR_CONFIG_FILE, $cfg, LOCK_EX) === false) throw new RuntimeException('Could not save the settings file. Check folder permissions.');
        @chmod(GR_CONFIG_FILE, 0600);
        $done = true;
    } catch (PDOException $ex) {
        $error = 'Could not connect to the database with those details. Check the name, user and password in hPanel.';
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="en-ZA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Set up Gesond Rewards</title><link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/members/assets/members.css"></head>
<body class="mem"><main class="mem__main"><div class="wrap narrowbox">
<h1 class="display display--sm">Set up Gesond Rewards</h1>
<?php if ($done): ?>
  <p class="notice notice--ok">Setup is complete. This page is now switched off.</p>
  <p><a class="btn btn--gold" href="staff/index.php">Go to the staff log-in</a></p>
<?php else: ?>
  <p>Create a MySQL database in hPanel first (Websites, Manage, Databases), then enter its details here. This page works only once.</p>
  <?php if ($error): ?><p class="notice notice--err" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="mform" autocomplete="off">
    <h2>Database</h2>
    <label>Database name<input name="db_name" required value="<?= e($_POST['db_name'] ?? '') ?>"></label>
    <label>Database user<input name="db_user" required value="<?= e($_POST['db_user'] ?? '') ?>"></label>
    <label>Database password<input name="db_pass" type="password" required></label>
    <h2>First admin (staff log-in)</h2>
    <label>Full name<input name="admin_name" required value="<?= e($_POST['admin_name'] ?? '') ?>"></label>
    <label>Username<input name="admin_user" required value="<?= e($_POST['admin_user'] ?? '') ?>"></label>
    <label>Password (10+ characters)<input name="admin_pass" type="password" minlength="10" required></label>
    <button class="btn btn--gold" type="submit">Set up Gesond Rewards</button>
  </form>
<?php endif; ?>
</div></main></body></html>
