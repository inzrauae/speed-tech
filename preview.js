/* Static design-preview shim: no server behind this copy of the site. */
(() => {
  const P = window.SPEEDTECH_PREVIEW || { models: {}, generations: {}, parts: {}, files: [] };
  const MESSAGE = 'This is a design preview. Ordering, bookings and forms will work on the live website. For now, please contact SpeedTech on WhatsApp or by phone.';

  // Banner
  const bar = document.createElement('div');
  bar.className = 'preview-banner';
  bar.innerHTML = '<b>Design preview</b><span>Products, prices and stock shown are sample data.</span>';
  document.body.prepend(bar);

  // Serve the spare-part finder lists from exported data.
  const realFetch = window.fetch.bind(window);
  window.fetch = (input, init) => {
    const url = typeof input === 'string' ? input : input.url;
    if (url.includes('api/finder/')) {
      const u = new URL(url, location.href);
      const kind = u.pathname.split('/').pop();
      const q = u.searchParams;
      let data = [];
      if (kind === 'models') data = P.models[q.get('brand')] || [];
      else if (kind === 'generations') data = P.generations[q.get('model')] || [];
      else if (kind === 'parts') {
        const key = [q.get('brand'), q.get('model')].filter(Boolean).join('-');
        data = P.parts[key] || P.parts[q.get('brand') || ''] || P.parts[''] || [];
      }
      return Promise.resolve(new Response(JSON.stringify(data), { headers: { 'Content-Type': 'application/json' } }));
    }
    return realFetch(input, init);
  };

  const pageFor = params => {
    const q = Object.entries(params).filter(([, v]) => v).sort(([a], [b]) => (a < b ? -1 : 1));
    return 'spare-parts' + (q.length ? '--' + q.map(([k, v]) => `${k}-${v}`).join('-') : '') + '.html';
  };

  function notice(form) {
    const box = form && form.querySelector('.form-error');
    if (box) { box.textContent = MESSAGE; box.hidden = false; }
    const toast = document.getElementById('toast');
    if (toast) {
      toast.textContent = 'Design preview: this works on the live website.';
      toast.classList.add('show');
      clearTimeout(notice.t);
      notice.t = setTimeout(() => toast.classList.remove('show'), 3200);
    }
  }

  // Capture every form submit before the live-site handlers run.
  document.addEventListener('submit', event => {
    const form = event.target;
    event.preventDefault();
    event.stopImmediatePropagation();

    if (form.matches('[data-finder]')) {
      const v = name => (form.elements[name] && form.elements[name].value) || '';
      const brand = v('brand'), model = v('model'), part = v('part');
      const options = [{ brand, model, part }, { brand, model }, { brand, part }, { brand }, { part }, {}];
      for (const option of options) {
        const file = pageFor(option);
        if (P.files.includes(file)) { location.href = file; return; }
      }
    }
    notice(form);
  }, true);
})();
