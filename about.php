<?php
require_once __DIR__ . '/includes/init.php';
$page_title = 'Our story';
$page_description = 'The BFM Clothing approach to considered, everyday clothing.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container narrow"><p class="eyebrow">The BFM point of view</p><h1>Less, but better.</h1><p class="lead">BFM Clothing is a modern essentials label built around one simple idea: the pieces you reach for most should feel the best.</p></div></section>
<section class="section"><div class="container brand-grid"><div class="brand-image"><img src="<?= e(url('placeholder.php?t=BFM%20Studio&i=7&w=900&h=1100')) ?>" alt="BFM studio" width="900" height="1100"></div><div><p class="eyebrow">Designed with intention</p><h2>A wardrobe that earns its place.</h2><p>We make considered clothing for people moving through full, interesting lives. Every silhouette is refined, every fabric is selected for comfort and longevity, and every detail has a reason to be there.</p><ul class="brand-values"><li><strong>01</strong><div><strong>Quality first</strong><p>Better materials, made to be worn often.</p></div></li><li><strong>02</strong><div><strong>Quiet confidence</strong><p>Clean design that leaves room for your point of view.</p></div></li><li><strong>03</strong><div><strong>Real life tested</strong><p>Comfort, movement and versatility are never afterthoughts.</p></div></li></ul></div></div></section>
<section class="section section-dark"><div class="container editorial-grid"><div><p class="eyebrow">Start with less</p><h2>Build your uniform.</h2></div><div><p>Find the pieces that make getting dressed feel simpler, sharper and more like you.</p><a class="link-arrow" href="<?= e(url('shop.php')) ?>">Shop the collection <?= icon('arrow') ?></a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
