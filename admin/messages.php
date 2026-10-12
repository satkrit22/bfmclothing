<?php
/** Contact form messages: read, mark, reply by email, delete. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('messages.manage');

if (is_post()) {
    require_csrf();
    $action = input_str('action', 10);
    $mid = input_int('id');
    if ($action === 'readall') {
        db_query('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        flash('success', 'All messages marked as read.');
    } elseif ($mid > 0 && db_value('SELECT id FROM contact_messages WHERE id = ?', [$mid])) {
        if ($action === 'read' || $action === 'unread') {
            db_query('UPDATE contact_messages SET is_read = ? WHERE id = ?', [$action === 'read' ? 1 : 0, $mid]);
        } elseif ($action === 'delete') {
            db_query('DELETE FROM contact_messages WHERE id = ?', [$mid]);
            audit_log('message.delete', 'message', $mid);
            flash('success', 'Message deleted.');
        }
    }
    redirect('admin/messages.php' . admin_qs());
}

$filter = input_str('show', 8, 'get') === 'unread' ? 'unread' : 'all';
$whereSql = $filter === 'unread' ? 'WHERE is_read = 0' : '';
$total = (int)db_value("SELECT COUNT(*) FROM contact_messages $whereSql");
$pg = paginate($total, 15);
$messages = db_all("SELECT * FROM contact_messages $whereSql ORDER BY is_read ASC, created_at DESC, id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$unread = (int)db_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');

admin_header('Messages', 'messages', [
    'subtitle' => $unread . ' unread',
    'actions'  => $unread ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="readall"><button class="adm-btn" type="submit">' . ai('check', 16) . ' Mark all read</button></form>' : '',
]);
?>
<div class="adm-tabs">
    <a class="adm-tab<?= $filter === 'all' ? ' is-active' : '' ?>" href="<?= e(admin_url('messages.php')) ?>">All</a>
    <a class="adm-tab<?= $filter === 'unread' ? ' is-active' : '' ?>" href="<?= e(admin_url('messages.php', ['show' => 'unread'])) ?>">Unread<em><?= $unread ?></em></a>
</div>
<section class="adm-stack">
<?php if (!$messages): ?>
    <div class="adm-card adm-empty"><?= ai('mail', 30) ?><p>No messages.</p></div>
<?php endif; ?>
<?php foreach ($messages as $m): ?>
    <article class="adm-card" style="<?= $m['is_read'] ? '' : 'border-left:4px solid var(--a-accent)' ?>">
        <div class="adm-card-head">
            <div><strong><?= e($m['subject'] ?: '(no subject)') ?></strong> <?= $m['is_read'] ? '' : adm_badge('New', 'info') ?><br>
                <small class="adm-muted"><?= e($m['name']) ?> &lt;<?= e($m['email']) ?>&gt; &middot; <?= e(format_date($m['created_at'], 'd M Y, H:i')) ?></small></div>
            <div class="adm-row-actions">
                <a class="adm-btn adm-btn-sm adm-btn-primary" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . ($m['subject'] ?: 'Your message'))) ?>">Reply</a>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="adm-btn adm-btn-sm" type="submit"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="adm-btn adm-btn-sm adm-btn-danger" type="submit" aria-label="Delete message" data-confirm="Delete this message permanently?" data-confirm-yes="Delete"><?= ai('trash', 14) ?></button></form>
            </div>
        </div>
        <div class="adm-card-body"><?= nl2br(e($m['message'])) ?></div>
    </article>
<?php endforeach; ?>
</section>
<?= admin_pager($pg) ?>
<?php admin_footer(); ?>
