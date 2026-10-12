<?php
/** Create / edit a product: details, pricing, sizes & colours with stock, and photos. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('products.manage');

/** Turn the multi-file $_FILES entry into a list of single-file arrays. */
function admin_uploaded_files(string $key): array
{
    $f = $_FILES[$key] ?? null;
    if (!is_array($f) || !isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function admin_variant_row(string $key, array $v, bool $existing): string
{
    $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($v['color_hex'] ?? '')) ? $v['color_hex'] : '#111111';
    $n = 'v[' . e($key) . ']';
    return '<div class="adm-var-row" data-existing="' . ($existing ? '1' : '0') . '"' . (!empty($v['delete']) ? ' hidden' : '') . '>'
        . '<input class="adm-input" name="' . $n . '[size]" value="' . e($v['size'] ?? '') . '" list="sizeList" placeholder="Size" maxlength="20" aria-label="Size">'
        . '<input class="adm-input" name="' . $n . '[color]" value="' . e($v['color'] ?? '') . '" list="colorList" placeholder="Colour name" maxlength="40" aria-label="Colour">'
        . '<input type="color" name="' . $n . '[hex]" value="' . e($hex) . '" aria-label="Colour swatch">'
        . '<input class="adm-input adm-mono" name="' . $n . '[sku]" value="' . e($v['sku'] ?? '') . '" placeholder="Auto SKU" maxlength="60" aria-label="Variant SKU">'
        . '<input class="adm-input" type="number" min="0" max="100000" name="' . $n . '[stock]" value="' . e($v['stock'] ?? 0) . '" aria-label="Stock">'
        . '<input type="hidden" name="' . $n . '[delete]" value="' . (!empty($v['delete']) ? '1' : '0') . '" data-var-delete>'
        . '<button class="adm-btn adm-btn-sm adm-btn-danger" type="button" data-var-remove aria-label="Remove variant">' . ai('x', 14) . '</button></div>';
}

$id = input_int('id', 0, 'get');
$existing = $id > 0 ? db_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id > 0 && !$existing) {
    flash('error', 'That product was not found.');
    admin_redirect('products.php');
}

$p = $existing ?: [
    'name' => '', 'slug' => '', 'sku' => '', 'category_id' => 0, 'gender' => 'unisex', 'short_description' => '', 'description' => '',
    'price' => '', 'sale_price' => '', 'tags' => '', 'is_active' => 1, 'is_featured' => 0, 'is_new' => 1,
];
$variantRows = [];
foreach ($id ? db_all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY id', [$id]) : [] as $v) {
    $variantRows[(string)$v['id']] = ['size' => $v['size'], 'color' => $v['color'], 'color_hex' => $v['color_hex'], 'sku' => $v['sku'], 'stock' => $v['stock'], 'existing' => true];
}
if (!$id) {
    $variantRows['n0'] = ['size' => 'M', 'color' => 'Black', 'color_hex' => '#111111', 'sku' => '', 'stock' => 10, 'existing' => false];
}
$errors = [];

