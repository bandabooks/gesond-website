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
    if ($action === 'add') {
        $u = strtolower(trim((string)($_POST['username'] ?? '')));
        $name = trim((string)($_POST['full_name'] ?? ''));
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
        $pw = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $u) || $name === '' || strlen($pw) < 10) {
            flash('Username 3 to 40 letters or numbers, a full name, and a password of 10+ characters are needed.', 'err');
        } else {
            try {
                db()->prepare('INSERT INTO staff (username, full_name, password_hash, role, active, created_at) VALUES (?,?,?,?,1,NOW())')
                    ->execute([$u, $name, password_hash($pw, PASSWORD_DEFAULT), $role]);
                audit('staff', $sid, 'staff_added', null, ['username' => $u, 'role' => $role]);
                flash('Added ' . $name . '. Give them their username and password in person.');
            } catch (PDOException $ex) {
                flash('That username is taken.', 'err');
            }
        }
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle' && $id !== $sid) {
        db()->prepare('UPDATE staff SET active = 1 - active WHERE id = ?')->execute([$id]);
        audit('staff', $sid, 'staff_toggled', null, ['staff' => $id]);
        flash('Updated.');
    }
    if ($action === 'password') {
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 10) flash('Passwords need 10+ characters.', 'err');
        else {
            db()->prepare('UPDATE staff SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            audit('staff', $sid, 'staff_password_set', null, ['staff' => $id]);
            flash('Password changed.');
        }
    }
    redirect('users.php');
}
$rows = db()->query('SELECT * FROM staff ORDER BY active DESC, full_name')->fetchAll();
page_head('Staff', '../', null, $s);
?>
<h1 class="display display--sm">Staff log-ins</h1>
<p class="muted">Give every person their own log-in, so the history shows who did what. Staff can add points, accept vouchers, reset member PINs, answer the inbox and post updates. Admins can also reverse points, cancel vouchers, suspend members, manage staff and connect Facebook.</p>
<div class="tablewrap"><table class="tbl">
  <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last log-in</th><th></th><th>New password</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['active'] ? '' : 'dim' ?>">
      <td><?= e($r['full_name']) ?></td><td><?= e($r['username']) ?></td><td><?= e($r['role']) ?></td>
      <td><?= $r['last_login_at'] ? e(date('j M Y H:i', strtotime($r['last_login_at']))) : '<span class="muted">Never</span>' ?></td>
      <td><?php if ((int)$r['id'] !== $sid): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="linkbtn" type="submit"><?= $r['active'] ? 'Switch off' : 'Switch on' ?></button></form><?php endif; ?></td>
      <td><form method="post" class="inline rev"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="password" name="password" minlength="10" placeholder="10+ characters" autocomplete="new-password" required><button class="linkbtn" type="submit">Set</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<form method="post" class="mform card-form postform">
  <h2>Add a staff member</h2>
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="row2">
    <label>Full name<input name="full_name" required></label>
    <label>Username<input name="username" required autocomplete="off"></label>
  </div>
  <div class="row2">
    <label>Password (10+ characters)<input name="password" type="password" minlength="10" required autocomplete="new-password"></label>
    <label>Role<select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></label>
  </div>
  <button class="btn btn--gold" type="submit">Add staff member</button>
</form>
<?php page_foot();
