// Jaket Imports — interaksi umum
document.addEventListener('DOMContentLoaded', function () {

  // Dropdown user
  document.querySelectorAll('.dropdown').forEach(function (dd) {
    var toggle = dd.querySelector('.dropdown-toggle');
    if (!toggle) return;
    toggle.addEventListener('click', function (ev) {
      ev.stopPropagation();
      dd.classList.toggle('open');
    });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
  });

  // Qty +/- pada halaman detail & keranjang
  document.querySelectorAll('.qty-box').forEach(function (box) {
    var minus = box.querySelector('.qty-btn.minus');
    var plus = box.querySelector('.qty-btn.plus');
    var input = box.querySelector('.qty-input');
    var max = parseInt(input.getAttribute('max') || '999', 10);
    if (!minus || !plus || !input) return;
    minus.addEventListener('click', function () {
      var v = parseInt(input.value, 10) || 1;
      if (v > 1) input.value = v - 1;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    plus.addEventListener('click', function () {
      var v = parseInt(input.value, 10) || 1;
      if (v < max) input.value = v + 1;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Input qty di keranjang: submit form saat berubah
  document.querySelectorAll('.qty-input[data-auto-submit]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var v = parseInt(inp.value, 10);
      if (!v || v < 1) inp.value = 1;
      var max = parseInt(inp.getAttribute('max') || '999', 10);
      if (parseInt(inp.value, 10) > max) inp.value = max;
      inp.closest('form').submit();
    });
  });

  // Auto-submit filter katalog
  document.querySelectorAll('[data-auto-filter]').forEach(function (el) {
    el.addEventListener('change', function () {
      var form = el.closest('form');
      if (form) form.submit();
    });
  });

  // Konfirmasi hapus
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
      }
    });
  });

  // Bar chart (dashboard admin)
  var chart = document.getElementById('barChart');
  if (chart) {
    var values = (chart.getAttribute('data-values') || '').split(',').map(Number);
    var labels = (chart.getAttribute('data-labels') || '').split(',');
    var max = Math.max.apply(null, values.concat([1]));
    var html = '';
    values.forEach(function (v, i) {
      var pct = Math.max(v / max * 100, 2);
      html += '<div class="bar-item"><span class="bar-value">' +
        (v ? 'Rp' + v.toLocaleString('id-ID') : '') +
        '</span><div class="bar" style="height:' + pct + '%"></div><span class="bar-label">' + labels[i] + '</span></div>';
    });
    chart.innerHTML = html;
  }
});
