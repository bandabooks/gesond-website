<?php
/* Public JSON feed of published Gesond updates for the website's Updates page. */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!gr_is_installed()) { echo json_encode(['posts' => []]); exit; }
try {
    // Fallback publisher in case the cron job is not running: send at most one due Facebook post per request.
    if (random_int(1, 3) === 1) publish_due_facebook_posts(1);
    $rows = db()->query("SELECT id, title, body, link_url, publish_at FROM posts WHERE status = 'scheduled' AND publish_at <= NOW() ORDER BY publish_at DESC LIMIT 20")->fetchAll();
    echo json_encode(['posts' => array_map(fn($r) => [
        'id' => (int)$r['id'],
        'date' => substr($r['publish_at'], 0, 10),
        'title' => $r['title'],
        'text' => $r['body'],
        'link' => $r['link_url'],
    ], $rows)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['posts' => []]);
}
