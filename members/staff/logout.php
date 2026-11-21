<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
gr_start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_SESSION['staff_id'])) audit('staff', (int)$_SESSION['staff_id'], 'staff_logout');
    unset($_SESSION['staff_id']);
    session_regenerate_id(true);
}
redirect('index.php');
