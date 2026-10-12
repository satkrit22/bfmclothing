<?php
/**
 * Checkout (Cash on Delivery). Requires a logged-in customer.
 * Totals come from cart_summary(); the order is written in ONE transaction that
 * locks the stock rows, re-checks the coupon, saves the order, decrements stock
 * and clears the cart. On success we redirect to order-success.php (no re-posting).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();

/** Thrown for problems we WANT the customer to read (stock changed, coupon expired). */
class CheckoutException extends RuntimeException
{
}

/**
 * Create the order. Returns the order number. Throws CheckoutException with a
 * customer-friendly message when something changed (stock, coupon). Any other
 * exception (database errors etc.) is logged by the caller and never shown.
 */
function place_order(array $form, int $userId, array $data): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 1. Lock the variant rows (always in id order to avoid deadlocks) and re-check stock.
        $ids = array_map(static fn(array $l): int => (int)$l['variant_id'], $data['lines']);
        sort($ids);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stock = [];
        foreach (db_all("SELECT id, stock FROM product_variants WHERE id IN ($in) ORDER BY id FOR UPDATE", $ids) as $row) {
            $stock[(int)$row['id']] = (int)$row['stock'];
        }
        foreach ($data['lines'] as $line) {
            if (($stock[(int)$line['variant_id']] ?? 0) < (int)$line['quantity']) {
                throw new CheckoutException('Stock just changed for ' . $line['name'] . ' (' . $line['color'] . ', ' . $line['size'] . '). Please review your cart.');
            }
        }

        // 2. Re-validate the coupon while holding its row lock.
        $discount = 0.0;
        $couponCode = null;
        if ($data['coupon']) {
            $coupon = db_one('SELECT * FROM coupons WHERE id = ? FOR UPDATE', [(int)$data['coupon']['id']]);
            $err = $coupon ? coupon_error($coupon, (float)$data['subtotal']) : 'That coupon is no longer available.';
            if ($err !== null) {
                throw new CheckoutException($err);
            }
            $discount   = coupon_discount($coupon, (float)$data['subtotal']);
            $couponCode = (string)$coupon['code'];
        }

        $subtotal = (float)$data['subtotal'];
        $shipping = shipping_cost($subtotal);
        $total    = round($subtotal - $discount + $shipping, 2);

        // 3. The order itself.
        db_query(
            'INSERT INTO orders (user_id, customer_name, email, phone, address_line, city, state, country, postal_code,
                                 subtotal, discount, shipping, total, coupon_code, payment_method, payment_status, status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$userId, $form['customer_name'], $form['email'], $form['phone'], $form['address_line'], $form['city'],
             $form['state'], $form['country'], $form['postal_code'], $subtotal, $discount, $shipping, $total,
             $couponCode, 'cod', 'unpaid', 'pending', $form['notes'] !== '' ? $form['notes'] : null]
        );
        $orderId = (int)$pdo->lastInsertId();
        $number  = make_order_number($orderId);
        db_query('UPDATE orders SET order_number = ? WHERE id = ?', [$number, $orderId]);

        // 4. Items, stock and sales counters.
        foreach ($data['lines'] as $line) {
            $qty = (int)$line['quantity'];
            db_query(
                'INSERT INTO order_items (order_id, product_id, variant_id, product_name, sku, size, color, unit_price, quantity, line_total)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$orderId, (int)$line['product_id'], (int)$line['variant_id'], $line['name'], $line['sku'],
                 $line['size'], $line['color'], $line['unit_price'], $qty, $line['line_total']]
            );
            db_query('UPDATE product_variants SET stock = stock - ? WHERE id = ?', [$qty, (int)$line['variant_id']]);
            db_query('UPDATE products SET sold_count = sold_count + ? WHERE id = ?', [$qty, (int)$line['product_id']]);
        }
        if ($couponCode !== null) {
            db_query('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int)$data['coupon']['id']]);
        }
        db_query('DELETE FROM cart WHERE user_id = ?', [$userId]);

        $pdo->commit();
        return $number;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$data = cart_summary();
if (!$data['lines']) {
    redirect('cart.php');
}
if ($data['issues']) {
    flash('error', 'Please fix the items in your cart before checking out.');
    redirect('cart.php');
}

$user = current_user();
$uid  = (int)$user['id'];

// Pre-fill from the customer's last order, falling back to their profile.
$form = [
    'customer_name' => trim($user['first_name'] . ' ' . $user['last_name']),
    'email'         => (string)$user['email'],
    'phone'         => (string)($user['phone'] ?? ''),
    'address_line'  => '',
    'city'          => '',
    'state'         => '',
    'country'       => 'Nepal',
    'postal_code'   => '',
    'notes'         => '',
];
$last = db_one('SELECT customer_name, phone, address_line, city, state, country, postal_code FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
if ($last) {
    foreach ($last as $key => $value) {
        if ($value !== null && $value !== '') {
            $form[$key] = (string)$value;
        }
    }
}

$errors = [];
if (is_post()) {
    require_csrf();
    $limits = ['customer_name' => 120, 'email' => 190, 'phone' => 30, 'address_line' => 255, 'city' => 80,
               'state' => 80, 'country' => 80, 'postal_code' => 20, 'notes' => 500];
    foreach ($limits as $key => $max) {
        $form[$key] = input_str($key, $max);
    }
    $form['email'] = strtolower($form['email']);

    foreach (['customer_name' => 'your name', 'phone' => 'a phone number', 'address_line' => 'your street address',
              'city' => 'your city', 'state' => 'your province', 'country' => 'your country', 'postal_code' => 'a postal code'] as $key => $label) {
        if ($form[$key] === '') {
            $errors[] = 'Please enter ' . $label . '.';
        }
    }
    if (!is_valid_email($form['email'])) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $form['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (input_str('payment_method', 10) !== 'cod') {
        $errors[] = 'Please choose Cash on delivery. Online payment is not available yet.';
    }

    if (!$errors) {
        try {
            $number = place_order($form, $uid, $data);
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon']);
            redirect('order-success.php?order=' . rawurlencode($number));
        } catch (CheckoutException $e) {
            $errors[] = $e->getMessage();
            $data = cart_summary(); // show fresh numbers
        } catch (Throwable $e) {
            error_log('[checkout] ' . $e->getMessage());
            $errors[] = 'We could not place your order. Please try again.';
        }
    }
}

$page_title  = 'Checkout';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="container page-section">
    <p class="eyebrow">Secure checkout</p>
    <h1>Complete your order</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <div class="checkout-layout">
        <form method="post" class="form-stack checkout-form" novalidate>
            <?= csrf_field() ?>

            <fieldset class="form-block">
                <legend>Contact</legend>
                <div class="form-grid">
                    <label>Full name<input name="customer_name" required maxlength="120" autocomplete="name" value="<?= e($form['customer_name']) ?>"></label>
                    <label>Phone<input name="phone" type="tel" required maxlength="30" autocomplete="tel" value="<?= e($form['phone']) ?>"></label>
                </div>
                <label>Email<input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($form['email']) ?>"></label>
            </fieldset>

            <fieldset class="form-block">
                <legend>Delivery address</legend>
                <label>Street address<input name="address_line" required maxlength="255" autocomplete="street-address" value="<?= e($form['address_line']) ?>"></label>
                <div class="form-grid">
                    <label>City<input name="city" required maxlength="80" autocomplete="address-level2" value="<?= e($form['city']) ?>"></label>
                    <label>Province<input name="state" required maxlength="80" autocomplete="address-level1" value="<?= e($form['state']) ?>"></label>
                </div>
                <div class="form-grid">
                    <label>Postal code<input name="postal_code" required maxlength="20" autocomplete="postal-code" value="<?= e($form['postal_code']) ?>"></label>
                    <label>Country<input name="country" required maxlength="80" autocomplete="country-name" value="<?= e($form['country']) ?>"></label>
                </div>
                <label>Order notes <small>(optional)</small><input name="notes" maxlength="500" placeholder="Landmark, delivery time, gift note..." value="<?= e($form['notes']) ?>"></label>
            </fieldset>

            <fieldset class="form-block">
                <legend>Payment</legend>
                <label class="pay-option is-selected">
                    <input type="radio" name="payment_method" value="cod" checked>
                    <span><strong>Cash on delivery</strong><small>Pay in cash when your order arrives.</small></span>
                </label>
                <label class="pay-option is-disabled">
                    <input type="radio" name="payment_method" value="online" disabled>
                    <span><strong>Online payment</strong><small>Coming soon.</small></span>
                </label>
            </fieldset>

            <button class="btn btn-primary btn-block" type="submit">Place order &middot; <?= e(money($data['total'])) ?></button>
            <p class="summary-note"><a href="<?= e(url('cart.php')) ?>">&larr; Back to cart</a></p>
        </form>

        <aside class="cart-summary">
            <h2>Order summary</h2>
            <ul class="summary-items">
                <?php foreach ($data['lines'] as $line): ?>
                    <li>
                        <img src="<?= e($line['image_url']) ?>" alt="" width="56" height="70" loading="lazy">
                        <div>
                            <strong><?= e($line['name']) ?></strong>
                            <span><?= e($line['color']) ?> &middot; <?= e($line['size']) ?> &times; <?= (int)$line['quantity'] ?></span>
                        </div>
                        <b><?= e(money($line['line_total'])) ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="summary-lines">
                <div><dt>Subtotal</dt><dd><?= e(money($data['subtotal'])) ?></dd></div>
                <?php if ($data['discount'] > 0): ?>
                    <div class="is-discount"><dt>Discount<?= $data['coupon'] ? ' (' . e($data['coupon']['code']) . ')' : '' ?></dt><dd>- <?= e(money($data['discount'])) ?></dd></div>
                <?php endif; ?>
                <div><dt>Delivery</dt><dd><?= $data['shipping'] > 0 ? e(money($data['shipping'])) : 'Free' ?></dd></div>
                <div class="is-total"><dt>Total</dt><dd><?= e(money($data['total'])) ?></dd></div>
            </dl>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
