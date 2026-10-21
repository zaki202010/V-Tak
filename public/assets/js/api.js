/* Klien API V-TAK: cookie session HttpOnly + header CSRF. Tidak ada token di localStorage. */
const VT = window.VT || (window.VT = {});
let _csrf = null;

// Halaman admin mengisi VT.area = 'admin' agar memakai sesi terpisah dari area pelanggan.
VT.area = VT.area || '';
const areaHeaders = () => (VT.area ? { 'X-VTAK-Area': VT.area } : {});

VT.csrf = async function (force) {
  if (!_csrf || force) {
    const r = await fetch('/api/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json', ...areaHeaders() } });
    _csrf = (await r.json()).data.token;
  }
  return _csrf;
};

VT.api = async function (path, opts = {}) {
  const method = (opts.method || 'GET').toUpperCase();
  const headers = { Accept: 'application/json', ...areaHeaders() };
  let body;
  if (opts.form) body = opts.form;
  else if (opts.body !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(opts.body); }
  const send = async () => {
    if (method !== 'GET') headers['X-CSRF-TOKEN'] = await VT.csrf();
    return fetch('/api' + path, { method, headers, body, credentials: 'same-origin' });
  };
  let res = await send();
  if (res.status === 419) { await VT.csrf(true); res = await send(); }
  let json = null;
  try { json = await res.json(); } catch (e) { /* bukan JSON */ }
  if (!res.ok) {
    const err = new Error((json && json.message) || 'Terjadi kesalahan (' + res.status + ').');
    err.status = res.status; err.errors = (json && json.errors) || {};
    throw err;
  }
  return json ? json.data : null;
};

VT.qs = (o) => { const p = new URLSearchParams(); Object.entries(o).forEach(([k, v]) => { if (v !== '' && v != null) p.set(k, v); }); const s = p.toString(); return s ? '?' + s : ''; };
