# SpeedTech IT Solutions: store and admin

A Laravel 12 store for SpeedTech, Kaduwela. It has two shopping sections:

1. **Laptop spare parts.** Customers search by **Brand → Model → Year / generation → Part**. They can also search by OEM part number.
2. **Universal computer accessories.** SSDs, RAM, chargers, cables, mice and similar items, organised by shop category.

Customers can add priced, in-stock items to a cart and check out. They can choose pickup or delivery, and pay at the store, cash on delivery or by bank transfer. Items with no price or no stock show an enquiry form instead. Repair bookings, part requests and product enquiries are all saved to the database and offer a WhatsApp follow-up link. Staff manage everything at `/admin`.

## Local development

Requirements: PHP 8.2+ with pdo_sqlite (or pdo_mysql) and Composer.

```bash
composer install
cp .env.example .env        # then set ADMIN_EMAIL / ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed  # creates the admin user, catalogue structure and demo products
php artisan serve           # http://127.0.0.1:8000  ·  admin: http://127.0.0.1:8000/admin
```

If `ADMIN_PASSWORD` is empty, the seeder generates a password and prints it once.

Tests:

```bash
php artisan test   # feature tests: finder, cart, checkout and stock, enquiries, admin
node qa.mjs        # browser QA (needs Playwright, with the server running)
```

## Data model

| Table | Purpose |
|---|---|
| `brands` → `laptop_models` → `model_generations` | Finder steps 1–3 |
| `part_categories` | Finder step 4 (LCD display, keyboard, battery…) |
| `categories` | Shop categories for accessories |
| `products` | Spare parts and accessories. Fields: OEM part number, SKU, specification, connector, size, price (empty = "price on request"), stock, warranty, image |
| `model_generation_product` | Which model years a part fits. One part can fit many laptops. |
| `orders`, `order_items` | Orders. Each line keeps a copy of the product name, part number and price at the time of purchase. |
| `enquiries` | Repair bookings, part requests and product enquiries |

Stock is reserved when an order is placed, inside a locked transaction. It is returned if an admin cancels the order.

## Admin (`/admin`)

- **Dashboard:** pending orders, new enquiries, this month's sales, low stock.
- **Orders:** change status, add internal notes, call or WhatsApp the customer.
- **Enquiries & repairs:** follow-up status and notes.
- **Products:** create and edit parts or accessories, upload images (saved to `public/uploads/products`), and tick the compatible model years.
- **Brands / Models & years / Part types / Shop categories:** the finder structure.

## Settings

Shop settings are read from `.env` into `config/speedtech.php`:

`SHOP_PHONE`, `SHOP_WHATSAPP`, `SHOP_EMAIL`, `SHOP_DELIVERY_FEE`, `SHOP_FREE_DELIVERY_OVER`, `SHOP_BANK_DETAILS`, `SHOP_NOTIFY_EMAIL`.

If `SHOP_NOTIFY_EMAIL` is set, the shop gets an e-mail for each new order and enquiry. This also needs the `MAIL_*` settings.

## Deploying to cPanel

1. Create a MySQL database and user in cPanel.
2. Upload the project **outside** `public_html`, for example to `~/speedtech`. Run `composer install --no-dev --optimize-autoloader` there, or upload the `vendor/` folder.
3. Point the domain's document root at `~/speedtech/public`. If you can't change the document root, copy the contents of `public/` into `public_html`, then edit the two paths in `public_html/index.php` so they point to `../speedtech/`.
4. In `.env`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://speedtech.lk`, `DB_CONNECTION=mysql` and the `DB_*` values, `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
5. Run:
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --class=AdminUserSeeder --force
   php artisan db:seed --class=CatalogStructureSeeder --force   # brands, models, part types
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   Run `DemoProductSeeder` only if you want the sample products. **Its prices, stock and part numbers are illustrative.**
6. Make `storage/`, `bootstrap/cache/` and `public/uploads/` writable.

## Updating the live site from GitHub

The live site at https://com.inzra.com is a Git clone of this repository in `~/speedtech` on the server. To publish changes:

1. Commit and push to `main` here.
2. On the server, in cPanel → Terminal:
   ```bash
   cd ~/speedtech && bash deploy.sh
   ```

`deploy.sh` puts the site in maintenance mode, pulls `main`, installs PHP packages, runs database migrations, rebuilds the caches, and brings the site back up. The server's `.env` and the uploaded product images (`public/uploads`) are not in Git, so updates never overwrite them.

## Not included yet

- Online card payments (for example PayHere). Checkout currently records pay-at-store, cash-on-delivery and bank-transfer orders.
- Bulk CSV import for spare parts.
- Customer accounts and order tracking. Customers get a signed confirmation link instead.
