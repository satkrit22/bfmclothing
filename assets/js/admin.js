/* BFM admin panel: mobile menu, confirm dialog, variant rows, small helpers (vanilla JS) */
(function () {
  'use strict';
  function $(s, c) { return (c || document).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }

  /* mobile sidebar */
  var scrim = $('.adm-scrim');
  function menu(open) {
    document.body.classList.toggle('adm-menu-open', open);
    if (scrim) scrim.hidden = !open;
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-adm-menu]')) menu(true);
    else if (e.target.closest('[data-adm-close]')) menu(false);
    var p = e.target.closest('[data-print]');
    if (p) { e.preventDefault(); window.print(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menu(false); });

  /* confirm dialog for forms / buttons carrying data-confirm */
  var dlg = $('#admConfirm');
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    var sub = e.submitter;
    var msg = (sub && sub.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (!msg || form.dataset.confirmed) return;
    if (!dlg || typeof dlg.showModal !== 'function') { if (!window.confirm(msg)) e.preventDefault(); return; }
    e.preventDefault();
    $('#admConfirmText').textContent = msg;
    $('#admConfirmOk').textContent = (sub && sub.getAttribute('data-confirm-yes')) || 'Confirm';
    dlg.returnValue = '';
    dlg.showModal();
    dlg.addEventListener('close', function once() {
      dlg.removeEventListener('close', once);
      if (dlg.returnValue === 'ok') {
        form.dataset.confirmed = '1';
        if (form.requestSubmit) form.requestSubmit(sub || undefined); else form.submit();
      }
    });
  });
  if (dlg) dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close('cancel'); });

  /* double-submit guard */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
    var btn = e.submitter;
    if (btn && !btn.hasAttribute('data-no-lock')) setTimeout(function () { btn.disabled = true; }, 0);
  });
  window.addEventListener('pageshow', function (e) { if (e.persisted) $$('button[type=submit]:disabled').forEach(function (b) { b.disabled = false; }); });

  /* variant rows on the product form */
  var rows = $('#varRows'), tpl = $('#varTemplate');
  if (rows && tpl) {
    var next = rows.children.length;
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-var-add]')) {
        var html = tpl.innerHTML.replace(/__i__/g, 'n' + (next++));
        rows.insertAdjacentHTML('beforeend', html);
      }
      var rm = e.target.closest('[data-var-remove]');
      if (rm) {
        var row = rm.closest('.adm-var-row');
        var del = $('input[data-var-delete]', row);
        if (row.getAttribute('data-existing') === '1' && del) { del.value = '1'; row.hidden = true; }
        else row.remove();
      }
    });
  }

  /* live preview of newly chosen product images */
  var pick = $('#newImages'), prev = $('#newImagesPreview');
  if (pick && prev) {
    pick.addEventListener('change', function () {
      prev.textContent = '';
      Array.prototype.forEach.call(pick.files, function (f) {
        if (!/^image\//.test(f.type)) return;
        var img = document.createElement('img');
        img.className = 'adm-thumb'; img.alt = '';
        img.src = URL.createObjectURL(f);
        prev.appendChild(img);
      });
    });
  }

  /* auto-hide success alerts */
  $$('.adm-alert-success').forEach(function (a) { setTimeout(function () { a.style.display = 'none'; }, 6000); });
})();
