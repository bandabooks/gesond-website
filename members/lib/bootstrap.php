<?php
/*
 * Gesond Rewards - shared bootstrap.
 * Settings and database credentials live OUTSIDE the web root in
 * gesond-members-config.php (written once by members/setup.php), never in Git.
 */
declare(strict_types=1);

const GR_CONFIG_FILE = __DIR__ . '/../../../gesond-members-config.php';

date_default_timezone_set('Africa/Johannesburg');

function gr_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        if (!is_file(GR_CONFIG_FILE)) {
            return [];
        }
        $cfg = require GR_CONFIG_FILE;
    }
    return $cfg;
}

function gr_is_installed(): bool {
    return gr_config() !== [];
}

/* Rewards rules (from config, with the agreed defaults) */
function gr_rand_per_point(): int { return (int)(gr_config()['rand_per_point'] ?? 10); }      // R10 spent = 1 point
function gr_voucher_points(): int { return (int)(gr_config()['voucher_points'] ?? 100); }     // 100 points ...
function gr_voucher_value_cents(): int { return (int)(gr_config()['voucher_value_cents'] ?? 1000); } // ... = R10 voucher

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = gr_config();
        $pdo = new PDO(
            'mysql:host=' . ($c['db_host'] ?? 'localhost') . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '+02:00'");
    }
    return $pdo;
}

