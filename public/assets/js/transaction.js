/* Render status pesanan (dipakai payment.html & transaction-status.html) + form cek transaksi. */
VT.renderOrder = function (el, o) {
  const paid = o.payment_status === 'paid';
  const help = {
    pending: 'Selesaikan pembayaran sebelum batas waktu. Pesanan akan kedaluwarsa otomatis jika tidak dibayar.',
    expired: 'Pesanan kedaluwarsa. Silakan buat pesanan baru.',
    failed: 'Pembayaran gagal. Silakan buat pesanan baru atau hubungi layanan pelanggan.',
  }[o.payment_status] || ({
    waiting: 'Pembayaran diterima, top up segera diproses.', processing: 'Top up sedang diproses. Halaman ini diperbarui otomatis.',
    success: 'Top up berhasil. Terima kasih sudah memakai V-TAK!',
    failed: 'Top up gagal. Hubungi layanan pelanggan dengan nomor pesanan Anda.',
    manual_review: 'Top up membutuhkan pemeriksaan manual oleh tim kami. Hubungi layanan pelanggan dengan nomor pesanan Anda.',
  }[o.topup_status] || '');
  el.innerHTML = `
    <div class="card"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><div><small class="muted">Nomor pesanan</small><h2 style="margin:0">${VT.esc(o.order_no)}</h2></div>
      <div style="text-align:right"><small class="muted">Waktu transaksi</small><div>${VT.date(o.created_at)}</div></div></div>
      <div class="grid cols-2 mt"><div><small class="muted">Status pembayaran</small><div>${VT.badge(VT.payStatus, o.payment_status)}</div></div>
      <div><small class="muted">Status top up</small><div>${paid ? VT.badge(VT.topupStatus, o.topup_status) : '<span class="muted">— (menunggu pembayaran)</span>'}</div></div></div>
      <div class="alert">${VT.esc(help)}</div>
      <div class="row"><span>Produk</span><span>${VT.esc(o.product_name)} — ${VT.esc(o.variant_name)}</span></div>
      ${o.target_user_id ? `<div class="row"><span>Tujuan</span><span>${VT.esc(o.target_user_id)}${o.target_server_id ? ' (' + VT.esc(o.target_server_id) + ')' : ''}</span></div>` : ''}
      <div class="row"><span>Harga</span><span>${VT.rp(o.unit_price)}</span></div>
      <div class="row"><span>Diskon${o.promo_code ? ' (' + VT.esc(o.promo_code) + ')' : ''}</span><span>-${VT.rp(o.discount)}</span></div>
      <div class="row"><span>Biaya layanan</span><span>${VT.rp(o.service_fee)}</span></div>
      <div class="row total"><span>Total pembayaran</span><span class="price">${VT.rp(o.total)}</span></div>
      <div class="row"><span>Metode</span><span>${VT.esc(o.payment_method_name)}</span></div></div>
    ${(o.timeline || []).length ? `<div class="card mt"><h3>Riwayat proses</h3><ul class="timeline">${o.timeline.map((t) => `<li><small class="muted">${VT.date(t.created_at)}</small><br>${VT.esc(t.message || t.event)}</li>`).join('')}</ul></div>` : ''}`;
};

(async function () {
  const form = VT.$('#track-form');
  if (!form) return;
  const { user } = await VT.init('track');
  const out = VT.$('#track-result');
  if (user) VT.$('#login-hint').innerHTML = 'Anda login — lihat semua pesanan di <a href="/history.html">Riwayat Transaksi</a>.';
  form.onsubmit = async (e) => {
    e.preventDefault(); VT.loading(out);
    try { VT.renderOrder(out, await VT.api('/orders/track' + VT.qs({ order_no: form.order_no.value.trim(), token: form.token.value.trim() }))); }
    catch (err) { VT.errorBox(out, err.message); }
  };
  if (VT.param('order')) { form.order_no.value = VT.param('order'); form.token.value = VT.param('token') || ''; form.requestSubmit(); }
})();
