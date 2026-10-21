/* Halaman konfirmasi & pembayaran. Status hanya dibaca dari server; tombol simulasi hanya muncul pada VTAK_MODE=simulation. */
(async function () {
  const { site } = await VT.init('');
  const root = VT.$('#payment'), no = VT.param('order'), token = VT.param('token') || '';
  if (!no) return VT.empty(root, 'Nomor pesanan tidak ditemukan.');
  let timer;
  async function load() {
    try {
      const o = await VT.api('/orders/track' + VT.qs({ order_no: no, token }));
      const box = document.createElement('div'); VT.renderOrder(box, o);
      let extra = '';
      if (o.payment_status === 'pending') {
        extra = `<div class="card mt"><h3>Instruksi pembayaran</h3>
          <p>Batas pembayaran: <b>${VT.date(o.expires_at)}</b> (<span id="cd"></span>)</p>
          ${site.mode === 'simulation'
            ? `<div class="alert warn"><b>MODE SIMULASI</b> — tidak ada pembayaran sungguhan. Tombol di bawah hanya untuk menguji alur pesanan.</div><button class="btn" id="simpay">Bayar (Simulasi)</button>`
            : '<div class="alert">Selesaikan pembayaran sesuai instruksi dari metode yang dipilih. Status akan diperbarui otomatis setelah pembayaran diverifikasi server.</div>'}
          <p class="muted"><small>Simpan nomor pesanan${token ? ' dan tautan halaman ini' : ''} untuk mengecek status di menu Cek Transaksi.</small></p></div>`;
      }
      root.innerHTML = ''; root.appendChild(box);
      if (extra) { root.insertAdjacentHTML('beforeend', extra); countdown(o.expires_at); const b = VT.$('#simpay'); if (b) b.onclick = simPay; }
      clearTimeout(timer);
      if (o.payment_status === 'pending' || o.topup_status === 'processing') timer = setTimeout(load, 8000);
    } catch (e) { VT.errorBox(root, e.message, load); }
  }
  function countdown(end) {
    const el = VT.$('#cd'); if (!el) return;
    const tick = () => { const s = Math.max(0, Math.floor((new Date(end) - Date.now()) / 1000)); el.textContent = Math.floor(s / 60) + ' menit ' + (s % 60) + ' detik lagi'; if (s > 0 && VT.$('#cd')) setTimeout(tick, 1000); };
    tick();
  }
  async function simPay() {
    const b = VT.$('#simpay'); b.disabled = true;
    try { await VT.api('/orders/' + encodeURIComponent(no) + '/simulate-pay', { method: 'POST', body: { token } }); VT.toast('[SIMULASI] Pembayaran ditandai lunas.', 'ok'); }
    catch (e) { VT.toast(e.message, 'err'); }
    load();
  }
  VT.loading(root); load();
})();