function gr_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('gesond_rewards');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/members/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    // Expire idle sessions after 2 hours
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function csrf_token(): string {
    gr_start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    gr_start_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function redirect(string $to): never { header('Location: ' . $to); exit; }

function flash(?string $msg = null, string $type = 'ok'): ?array {
    gr_start_session();
    if ($msg !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

/* South African cellphone -> 27XXXXXXXXX, or null when invalid */
function normalise_phone(string $raw): ?string {
    $d = preg_replace('/\D+/', '', $raw);
    if (strlen($d) === 10 && $d[0] === '0') $d = '27' . substr($d, 1);
    if (strlen($d) === 9 && $d[0] !== '0') $d = '27' . $d;
    return preg_match('/^27[6-8]\d{8}$/', $d) ? $d : null;
}
function format_phone(string $p): string { return '0' . substr($p, 2, 2) . ' ' . substr($p, 4, 3) . ' ' . substr($p, 7); }

function money(int $cents): string { return 'R' . number_format($cents / 100, 2, '.', ' '); }

/* Rate limiting for log-ins: max $max failures per key in $minutes */
function too_many_attempts(string $key, int $max = 5, int $minutes = 15): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE key_hash = ? AND created_at > (NOW() - INTERVAL ? MINUTE)');
    $st->execute([hash('sha256', $key), $minutes]);
    return (int)$st->fetchColumn() >= $max;
}
function record_attempt(string $key): void {
    db()->prepare('INSERT INTO login_attempts (key_hash, created_at) VALUES (?, NOW())')->execute([hash('sha256', $key)]);
}
function clear_attempts(string $key): void {
    db()->prepare('DELETE FROM login_attempts WHERE key_hash = ?')->execute([hash('sha256', $key)]);
}

function audit(string $actorType, ?int $actorId, string $action, ?int $memberId = null, array $details = []): void {
    db()->prepare('INSERT INTO audit_log (actor_type, actor_id, action, member_id, details, ip, created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$actorType, $actorId, $action, $memberId, $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null, client_ip()]);
}

function member_balance(int $memberId): int {
    $st = db()->prepare('SELECT COALESCE(SUM(points),0) FROM points_ledger WHERE member_id = ?');
    $st->execute([$memberId]);
    return (int)$st->fetchColumn();
}

function current_member(): ?array {
    gr_start_session();
    if (empty($_SESSION['member_id'])) return null;
    $st = db()->prepare("SELECT * FROM members WHERE id = ? AND status = 'active'");
    $st->execute([$_SESSION['member_id']]);
    return $st->fetch() ?: null;
}
function require_member(): array {
    $m = current_member();
    if (!$m) redirect('login.php');
    return $m;
}

function current_staff(): ?array {
    gr_start_session();
    if (empty($_SESSION['staff_id'])) return null;
    $st = db()->prepare('SELECT * FROM staff WHERE id = ? AND active = 1');
    $st->execute([$_SESSION['staff_id']]);
    return $st->fetch() ?: null;
}
function require_staff(bool $adminOnly = false): array {
    $s = current_staff();
    if (!$s) redirect('index.php');
    if ($adminOnly && $s['role'] !== 'admin') { http_response_code(403); exit('Only an admin can do this.'); }
    return $s;
}

function require_installed(): void {
    if (!gr_is_installed()) {
        http_response_code(503);
        exit('Gesond Rewards is being set up. Please check back soon.');
    }
}

function setting(string $k, ?string $default = null): ?string {
    $st = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}
function set_setting(string $k, ?string $v): void {
    db()->prepare('INSERT INTO settings (k, v, updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()')->execute([$k, $v]);
}

const GR_SITE = 'https://www.gesondlifestyle.co.za';
const GR_GRAPH = 'https://graph.facebook.com/v26.0';

/*
 * Publish due posts to the Facebook Page (posts with to_facebook = 1 whose time has come).
 * Called by the cron job (members/cron.php) and, as a fallback, when the website asks for updates.
 * Each post is claimed with an UPDATE ... WHERE fb_status = 'pending' so it can never be sent twice.
 */
function publish_due_facebook_posts(int $limit = 3): array {
    $pageId = setting('fb_page_id');
    $token = setting('fb_page_token');
    $done = [];
    if (!$pageId || !$token || !function_exists('curl_init')) return $done;
    $st = db()->prepare("SELECT * FROM posts WHERE status = 'scheduled' AND to_facebook = 1 AND fb_status = 'pending' AND publish_at <= NOW() ORDER BY publish_at LIMIT " . (int)$limit);
    $st->execute();
    foreach ($st->fetchAll() as $p) {
        $claim = db()->prepare("UPDATE posts SET fb_status = 'sending' WHERE id = ? AND fb_status = 'pending'");
        $claim->execute([$p['id']]);
        if ($claim->rowCount() !== 1) continue;
        $message = $p['title'] . "\n\n" . $p['body'];
        $fields = ['message' => $message, 'access_token' => $token];
        $fields['link'] = $p['link_url'] ?: GR_SITE . '/updates.html';
        $ch = curl_init(GR_GRAPH . '/' . rawurlencode($pageId) . '/feed');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $res = json_decode((string)$raw, true) ?: [];
        if ($code === 200 && !empty($res['id'])) {
            db()->prepare("UPDATE posts SET fb_status = 'posted', fb_post_id = ?, fb_error = NULL WHERE id = ?")->execute([$res['id'], $p['id']]);
            audit('system', null, 'fb_posted', null, ['post' => (int)$p['id'], 'fb_id' => $res['id']]);
        } else {
            $msg = substr((string)($res['error']['message'] ?? ('HTTP ' . $code)), 0, 250);
            db()->prepare("UPDATE posts SET fb_status = 'failed', fb_error = ? WHERE id = ?")->execute([$msg, $p['id']]);
            audit('system', null, 'fb_failed', null, ['post' => (int)$p['id'], 'error' => $msg]);
        }
        $done[] = (int)$p['id'];
    }
    return $done;
}

/* A closed membership keeps its points and voucher records but has its personal details removed. */
function member_is_closed(array $m): bool { return str_starts_with((string)$m['phone'], 'X'); }

/*
 * Email the manager about a new Talk-to-us message. Uses the hosting's mail();
 * the From address must be a real mailbox on gesondlifestyle.co.za. Returns false if not sent.
 */
function notify_new_enquiry(string $ref, string $kind, string $name, ?string $phone, ?string $email, string $message): bool {
    $to = setting('notify_email');
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = setting('notify_from', 'info@gesondlifestyle.co.za');
    $labels = ['question' => 'Question', 'booking' => 'Booking or function', 'complaint' => 'COMPLAINT', 'idea' => 'Idea'];
    $subject = ($kind === 'complaint' ? 'Complaint, reply today: ' : 'New message: ') . ($labels[$kind] ?? $kind) . ' ' . $ref;
    $body = "A new message came in through the website's Talk to us page.\n\n"
        . "Reference: $ref\nType: " . ($labels[$kind] ?? $kind) . "\nName: $name\n"
        . 'Cellphone: ' . ($phone ? format_phone($phone) : '-') . "\nEmail: " . ($email ?: '-') . "\n\n"
        . mb_substr($message, 0, 1500) . "\n\n"
        . "Reply and record what was done in the staff Inbox:\n" . GR_SITE . "/members/staff/inbox.php\n\n"
        . ($kind === 'complaint' ? "Complaints must be answered the same day.\n" : "Please reply within one working day.\n");
    $headers = 'From: Gesond website <' . $from . ">\r\nReply-To: " . ($email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $from)
        . "\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: Gesond";
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);
    if (!$ok) audit('system', null, 'notify_failed', null, ['ref' => $ref]);
    return $ok;
}

/* Once a day (from cron) check the Facebook connection still works, so a dead token shows on the dashboard before a post fails. */
function check_facebook_health(bool $force = false): void {
    $page = setting('fb_page_id'); $token = setting('fb_page_token');
    if (!$page || !$token || !function_exists('curl_init')) { set_setting('fb_health', null); return; }
    $last = (int)setting('fb_checked_at', '0');
    if (!$force && time() - $last < 86400) return;
    $ch = curl_init(GR_GRAPH . '/' . rawurlencode($page) . '?fields=name&access_token=' . rawurlencode($token));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw === false || $code === 0) return;                  // network hiccup: try again next run
    $res = json_decode((string)$raw, true) ?: [];
    set_setting('fb_checked_at', (string)time());
    if (!empty($res['name'])) { set_setting('fb_health', null); return; }
    $msg = mb_substr((string)($res['error']['message'] ?? ('HTTP ' . $code)), 0, 200);
    if (setting('fb_health') !== $msg) audit('system', null, 'fb_connection_failed', null, ['error' => $msg]);
    set_setting('fb_health', $msg);
}
