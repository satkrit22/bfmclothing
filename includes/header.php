<?php
/**
 * Storefront header. Set these variables BEFORE including it (all optional):
 *   $page_title        string  e.g. 'Shop'              (becomes "Shop | BFM Clothing")
 *   $page_description  string  meta description
 *   $page_image        string  absolute or site-relative image for Open Graph
 *   $page_robots       string  e.g. 'noindex,nofollow' for private pages
 *   $body_class        string  extra class on <body>
 *   $extra_head        string  raw HTML to add inside <head> (trusted content only)
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/components.php';

$siteName   = setting('site_name', APP_NAME);
$pageTitle  = isset($page_title) && $page_title !== '' ? $page_title . ' | ' . $siteName : $siteName . ' - ' . setting('tagline', 'Modern essentials');
$pageDesc   = $page_description ?? 'BFM Clothing: premium everyday essentials designed for comfort, fit and lasting quality. Free shipping on orders over ' . money((float)setting('free_shipping_threshold', '100')) . '.';
$pageImage  = $page_image ?? url('placeholder.php?t=BFM&i=0&w=1200&h=630');
if (strpos($pageImage, 'http') !== 0) {
    $pageImage = SITE_ORIGIN . $pageImage;
}
$canonical  = SITE_ORIGIN . strtok(current_uri(), '?');
$robots     = $page_robots ?? 'index,follow';
$bodyClass  = trim('site ' . ($body_class ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <meta name="theme-color" content="#0b0b0b">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($pageImage) ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Instrument+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <?= $extra_head ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php require __DIR__ . '/icons.php'; ?>
<a class="skip-link" href="#main">Skip to content</a>
<?php require __DIR__ . '/navbar.php'; ?>
<main id="main">
