// assets/js/core.js
// Loaded first on every page. Sends the session's CSRF token with every
// write request and resolves bare "api/..." URLs against the app's base URL.
(function () {
  'use strict';
  var meta = function (n) {
    var el = document.querySelector('meta[name="' + n + '"]');
    return el ? el.getAttribute('content') || '' : '';
  };
  var TOKEN = meta('csrf-token');
  var BASE = meta('base-url');
  window.BM = window.BM || {};
  window.BM.csrf = TOKEN;
  window.BM.baseUrl = BASE;
  window.BM.url = function (path) { return BASE + '/' + String(path).replace(/^\/+/, ''); };

  function resolve(url) {
    if (typeof url !== 'string') return url;
    if (/^(api|modules)\//.test(url)) return BASE + '/' + url;
    return url;
  }
  function sameOrigin(url) {
    try { return new URL(url, window.location.href).origin === window.location.origin; }
    catch (e) { return false; }
  }

  // fetch
  if (window.fetch) {
    var _fetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      init = init || {};
      if (typeof input === 'string') input = resolve(input);
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var method = String(init.method || (input && input.method) || 'GET').toUpperCase();
      if (method !== 'GET' && method !== 'HEAD' && sameOrigin(url)) {
        var headers = new Headers(init.headers || (typeof input !== 'string' && input.headers) || {});
        if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', TOKEN);
        init.headers = headers;
        if (!init.credentials) init.credentials = 'same-origin';
      }
      return _fetch(input, init);
    };
  }

  // XMLHttpRequest (covers jQuery and older scripts)
  var _open = XMLHttpRequest.prototype.open;
  var _send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this.__bmWrite = !/^(GET|HEAD)$/i.test(method);
    var args = Array.prototype.slice.call(arguments);
    args[1] = resolve(url);
    this.__bmSame = sameOrigin(args[1]);
    return _open.apply(this, args);
  };
  XMLHttpRequest.prototype.send = function () {
    if (this.__bmWrite && this.__bmSame) {
      try { this.setRequestHeader('X-CSRF-Token', TOKEN); } catch (e) {}
    }
    return _send.apply(this, arguments);
  };

  // Plain HTML forms
  function tag(form) {
    if (!form || String(form.method).toLowerCase() !== 'post') return;
    if (form.querySelector('input[name="csrf"]')) return;
    var i = document.createElement('input');
    i.type = 'hidden'; i.name = 'csrf'; i.value = TOKEN;
    form.appendChild(i);
  }
  document.addEventListener('submit', function (e) { tag(e.target); }, true);
  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.forms, tag);
  });
  var _submit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () { tag(this); return _submit.call(this); };
})();
