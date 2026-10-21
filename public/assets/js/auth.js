/* Login, registrasi, profil, dan riwayat. */
(async function () {
  await VT.init('');
  const showErrors = (form, e) => {
    form.querySelectorAll('.err').forEach((x) => x.remove());
    Object.entries(e.errors || {}).forEach(([k, msgs]) => {
      const inp = form.querySelector(`[name="${k}"]`);
      if (inp) { const d = document.createElement('div'); d.className = 'err'; d.textContent = msgs[0]; inp.after(d); }
    });
    VT.toast(e.message, 'err');
  };
  const submit = (form, fn) => form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    try { await fn(Object.fromEntries(new FormData(form))); } catch (e) { showErrors(form, e); } finally { btn.disabled = false; }
  });
  const next = () => { const n = VT.param('next'); return n && n.startsWith('/') && !n.startsWith('//') ? n : '/'; };

  const lf = VT.$('#login-form');
  if (lf) submit(lf, async (d) => {
    try {
      await VT.api('/login', { method: 'POST', body: d });
      await VT.csrf(true);
      location.href = next();
    } catch (e) {
      if (e.errors && e.errors.area) {
        // Akun admin: ulangi login di area admin (sesi terpisah dari pelanggan)
        VT.area = 'admin';
        await VT.csrf(true);
        await VT.api('/login', { method: 'POST', body: d });
        location.href = '/admin/index.html';
        return;
      }
      throw e;
    }
  });

  const rf = VT.$('#register-form');
  if (rf) submit(rf, async (d) => { await VT.api('/register', { method: 'POST', body: d }); await VT.csrf(true); location.href = next(); });

  const pf = VT.$('#profile-form');
  if (pf) {
    if (!VT.requireLogin()) return;
    pf.name.value = VT.user.name; pf.phone.value = VT.user.phone || ''; VT.$('#p-email').textContent = VT.user.email;
    submit(pf, async (d) => { await VT.api('/profile', { method: 'PUT', body: d }); VT.toast('Profil diperbarui.', 'ok'); pf.password.value = pf.password_confirmation.value = pf.current_password.value = ''; });
  }

  const hist = VT.$('#history');
  if (hist) {
    if (!VT.requireLogin()) return;
    let page = 1;
    const load = async () => {
      VT.loading(hist);
      try {
        const d = await VT.api('/my/orders?page=' + page);
        if (!d.items.length) return VT.empty(hist, 'Belum ada transaksi.');
        hist.innerHTML = `<div class="tablewrap"><table><thead><tr><th>No. Pesanan</th><th>Produk</th><th>Total</th><th>Pembayaran</th><th>Top Up</th><th>Waktu</th></tr></thead><tbody>` +
          d.items.map((o) => `<tr><td><a href="/payment.html?order=${encodeURIComponent(o.order_no)}">${VT.esc(o.order_no)}</a></td><td>${VT.esc(o.product_name)}<br><small class="muted">${VT.esc(o.variant_name)}</small></td>
          <td class="price">${VT.rp(o.total)}</td><td>${VT.badge(VT.payStatus, o.payment_status)}</td><td>${o.payment_status === 'paid' ? VT.badge(VT.topupStatus, o.topup_status) : '—'}</td><td>${VT.date(o.created_at)}</td></tr>`).join('') + '</tbody></table></div>' +
          `<div class="pager">${d.meta.current_page > 1 ? '<button class="btn sm ghost" id="pv">‹ Sebelumnya</button>' : ''}${d.meta.current_page < d.meta.last_page ? '<button class="btn sm ghost" id="nx">Berikutnya ›</button>' : ''}</div>`;
        VT.$('#pv') && (VT.$('#pv').onclick = () => { page--; load(); });
        VT.$('#nx') && (VT.$('#nx').onclick = () => { page++; load(); });
      } catch (e) { VT.errorBox(hist, e.message, load); }
    };
    load();
  }
})();
