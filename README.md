# BFM Clothing

A premium clothing e-commerce store built with **PHP 8 + MySQL + HTML/CSS + vanilla JavaScript**, designed to run locally in **XAMPP**. No frameworks, no build step.

> **Status: Part 1 of 6 (foundation).** Database, configuration, shared functions, authentication helpers, layout (header / navbar / footer), global CSS and JavaScript are done. Storefront pages arrive in Parts 2-4 and the admin panel in Part 5. See the roadmap at the bottom.

---

## 1. Install

1. **Install XAMPP** (PHP 8.0 or newer) from https://www.apachefriends.org
2. Open the **XAMPP Control Panel** and **Start Apache** and **Start MySQL**.
3. **Copy the project folder** to:
   `C:\xampp\htdocs\bfm-clothing`
   (on macOS: `/Applications/XAMPP/htdocs/bfm-clothing`)
4. Open **phpMyAdmin**: http://localhost/phpmyadmin
5. Click the **Import** tab, choose `database/bfm_clothing.sql`, then click **Import**.
   This creates the `bfm_clothing` database, all tables, and sample data (12 products, coupons, demo users, one admin).
6. **Database credentials** are in `config/config.php`. The XAMPP defaults already work:
   ```php
   const DB_HOST = '127.0.0.1';
   const DB_NAME = 'bfm_clothing';
   const DB_USER = 'root';
   const DB_PASS = '';
   ```
7. Visit the store: **http://localhost/bfm-clothing/**

If PHP shows `could not find driver` or `finfo` / `mbstring` errors, open `C:\xampp\php\php.ini` and make sure these lines are **not** commented out with `;`:
`extension=pdo_mysql`, `extension=mbstring`, `extension=fileinfo`. Then restart Apache.

### If you use a different folder name
The site detects its own URL path automatically. Only two files hard-code `/bfm-clothing/`:
`.htaccess` (the `ErrorDocument 404` line) and `robots.txt`. Update them if you rename the folder.

---

## 2. Admin panel (built in Part 5)

URL: **http://localhost/bfm-clothing/admin/**

| | |
|---|---|
| Email | `admin@bfmclothing.com` |
| Password | `Admin@12345` |

**These are development credentials. Change the password immediately** (Admin > Profile) before the site is ever reachable from the internet. The database stores only a bcrypt hash; no password is written in any PHP file.

### Demo customer accounts
| Email | Password |
|---|---|
| `customer@example.com` | `Customer@123` |
| `sam@example.com` | `Customer@123` |

### Coupon codes in the sample data
`WELCOME10` (10% off), `BFM20` (20% off orders of 100+), `SAVE15` (15 off orders of 75+).

---

## 3. Project structure

```
bfm-clothing/
├── index.php, shop.php, product.php, ...    storefront pages (Parts 2-4)
├── 404.php                                  not-found page
├── placeholder.php                          generates placeholder product images (SVG)
├── robots.txt, .htaccess
├── admin/                                   admin panel (Part 5)
├── api/                                     JSON endpoints used by main.js (Parts 2-4)
├── config/
│   ├── config.php                           constants, secure session, headers, error handling
│   └── database.php                         PDO connection + db_query/db_all/db_one/db_value
├── includes/
│   ├── init.php                             bootstrap: require this first on every page
│   ├── functions.php                        helpers (e(), url(), CSRF, flash, pricing, uploads...)
│   ├── auth.php                             customer login/logout/remember-me/protection
│   ├── admin-auth.php                       admin login/logout/protection
│   ├── components.php                       product_card(), product_list_select()
│   ├── header.php  navbar.php  footer.php   storefront layout
│   └── icons.php                            inline SVG icon sprite
├── assets/
│   ├── css/style.css   js/main.js
│   ├── images/                              your own images (logo, hero, ...)
│   └── uploads/products/                    admin uploads (script execution disabled)
├── database/bfm_clothing.sql                schema + sample data
└── logs/                                    PHP error log (not web accessible)
```

---

## 4. Conventions (every later part follows these)

**A normal storefront page:**
```php
<?php
require_once __DIR__ . '/includes/init.php';
$page_title = 'Shop';
$page_description = '...';
require __DIR__ . '/includes/header.php';
?>
...page HTML...
<?php require __DIR__ . '/includes/footer.php'; ?>
```
**A protected customer page:** call `require_login();` right after `init.php`.
**An admin page:** `require_once __DIR__ . '/../includes/admin-auth.php';` (protects the page automatically). `admin/login.php` first does `define('ADMIN_LOGIN_PAGE', true);`.

**Session keys:** `user_id`, `admin_id`, `cart` (`[variant_id => qty]`), `coupon`, `csrf_token`, `flash`.
**Cart rule:** the cart stores only variant IDs and quantities. Prices, stock and totals are always recalculated from the database on the server.
**Output:** always escape with `e()`. **Forms:** always include `<?= csrf_field() ?>` and call `require_csrf()` on POST. **AJAX:** `require_ajax_post()` (checks method + CSRF token header).
**Database:** only prepared statements via `db_query / db_all / db_one / db_value`.
**Categories vs gender:** categories are product types (T-Shirts, Hoodies...). *Men / Women* are a separate `products.gender` filter (`shop.php?gender=men`).
**Order numbers:** `BFM-YYYY-000001`, built from the order id by `make_order_number()`.

### JSON endpoints expected by `assets/js/main.js`
| Endpoint | Method | Purpose |
|---|---|---|
| `api/cart.php` | POST | `action=add\|update\|remove`, `variant_id`, `quantity` |
| `api/wishlist.php` | POST | toggle `product_id` (401 if logged out) |
| `api/newsletter.php` | POST | `email` |
| `api/quickview.php` | GET | `id` returns product + variants |
| `api/search-suggest.php` | GET | `q` returns up to 6 results |

---

## 5. Security built in so far

- PDO prepared statements everywhere, emulation off
- Output escaping helper `e()`; CSRF tokens (form field or `X-CSRF-Token` header)
- `HttpOnly` + `SameSite=Lax` session cookie, strict mode, `session_regenerate_id()` on login/logout
- `password_hash()` / `password_verify()`; remember-me uses a selector + SHA-256-hashed validator that is rotated on use
- Login throttling (5 failures / 15 min per account, 20 per IP)
- Open-redirect protection for `?next=`
- Upload validation by real MIME type + `getimagesize()`, random filenames, `.htaccess` blocking script execution in `uploads/`
- Errors are logged to `logs/error.log` and never shown to customers (set `APP_DEBUG = true` only while developing)
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`

**Online payments:** only Cash on Delivery will be implemented. The checkout will contain a clearly labelled, disabled "online payment" placeholder. No payment gateway is integrated.

---

## 6. Replacing placeholder images

Products without photos use `placeholder.php`. To add photos, use **Admin > Products > Edit** (Part 5), or put files in `assets/uploads/products/` and add rows to `product_images`. For the hero, brand and social sections, drop your images into `assets/images/` and update the paths in `index.php`.

---

## 7. Roadmap

| Part | Contents | Status |
|---|---|---|
| 1 | Architecture, DB, config, functions, layout, CSS, JS, README | **Done** |
| 2 | Home, Shop (filters/sort/pagination), Product, Categories, Search, quick view + search APIs | Next |
| 3 | Register, Login, Account, Wishlist, Cart, cart + wishlist APIs | |
| 4 | Checkout, orders, order success, reviews, coupons, contact, newsletter | |
| 5 | Admin panel (dashboard, products, categories, orders, customers, coupons, settings) | |
| 6 | Security review, fixes, responsive polish, About/FAQ/Privacy/Terms, sitemap | |
