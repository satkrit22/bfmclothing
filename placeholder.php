<?php
/**
 * Generates a neutral SVG placeholder so the store never shows broken images.
 * Usage: placeholder.php?t=Product+Name&i=0&w=600&h=750
 * Replace by uploading real product photos in the admin panel.
 */
declare(strict_types=1);

$text = mb_substr(trim((string)($_GET['t'] ?? 'BFM')), 0, 40);
$i    = max(0, min(3, (int)($_GET['i'] ?? 0)));
$w    = max(100, min(1600, (int)($_GET['w'] ?? 600)));
$h    = max(100, min(1600, (int)($_GET['h'] ?? 750)));

$palettes = [['#e9e6df', '#cfcac0'], ['#d9d6cf', '#b9b4a8'], ['#ecebe8', '#d4d2cc'], ['#dedad2', '#c2bdb2']];
[$bg, $fg] = $palettes[$i];
$safe = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
$big  = (int)($h * 0.32);

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img" aria-label="{$safe}">
  <rect width="100%" height="100%" fill="{$bg}"/>
  <text x="50%" y="52%" text-anchor="middle" font-family="Arial Black, Arial, sans-serif" font-weight="900" font-size="{$big}" fill="{$fg}" letter-spacing="-4">BFM</text>
  <text x="50%" y="92%" text-anchor="middle" font-family="Arial, sans-serif" font-size="15" fill="#7b776e">{$safe}</text>
</svg>
SVG;
