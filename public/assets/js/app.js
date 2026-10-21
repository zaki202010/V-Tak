/* Layout & utilitas bersama V-TAK. Semua teks dari server di-escape (VT.esc) sebelum masuk innerHTML. */
VT.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
VT.rp = (n) => 'Rp' + Number(n || 0).toLocaleString('id-ID');
VT.date = (d) => (d ? new Date(d).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short' }) + ' WIB' : '-');
VT.$ = (s, r = document) => r.querySelector(s);
VT.param = (k) => new URLSearchParams(location.search).get(k);

VT.payStatus = { pending: ['Menunggu pembayaran', 'warn'], paid: ['Dibayar', 'good'], expired: ['Kedaluwarsa', 'bad'], failed: ['Gagal', 'bad'], refunded: ['Dikembalikan', 'info'] };
VT.topupStatus = { waiting: ['Menunggu proses', 'warn'], processing: ['Diproses', 'info'], success: ['Berhasil', 'good'], failed: ['Gagal', 'bad'], manual_review: ['Perlu pemeriksaan manual', 'warn'] };
VT.badge = (map, k) => { const [t, c] = map[k] || [k, '']; return `<span class="badge ${c}">${VT.esc(t)}</span>`; };

VT.toast = (msg, type = '') => {
  let box = VT.$('.toasts');
  if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
  const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; box.appendChild(t);
  setTimeout(() => t.remove(), 4500);
};

VT.initial = (name) => VT.esc((name || '?').trim().charAt(0).toUpperCase());

VT.productCard = (p) => `
  <a class="pcard" href="/topup.html?slug=${encodeURIComponent(p.slug)}">
    <div class="pimg">${p.image_url ? `<img src="${VT.esc(p.image_url)}" alt="${VT.esc(p.name)}" loading="lazy">` : VT.initial(p.name)}</div>
    <div class="pbody"><b>${VT.esc(p.name)}</b><small>${VT.esc(p.category ? p.category.name : '')}</small>
      <div>${p.min_price != null ? `<small>Mulai</small> <span class="price">${VT.rp(p.min_price)}</span>` : '<small>Belum ada nominal</small>'}</div></div>
  </a>`;

VT.loading = (el) => { el.innerHTML = '<div class="spinner" role="status" aria-label="Memuat"></div>'; };
VT.empty = (el, msg) => { el.innerHTML = `<div class="empty">${VT.esc(msg)}</div>`; };
VT.errorBox = (el, msg, retry) => {
  el.innerHTML = `<div class="alert bad">${VT.esc(msg)} ${retry ? '<button class="btn sm ghost" id="vt-retry">Coba lagi</button>' : ''}</div>`;
  if (retry) VT.$('#vt-retry', el).onclick = retry;
};

VT.init = async function (active) {
  const [user, site] = await Promise.all([VT.api('/me').catch(() => null), VT.api('/site').catch(() => ({ site_name: 'V-TAK', mode: 'simulation' }))]);
  VT.user = user; VT.site = site;
  const link = (href, label, key) => `<a href="${href}" class="${active === key ? 'active' : ''}">${label}</a>`;
  const auth = user
    ? `<a class="btn sm ghost" href="/history.html">Riwayat</a><a class="btn sm ghost" href="/profile.html">${VT.esc(user.name.split(' ')[0])}</a>
       <button class="btn sm" id="vt-logout">Keluar</button>`
    : '<a class="btn sm ghost" href="/login.html">Masuk</a><a class="btn sm" href="/register.html">Daftar</a>';
  const h = VT.$('#site-header');
  if (h) {
    h.className = 'site-header';
    h.innerHTML = `
      <div class="container nav" id="nav"><a class="logo-link" href="/"><img class="logo-img" src="/assets/images/logo.png" alt="V-TAK" onerror="this.replaceWith(document.createTextNode('V-TAK'))"></a>
        <nav class="nav-links">${link('/', 'Beranda', 'home')}${link('/games.html?group=game', 'Game', 'game')}${link('/games.html?group=digital', 'Produk Digital', 'digital')}
          ${link('/transaction-status.html', 'Cek Transaksi', 'track')}${link('/info.html?p=bantuan', 'Bantuan', 'help')}</nav>
        <div class="nav-auth">${auth}</div>
        <button class="btn sm ghost theme-btn" data-theme-btn aria-label="Ganti tema">☀️</button>
        <button class="burger" aria-label="Menu" id="vt-burger">☰</button></div>`;
    VT.$('#vt-burger').onclick = () => VT.$('#nav').classList.toggle('open');
    const lo = VT.$('#vt-logout');
    if (lo) lo.onclick = async () => { try { await VT.api('/logout', { method: 'POST' }); await VT.csrf(true); } catch (e) {} location.href = '/'; };
  }
  const pms = await VT.api('/payment-methods').catch(() => []);
  const f = VT.$('#site-footer');
  if (f) {
    f.className = 'site-footer';
    const wa = site.contact_wa ? `<a href="https://wa.me/${VT.esc(site.contact_wa)}" target="_blank" rel="noopener">💬 +${VT.esc(site.contact_wa)}</a>` : '';
    const mail = site.contact_email ? `<a href="mailto:${VT.esc(site.contact_email)}">✉️ ${VT.esc(site.contact_email)}</a>` : '';
    const hours = site.contact_hours ? `<span class="muted">🕘 ${VT.esc(site.contact_hours)}</span>` : '';
    f.innerHTML = `<div class="container">
      <div class="cols">
        <div class="f-brand">
          <a class="logo-link" href="/"><img class="logo-img" src="/assets/images/logo.png" alt="V-TAK" onerror="this.replaceWith(document.createTextNode('V-TAK'))"></a>
          <p class="muted">${VT.esc(site.tagline || 'Top up game & produk digital, cepat dan transparan.')}</p>
        </div>
        <div>
          <h4>Halaman</h4>
          <a href="/">Beranda</a>
          <a href="/games.html?group=game">Game</a>
          <a href="/games.html?group=digital">Produk Digital</a>
          <a href="/transaction-status.html">Cek Transaksi</a>
        </div>
        <div>
          <h4>Member</h4>
          ${user
            ? '<a href="/profile.html">Profil</a><a href="/history.html">Riwayat Transaksi</a>'
            : '<a href="/register.html">Daftar Member</a><a href="/login.html">Login Member</a>'}
        </div>
        <div>
          <h4>Informasi &amp; Lainnya</h4>
          <a href="/info.html?p=bantuan">Pusat Bantuan</a>
          <a href="/info.html?p=syarat">Syarat &amp; Ketentuan</a>
          <a href="/info.html?p=privasi">Kebijakan Privasi</a>
          <a href="/info.html?p=tentang">Tentang V-TAK</a>
          <a href="/developer.html">Tentang Pembuat</a>
        </div>
        <div>
          <h4>Kontak</h4>
          ${mail}${wa}${hours}
          <a href="/contact.html">Halaman Kontak</a>
        </div>
      </div>
      ${pms.length ? `<div class="f-pay"><h4>Pembayaran Tersedia</h4><div class="pay-row">${pms.map((m) => `<span class="pay-chip">${VT.esc(m.name.replace(/\s*\(Simulasi\)/, ''))}</span>`).join('')}</div></div>` : ''}
      <p class="f-copy">© ${new Date().getFullYear()} V-TAK. Seluruh nama game adalah milik pemegang mereknya masing-masing.</p>
    </div>`;
  }
  return { user, site };
};

VT.requireLogin = () => { if (!VT.user) location.href = '/login.html?next=' + encodeURIComponent(location.pathname + location.search); return !!VT.user; };
