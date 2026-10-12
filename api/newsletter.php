<?php
/**
 * Newsletter sign-up (footer + home page forms). POST email -> {ok, message}
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_ajax_post();

$email = strtolower(input_str('email', 190));
if (!is_valid_email($email)) {
    json_response(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
}

$exists = db_value('SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1', [$email]);
if (!$exists) {
    db_query('INSERT INTO newsletter_subscribers (email) VALUES (?)', [$email]);
}

// Same answer either way so the endpoint can't be used to discover subscribers.
json_response(['ok' => true, 'message' => 'Thanks for subscribing!']);
