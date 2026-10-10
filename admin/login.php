<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
if (is_admin()) redirect('admin/index.php');
redirect('login.php?next='.rawurlencode('/admin/index.php'));?