if (is_post()) {
    require_csrf();

    $p['name']              = input_str('name', 160);
    $p['slug']              = input_str('slug', 190);
    $p['sku']               = strtoupper(preg_replace('/[^A-Za-z0-9._-]/', '', input_str('sku', 40)));
    $p['category_id']       = input_int('category_id');
    $p['gender']            = input_str('gender', 10);
    $p['short_description'] = input_str('short_description', 255);
    $p['description']       = trim(mb_substr((string)($_POST['description'] ?? ''), 0, 8000));
    $p['tags']              = input_str('tags', 255);
    $p['is_active']         = admin_checked('is_active');
    $p['is_featured']       = admin_checked('is_featured');
    $p['is_new']            = admin_checked('is_new');
    $price = admin_float('price');
    $sale  = admin_float('sale_price');
    $p['price'] = $price ?? '';
    $p['sale_price'] = $sale ?? '';

    if ($p['name'] === '') {
        $errors[] = 'Please enter a product name.';
    }
    if (!db_value('SELECT id FROM categories WHERE id = ?', [(int)$p['category_id']])) {
        $errors[] = 'Please choose a category.';
    }
    if (!in_array($p['gender'], ['men', 'women', 'unisex'], true)) {
        $p['gender'] = 'unisex';
    }
    if ($price === null || $price <= 0 || $price > 9999999) {
        $errors[] = 'Please enter a price greater than 0.';
    }
    if ($sale !== null && $sale > 0 && $price !== null && $sale >= $price) {
        $errors[] = 'The sale price must be lower than the regular price (or leave it empty).';
    }
    if ($p['sku'] === '' && $existing) {
        $p['sku'] = (string)$existing['sku'];
    }
    if ($p['sku'] === '') {
        $p['sku'] = 'BFM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    // variants
    $rawV = $_POST['v'] ?? [];
    $rawV = is_array($rawV) ? $rawV : [];
    $existingIds = array_map('strval', array_keys(array_filter($variantRows, static fn($r) => !empty($r['existing']))));
    $variantRows = [];
    $seen = [];
    $deleteVariantIds = [];
    $liveVariants = 0;
    foreach ($rawV as $key => $row) {
        $key = (string)$key;
        if (!is_array($row)) {
            continue;
        }
        $isExisting = ctype_digit($key);
        if ($isExisting && !in_array($key, $existingIds, true)) {
            continue;
        }
        $size  = trim(mb_substr((string)($row['size'] ?? ''), 0, 20));
        $color = trim(mb_substr((string)($row['color'] ?? ''), 0, 40));
        $hex   = (string)($row['hex'] ?? '#111111');
        $hex   = preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtoupper($hex) : '#111111';
        $sku   = strtoupper(preg_replace('/[^A-Za-z0-9._-]/', '', mb_substr((string)($row['sku'] ?? ''), 0, 60)));
        $stock = max(0, min(100000, (int)($row['stock'] ?? 0)));
        $del   = !empty($row['delete']) && $row['delete'] === '1';
        if ($isExisting && $del) {
            $deleteVariantIds[] = (int)$key;
            $variantRows[$key] = ['size' => $size, 'color' => $color, 'color_hex' => $hex, 'sku' => $sku, 'stock' => $stock, 'existing' => true, 'delete' => true];
            continue;
        }
        if (!$isExisting && $size === '' && $color === '') {
            continue; // blank new row
        }
        if ($size === '' || $color === '') {
            $errors[] = 'Every variant needs a size and a colour.';
        }
        $combo = strtolower($size . '|' . $color);
        if (isset($seen[$combo])) {
            $errors[] = 'The combination ' . $color . ' / ' . $size . ' is listed twice.';
        }
        $seen[$combo] = true;
        if ($sku === '') {
            $sku = $p['sku'] . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $color), 0, 3)) . '-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $size));
        }
        $variantRows[$key] = ['size' => $size, 'color' => $color, 'color_hex' => $hex, 'sku' => $sku, 'stock' => $stock, 'existing' => $isExisting];
        $liveVariants++;
    }
    if ($liveVariants === 0) {
        $errors[] = 'Add at least one size and colour so customers can order this product.';
        if (!$variantRows) {
            $variantRows['n0'] = ['size' => '', 'color' => '', 'color_hex' => '#111111', 'sku' => '', 'stock' => 0, 'existing' => false];
        }
    }

    // photos: validate before touching the database
    $newFiles = admin_uploaded_files('images');
    if (count($newFiles) > 8) {
        $errors[] = 'Please upload at most 8 photos at a time.';
    }
    foreach ($newFiles as $f) {
        [$okImg, $msgImg] = validate_image_upload($f);
        if (!$okImg) {
            $errors[] = $f['name'] . ': ' . $msgImg;
        }
    }

    if (!$errors) {
        $pdo = db();
        $savedFiles = [];
        $removeFiles = [];
        $pdo->beginTransaction();
        try {
            $slug = unique_slug('products', slugify($p['slug'] !== '' ? $p['slug'] : $p['name']), $id);
            $salePrice = ($sale !== null && $sale > 0) ? $sale : null;
            $cols = [(int)$p['category_id'], $p['name'], $slug, $p['sku'], $p['gender'], $p['short_description'] ?: null,
                     $p['description'] ?: null, $price, $salePrice, $p['tags'] ?: null, $p['is_active'], $p['is_featured'], $p['is_new']];
            if ($id) {
                db_query('UPDATE products SET category_id=?, name=?, slug=?, sku=?, gender=?, short_description=?, description=?, price=?, sale_price=?, tags=?, is_active=?, is_featured=?, is_new=? WHERE id=?', array_merge($cols, [$id]));
            } else {
                db_query('INSERT INTO products (category_id, name, slug, sku, gender, short_description, description, price, sale_price, tags, is_active, is_featured, is_new) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', $cols);
                $id = (int)$pdo->lastInsertId();
            }

            foreach ($deleteVariantIds as $vid) {
                db_query('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $id]);
            }
            foreach ($variantRows as $key => $v) {
                if (!empty($v['delete'])) {
                    continue;
                }
                if (ctype_digit((string)$key)) {
                    db_query('UPDATE product_variants SET size=?, color=?, color_hex=?, sku=?, stock=? WHERE id=? AND product_id=?', [$v['size'], $v['color'], $v['color_hex'], $v['sku'], $v['stock'], (int)$key, $id]);
                } else {
                    db_query('INSERT INTO product_variants (product_id, size, color, color_hex, sku, stock) VALUES (?,?,?,?,?,?)', [$id, $v['size'], $v['color'], $v['color_hex'], $v['sku'], $v['stock']]);
                }
            }

            // remove ticked photos
            $del = $_POST['delete_images'] ?? [];
            foreach (is_array($del) ? $del : [] as $imgId) {
                $img = db_one('SELECT id, image_path FROM product_images WHERE id = ? AND product_id = ?', [(int)$imgId, $id]);
                if ($img) {
                    db_query('DELETE FROM product_images WHERE id = ?', [(int)$img['id']]);
                    $removeFiles[] = (string)$img['image_path'];
                }
            }
            // add new photos
            $order = (int)db_value('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?', [$id]);
            foreach ($newFiles as $f) {
                [$okSave, $path] = save_uploaded_image($f, 'products');
                if (!$okSave) {
                    throw new AdminException($f['name'] . ': ' . $path);
                }
                $savedFiles[] = $path;
                db_query('INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order) VALUES (?,?,?,0,?)', [$id, $path, $p['name'], ++$order]);
            }
            // exactly one primary photo
            $primary = input_int('primary_image');
            if ($primary > 0 && db_value('SELECT id FROM product_images WHERE id = ? AND product_id = ?', [$primary, $id])) {
                db_query('UPDATE product_images SET is_primary = (id = ?) WHERE product_id = ?', [$primary, $id]);
            } elseif (!db_value('SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1', [$id])) {
                $first = db_value('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
                if ($first) {
                    db_query('UPDATE product_images SET is_primary = 1 WHERE id = ?', [(int)$first]);
                }
            }
            $pdo->commit();
            foreach ($removeFiles as $f) {
                admin_delete_upload($f);
            }
            audit_log($existing ? 'product.update' : 'product.create', 'product', $id, ['name' => $p['name']]);
            flash('success', 'Product saved.');
            admin_redirect('product-edit.php', ['id' => $id]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($savedFiles as $f) {
                admin_delete_upload($f);
            }
            if ($e instanceof AdminException) {
                $errors[] = $e->getMessage();
            } elseif ($e instanceof PDOException && (string)$e->getCode() === '23000') {
                $errors[] = 'A product or variant with the same SKU or size/colour already exists. SKUs must be unique.';
            } else {
                error_log('[product save] ' . $e->getMessage());
                $errors[] = 'The product could not be saved. Check logs/error.log for details.';
            }
            if (!$existing) {
                $id = 0;
            }
        }
    }
}

$cats = db_all('SELECT id, name FROM categories ORDER BY sort_order, name');
$images = $existing ? db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id', [$id]) : [];
$title = $existing ? 'Edit product' : 'Add product';

admin_header($title, 'products', [
    'back'     => 'products.php',
    'subtitle' => $existing ? $existing['name'] : 'Fill in the details, add sizes and colours, then upload photos.',
    'actions'  => $existing ? '<a class="adm-btn" href="' . e(url('product.php?slug=' . rawurlencode((string)$existing['slug']))) . '" target="_blank" rel="noopener">' . ai('external', 16) . ' View in store</a>' : '',
]);
foreach ($errors as $err) {
    echo '<div class="adm-alert adm-alert-error" role="alert">' . ai('alert', 18) . '<span>' . e($err) . '</span></div>';
}
?>
<form method="post" enctype="multipart/form-data" action="<?= e(admin_url('product-edit.php', $existing ? ['id' => $id] : [])) ?>">
<?= csrf_field() ?>
<div class="adm-split">
    <div class="adm-stack">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Details</h2></div>
            <div class="adm-card-body adm-form-grid">
                <div class="adm-field adm-full"><label for="name">Name</label><input class="adm-input" id="name" name="name" required maxlength="160" value="<?= e($p['name']) ?>"></div>
                <div class="adm-field adm-full"><label for="short_description">Short description</label><input class="adm-input" id="short_description" name="short_description" maxlength="255" value="<?= e($p['short_description']) ?>"><small>One line shown on cards and the quick view.</small></div>
                <div class="adm-field adm-full"><label for="description">Full description</label><textarea class="adm-textarea" id="description" name="description" rows="7"><?= e($p['description']) ?></textarea></div>
                <div class="adm-field"><label for="tags">Tags</label><input class="adm-input" id="tags" name="tags" maxlength="255" value="<?= e($p['tags']) ?>" placeholder="tee, oversized, cotton"><small>Comma separated. Used by search.</small></div>
                <div class="adm-field"><label for="slug">URL slug</label><input class="adm-input adm-mono" id="slug" name="slug" maxlength="190" value="<?= e($p['slug']) ?>" placeholder="auto from name"><small>Leave empty to generate it from the name.</small></div>
            </div>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Sizes, colours &amp; stock</h2><button class="adm-btn adm-btn-sm" type="button" data-var-add><?= ai('plus', 14) ?> Add variant</button></div>
            <div class="adm-card-body">
                <div class="adm-var-row adm-var-head"><span>Size</span><span>Colour</span><span>Swatch</span><span>SKU</span><span>Stock</span><span></span></div>
                <div id="varRows"><?php foreach ($variantRows as $key => $v): echo admin_variant_row((string)$key, $v, !empty($v['existing'])); endforeach; ?></div>
                <template id="varTemplate"><?= admin_variant_row('__i__', ['size' => '', 'color' => '', 'color_hex' => '#111111', 'sku' => '', 'stock' => 10], false) ?></template>
                <datalist id="sizeList"><?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', 'One Size'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
                <datalist id="colorList"><?php foreach (['Black', 'White', 'Sand', 'Olive', 'Khaki', 'Navy', 'Grey', 'Indigo', 'Washed Black', 'Red', 'Green'] as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                <p class="adm-muted" style="margin-top:8px">Each size/colour pair is a variant with its own stock. Leave the SKU empty to generate it. Removing a variant also removes it from carts.</p>
            </div>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Photos</h2></div>
            <div class="adm-card-body adm-stack">
                <?php if ($images): ?>
                    <div class="adm-imgs">
                        <?php foreach ($images as $img): ?>
                            <div class="adm-img">
                                <img src="<?= e(product_image_url($img['image_path'], (string)$p['name'], 0)) ?>" alt="" loading="lazy">
                                <label><input type="radio" name="primary_image" value="<?= (int)$img['id'] ?>"<?= adm_chk($img['is_primary']) ?>> Main photo</label>
                                <label><input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>"> Remove</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="adm-muted">No photos yet. A neutral placeholder is shown in the store until you upload one.</p>
                <?php endif; ?>
                <div class="adm-field"><label for="newImages">Add photos</label>
                    <input class="adm-input" id="newImages" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                    <small>JPG, PNG or WEBP, up to <?= (int)(MAX_UPLOAD_BYTES / 1048576) ?> MB each. Portrait 4:5 photos look best.</small></div>
                <div id="newImagesPreview" style="display:flex;gap:8px;flex-wrap:wrap"></div>
            </div>
        </section>
    </div>

    <aside class="adm-stack">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Publish</h2></div>
            <div class="adm-card-body adm-stack">
                <label class="adm-check"><input type="checkbox" name="is_active" value="1"<?= adm_chk($p['is_active']) ?>> Visible in the store</label>
                <label class="adm-check"><input type="checkbox" name="is_featured" value="1"<?= adm_chk($p['is_featured']) ?>> Featured on the home page</label>
                <label class="adm-check"><input type="checkbox" name="is_new" value="1"<?= adm_chk($p['is_new']) ?>> Show &ldquo;New&rdquo; badge</label>
                <button class="adm-btn adm-btn-primary adm-btn-block" type="submit"><?= $existing ? 'Save changes' : 'Create product' ?></button>
            </div>
        </section>
        <section class="adm-card">
            <div class="adm-card-head"><h2>Pricing</h2></div>
            <div class="adm-card-body adm-stack">
                <div class="adm-field"><label for="price">Price (<?= e(setting('currency_symbol', 'Rs.')) ?>)</label><input class="adm-input" id="price" name="price" type="number" step="0.01" min="0.01" required value="<?= e($p['price']) ?>"></div>
                <div class="adm-field"><label for="sale_price">Sale price</label><input class="adm-input" id="sale_price" name="sale_price" type="number" step="0.01" min="0" value="<?= e($p['sale_price']) ?>"><small>Optional. Must be lower than the price.</small></div>
            </div>
        </section>
        <section class="adm-card">
            <div class="adm-card-head"><h2>Organisation</h2></div>
            <div class="adm-card-body adm-stack">
                <div class="adm-field"><label for="category_id">Category</label>
                    <select class="adm-select" id="category_id" name="category_id" required>
                        <option value="">Choose&hellip;</option>
                        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= adm_sel($p['category_id'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="adm-field"><label for="gender">For</label>
                    <select class="adm-select" id="gender" name="gender"><?php foreach (['unisex' => 'Unisex', 'men' => 'Men', 'women' => 'Women'] as $k => $l): ?><option value="<?= e($k) ?>"<?= adm_sel($p['gender'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="adm-field"><label for="sku">SKU</label><input class="adm-input adm-mono" id="sku" name="sku" maxlength="40" value="<?= e($p['sku']) ?>" placeholder="auto"></div>
            </div>
        </section>
        <?php if ($existing): ?>
            <p class="adm-muted">Sold so far: <strong><?= (int)$existing['sold_count'] ?></strong> &middot; Rating <?= e(number_format((float)$existing['rating_avg'], 1)) ?> (<?= (int)$existing['rating_count'] ?>)</p>
        <?php endif; ?>
    </aside>
</div>
</form>
<?php admin_footer(); ?>
