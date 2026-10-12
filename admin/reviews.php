<?php
/** Product reviews: approve, hide or delete. Ratings on products are recalculated automatically. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('reviews.manage');

if (is_post()) {
    require_csrf();
    $action = input_str('action', 10);
    $rid = input_int('id');
    $r = $rid > 0 ? db_one('SELECT id, product_id, is_approved FROM reviews WHERE id = ?', [$rid]) : null;
    if ($r) {
        if ($action === 'toggle') {
            db_query('UPDATE reviews SET is_approved = ? WHERE id = ?', [(int)$r['is_approved'] ? 0 : 1, $rid]);
            audit_log('review.' . ((int)$r['is_approved'] ? 'hide' : 'approve'), 'review', $rid);
            flash('success', 'Review ' . ((int)$r['is_approved'] ? 'hidden from the store.' : 'approved and published.'));
        } elseif ($action === 'delete') {
            db_query('DELETE FROM reviews WHERE id = ?', [$rid]);
            audit_log('review.delete', 'review', $rid);
            flash('success', 'Review deleted.');
        }
        recalc_product_rating((int)$r['product_id']);
    }
    redirect('admin/reviews.php' . admin_qs());
}

$state = input_str('state', 10, 'get');
$q     = input_str('q', 80, 'get');
$where = [];
$params = [];
if ($state === 'approved') {
    $where[] = 'r.is_approved = 1';
} elseif ($state === 'hidden') {
    $where[] = 'r.is_approved = 0';
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR r.title LIKE ? OR r.comment LIKE ? OR u.email LIKE ?)';
    $like = admin_like($q);
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$base = "FROM reviews r JOIN products p ON p.id = r.product_id JOIN users u ON u.id = r.user_id $whereSql";
$total = (int)db_value("SELECT COUNT(*) $base", $params);
$pg = paginate($total, 15);
$reviews = db_all(
    "SELECT r.*, p.name AS product, u.first_name, u.last_name, u.email $base ORDER BY r.created_at DESC, r.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

admin_header('Reviews', 'reviews', ['subtitle' => number_format($total) . ' review' . ($total === 1 ? '' : 's')]);
?>
<section class="adm-card">
    <form class="adm-filters" method="get">
        <input class="adm-input adm-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search product, customer or text" aria-label="Search reviews">
        <select class="adm-select" name="state" aria-label="Status"><option value="">All reviews</option><option value="approved"<?= adm_sel($state, 'approved') ?>>Published</option><option value="hidden"<?= adm_sel($state, 'hidden') ?>>Hidden</option></select>
        <button class="adm-btn adm-btn-primary" type="submit">Filter</button>
        <?php if ($q !== '' || $state !== ''): ?><a class="adm-btn" href="<?= e(url('admin/reviews.php')) ?>">Reset</a><?php endif; ?>
    </form>
    <?php if (!$reviews): ?>
        <div class="adm-empty"><?= ai('star', 30) ?><p>No reviews found.</p></div>
    <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td><a href="<?= e(admin_url('product-edit.php', ['id' => $r['product_id']])) ?>"><?= e($r['product']) ?></a></td>
                    <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?><br><small class="adm-muted"><?= e($r['email']) ?></small></td>
                    <td class="adm-nowrap" title="<?= (int)$r['rating'] ?> out of 5"><?= str_repeat('&#9733;', (int)$r['rating']) . '<span class="adm-muted">' . str_repeat('&#9733;', 5 - (int)$r['rating']) . '</span>' ?></td>
                    <td style="max-width:340px"><?php if ($r['title']): ?><strong><?= e($r['title']) ?></strong><br><?php endif; ?><?= e($r['comment']) ?></td>
                    <td class="adm-muted adm-nowrap"><?= e(format_date($r['created_at'])) ?></td>
                    <td><?= $r['is_approved'] ? adm_badge('Published', 'ok') : adm_badge('Hidden', 'neutral') ?></td>
                    <td><div class="adm-row-actions">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="adm-btn adm-btn-sm" type="submit"><?= $r['is_approved'] ? 'Hide' : 'Approve' ?></button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="adm-btn adm-btn-sm adm-btn-danger" type="submit" aria-label="Delete review" data-confirm="Delete this review permanently?" data-confirm-yes="Delete"><?= ai('trash', 14) ?></button></form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= admin_pager($pg) ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
