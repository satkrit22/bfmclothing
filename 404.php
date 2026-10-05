<?php
require_once __DIR__ . '/includes/init.php';
http_response_code(404);
$page_title = 'Page not found';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container narrow text-center">
        <p class="error-code">404</p>
        <h1>This page is not here</h1>
        <p class="lead">The link may be broken or the page may have moved. Try the shop or search for what you need.</p>
        <div class="btn-row justify-center">
            <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Shop all products</a>
            <a class="btn btn-outline" href="<?= e(url()) ?>">Back to home</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
