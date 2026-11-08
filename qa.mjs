// Browser QA for the Laravel storefront. Start the app first: php artisan serve
// Run: node qa.mjs [--visual]   (BASE_URL defaults to http://127.0.0.1:8000)
import { chromium } from 'file:///C:/Users/inzra/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright/index.mjs';
import { mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';

// The checkout test places a real order, which uses up demo stock. Reset it first.
execSync('php artisan db:seed --class=DemoProductSeeder --force', { stdio: 'ignore' });

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
await mkdir('.ui-review', { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: 'C:/Users/inzra/AppData/Local/ms-playwright/chromium_headless_shell-1248/chrome-headless-shell-win64/chrome-headless-shell.exe' });

try {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  await page.emulateMedia({ reducedMotion: 'reduce' });

  const firstPart = await (await fetch(`${BASE}/spare-parts?q=WDX0R`)).text();
  const productPath = firstPart.match(/href="[^"]*(\/products\/[^"]+)"/)?.[1] ?? '/products/dell-41wh-battery-wdx0r';
  const pages = { home: '/', about: '/about', contact: '/contact', repairs: '/repairs', service: '/repairs/motherboard-repair', shop: '/shop', parts: '/spare-parts', results: '/spare-parts?brand=1', product: productPath, cart: '/cart' };

  for (const width of [1440, 1024, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    for (const [name, path] of Object.entries(pages)) {
      const response = await page.goto(BASE + path, { waitUntil: 'networkidle' });
      assert.equal(response.status(), 200, `${path} status`);
      const sizes = await page.evaluate(() => ({ document: document.documentElement.scrollWidth, viewport: innerWidth }));
      assert.ok(sizes.document <= sizes.viewport, `Overflow on ${name} at ${width}: ${JSON.stringify(sizes)}`);
      const broken = await page.locator('img').evaluateAll(imgs => imgs.filter(i => i.complete && !i.naturalWidth).map(i => i.src));
      assert.deepEqual(broken, [], `Broken images on ${name}`);
      if ([1440, 390].includes(width)) await page.screenshot({ path: `.ui-review/${name}-${width}.png`, fullPage: true });
    }
    console.log(`Layout passed at ${width}px`);
  }

  if (!process.argv.includes('--visual')) {
    await page.setViewportSize({ width: 1440, height: 1000 });

    // Cascading finder on the home page
    await page.goto(BASE, { waitUntil: 'networkidle' });
    await page.locator('[data-finder] [name=brand]').selectOption({ label: 'Dell' });
    await page.waitForFunction(() => document.querySelector('[data-finder] [name=model]').options.length > 1);
    await page.locator('[data-finder] [name=model]').selectOption({ label: 'Inspiron 15 3520' });
    await page.waitForFunction(() => document.querySelector('[data-finder] [name=generation]').options.length > 1);
    await page.locator('[data-finder] [name=generation]').selectOption({ index: 1 });
    await page.waitForFunction(() => [...document.querySelector('[data-finder] [name=part]').options].some(o => /\(\d+\)/.test(o.textContent)));
    await page.locator('[data-finder] [name=part]').selectOption(await page.locator('[data-finder] [name=part] option', { hasText: 'Battery' }).getAttribute('value'));
    await page.locator('[data-finder] button[type=submit]').click();
    await page.waitForURL(/spare-parts\?brand=\d+&model=\d+&generation=\d+&part=\d+/);
    assert.ok(await page.locator('.part-row').count() >= 1, 'finder results');
    console.log('Finder cascade passed:', new URL(page.url()).search);

    // Add to cart via fetch
    await page.locator('.part-row .js-add-to-cart button').first().click();
    await page.waitForFunction(() => document.querySelector('#count').textContent === '1');
    console.log('Add to cart passed');

    // Enquiry for an item without price
    await page.goto(`${BASE}/spare-parts?q=motherboard`, { waitUntil: 'networkidle' });
    await page.locator('[data-enquire]').first().click();
    const dialog = page.locator('#enquiryDialog');
    await dialog.locator('[name=name]').fill('QA Tester');
    await dialog.locator('[name=phone]').fill('077 123 4567');
    await dialog.locator('button[type=submit]').click();
    await dialog.locator('.enquiry-done').waitFor();
    assert.match(await dialog.locator('.enquiry-done').innerText(), /Reference PE/);
    console.log('Product enquiry passed');

    // Repair booking
    await page.goto(BASE, { waitUntil: 'networkidle' });
    await page.locator('[data-service="Data recovery"]').click();
    const repair = page.locator('#repairDialog');
    assert.equal(await repair.locator('#service').inputValue(), 'Data recovery');
    await repair.locator('[name=name]').fill('QA Tester');
    await repair.locator('[name=phone]').fill('0771234567');
    await repair.locator('[name=device]').fill('HP Pavilion 15');
    await repair.locator('[name=message]').fill('Drive not detected');
    await repair.locator('button[type=submit]').click();
    await repair.locator('.enquiry-done').waitFor();
    assert.match(await repair.locator('.enquiry-done a').getAttribute('href'), /wa\.me/);
    await page.keyboard.press('Escape');
    console.log('Repair booking passed');

    // Contact page form (saved as a general enquiry)
    await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
    const contact = page.locator('.contact-form');
    await contact.locator('[name=name]').fill('QA Tester');
    await contact.locator('[name=phone]').fill('0771234567');
    await contact.locator('[name=message]').fill('Do you have HP chargers?');
    await contact.locator('button[type=submit]').click();
    await contact.locator('.enquiry-done').waitFor();
    assert.match(await contact.locator('.enquiry-done').innerText(), /Reference EQ/);
    console.log('Contact form passed');

    // Mobile quick actions visible only on phones
    assert.ok(await page.locator('.mobile-actions').isHidden(), 'mobile bar hidden on desktop');
    await page.setViewportSize({ width: 390, height: 800 });
    assert.ok(await page.locator('.mobile-actions .ma-call').isVisible(), 'call button on mobile');
    assert.ok(await page.locator('.mobile-actions .ma-whatsapp').isVisible(), 'WhatsApp button on mobile');
    await page.setViewportSize({ width: 1440, height: 1000 });
    console.log('Mobile call / WhatsApp bar passed');

    // Checkout
    await page.goto(`${BASE}/checkout`, { waitUntil: 'networkidle' });
    assert.ok(await page.locator('[data-delivery-fields]').isHidden(), 'address hidden for pickup');
    await page.locator('input[name=fulfilment][value=delivery]').check();
    assert.ok(await page.locator('[data-delivery-fields]').isVisible(), 'address shown for delivery');
    assert.ok(await page.locator('[data-payment="pay_at_store"]').isHidden());
    await page.screenshot({ path: '.ui-review/checkout-1440.png', fullPage: true });
    await page.locator('#checkoutForm [name=customer_name]').fill('QA Tester');
    await page.locator('#checkoutForm [name=phone]').fill('0771234567');
    await page.locator('#checkoutForm [name=address]').fill('12 Temple Road');
    await page.locator('#checkoutForm [name=city]').fill('Malabe');
    await page.locator('#checkoutForm button[type=submit]').click();
    await page.waitForURL(/\/orders\/ST/);
    await page.screenshot({ path: '.ui-review/order-1440.png', fullPage: true });
    assert.equal(await page.locator('#count').innerText(), '0');
    console.log('Checkout passed:', await page.locator('.lead b').innerText());
  }

  assert.deepEqual(errors, [], 'Browser JavaScript errors');
  console.log('Browser JavaScript errors: none.');
} finally {
  await browser.close();
}
