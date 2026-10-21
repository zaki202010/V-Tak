/* Tema terang/gelap. Pilihan disimpan di localStorage (hanya preferensi tampilan). */
(function () {
  if (window.__vtTheme) return;
  window.__vtTheme = true;

  var KEY = 'vtak_theme', root = document.documentElement;

  function saved() {
    try { var t = localStorage.getItem(KEY); if (t === 'light' || t === 'dark') return t; } catch (e) {}
    return 'dark';
  }

  function paint() {
    var n = root.getAttribute('data-theme');
    document.querySelectorAll('[data-theme-btn]').forEach(function (b) {
      var icon = n === 'light' ? '\uD83C\uDF19' : '\u2600\uFE0F';
      var txt = b.hasAttribute('data-full') ? icon + (n === 'light' ? ' Mode gelap' : ' Mode terang') : icon;
      if (b.textContent !== txt) b.textContent = txt;
      b.setAttribute('title', n === 'light' ? 'Ganti ke mode gelap' : 'Ganti ke mode terang');
    });
  }

  function toggle() {
    var n = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', n);
    try { localStorage.setItem(KEY, n); } catch (e) {}
    paint();
  }

  root.setAttribute('data-theme', saved());

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-theme-btn]')) toggle();
  });

  // Tombol dibuat belakangan oleh app.js, jadi pantau DOM dan isi ikonnya begitu muncul.
  new MutationObserver(paint).observe(root, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', paint);

  window.VTTheme = { sync: paint, toggle: toggle };
})();
