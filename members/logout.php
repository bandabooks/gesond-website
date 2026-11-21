<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
gr_start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['member_id']);
    session_regenerate_id(true);
}
redirect('login.php');
