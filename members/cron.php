<?php
/* Run every 5 minutes by the hosting cron job: checks the Facebook connection daily and publishes scheduled posts. CLI only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/lib/bootstrap.php';
if (!gr_is_installed()) exit(0);
check_facebook_health();
$ids = publish_due_facebook_posts(10);
db()->exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
echo date('c') . ' facebook posts processed: ' . count($ids) . "\n";
