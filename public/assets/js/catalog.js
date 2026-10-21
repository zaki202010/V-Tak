/* Beranda (index.html) dan katalog (games.html). Data dari Laravel API / MySQL. */
(async function () {
  const isHome = !!VT.$('#home-popular');
  const group = VT.param('group');
  await VT.init(isHome ? 'home' : group === 'digital' ? 'digital' : group === 'game' ? 'game' : '');

  // Kategori
  let cats = [];
  try { cats = await VT.api('/categories'); } catch (e) {}
  const chips = VT.$('#cat-chips');
  if (chips) chips.innerHTML = cats.map((c) => `<a class="chip" href="/games.html?category=${encodeURIComponent(c.slug)}"><span>${VT.esc(c.icon || '')}</span>${VT.esc(c.name)}</a>`).join('');

  if (isHome) {
    const pop = VT.$('#home-popular');
    VT.loading(pop);
    const loadPop = async () => {
      try {
        const d = await VT.api('/products' + VT.qs({ popular: 1, per_page: 8 }));
        pop.className = 'grid products';
        pop.innerHTML = d.items.length ? d.items.map(VT.productCard).join('') : '';
        if (!d.items.length) { pop.className = ''; VT.empty(pop, 'Belum ada produk populer.'); }
      } catch (e) { pop.className = ''; VT.errorBox(pop, e.message, loadPop); }
    };
    loadPop();
    VT.api('/banners').then((b) => {
      const el = VT.$('#home-banners');
      if (!b.length) { el.remove(); return; }
      el.innerHTML = b.map((x) => `<a class="banner" ${x.link_url ? `href="${VT.esc(x.link_url)}"` : ''} style="color:#fff"><h3>${VT.esc(x.title)}</h3><span>${VT.esc(x.subtitle || '')}</span></a>`).join('');
    }).catch(() => VT.$('#home-banners')?.remove());
    VT.$('#hero-search').onsubmit = (e) => { e.preventDefault(); location.href = '/games.html' + VT.qs({ q: VT.$('#hero-q').value.trim() }); };
    return;
  }

  // Katalog
  const state = { q: VT.param('q') || '', category: VT.param('category') || '', group: group || '', sort: '', page: 1 };
  const grid = VT.$('#grid'), pager = VT.$('#pager');
  const catSel = VT.$('#f-cat');
  catSel.innerHTML = '<option value="">Semua kategori</option>' + cats.map((c) => `<option value="${VT.esc(c.slug)}">${VT.esc(c.name)}</option>`).join('');
  catSel.value = state.category; VT.$('#f-q').value = state.q;
  VT.$('#page-title').textContent = group === 'digital' ? 'Produk Digital' : group === 'game' ? 'Game' : 'Semua Produk';

  async function load() {
    VT.loading(grid); pager.innerHTML = ''; grid.className = '';
    try {
      const d = await VT.api('/products' + VT.qs({ ...state, per_page: 12 }));
      if (!d.items.length) return VT.empty(grid, 'Produk tidak ditemukan. Coba kata kunci atau kategori lain.');
      grid.className = 'grid products'; grid.innerHTML = d.items.map(VT.productCard).join('');
      const { current_page: c, last_page: l } = d.meta;
      if (l > 1) pager.innerHTML = Array.from({ length: l }, (_, i) => `<button class="btn sm ${i + 1 === c ? '' : 'ghost'}" data-p="${i + 1}">${i + 1}</button>`).join('');
      pager.querySelectorAll('button').forEach((b) => (b.onclick = () => { state.page = +b.dataset.p; load(); scrollTo({ top: 0 }); }));
    } catch (e) { VT.errorBox(grid, e.message, load); }
  }
  let t;
  VT.$('#f-q').oninput = (e) => { clearTimeout(t); t = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; load(); }, 350); };
  catSel.onchange = (e) => { state.category = e.target.value; state.group = ''; state.page = 1; load(); };
  VT.$('#f-sort').onchange = (e) => { state.sort = e.target.value; state.page = 1; load(); };
  load();
})();
