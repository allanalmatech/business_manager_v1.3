// assets/js/ui.js — toasts, confirm dialog, phone-friendly tables
(function () {
  'use strict';
  var BM = window.BM = window.BM || {};

  /* ---------- Toasts ---------- */
  var ICONS = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-octagon-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  function guessKind(msg) {
    var m = String(msg).toLowerCase();
    if (/(fail|error|invalid|cannot|can't|denied|unable|not allowed|insufficient|required|wrong|missing)/.test(m)) return 'danger';
    if (/(success|saved|updated|created|deleted|added|done|sent|copied|completed)/.test(m)) return 'success';
    return 'info';
  }
  BM.toast = function (message, kind, ms) {
    var host = document.getElementById('bmToasts');
    if (!host) { host = document.createElement('div'); host.id = 'bmToasts'; host.className = 'bm-toasts'; host.setAttribute('role', 'status'); host.setAttribute('aria-live', 'polite'); document.body.appendChild(host); }
    kind = kind || guessKind(message);
    var el = document.createElement('div');
    el.className = 'bm-toast is-' + kind;
    var ico = document.createElement('i'); ico.className = 'bi ' + (ICONS[kind] || ICONS.info); ico.setAttribute('aria-hidden', 'true');
    var txt = document.createElement('div'); txt.textContent = String(message); txt.style.whiteSpace = 'pre-line';
    var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Dismiss'); x.innerHTML = '&times;';
    el.appendChild(ico); el.appendChild(txt); el.appendChild(x);
    host.appendChild(el);
    var gone = false;
    function close() { if (gone) return; gone = true; el.classList.add('is-leaving'); setTimeout(function () { el.remove(); }, 240); }
    x.addEventListener('click', close);
    setTimeout(close, ms || (kind === 'danger' ? 8000 : 4500));
    return el;
  };
  // Every existing alert() in the app becomes a toast: nothing blocks the till any more.
  window.alert = function (msg) { BM.toast(msg == null ? '' : msg); };

  /* ---------- Confirm dialog (returns a Promise<boolean>) ---------- */
  BM.confirm = function (message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      if (!window.bootstrap) { resolve(window.confirm(message)); return; }
      var wrap = document.createElement('div');
      wrap.className = 'modal fade'; wrap.tabIndex = -1; wrap.setAttribute('aria-modal', 'true'); wrap.setAttribute('role', 'dialog');
      wrap.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">' +
        '<div class="modal-body pt-4"><div class="fw-semibold mb-1" data-t></div><div class="text-muted small" data-m style="white-space:pre-line"></div></div>' +
        '<div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary" data-no></button><button type="button" class="btn" data-yes></button></div></div></div>';
      wrap.querySelector('[data-t]').textContent = opts.title || 'Are you sure?';
      wrap.querySelector('[data-m]').textContent = message;
      var yes = wrap.querySelector('[data-yes]'), no = wrap.querySelector('[data-no]');
      yes.textContent = opts.confirmText || 'Confirm'; no.textContent = opts.cancelText || 'Cancel';
      yes.classList.add(opts.danger ? 'btn-danger' : 'btn-primary');
      document.body.appendChild(wrap);
      var modal = new bootstrap.Modal(wrap), answer = false;
      yes.addEventListener('click', function () { answer = true; modal.hide(); });
      no.addEventListener('click', function () { modal.hide(); });
      wrap.addEventListener('shown.bs.modal', function () { (opts.danger ? no : yes).focus(); });
      wrap.addEventListener('hidden.bs.modal', function () { modal.dispose(); wrap.remove(); resolve(answer); });
      modal.show();
    });
  };
  // Declarative use: <a data-confirm="Delete this item?" href="..."> or on a submit button
  document.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('[data-confirm]');
    if (!el || el.dataset.confirmed === '1') return;
    e.preventDefault(); e.stopPropagation();
    BM.confirm(el.dataset.confirm, { danger: el.hasAttribute('data-confirm-danger') || /delete|remove|reset|void/i.test(el.dataset.confirm), confirmText: el.dataset.confirmText }).then(function (ok) {
      if (!ok) return;
      el.dataset.confirmed = '1'; el.click(); delete el.dataset.confirmed;
    });
  }, true);

  /* ---------- Tables: rows become cards on phones ---------- */
  function labelTable(table) {
    if (table.dataset.noStack !== undefined || table.closest('[data-no-stack]') || table.closest('.modal .bm-keep-table')) return;
    var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent.replace(/\s+/g, ' ').trim(); });
    if (heads.length < 3) return; // small tables read fine as they are
    table.classList.add('bm-stack');
    Array.prototype.forEach.call(table.tBodies, function (tb) {
      Array.prototype.forEach.call(tb.rows, function (row) {
        if (row.dataset.bmLabelled === String(row.cells.length)) return;
        Array.prototype.forEach.call(row.cells, function (cell, i) {
          if (!cell.hasAttribute('colspan')) cell.setAttribute('data-label', heads[i] || '');
        });
        row.dataset.bmLabelled = String(row.cells.length);
      });
    });
  }
  function labelAll(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll('.page-wrap table, .modal table'), labelTable);
  }
  document.addEventListener('DOMContentLoaded', function () {
    labelAll();
    // Tables filled in later by scripts get labelled too
    var pending = false;
    new MutationObserver(function () {
      if (pending) return; pending = true;
      requestAnimationFrame(function () { pending = false; labelAll(); });
    }).observe(document.body, { childList: true, subtree: true });

    // Bootstrap tooltips where a page asks for them
    if (window.bootstrap) Array.prototype.forEach.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'), function (el) { new bootstrap.Tooltip(el); });
  });
})();
