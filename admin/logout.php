<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin-auth.php';
admin_logout();
redirect('admin/login.php');
