/* SpeedTech storefront — progressive enhancement over server-rendered pages. */
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const csrf = $('meta[name="csrf-token"]')?.content;
let toastTimer;

function showToast(message) {
  const toast = $('#toast');
  if (!toast) return;
  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.classList.add('show');
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
}

async function send(form) {
  const response = await fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
  });
  let data = {};
  try { data = await response.json(); } catch { /* non-JSON error page */ }
  if (!response.ok) {
    const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
    if (response.status === 419) throw new Error('Your session expired. Please refresh the page and try again.');
    if (response.status === 429) throw new Error('Too many attempts. Please wait a minute and try again.');
    throw new Error(firstError || data.message || 'Something went wrong. Please try again or contact us on WhatsApp.');
  }
  return data;
}

function setCartCount(count) {
  const counter = $('#count');
  if (counter) counter.textContent = count;
  const link = $('[data-cart-link]');
  if (link) link.setAttribute('aria-label', `Cart, ${count} ${count === 1 ? 'item' : 'items'}`);
}

// ---------------------------------------------------------------- Dialogs
function resetEnquiryDialog(dialog) {
  const form = $('form', dialog);
  form.hidden = false;
  $('.form-error', form).hidden = true;
  $('.enquiry-done', dialog).hidden = true;
}

function openRepair(service) {
  const dialog = $('#repairDialog');
  resetEnquiryDialog(dialog);
  if (service) $('#service', dialog).value = service;
  dialog.showModal();
}

function openEnquiry({ type, productId = '', subject, details = {} }) {
  const dialog = $('#enquiryDialog');
  const form = $('form', dialog);
  resetEnquiryDialog(dialog);
  form.elements.type.value = type;
  form.elements.product_id.value = productId;
  ['brand', 'model', 'generation', 'part'].forEach(key => { form.elements[`details[${key}]`].value = details[key] || ''; });
  $('#enquiry-title', dialog).textContent = type === 'part' ? 'Request a spare part.' : 'Ask us about this item.';
  $('.enquiry-subject', dialog).textContent = subject;
  dialog.showModal();
}

document.addEventListener('click', event => {
  if (!(event.target instanceof Element)) return;

  const close = event.target.closest('[data-close]');
  if (close) close.closest('dialog').close();

  const repair = event.target.closest('[data-repair], [data-service]');
  if (repair) openRepair(repair.dataset.service);

  const enquire = event.target.closest('[data-enquire]');
  if (enquire) {
    openEnquiry({
      type: 'product',
      productId: enquire.dataset.enquire,
      subject: `${enquire.dataset.enquireName}. We'll confirm price, availability and compatibility.`,
    });
  }

  const request = event.target.closest('[data-request-part]');
  if (request) {
    const details = { brand: request.dataset.brand, model: request.dataset.model, generation: request.dataset.generation, part: request.dataset.part };
    const label = Object.values(details).filter(Boolean).join(' / ');
    openEnquiry({ type: 'part', details, subject: label ? `${label}. We'll check our suppliers and call you back.` : "Tell us your laptop model and the part you need. We'll check our suppliers and call you back." });
  }
});

$$('dialog').forEach(dialog => {
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const b = dialog.getBoundingClientRect();
    if (event.clientX < b.left || event.clientX > b.right || event.clientY < b.top || event.clientY > b.bottom) dialog.close();
  });
});

// Repair / enquiry forms are saved to the shop's database, then offer a WhatsApp follow-up.
$$('form.js-enquiry').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = $('button[type="submit"]', form);
    const error = $('.form-error', form);
    button.disabled = true;
    error.hidden = true;
    try {
      const data = await send(form);
      const done = $('.enquiry-done', form.closest('dialog, [data-enquiry-wrap]'));
      done.innerHTML = '';
      const heading = Object.assign(document.createElement('h3'), { textContent: `Reference ${data.reference}` });
      const text = Object.assign(document.createElement('p'), { textContent: data.message });
      const link = Object.assign(document.createElement('a'), { className: 'button whatsapp-link', href: data.whatsapp, target: '_blank', rel: 'noopener', textContent: 'Follow up on WhatsApp' });
      done.append(heading, text, link);
      form.hidden = true;
      done.hidden = false;
      form.reset();
    } catch (e) {
      error.textContent = e.message;
      error.hidden = false;
    } finally {
      button.disabled = false;
    }
  });
});

