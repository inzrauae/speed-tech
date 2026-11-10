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

## Deploying to cPanel (speedtech.lk)

The live site is **https://speedtech.lk** on the client's cPanel account `speedtech`. The app is a Git clone in `/home/speedtech/speedtech`, and `public_html` is a link to its `public/` folder. Every push to `main` deploys automatically.

```
VS Code: commit + push to main
  → GitHub Action (.github/workflows/deploy.yml): runs the tests
  → cPanel API: Git Version Control pulls main
  → .cpanel.yml runs deploy.sh: composer install, migrate, rebuild caches
```

If a test fails, nothing is deployed. The server's `.env` and the uploaded images in `public/uploads` are not in Git, so deployments never overwrite them.

### One-time setup

1. **PHP:** cPanel → MultiPHP Manager → set `speedtech.lk` to **PHP 8.2 or newer**.
2. **Database:** cPanel → MySQL Databases. Database `speedtech_db`, user `speedtech_user`, and add the user to the database with **ALL PRIVILEGES**.
3. **Clone:** cPanel → Git Version Control → Create.
   - Clone URL: `https://github.com/inzrauae/speed-tech.git`
   - Repository Path: `/home/speedtech/speedtech`
   - Repository Name: `speedtech`

   Do not clone into `public_html`.
4. **`.env`:** cPanel → File Manager → `/home/speedtech/speedtech` → create a file named `.env` with the production settings. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://speedtech.lk`, the `DB_*` values, `ADMIN_EMAIL`/`ADMIN_PASSWORD` and `DEPLOY_PUBLIC_HTML=/home/speedtech/public_html`. Keep this file out of Git.
5. **First deploy:** cPanel → Git Version Control → Manage → Pull or Deploy → **Deploy HEAD Commit**. On the first run, `deploy.sh`:
   - generates the app key if it is missing
   - creates the tables, the admin user, and the brands, models and part types
   - moves the old `public_html` to `public_html.before-laravel-<date>` and links `public_html` to the app's `public/` folder

   If the site does not load, read the log in `~/.cpanel/logs/`.
6. **SSL:** cPanel → SSL/TLS Status → Run AutoSSL. The site redirects to `https://speedtech.lk`.
7. **Auto-deploy from GitHub:**
   - Create a token in cPanel → Security → Manage API Tokens (name it `github-deploy`).
   - In GitHub → repo Settings → Secrets and variables → Actions, add these secrets:

     | Secret | Value |
     |---|---|
     | `CPANEL_HOST` | `speedtech.lk` |
     | `CPANEL_USER` | `speedtech` |
     | `CPANEL_TOKEN` | the token |
     | `CPANEL_REPO_PATH` | `/home/speedtech/speedtech` |

   After this, each push to `main` goes live. Progress shows in the repo's **Actions** tab.

Run `DemoProductSeeder` only if you want the sample products. **Its prices, stock and part numbers are illustrative.**

### Deploying by hand

If GitHub Actions is down, use cPanel → Git Version Control → **Update from Remote**, then **Deploy HEAD Commit**. If the account has Terminal, you can instead run `cd ~/speedtech && bash deploy.sh`.

## Not included yet

- Online card payments (for example PayHere). Checkout currently records pay-at-store, cash-on-delivery and bank-transfer orders.
- Bulk CSV import for spare parts.
- Customer accounts and order tracking. Customers get a signed confirmation link instead.
