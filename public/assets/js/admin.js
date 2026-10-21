/* Dashboard admin V-TAK (JS murni). Otorisasi sebenarnya dilakukan backend (middleware "admin"). */
(async function () {
  VT.area = 'admin';
  const me = await VT.api('/me').catch(() => null);
  if (!me || me.role !== 'admin') { location.href = '/login.html'; return; }

  const A = (p, o) => VT.api('/admin' + p, o);
  const $c = VT.$('#content');
  const E = VT.esc;
  const yn = (v) => (v ? '<span class="badge good">Aktif</span>' : '<span class="badge bad">Nonaktif</span>');
  const localInput = (iso) => (iso ? new Date(iso).toLocaleString('sv-SE', { timeZone: 'Asia/Jakarta' }).replace(' ', 'T').slice(0, 16) : '');

  // ---------- helper UI ----------
  function modal(title, html, wide) {
    const bg = document.createElement('div'); bg.className = 'modal-bg';
    bg.innerHTML = `<div class="modal ${wide ? 'wide' : ''}"><div class="toolbar"><h2 style="margin:0">${E(title)}</h2><span class="sp"></span><button class="btn sm ghost" data-x>✕</button></div><div data-body>${html}</div></div>`;
    document.body.appendChild(bg);
    const close = () => bg.remove();
    bg.querySelector('[data-x]').onclick = close;
    bg.addEventListener('mousedown', (e) => { if (e.target === bg) close(); });
    return { el: bg.querySelector('[data-body]'), close };
  }

  function formModal({ title, fields, values = {}, onSave, wide }) {
    const html = `<form novalidate>${fields.map((f) => {
      const v = values[f.name];
      if (f.type === 'checkbox') return `<label class="check"><input type="checkbox" name="${f.name}" ${v ?? f.def ? 'checked' : ''}> ${E(f.label)}</label>`;
      let input;
      if (f.type === 'select') input = `<select name="${f.name}">${f.options.map(([val, lab]) => `<option value="${E(val)}" ${String(v ?? f.def ?? '') === String(val) ? 'selected' : ''}>${E(lab)}</option>`).join('')}</select>`;
      else if (f.type === 'textarea') input = `<textarea name="${f.name}" rows="3">${E(v ?? '')}</textarea>`;
      else input = `<input name="${f.name}" type="${f.type || 'text'}" value="${E(v ?? f.def ?? '')}" ${f.type === 'number' ? 'step="any"' : ''}>`;
      return `<label>${E(f.label)}</label>${input}${f.hint ? `<small class="muted">${E(f.hint)}</small>` : ''}`;
    }).join('')}<div id="ferr"></div><button class="btn mt" type="submit">Simpan</button></form>`;
    const m = modal(title, html, wide);
    const form = m.el.querySelector('form');
    form.onsubmit = async (e) => {
      e.preventDefault();
      const data = {};
      fields.forEach((f) => {
        const el = form.elements[f.name];
        if (f.type === 'checkbox') data[f.name] = el.checked;
        else if (f.type === 'number') data[f.name] = el.value === '' ? null : Number(el.value);
        else data[f.name] = el.value === '' ? null : el.value;
      });
      const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
      try { await onSave(data); m.close(); }
      catch (err) { m.el.querySelector('#ferr').innerHTML = `<div class="alert bad">${E(err.message)}</div>`; btn.disabled = false; }
    };
    return m;
  }

  const table = (cols, rows) => rows.length
    ? `<div class="tablewrap"><table><thead><tr>${cols.map((c) => `<th>${c.h}</th>`).join('')}</tr></thead><tbody>${rows.map((r) => `<tr>${cols.map((c) => `<td>${c.f(r)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`
    : '<div class="empty">Belum ada data.</div>';

  const pagerHtml = (m) => (m.last_page > 1 ? `<div class="pager"><button class="btn sm ghost" data-pg="${m.current_page - 1}" ${m.current_page <= 1 ? 'disabled' : ''}>‹</button><span class="muted">Hal ${m.current_page} / ${m.last_page}</span><button class="btn sm ghost" data-pg="${m.current_page + 1}" ${m.current_page >= m.last_page ? 'disabled' : ''}>›</button></div>` : '');
  const bindPager = (root, cb) => root.querySelectorAll('[data-pg]').forEach((b) => (b.onclick = () => cb(+b.dataset.pg)));
  const act = (root, name, fn) => root.querySelectorAll(`[data-${name}]`).forEach((b) => (b.onclick = () => fn(b.dataset[name])));
  const guard = async (fn) => { try { await fn(); } catch (e) { VT.toast(e.message, 'err'); } };

  // ---------- Ringkasan ----------
  async function dashboard() {
    const d = await A('/dashboard');
    const st = [['Total transaksi', d.total_orders], ['Transaksi hari ini', d.today_orders], ['Menunggu pembayaran', d.pending], ['Berhasil', d.success], ['Gagal / perlu cek', d.failed], ['Kedaluwarsa', d.expired], ['Total penjualan (berhasil)', VT.rp(d.revenue)]];
    const max = Math.max(1, ...d.chart.map((x) => x.total));
    $c.innerHTML = `<h1>Ringkasan</h1><div class="grid cols-4 keep2">${st.map(([l, v]) => `<div class="stat"><small class="muted">${l}</small><b>${v}</b></div>`).join('')}</div>
      <div class="card mt"><h3>Penjualan 7 hari terakhir</h3>${d.total_orders ? `<div class="bars">${d.chart.map((x) => `<div title="${VT.rp(x.total)}"><i style="height:${Math.round((x.total / max) * 100)}%"></i>${x.date.slice(5)}</div>`).join('')}</div>` : '<div class="empty">Belum ada transaksi.</div>'}</div>
      <p class="muted"><small>Penjualan = jumlah total pesanan dengan top up berhasil. Gagal = pembayaran gagal atau top up gagal/perlu pemeriksaan manual.</small></p>`;
  }

  // ---------- Produk ----------
  let pq = '';
  async function products(page = 1) {
    const [d, cats] = await Promise.all([A('/products' + VT.qs({ page, q: pq })), A('/categories')]);
    $c.innerHTML = `<h1>Produk</h1><div class="toolbar"><input id="pq" type="search" placeholder="Cari produk…" value="${E(pq)}"><button class="btn sm ghost" id="pqb">Cari</button><span class="sp"></span><button class="btn" id="padd">+ Tambah Produk</button></div>
      ${table([
        { h: 'Produk', f: (p) => `<b>${E(p.name)}</b><br><small class="muted">${E(p.category?.name || '')}</small>` },
        { h: 'Nominal', f: (p) => p.variants_count }, { h: 'Transaksi', f: (p) => p.orders_count },
        { h: 'Status', f: (p) => yn(p.is_active) + (p.is_popular ? ' <span class="badge info">Populer</span>' : '') },
        { h: 'Aksi', f: (p) => `<button class="btn sm ghost" data-edit="${p.id}">Edit</button> <button class="btn sm ghost" data-var="${p.id}">Nominal</button> <button class="btn sm ghost" data-img="${p.id}">Gambar</button> <button class="btn sm danger" data-del="${p.id}">Hapus</button>` },
      ], d.items)}${pagerHtml(d.meta)}`;
    VT.$('#pqb').onclick = () => { pq = VT.$('#pq').value.trim(); products(); };
    bindPager($c, products);
    const fields = [
      { name: 'name', label: 'Nama produk' }, { name: 'category_id', label: 'Kategori', type: 'select', options: cats.map((c) => [c.id, c.name]) },
      { name: 'description', label: 'Deskripsi', type: 'textarea' },
      { name: 'needs_user_id', label: 'Butuh User ID', type: 'checkbox', def: true }, { name: 'user_id_label', label: 'Label User ID', def: 'User ID' },
      { name: 'needs_server_id', label: 'Butuh Server ID', type: 'checkbox' }, { name: 'server_id_label', label: 'Label Server ID' },
      { name: 'user_id_hint', label: 'Petunjuk pengisian ID' }, { name: 'provider_code', label: 'Kode produk provider (opsional)' },
      { name: 'sort_order', label: 'Urutan', type: 'number', def: 0 },
      { name: 'is_popular', label: 'Tampilkan di Produk Populer', type: 'checkbox' }, { name: 'is_active', label: 'Aktif', type: 'checkbox', def: true },
    ];
    VT.$('#padd').onclick = () => formModal({ title: 'Tambah produk', fields, onSave: async (v) => { await A('/products', { method: 'POST', body: v }); VT.toast('Produk dibuat.', 'ok'); products(); } });
    act($c, 'edit', (id) => formModal({ title: 'Edit produk', fields, values: d.items.find((x) => x.id == id), onSave: async (v) => { await A('/products/' + id, { method: 'PUT', body: v }); VT.toast('Produk diperbarui.', 'ok'); products(page); } }));
    act($c, 'del', (id) => { if (confirm('Hapus produk ini? Jika sudah punya transaksi, produk hanya dinonaktifkan.')) guard(async () => { const r = await A('/products/' + id, { method: 'DELETE' }); VT.toast('Selesai.', 'ok'); products(page); }); });
    act($c, 'img', (id) => {
      const m = modal('Gambar produk', '<input type="file" accept=".jpg,.jpeg,.png,.webp"><small class="muted">JPG/PNG/WEBP, maks 2 MB. Pastikan Anda berhak memakai gambar.</small><button class="btn mt" id="up">Unggah</button>');
      m.el.querySelector('#up').onclick = () => guard(async () => {
        const f = m.el.querySelector('input').files[0]; if (!f) return VT.toast('Pilih berkas dulu.', 'err');
        const fd = new FormData(); fd.append('image', f);
        await A('/products/' + id + '/image', { method: 'POST', form: fd }); VT.toast('Gambar diperbarui.', 'ok'); m.close(); products(page);
      });
    });
    act($c, 'var', (id) => variants(d.items.find((x) => x.id == id)));
  }

  async function variants(p) {
    const m = modal('Nominal — ' + p.name, '<div class="spinner"></div>', true);
    const vf = [{ name: 'name', label: 'Nama nominal' }, { name: 'price', label: 'Harga jual (Rp)', type: 'number' }, { name: 'original_price', label: 'Harga coret (opsional)', type: 'number' },
      { name: 'provider_sku', label: 'SKU provider (opsional)' }, { name: 'sort_order', label: 'Urutan', type: 'number', def: 0 }, { name: 'is_active', label: 'Aktif', type: 'checkbox', def: true }];
    async function render() {
      const list = await A('/products/' + p.id + '/variants');
      m.el.innerHTML = `<div class="toolbar"><button class="btn sm" id="vadd">+ Tambah nominal</button></div>${table([
        { h: 'Nama', f: (v) => E(v.name) }, { h: 'Harga', f: (v) => VT.rp(v.price) }, { h: 'Status', f: (v) => yn(v.is_active) },
        { h: 'Aksi', f: (v) => `<button class="btn sm ghost" data-ve="${v.id}">Edit</button> <button class="btn sm danger" data-vd="${v.id}">Hapus</button>` }], list)}`;
      m.el.querySelector('#vadd').onclick = () => formModal({ title: 'Tambah nominal', fields: vf, onSave: async (v) => { await A('/products/' + p.id + '/variants', { method: 'POST', body: v }); render(); } });
      act(m.el, 've', (id) => formModal({ title: 'Edit nominal', fields: vf, values: list.find((x) => x.id == id), onSave: async (v) => { await A('/variants/' + id, { method: 'PUT', body: v }); render(); } }));
      act(m.el, 'vd', (id) => { if (confirm('Hapus nominal?')) guard(async () => { await A('/variants/' + id, { method: 'DELETE' }); render(); }); });
    }
    render();
  }

  // ---------- Transaksi ----------
  const of = { q: '', payment_status: '', topup_status: '', from: '', to: '' };
  async function orders(page = 1) {
    const d = await A('/orders' + VT.qs({ ...of, page }));
    const opts = (all, map) => `<option value="">${all}</option>` + Object.entries(map).map(([k, v]) => `<option value="${k}">${v[0]}</option>`).join('');
    const lb = (t, el) => `<div><small class="muted" style="display:block">${t}</small>${el}</div>`;
    $c.innerHTML = `<h1>Transaksi</h1><div class="toolbar" style="align-items:flex-end">
      ${lb('No. pesanan', `<input id="oq" placeholder="Cari no. pesanan" value="${E(of.q)}">`)}
      ${lb('Status pembayaran', `<select id="ops">${opts('Semua status bayar', VT.payStatus)}</select>`)}
      ${lb('Status top up', `<select id="ots">${opts('Semua status top up', VT.topupStatus)}</select>`)}
      ${lb('Dari tanggal', `<input id="ofr" type="date" value="${of.from}">`)}
      ${lb('Sampai tanggal', `<input id="oto" type="date" value="${of.to}">`)}
      <button class="btn sm" id="ogo">Filter</button><button class="btn sm ghost" id="orst">Reset</button></div>
      ${table([
        { h: 'No. Pesanan', f: (o) => `<a href="#" data-o="${o.id}">${E(o.order_no)}</a>` }, { h: 'Produk', f: (o) => `${E(o.product_name)}<br><small class="muted">${E(o.variant_name)}</small>` },
        { h: 'Total', f: (o) => `<span class="price">${VT.rp(o.total)}</span>` }, { h: 'Bayar', f: (o) => VT.badge(VT.payStatus, o.payment_status) },
        { h: 'Top up', f: (o) => (o.payment_status === 'paid' ? VT.badge(VT.topupStatus, o.topup_status) : '—') }, { h: 'Waktu', f: (o) => VT.date(o.created_at) },
      ], d.items)}${pagerHtml(d.meta)}`;
    VT.$('#orst').onclick = () => { Object.assign(of, { q: '', payment_status: '', topup_status: '', from: '', to: '' }); orders(); };
    VT.$('#ops').value = of.payment_status; VT.$('#ots').value = of.topup_status;
    VT.$('#ogo').onclick = () => { of.q = VT.$('#oq').value.trim(); of.payment_status = VT.$('#ops').value; of.topup_status = VT.$('#ots').value; of.from = VT.$('#ofr').value; of.to = VT.$('#oto').value; orders(); };
    bindPager($c, orders);
    $c.querySelectorAll('[data-o]').forEach((a) => (a.onclick = (e) => { e.preventDefault(); orderDetail(a.dataset.o, () => orders(page)); }));
  }

  async function orderDetail(id, done) {
    const o = await A('/orders/' + id);
    const canFix = o.payment_status === 'paid' && o.topup_status !== 'success';
    const m = modal('Pesanan ' + o.order_no, `
      <div class="grid cols-2"><div><small class="muted">Produk</small><div>${E(o.product_name)} — ${E(o.variant_name)}</div></div><div><small class="muted">Tujuan</small><div>${E(o.target_user_id || '-')}${o.target_server_id ? ' (' + E(o.target_server_id) + ')' : ''}</div></div>
      <div><small class="muted">Pelanggan</small><div>${o.customer ? E(o.customer.name) + '<br><small>' + E(o.customer.email) + '</small>' : 'Tamu'}</div></div><div><small class="muted">Metode</small><div>${E(o.payment_method_name)}</div></div>
      <div><small class="muted">Total</small><div class="price">${VT.rp(o.total)}</div><small>Harga ${VT.rp(o.unit_price)} · Diskon ${VT.rp(o.discount)} · Biaya ${VT.rp(o.service_fee)}</small></div>
      <div><small class="muted">Status</small><div>${VT.badge(VT.payStatus, o.payment_status)} ${o.payment_status === 'paid' ? VT.badge(VT.topupStatus, o.topup_status) : ''}</div><small>Ref provider: ${E(o.provider_ref || '-')}</small></div></div>
      <h3 class="mt">Log transaksi</h3><ul class="timeline">${o.logs.map((l) => `<li><small class="muted">${VT.date(l.created_at)}</small><br>${E(l.message || l.event)}</li>`).join('')}</ul>
      <label>Catatan admin</label><textarea id="note" rows="3">${E(o.admin_note || '')}</textarea>
      ${canFix ? `<label>Tandai status top up</label><select id="ts"><option value="">(tidak diubah)</option><option value="manual_review">Perlu pemeriksaan manual</option><option value="failed">Gagal</option></select>` : ''}
      <div class="toolbar mt"><button class="btn" id="osave">Simpan</button>${canFix && ['failed', 'manual_review'].includes(o.topup_status) ? '<button class="btn ghost" id="oretry">Proses ulang top up</button>' : ''}</div>
      <small class="muted">Admin tidak dapat menandai pembayaran sebagai lunas secara manual; status pembayaran hanya berubah lewat verifikasi backend.</small>`, true);
    m.el.querySelector('#osave').onclick = () => guard(async () => { await A('/orders/' + id, { method: 'PUT', body: { admin_note: m.el.querySelector('#note').value || null, topup_status: m.el.querySelector('#ts')?.value || null } }); VT.toast('Disimpan.', 'ok'); m.close(); done(); });
    const r = m.el.querySelector('#oretry');
    if (r) r.onclick = () => guard(async () => { const x = await A('/orders/' + id + '/retry', { method: 'POST' }); VT.toast('Hasil: ' + x.topup_status, 'ok'); m.close(); done(); });
  }

  // ---------- Pelanggan ----------
  let cq = '';
  async function customers(page = 1) {
    const d = await A('/customers' + VT.qs({ page, q: cq }));
    $c.innerHTML = `<h1>Pelanggan</h1><div class="toolbar"><input id="cqi" placeholder="Nama / email" value="${E(cq)}"><button class="btn sm" id="cqb">Cari</button></div>${table([
      { h: 'Nama', f: (u) => `<b>${E(u.name)}</b><br><small class="muted">${E(u.email)}</small>` }, { h: 'HP', f: (u) => E(u.phone || '-') }, { h: 'Pesanan', f: (u) => u.orders_count },
      { h: 'Status', f: (u) => yn(u.is_active) }, { h: 'Daftar', f: (u) => VT.date(u.created_at) },
      { h: 'Aksi', f: (u) => `<button class="btn sm ghost" data-cd="${u.id}">Detail</button> <button class="btn sm ${u.is_active ? 'danger' : ''}" data-ct="${u.id}" data-on="${u.is_active ? 0 : 1}">${u.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>` }], d.items)}${pagerHtml(d.meta)}`;
    VT.$('#cqb').onclick = () => { cq = VT.$('#cqi').value.trim(); customers(); };
    bindPager($c, customers);
    $c.querySelectorAll('[data-ct]').forEach((b) => (b.onclick = () => guard(async () => { await A('/customers/' + b.dataset.ct, { method: 'PUT', body: { is_active: b.dataset.on === '1' } }); customers(page); })));
    act($c, 'cd', (id) => guard(async () => {
      const x = await A('/customers/' + id);
      modal('Pelanggan — ' + x.user.name, `<p>${E(x.user.email)} · ${E(x.user.phone || '-')}</p>${table([{ h: 'No.', f: (o) => E(o.order_no) }, { h: 'Produk', f: (o) => E(o.product_name) + ' — ' + E(o.variant_name) }, { h: 'Total', f: (o) => VT.rp(o.total) }, { h: 'Status', f: (o) => VT.badge(VT.payStatus, o.payment_status) }], x.orders)}`, true);
    }));
  }

  // ---------- Promosi ----------
  async function promos() {
    const [p, b] = await Promise.all([A('/promos'), A('/banners')]);
    $c.innerHTML = `<h1>Promosi</h1><div class="toolbar"><h2 style="margin:0">Kode Promo</h2><span class="sp"></span><button class="btn sm" id="pradd">+ Promo</button></div>${table([
      { h: 'Kode', f: (x) => `<b>${E(x.code)}</b><br><small class="muted">${E(x.description || '')}</small>` }, { h: 'Diskon', f: (x) => (x.type === 'percent' ? x.value + '%' : VT.rp(x.value)) + (x.max_discount ? ` (maks ${VT.rp(x.max_discount)})` : '') },
      { h: 'Terpakai', f: (x) => x.used_count + (x.quota ? ' / ' + x.quota : '') }, { h: 'Berlaku', f: (x) => (x.starts_at ? VT.date(x.starts_at) : '—') + ' s/d ' + (x.ends_at ? VT.date(x.ends_at) : '—') }, { h: 'Status', f: (x) => yn(x.is_active) },
      { h: 'Aksi', f: (x) => `<button class="btn sm ghost" data-pe="${x.id}">Edit</button> <button class="btn sm danger" data-pd="${x.id}">Hapus</button>` }], p)}
      <div class="toolbar mt"><h2 style="margin:0">Banner Beranda</h2><span class="sp"></span><button class="btn sm" id="bnadd">+ Banner</button></div>${table([
      { h: 'Judul', f: (x) => `<b>${E(x.title)}</b><br><small class="muted">${E(x.subtitle || '')}</small>` }, { h: 'Tautan', f: (x) => E(x.link_url || '-') }, { h: 'Urutan', f: (x) => x.sort_order }, { h: 'Status', f: (x) => yn(x.is_active) },
      { h: 'Aksi', f: (x) => `<button class="btn sm ghost" data-be="${x.id}">Edit</button> <button class="btn sm danger" data-bd="${x.id}">Hapus</button>` }], b)}`;
    const pf = [{ name: 'code', label: 'Kode' }, { name: 'description', label: 'Deskripsi' }, { name: 'type', label: 'Jenis', type: 'select', options: [['percent', 'Persen'], ['fixed', 'Nominal tetap (Rp)']] },
      { name: 'value', label: 'Nilai', type: 'number' }, { name: 'max_discount', label: 'Maks. diskon (Rp)', type: 'number' }, { name: 'min_purchase', label: 'Min. pembelian (Rp)', type: 'number', def: 0 },
      { name: 'quota', label: 'Kuota penggunaan', type: 'number', hint: 'Kosongkan = tanpa batas' }, { name: 'starts_at', label: 'Mulai', type: 'datetime-local' }, { name: 'ends_at', label: 'Berakhir', type: 'datetime-local' }, { name: 'is_active', label: 'Aktif', type: 'checkbox', def: true }];
    const bf = [{ name: 'title', label: 'Judul' }, { name: 'subtitle', label: 'Subjudul' }, { name: 'link_url', label: 'Tautan', hint: 'Contoh: /games.html' }, { name: 'sort_order', label: 'Urutan', type: 'number', def: 0 }, { name: 'is_active', label: 'Aktif', type: 'checkbox', def: true }];
    VT.$('#pradd').onclick = () => formModal({ title: 'Tambah promo', fields: pf, onSave: async (v) => { await A('/promos', { method: 'POST', body: v }); promos(); } });
    act($c, 'pe', (id) => { const x = p.find((y) => y.id == id); formModal({ title: 'Edit promo', fields: pf, values: { ...x, starts_at: localInput(x.starts_at), ends_at: localInput(x.ends_at) }, onSave: async (v) => { await A('/promos/' + id, { method: 'PUT', body: v }); promos(); } }); });
    act($c, 'pd', (id) => { if (confirm('Hapus promo?')) guard(async () => { const r = await A('/promos/' + id, { method: 'DELETE' }); promos(); }); });
    VT.$('#bnadd').onclick = () => formModal({ title: 'Tambah banner', fields: bf, onSave: async (v) => { await A('/banners', { method: 'POST', body: v }); promos(); } });
    act($c, 'be', (id) => formModal({ title: 'Edit banner', fields: bf, values: b.find((y) => y.id == id), onSave: async (v) => { await A('/banners/' + id, { method: 'PUT', body: v }); promos(); } }));
    act($c, 'bd', (id) => { if (confirm('Hapus banner?')) guard(async () => { await A('/banners/' + id, { method: 'DELETE' }); promos(); }); });
  }

  // ---------- Laporan ----------
  async function reports() {
    const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
    const from0 = VT.$('#rf')?.value || new Date(Date.now() - 29 * 864e5).toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
    const to0 = VT.$('#rt')?.value || today;
    const d = await A('/reports' + VT.qs({ from: from0, to: to0 }));
    $c.innerHTML = `<h1>Laporan</h1><div class="toolbar" style="align-items:flex-end">
      <div><small class="muted" style="display:block">Dari tanggal</small><input id="rf" type="date" value="${d.from}"></div>
      <div><small class="muted" style="display:block">Sampai tanggal</small><input id="rt" type="date" value="${d.to}"></div>
      <button class="btn sm" id="rg">Tampilkan</button><span class="sp"></span>
      <a class="btn sm ghost" href="/api/admin/reports/export${VT.qs({ from: d.from, to: d.to })}">Ekspor CSV</a></div>
      <div class="grid cols-4 keep2">${[['Total pesanan', d.total_orders], ['Nilai seluruh pesanan', VT.rp(d.total_order_value)], ['Berhasil', d.success], ['Gagal / perlu cek', d.failed], ['Menunggu bayar', d.pending], ['Kedaluwarsa', d.expired], ['Penjualan (berhasil)', VT.rp(d.revenue)]].map(([l, v]) => `<div class="stat"><small class="muted">${l}</small><b>${v}</b></div>`).join('')}</div>
      <div class="card mt"><h3>Rekap harian</h3>${table([{ h: 'Tanggal', f: (r) => r.date }, { h: 'Pesanan', f: (r) => r.orders }, { h: 'Nilai pesanan', f: (r) => VT.rp(r.order_value) }, { h: 'Berhasil', f: (r) => r.success }, { h: 'Penjualan', f: (r) => VT.rp(r.revenue) }], d.daily)}</div>`;
    VT.$('#rg').onclick = reports;
  }

  // ---------- Pengaturan ----------
  async function settings() {
    const [s, m, c, l] = await Promise.all([A('/settings'), A('/payment-methods'), A('/categories'), A('/logs')]);
    $c.innerHTML = `<h1>Pengaturan</h1>
      <div class="alert ${s.mode === 'simulation' ? 'warn' : 'good'}">Mode sistem: <b>${E(s.mode)}</b> ${s.mode === 'simulation' ? '— pembayaran & provider top up adalah SIMULASI. Ubah lewat VTAK_MODE di .env setelah integrasi resmi siap.' : ''}</div>
      <form class="card" id="sf"><h3>Profil situs & kontak</h3><div class="grid cols-2"><div><label>Nama situs</label><input name="site_name" value="${E(s.site_name || '')}"></div><div><label>Tagline</label><input name="tagline" value="${E(s.tagline || '')}"></div>
      <div><label>WhatsApp (angka, mis. 62812…)</label><input name="contact_wa" value="${E(s.contact_wa || '')}"></div><div><label>Email CS</label><input name="contact_email" value="${E(s.contact_email || '')}"></div>
      <div><label>Jam layanan</label><input name="contact_hours" value="${E(s.contact_hours || '')}"></div><div><label>Batas waktu bayar (menit)</label><input name="order_expiry_minutes" type="number" value="${E(s.order_expiry_minutes || 60)}"></div></div><button class="btn mt" type="submit">Simpan</button></form>
      <div class="toolbar mt"><h2 style="margin:0">Metode Pembayaran & Biaya Layanan</h2><span class="sp"></span><button class="btn sm" id="madd">+ Metode</button></div>${table([
      { h: 'Nama', f: (x) => `<b>${E(x.name)}</b><br><small class="muted">${E(x.code)} · ${E(x.type)}</small>` }, { h: 'Biaya', f: (x) => VT.rp(x.fee_flat) + ' + ' + x.fee_percent + '%' }, { h: 'Status', f: (x) => yn(x.is_active) },
      { h: 'Aksi', f: (x) => `<button class="btn sm ghost" data-me="${x.id}">Edit</button> <button class="btn sm danger" data-md="${x.id}">Hapus</button>` }], m)}
      <div class="toolbar mt"><h2 style="margin:0">Kategori</h2><span class="sp"></span><button class="btn sm" id="cadd">+ Kategori</button></div>${table([
      { h: 'Nama', f: (x) => E((x.icon || '') + ' ' + x.name) }, { h: 'Produk', f: (x) => x.products_count }, { h: 'Status', f: (x) => yn(x.is_active) },
      { h: 'Aksi', f: (x) => `<button class="btn sm ghost" data-ce="${x.id}">Edit</button> <button class="btn sm danger" data-cx="${x.id}">Hapus</button>` }], c)}
      <div class="card mt"><h3>Log aktivitas admin (terbaru)</h3>${table([{ h: 'Waktu', f: (x) => VT.date(x.created_at) }, { h: 'Admin', f: (x) => E(x.admin || '-') }, { h: 'Aksi', f: (x) => E(x.action) }, { h: 'Target', f: (x) => E(x.target || '') }, { h: 'IP', f: (x) => E(x.ip || '') }], l.items)}</div>`;
    VT.$('#sf').onsubmit = (e) => { e.preventDefault(); const f = e.target; guard(async () => { await A('/settings', { method: 'PUT', body: Object.fromEntries(['site_name', 'tagline', 'contact_wa', 'contact_email', 'contact_hours', 'order_expiry_minutes'].map((k) => [k, f[k].value || null])) }); VT.toast('Tersimpan.', 'ok'); }); };
    // Lipat bagian Metode Pembayaran, Kategori, dan Log: isi tampil hanya saat judulnya diklik.
    window.__openSec = window.__openSec || new Set();
    const makeCollapse = (key, title, parts, replaceEl) => {
      const det = document.createElement('details');
      const sum = document.createElement('summary');
      det.className = 'collapse';
      sum.textContent = title;
      replaceEl.parentNode.insertBefore(det, replaceEl);
      det.append(sum, ...parts);
      if (!parts.includes(replaceEl) && replaceEl.isConnected) replaceEl.remove();
      det.open = window.__openSec.has(key);
      det.ontoggle = () => (det.open ? window.__openSec.add(key) : window.__openSec.delete(key));
    };
    ['madd', 'cadd'].forEach((id) => {
      const bar = VT.$('#' + id).closest('.toolbar');
      const tbl = bar.nextElementSibling;
      const title = bar.querySelector('h2').textContent;
      bar.querySelector('h2').remove();
      makeCollapse(id, title, [bar, tbl], bar);
    });
    const logCard = [...$c.querySelectorAll('.card.mt')].find((x) => (x.querySelector('h3') || {}).textContent?.startsWith('Log aktivitas'));
    if (logCard) {
      const title = logCard.querySelector('h3').textContent;
      logCard.querySelector('h3').remove();
      makeCollapse('logs', title, [...logCard.children], logCard);
    }

    const mf = [{ name: 'code', label: 'Kode (unik)' }, { name: 'name', label: 'Nama' }, { name: 'type', label: 'Jenis', type: 'select', options: [['qris', 'QRIS'], ['va', 'Virtual Account'], ['ewallet', 'E-Wallet'], ['manual', 'Manual']] },
      { name: 'fee_flat', label: 'Biaya tetap (Rp)', type: 'number', def: 0 }, { name: 'fee_percent', label: 'Biaya persen (%)', type: 'number', def: 0 }, { name: 'sort_order', label: 'Urutan', type: 'number', def: 0 }, { name: 'is_active', label: 'Aktif', type: 'checkbox', def: true }];
    VT.$('#madd').onclick = () => formModal({ title: 'Tambah metode', fields: mf, onSave: async (v) => { await A('/payment-methods', { method: 'POST', body: v }); settings(); } });
    act($c, 'me', (id) => formModal({ title: 'Edit metode', fields: mf, values: m.find((x) => x.id == id), onSave: async (v) => { await A('/payment-methods/' + id, { method: 'PUT', body: v }); settings(); } }));
    act($c, 'md', (id) => { if (confirm('Hapus metode?')) guard(async () => { await A('/payment-methods/' + id, { method: 'DELETE' }); settings(); }); });
    const cf = [{ name: 'name', label: 'Nama' }, { name: 'icon', label: 'Ikon (emoji)' }, { name: 'sort_order', label: 'Urutan', type: 'number', def: 0 }, { name: 'is_active', label: 'Aktif', type: 'checkbox', def: true }];
    VT.$('#cadd').onclick = () => formModal({ title: 'Tambah kategori', fields: cf, onSave: async (v) => { await A('/categories', { method: 'POST', body: v }); settings(); } });
    act($c, 'ce', (id) => formModal({ title: 'Edit kategori', fields: cf, values: c.find((x) => x.id == id), onSave: async (v) => { await A('/categories/' + id, { method: 'PUT', body: v }); settings(); } }));
    act($c, 'cx', (id) => { if (confirm('Hapus kategori?')) guard(async () => { await A('/categories/' + id, { method: 'DELETE' }); settings(); }); });
  }

  // ---------- Developer ----------
  async function developers() {
    const d = await A('/developers');
    const thumb = (x) => (x.photo_url
      ? `<img src="${E(x.photo_url)}" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover">`
      : `<span class="pimg" style="width:42px;height:42px;border-radius:50%;font-size:1.1rem;aspect-ratio:auto">${VT.initial(x.name)}</span>`);
    $c.innerHTML = `<h1>Developer</h1>
      <div class="toolbar"><span class="muted">Tampil di <a href="/developer.html" target="_blank" rel="noopener">/developer.html</a></span><span class="sp"></span><button class="btn" id="dadd">+ Tambah Developer</button></div>
      ${table([
        { h: 'Foto', f: thumb },
        { h: 'Nama', f: (x) => `<b>${E(x.name)}</b><br><small class="muted">${E(x.role || '')}</small>` },
        { h: 'Urutan', f: (x) => x.sort_order },
        { h: 'Status', f: (x) => yn(x.is_active) },
        { h: 'Aksi', f: (x) => `<button class="btn sm ghost" data-dedit="${x.id}">Edit</button> <button class="btn sm ghost" data-dimg="${x.id}">Foto</button> <button class="btn sm danger" data-ddel="${x.id}">Hapus</button>` },
      ], d.items)}
      <form class="card mt" id="dpf"><h3>Tentang proyek V-TAK (tampil di halaman developer)</h3>
        <textarea name="project" rows="4">${E(d.project || '')}</textarea><button class="btn mt" type="submit">Simpan</button></form>`;

    const fields = [
      { name: 'name', label: 'Nama' },
      { name: 'role', label: 'Peran / jabatan', hint: 'Contoh: Backend Developer' },
      { name: 'bio', label: 'Bio singkat', type: 'textarea' },
      { name: 'skills', label: 'Keahlian', hint: 'Pisahkan dengan koma. Contoh: PHP, Laravel, MySQL' },
      { name: 'experience', label: 'Pendidikan / pengalaman', type: 'textarea', hint: 'Satu baris untuk satu item' },
      { name: 'links', label: 'Tautan', type: 'textarea', hint: 'Satu baris: Label | URL. Contoh: GitHub | https://github.com/username (boleh juga mailto:email@contoh.com)' },
      { name: 'sort_order', label: 'Urutan tampil', type: 'number', def: 0 },
      { name: 'is_active', label: 'Tampilkan di halaman publik', type: 'checkbox', def: true },
    ];
    VT.$('#dadd').onclick = () => formModal({ title: 'Tambah developer', fields, wide: true, onSave: async (v) => { await A('/developers', { method: 'POST', body: v }); VT.toast('Developer ditambahkan.', 'ok'); developers(); } });
    act($c, 'dedit', (id) => formModal({ title: 'Edit developer', fields, wide: true, values: d.items.find((x) => x.id == id), onSave: async (v) => { await A('/developers/' + id, { method: 'PUT', body: v }); VT.toast('Diperbarui.', 'ok'); developers(); } }));
    act($c, 'ddel', (id) => { if (confirm('Hapus developer ini?')) guard(async () => { await A('/developers/' + id, { method: 'DELETE' }); developers(); }); });
    act($c, 'dimg', (id) => {
      const dev = d.items.find((x) => x.id == id);
      const m = modal('Foto — ' + dev.name, `<input type="file" accept=".jpg,.jpeg,.png,.webp"><small class="muted">JPG/PNG/WEBP, maks 2 MB. Gunakan foto milik Anda atau yang berhak Anda pakai.</small>
        <div class="toolbar mt"><button class="btn" id="up">Unggah</button>${dev.photo_url ? '<button class="btn danger" id="rm">Hapus foto</button>' : ''}</div>`);
      m.el.querySelector('#up').onclick = () => guard(async () => {
        const f = m.el.querySelector('input').files[0]; if (!f) return VT.toast('Pilih berkas dulu.', 'err');
        const fd = new FormData(); fd.append('image', f);
        await A('/developers/' + id + '/image', { method: 'POST', form: fd }); VT.toast('Foto diperbarui.', 'ok'); m.close(); developers();
      });
      const rm = m.el.querySelector('#rm');
      if (rm) rm.onclick = () => guard(async () => { await A('/developers/' + id + '/image', { method: 'DELETE' }); m.close(); developers(); });
    });
    VT.$('#dpf').onsubmit = (e) => { e.preventDefault(); guard(async () => { await A('/developers-project', { method: 'PUT', body: { project: e.target.project.value || null } }); VT.toast('Tersimpan.', 'ok'); }); };
  }

  // ---------- navigasi ----------
  const views = { dashboard, products, orders, customers, promos, reports, settings, developers };
  async function show(name) {
    document.querySelectorAll('.sidebar [data-view]').forEach((b) => b.classList.toggle('active', b.dataset.view === name));
    VT.loading($c);
    try { await views[name](); } catch (e) { VT.errorBox($c, e.message, () => show(name)); }
  }
  document.querySelectorAll('.sidebar [data-view]').forEach((b) => (b.onclick = () => show(b.dataset.view)));
  VT.$('#logout').onclick = async () => { try { await VT.api('/logout', { method: 'POST' }); } catch (e) {} location.href = '/login.html'; };
  show('dashboard');
})();
