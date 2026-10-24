# Smart Recipe Hub — PHP + PostgreSQL sample

## Requirements
- PHP 8.1+ with `pdo_pgsql` enabled
- PostgreSQL
- A local PHP server (XAMPP can run PHP, but PostgreSQL must be installed separately; XAMPP's MySQL is not PostgreSQL)

## 1. Create the database
1. Open pgAdmin or `psql`.
2. Run the first line `CREATE DATABASE smart_recipe;` from `setup.sql` while connected to the default `postgres` database.
3. Connect to `smart_recipe` and run the rest of `setup.sql`.

## 2. Configure PHP
Edit `db.php` and set your PostgreSQL username/password. Do not commit real credentials to GitHub.

Make sure PHP has `pdo_pgsql` enabled. Check with `php -m`. If it is missing, enable/install the PostgreSQL PDO extension for your PHP version and restart the server.

## 3. Create password and PIN hashes
Run:
```bash
php create_hashes.php
```
Copy the two generated hashes. Then, while connected to `smart_recipe`, insert a staff account and a demo secret recipe (replace the placeholder values):
```sql
INSERT INTO app_users (email, password_hash, role)
VALUES ('staff@example.com', 'PASTE_STAFF_PASSWORD_HASH_HERE', 'staff');

INSERT INTO secret_recipes (product_id, slug, title, recipe_text, pin_hash)
SELECT id, 'mango-pickle', 'Confidential Mango Pickle Recipe',
       'Demo secret steps: keep the real recipe confidential and enter the authorized preparation steps here.',
       'PASTE_PIN_HASH_HERE'
FROM products WHERE name = 'Mango Pickle';
```
The sample staff login is:
- Email: `staff@example.com`
- Password: the plaintext password you used in `create_hashes.php` (`ChangeThisStaffPassword!` by default; change it before using).
- PIN: `4826` by default; change it before using.

## 4. Run the website
From this folder:
```bash
php -S 127.0.0.1:8000
```
Open `http://127.0.0.1:8000` in your browser.

## Features in this sample
- Responsive home page
- Ingredient calculator for Mango Pickle, scaling a 500 ml sample formulation
- Print button; the browser print dialog can save as PDF
- PHP session login against PostgreSQL
- Staff/admin role check plus PIN hash verification before returning secret recipe text

## Important prototype limitations
- The calculator's sample ingredient values are currently in `script.js` for clarity; a next step is to load them from PostgreSQL through a PHP API.
- Add CSRF protection, persistent login throttling/rate limiting, security audit logs, HTTPS, secure cookie settings, and admin-only account/recipe management before real deployment.
- Use a strong unique staff password and PIN. Never use the demo credentials in production.
- Do not store an actual hotel's confidential recipe in a demo/test database.
- The example quantities are illustrative only and are not food safety or production guidance.