// ---------------------------------------------------------------- Cart
$$('form.js-add-to-cart').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = $('button[type="submit"]', form);
    button.disabled = true;
    try {
      const data = await send(form);
      setCartCount(data.cart.count);
      showToast(data.message);
      if (data.ok) {
        button.classList.add('is-added');
        const label = $('span', button);
        if (label) label.textContent = 'Added — view cart';
        button.type = 'button';
        button.addEventListener('click', () => { location.href = $('[data-cart-link]').href; }, { once: true });
      }
    } catch (e) {
      showToast(e.message);
    } finally {
      button.disabled = false;
    }
  });
});

$$('[data-autosubmit]').forEach(control => control.addEventListener('change', () => control.form.requestSubmit ? control.form.requestSubmit() : control.form.submit()));

// ---------------------------------------------------------------- Spare-part finder
function fillSelect(select, items, placeholder, selectedValue = '') {
  select.replaceChildren(new Option(placeholder, ''), ...items.map(item => new Option(item.label ?? item.name, item.id)));
  select.disabled = items.length === 0;
  select.value = selectedValue;
}

async function getJson(url, params) {
  const response = await fetch(`${url}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Could not load the list. Please try again.');
  return response.json();
}

$$('[data-finder]').forEach(form => {
  const brand = $('[data-level="brand"]', form);
  const model = $('[data-level="model"]', form);
  const generation = $('[data-level="generation"]', form);
  const part = $('[data-level="part"]', form);
  const baseNames = new Map([...part.options].map(option => [option.value, option.textContent]));

  async function refreshPartCounts() {
    try {
      const counts = await getJson(form.dataset.partsUrl, { brand: brand.value, model: model.value, generation: generation.value });
      const byId = new Map(counts.map(item => [String(item.id), item.count]));
      [...part.options].forEach(option => {
        if (!option.value) return;
        const count = byId.get(option.value) ?? 0;
        option.textContent = brand.value ? `${baseNames.get(option.value)} (${count})` : baseNames.get(option.value);
      });
    } catch { /* counts are a nicety */ }
  }

  brand.addEventListener('change', async () => {
    fillSelect(model, [], 'Select model');
    fillSelect(generation, [], 'Select year');
    if (brand.value) {
      try {
        fillSelect(model, await getJson(form.dataset.modelsUrl, { brand: brand.value }), 'All models');
      } catch (e) { showToast(e.message); }
    }
    refreshPartCounts();
  });

  model.addEventListener('change', async () => {
    fillSelect(generation, [], 'Select year');
    if (model.value) {
      try {
        fillSelect(generation, await getJson(form.dataset.generationsUrl, { model: model.value }), 'All years');
      } catch (e) { showToast(e.message); }
    }
    refreshPartCounts();
  });

  generation.addEventListener('change', refreshPartCounts);

  // Leave empty selects out of the URL.
  form.addEventListener('submit', () => {
    [brand, model, generation, part].forEach(select => { if (!select.value) select.disabled = true; });
  });
  window.addEventListener('pageshow', () => { [brand, model, generation, part].forEach(select => { select.disabled = select.options.length < 2; }); });

  if (brand.value) refreshPartCounts();
});

// ---------------------------------------------------------------- Checkout
const checkout = $('#checkoutForm');
if (checkout) {
  const subtotal = Number(checkout.dataset.subtotal);
  const fee = Number(checkout.dataset.deliveryFee);
  const currency = checkout.dataset.currency;
  const format = amount => `${currency} ${amount.toLocaleString('en-US', { maximumFractionDigits: 2 })}`;

  function syncCheckout() {
    const delivery = checkout.elements.fulfilment.value === 'delivery';
    const fields = $('[data-delivery-fields]', checkout);
    fields.hidden = !delivery;
    $$('textarea, input', fields).forEach(input => { input.required = delivery; });
    $('[data-delivery-output]', checkout).textContent = delivery ? (fee > 0 ? format(fee) : 'Free') : 'Free';
    $('[data-total-output]', checkout).textContent = format(subtotal + (delivery ? fee : 0));

    // Cash on delivery only for delivery; paying in store only for collection.
    const hidden = delivery ? 'pay_at_store' : 'cod';
    $$('[data-payment]', checkout).forEach(option => {
      option.hidden = option.dataset.payment === hidden;
      const radio = $('input', option);
      if (option.hidden && radio.checked) {
        radio.checked = false;
        $(`[data-payment="${delivery ? 'cod' : 'pay_at_store'}"] input`, checkout).checked = true;
      }
    });
  }

  checkout.addEventListener('change', syncCheckout);
  checkout.addEventListener('submit', () => { $('button[type="submit"]', checkout).disabled = true; });
  syncCheckout();
}
