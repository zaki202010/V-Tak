/* Halaman top up + checkout: pilih nominal, isi tujuan, pilih pembayaran, lihat ringkasan, buat pesanan.
   Harga di ringkasan hanya tampilan; total final SELALU dihitung ulang oleh backend. */
(async function () {
  await VT.init('');
  const root = VT.$('#topup');
  const slug = VT.param('slug');
  if (!slug) return VT.empty(root, 'Produk tidak dipilih. Buka katalog untuk memilih produk.');
  VT.loading(root);

  let product, methods;
  try { [product, methods] = await Promise.all([VT.api('/products/' + encodeURIComponent(slug)), VT.api('/payment-methods')]); }
  catch (e) { return e.status === 404 ? VT.empty(root, 'Produk tidak ditemukan atau tidak aktif.') : VT.errorBox(root, e.message, () => location.reload()); }
  document.title = 'Top Up ' + product.name + ' — V-TAK';

  const sel = { variant: null, method: null, promo: '' };
  let idem = null, quote = null;

  const opt = (cls, attrs, html, on) => `<button type="button" class="opt ${cls}" ${attrs}>${html}</button>`;
  root.innerHTML = `
  <div class="layout"><div>
    <div class="card step" style="display:flex;gap:16px;align-items:center">
      <div class="pimg" style="width:84px;border-radius:14px;flex:none">${product.image_url ? `<img src="${VT.esc(product.image_url)}" alt="">` : VT.initial(product.name)}</div>
      <div><h1 style="font-size:1.6rem;margin:0">${VT.esc(product.name)}</h1><span class="badge">${VT.esc(product.category?.name || '')}</span>
      <p class="muted" style="margin:6px 0 0">${VT.esc(product.description || '')}</p></div></div>
    <form id="tf" novalidate>
    <div class="card step"><h3><span class="num">1</span> Data tujuan</h3>
      ${product.needs_user_id ? `<label for="uid">${VT.esc(product.user_id_label)}</label><input id="uid" name="target_user_id" maxlength="50" autocomplete="off" placeholder="${VT.esc(product.user_id_label)}">` : '<p class="muted">Produk ini tidak membutuhkan data tujuan.</p>'}
      ${product.needs_server_id ? `<label for="sid">${VT.esc(product.server_id_label)}</label><input id="sid" name="target_server_id" maxlength="20" autocomplete="off" placeholder="${VT.esc(product.server_id_label)}">` : ''}
      ${product.user_id_hint ? `<small class="muted">${VT.esc(product.user_id_hint)}</small>` : ''}
      <div class="alert warn" style="font-size:.85rem">V-TAK tidak pernah meminta kata sandi akun game Anda. Pastikan ID benar — kesalahan ID tidak dapat dibatalkan.</div></div>
    <div class="card step"><h3><span class="num">2</span> Pilih nominal</h3>
      ${product.variants.length ? `<div class="grid cols-3" id="variants">${product.variants.map((v) => opt('', `data-id="${v.id}"`, `<b>${VT.esc(v.name)}</b>${v.original_price ? `<s>${VT.rp(v.original_price)}</s> ` : ''}<span class="price">${VT.rp(v.price)}</span>`)).join('')}</div>` : '<div class="empty">Belum ada nominal tersedia.</div>'}</div>
    <div class="card step"><h3><span class="num">3</span> Metode pembayaran</h3>
      ${methods.length ? `<div class="grid cols-2" id="methods">${methods.map((m) => opt('', `data-code="${VT.esc(m.code)}"`, `<b>${VT.esc(m.name)}</b><small>Biaya layanan ${m.fee_flat ? VT.rp(m.fee_flat) : ''}${m.fee_flat && Number(m.fee_percent) ? ' + ' : ''}${Number(m.fee_percent) ? Number(m.fee_percent) + '%' : (m.fee_flat ? '' : 'Rp0')}</small>`)).join('')}</div>` : '<div class="alert bad">Belum ada metode pembayaran aktif.</div>'}</div>
    <div class="card step"><h3><span class="num">4</span> Kode promo (opsional)</h3>
      <div style="display:flex;gap:8px"><input id="promo" maxlength="30" placeholder="Contoh: DEMO10" autocomplete="off"><button type="button" class="btn ghost" id="apply">Pakai</button></div><div id="promo-msg"></div></div>
    </form></div>
    <aside class="card sticky"><h3>Ringkasan pesanan</h3><div id="sum" class="muted">Pilih nominal dan metode pembayaran.</div>
      <button class="btn block mt" id="go" disabled>Buat Pesanan</button><div id="form-err"></div></aside></div>`;

  const sum = VT.$('#sum'), go = VT.$('#go');

  function pick(container, attr, key) {
    const c = VT.$(container); if (!c) return;
    c.querySelectorAll('.opt').forEach((b) => (b.onclick = () => {
      c.querySelectorAll('.opt').forEach((x) => x.classList.remove('sel')); b.classList.add('sel');
      sel[key] = b.dataset[attr]; idem = null; refresh();
    }));
  }
  pick('#variants', 'id', 'variant'); pick('#methods', 'code', 'method');
  VT.$('#promo').oninput = () => { idem = null; };
  VT.$('#apply').onclick = () => { sel.promo = VT.$('#promo').value.trim(); idem = null; refresh(true); };

  async function refresh(showPromoMsg) {
    quote = null; go.disabled = true; const pm = VT.$('#promo-msg'); if (showPromoMsg) pm.innerHTML = '';
    if (!sel.variant || !sel.method) return;
    sum.innerHTML = '<div class="spinner"></div>';
    try {
      quote = await VT.api('/quote', { method: 'POST', body: { variant_id: +sel.variant, payment_method: sel.method, promo_code: sel.promo } });
      if (showPromoMsg && sel.promo) pm.innerHTML = '<div class="alert good">Kode promo diterapkan.</div>';
      const v = product.variants.find((x) => String(x.id) === String(sel.variant));
      sum.innerHTML = `<div class="row"><span>${VT.esc(product.name)}</span><span>${VT.esc(v.name)}</span></div>
        <div class="row"><span>Harga</span><span>${VT.rp(quote.unit_price)}</span></div>
        <div class="row"><span>Diskon</span><span>-${VT.rp(quote.discount)}</span></div>
        <div class="row"><span>Biaya layanan</span><span>${VT.rp(quote.service_fee)}</span></div>
        <div class="row total"><span>Total</span><span class="price">${VT.rp(quote.total)}</span></div>`;
      go.disabled = false;
    } catch (e) {
      sum.innerHTML = '<span class="muted">Ringkasan belum tersedia.</span>';
      if (e.errors && e.errors.promo_code) { pm.innerHTML = `<div class="alert bad">${VT.esc(e.errors.promo_code[0])}</div>`; sel.promo = ''; refresh(); }
      else VT.toast(e.message, 'err');
    }
  }

  go.onclick = async () => {
    const f = VT.$('#tf'); VT.$('#form-err').innerHTML = '';
    f.querySelectorAll('.err').forEach((x) => x.remove());
    go.disabled = true; go.textContent = 'Memproses…';
    if (!idem) idem = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random()).replace(/[^a-zA-Z0-9-]/g, '').slice(0, 64);
    try {
      const d = await VT.api('/orders', { method: 'POST', body: {
        variant_id: +sel.variant, payment_method: sel.method, promo_code: sel.promo || null, idempotency_key: idem,
        target_user_id: f.target_user_id ? f.target_user_id.value.trim() : null,
        target_server_id: f.target_server_id ? f.target_server_id.value.trim() : null } });
      location.href = '/payment.html?order=' + encodeURIComponent(d.order_no) + '&token=' + encodeURIComponent(d.tracking_token);
    } catch (e) {
      Object.entries(e.errors || {}).forEach(([k, m]) => { const i = f.querySelector(`[name="${k}"]`); if (i) { const d = document.createElement('div'); d.className = 'err'; d.textContent = m[0]; i.after(d); } });
      VT.$('#form-err').innerHTML = `<div class="alert bad">${VT.esc(e.message)}</div>`;
      go.disabled = false; go.textContent = 'Buat Pesanan';
    }
  };
})();
