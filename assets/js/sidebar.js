// assets/js/sidebar.js — menu groups, desktop rail, mobile drawer
(function () {
  'use strict';
  var body = document.body;
  var sidebar = document.getElementById('appSidebar');
  if (!sidebar) return;
  var backdrop = document.getElementById('sidebarBackdrop');
  var desktop = window.matchMedia('(min-width: 992px)');
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };

  // Groups: one open at a time; the group holding the current page starts open
  var groups = Array.prototype.slice.call(sidebar.querySelectorAll('.sb-group'));
  function setOpen(group, open) {
    group.classList.toggle('open', open);
    var btn = group.querySelector('.sb-toggle');
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (!sidebar.querySelector('.sb-group.has-active')) {
    var remembered = store.get('bm.sidebar.group');
    groups.forEach(function (g) { if (g.dataset.group === remembered) setOpen(g, true); });
  }
  groups.forEach(function (group) {
    var btn = group.querySelector('.sb-toggle');
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (desktop.matches && body.classList.contains('sidebar-collapsed')) return; // fly-out handles it
      var willOpen = !group.classList.contains('open');
      groups.forEach(function (g) { if (g !== group) setOpen(g, false); });
      setOpen(group, willOpen);
      store.set('bm.sidebar.group', willOpen ? group.dataset.group : '');
    });
  });
  var current = sidebar.querySelector('.active');
  if (current && current.scrollIntoView) current.scrollIntoView({ block: 'nearest' });

  // Desktop rail
  if (store.get('bm.sidebar.collapsed') === '1') body.classList.add('sidebar-collapsed');
  var btnToggle = document.getElementById('btnSidebarToggle');
  if (btnToggle) btnToggle.addEventListener('click', function () {
    var on = body.classList.toggle('sidebar-collapsed');
    store.set('bm.sidebar.collapsed', on ? '1' : '0');
    btnToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
  });

  // Fly-outs near the bottom of the screen open upward so they stay visible
  groups.forEach(function (group) {
    group.addEventListener('mouseenter', place);
    group.addEventListener('focusin', place);
    function place() {
      if (!body.classList.contains('sidebar-collapsed')) return;
      var sub = group.querySelector('.sb-sub');
      sub.style.top = '0px';
      var overflow = group.getBoundingClientRect().top + sub.offsetHeight - window.innerHeight + 12;
      if (overflow > 0) sub.style.top = (-overflow) + 'px';
    }
  });

  // Drawer
  var btnOpen = document.getElementById('btnSidebarOpen');
  var btnClose = document.getElementById('btnSidebarClose');
  function drawer(open) {
    body.classList.toggle('sidebar-open', open);
    if (backdrop) backdrop.hidden = !open;
    if (btnOpen) btnOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { var first = sidebar.querySelector('.sb-link'); if (first) first.focus(); }
    else if (btnOpen && document.activeElement && sidebar.contains(document.activeElement)) btnOpen.focus();
  }
  if (btnOpen) btnOpen.addEventListener('click', function () { drawer(!body.classList.contains('sidebar-open')); });
  if (btnClose) btnClose.addEventListener('click', function () { drawer(false); });
  if (backdrop) backdrop.addEventListener('click', function () { drawer(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && body.classList.contains('sidebar-open')) drawer(false); });
  desktop.addEventListener('change', function () { drawer(false); });
})();
