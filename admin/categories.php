<?php
/** Categories: list with product counts + add / edit form on the same page. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('categories.manage');

$errors = [];
$editId = input_int('edit', 0, 'get');
$form = ['name' => '', 'slug' => '', 'description' => '', 'sort_order' => (int)db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories'), 'is_active' => 1];

if (is_post()) {
    require_csrf();
    $action = input_str('action', 10);
    $cid = input_int('id');

    if ($action === 'delete') {
        $cat = $cid > 0 ? db_one('SELECT id, name FROM categories WHERE id = ?', [$cid]) : null;
        $n = $cat ? (int)db_value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$cid]) : 0;
        if (!$cat) {
            flash('error', 'Category not found.');
        } elseif ($n > 0) {
            flash('error', '"' . $cat['name'] . '" still has ' . $n . ' product' . ($n === 1 ? '' : 's') . '. Move or delete them first, or just hide the category.');
        } else {
            db_query('DELETE FROM categories WHERE id = ?', [$cid]);
            audit_log('category.delete', 'category', $cid, ['name' => $cat['name']]);
            flash('success', 'Category "' . $cat['name'] . '" deleted.');
        }
        admin_redirect('categories.php');
    }

    if ($action === 'toggle') {
        $cat = $cid > 0 ? db_one('SELECT id, name, is_active FROM categories WHERE id = ?', [$cid]) : null;
        if ($cat) {
            db_query('UPDATE categories SET is_active = ? WHERE id = ?', [(int)$cat['is_active'] ? 0 : 1, $cid]);
            flash('success', '"' . $cat['name'] . '" is now ' . ((int)$cat['is_active'] ? 'hidden.' : 'visible.'));
        }
        admin_redirect('categories.php');
    }

    // save (create or update)
    $editId = $cid;
    $form = [
        'name'        => input_str('name', 80),
        'slug'        => input_str('slug', 100),
        'description' => input_str('description', 255),
        'sort_order'  => input_int('sort_order'),
        'is_active'   => admin_checked('is_active'),
    ];
    if ($form['name'] === '') {
        $errors[] = 'Please enter a category name.';
    }
    if ($cid > 0 && !db_value('SELECT id FROM categories WHERE id = ?', [$cid])) {
        $errors[] = 'That category no longer exists.';
    }
    if (!$errors) {
        $slug = unique_slug('categories', slugify($form['slug'] !== '' ? $form['slug'] : $form['name']), $cid);
        if ($cid > 0) {
            db_query('UPDATE categories SET name=?, slug=?, description=?, sort_order=?, is_active=? WHERE id=?', [$form['name'], $slug, $form['description'] ?: null, $form['sort_order'], $form['is_active'], $cid]);
            audit_log('category.update', 'category', $cid, ['name' => $form['name']]);
        } else {
            db_query('INSERT INTO categories (name, slug, description, sort_order, is_active) VALUES (?,?,?,?,?)', [$form['name'], $slug, $form['description'] ?: null, $form['sort_order'], $form['is_active']]);
            audit_log('category.create', 'category', (int)db()->lastInsertId(), ['name' => $form['name']]);
        }
        flash('success', 'Category saved.');
        admin_redirect('categories.php');
    }
} elseif ($editId > 0) {
    $row = db_one('SELECT * FROM categories WHERE id = ?', [$editId]);
    if ($row) {
        $form = ['name' => $row['name'], 'slug' => $row['slug'], 'description' => (string)$row['description'], 'sort_order' => (int)$row['sort_order'], 'is_active' => (int)$row['is_active']];
    } else {
        $editId = 0;
    }
}

$cats = db_all(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products
     FROM categories c ORDER BY c.sort_order, c.name'
);

admin_header('Categories', 'categories', ['subtitle' => count($cats) . ' categories. Customers browse these in the shop menu.']);
foreach ($errors as $err) {
    echo '<div class="adm-alert adm-alert-error" role="alert">' . ai('alert', 18) . '<span>' . e($err) . '</span></div>';
}
?>
<div class="adm-split">
    <section class="adm-card">
        <?php if (!$cats): ?>
            <div class="adm-empty"><?= ai('tag', 30) ?><p>No categories yet. Add the first one on the right.</p></div>
        <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Name</th><th class="adm-num">Products</th><th class="adm-num">Order</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><a href="<?= e(admin_url('categories.php', ['edit' => $c['id']])) ?>"><strong><?= e($c['name']) ?></strong></a><br><small class="adm-muted adm-mono">/<?= e($c['slug']) ?></small></td>
                    <td class="adm-num"><a href="<?= e(admin_url('products.php', ['category' => $c['id']])) ?>"><?= (int)$c['products'] ?></a></td>
                    <td class="adm-num"><?= (int)$c['sort_order'] ?></td>
                    <td><?= $c['is_active'] ? adm_badge('Visible', 'ok') : adm_badge('Hidden', 'neutral') ?></td>
                    <td><div class="adm-row-actions">
                        <a class="adm-btn adm-btn-sm" href="<?= e(admin_url('categories.php', ['edit' => $c['id']])) ?>"><?= ai('edit', 14) ?> Edit</a>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="adm-btn adm-btn-sm" type="submit"><?= $c['is_active'] ? 'Hide' : 'Show' ?></button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="adm-btn adm-btn-sm adm-btn-danger" type="submit" aria-label="Delete <?= e($c['name']) ?>" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;?" data-confirm-yes="Delete"><?= ai('trash', 14) ?></button></form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>

    <aside class="adm-card">
        <div class="adm-card-head"><h2><?= $editId ? 'Edit category' : 'Add category' ?></h2><?php if ($editId): ?><a href="<?= e(admin_url('categories.php')) ?>">Cancel</a><?php endif; ?></div>
        <form class="adm-card-body adm-stack" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$editId ?>">
            <div class="adm-field"><label for="name">Name</label><input class="adm-input" id="name" name="name" required maxlength="80" value="<?= e($form['name']) ?>"></div>
            <div class="adm-field"><label for="slug">URL slug</label><input class="adm-input adm-mono" id="slug" name="slug" maxlength="100" value="<?= e($form['slug']) ?>" placeholder="auto from name"></div>
            <div class="adm-field"><label for="description">Description</label><textarea class="adm-textarea" id="description" name="description" maxlength="255" rows="3"><?= e($form['description']) ?></textarea></div>
            <div class="adm-field"><label for="sort_order">Menu order</label><input class="adm-input" id="sort_order" name="sort_order" type="number" value="<?= (int)$form['sort_order'] ?>"><small>Lower numbers come first.</small></div>
            <label class="adm-check"><input type="checkbox" name="is_active" value="1"<?= adm_chk($form['is_active']) ?>> Visible in the store</label>
            <button class="adm-btn adm-btn-primary" type="submit"><?= $editId ? 'Save category' : 'Add category' ?></button>
        </form>
    </aside>
</div>
<?php admin_footer(); ?>
