(function (window, document) {
  'use strict';

  var STYLE_ID = 'im-customer-embed-style';
  var defaults = {
    sessionUrl: '',
    method: 'POST',
    payload: {},
    headers: {},
    withCredentials: true,
    title: '在线客服',
    buttonText: '客服',
    loadingText: '正在连接客服...',
    errorText: '客服连接失败，请稍后重试',
    position: 'right',
    width: 420,
    height: 680,
    zIndex: 99999,
    autoOpen: false
  };

  function extend(target) {
    for (var i = 1; i < arguments.length; i++) {
      var source = arguments[i] || {};
      for (var key in source) {
        if (Object.prototype.hasOwnProperty.call(source, key)) {
          target[key] = source[key];
        }
      }
    }
    return target;
  }

  function createEl(tag, className, text) {
    var el = document.createElement(tag);
    if (className) {
      el.className = className;
    }
    if (text) {
      el.appendChild(document.createTextNode(text));
    }
    return el;
  }

  function sizeValue(value, fallback) {
    if (typeof value === 'number') {
      return value + 'px';
    }
    return value || fallback;
  }

  function appendQuery(url, data) {
    if (!data || typeof data !== 'object') {
      return url;
    }
    var query = [];
    for (var key in data) {
      if (!Object.prototype.hasOwnProperty.call(data, key)) {
        continue;
      }
      if (data[key] === undefined || data[key] === null) {
        continue;
      }
      var value = typeof data[key] === 'object' ? JSON.stringify(data[key]) : data[key];
      query.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
    }
    if (!query.length) {
      return url;
    }
    return url + (url.indexOf('?') === -1 ? '?' : '&') + query.join('&');
  }

  function pickUrl(response) {
    if (typeof response === 'string') {
      return response;
    }
    if (!response || typeof response !== 'object') {
      return '';
    }
    if (response.url) {
      return response.url;
    }
    if (response.data && typeof response.data === 'string') {
      return response.data;
    }
    if (response.data && response.data.url) {
      return response.data.url;
    }
    return '';
  }

  function parseResponse(text) {
    if (!text) {
      return {};
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      return text;
    }
  }

  function injectStyle(zIndex) {
    if (document.getElementById(STYLE_ID)) {
      return;
    }
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.type = 'text/css';
    style.appendChild(document.createTextNode(
      '.imcs-root{position:fixed;bottom:24px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;z-index:' + zIndex + ';}' +
      '.imcs-root.imcs-right{right:24px}.imcs-root.imcs-left{left:24px}' +
      '.imcs-button{height:44px;min-width:76px;border:0;border-radius:22px;background:#1677ff;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.18);cursor:pointer;font-size:15px;font-weight:600;padding:0 18px}' +
      '.imcs-button:focus{outline:2px solid rgba(22,119,255,.35);outline-offset:3px}' +
      '.imcs-panel{position:absolute;bottom:58px;width:420px;height:680px;max-height:calc(100vh - 96px);background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:8px;box-shadow:0 16px 48px rgba(0,0,0,.24);overflow:hidden;display:none}' +
      '.imcs-right .imcs-panel{right:0}.imcs-left .imcs-panel{left:0}' +
      '.imcs-root.imcs-open .imcs-panel{display:block}' +
      '.imcs-header{height:46px;display:flex;align-items:center;justify-content:space-between;padding:0 12px 0 16px;border-bottom:1px solid #edf0f5;background:#fff;color:#1f2937;box-sizing:border-box}' +
      '.imcs-title{font-size:15px;font-weight:600;line-height:1}' +
      '.imcs-close{width:32px;height:32px;border:0;border-radius:6px;background:transparent;color:#6b7280;font-size:24px;line-height:28px;cursor:pointer}' +
      '.imcs-close:hover{background:#f3f4f6;color:#111827}' +
      '.imcs-body{position:relative;height:calc(100% - 46px);background:#f7f8fb}' +
      '.imcs-frame{width:100%;height:100%;border:0;background:#fff;display:block}' +
      '.imcs-state{position:absolute;inset:0;display:none;align-items:center;justify-content:center;padding:24px;text-align:center;color:#4b5563;background:#f7f8fb;font-size:14px;box-sizing:border-box}' +
      '.imcs-loading .imcs-state-loading,.imcs-error .imcs-state-error{display:flex}' +
      '.imcs-retry{margin-top:12px;height:32px;border:1px solid #1677ff;border-radius:6px;background:#fff;color:#1677ff;cursor:pointer;padding:0 14px}' +
      '@media (max-width:640px){.imcs-root{left:0!important;right:0!important;bottom:0!important}.imcs-button{position:absolute;right:16px;bottom:16px}.imcs-panel{left:0!important;right:0!important;bottom:0!important;width:100vw!important;height:100vh!important;max-height:100vh;border-radius:0;border:0}.imcs-root.imcs-open .imcs-button{display:none}}'
    ));
    document.head.appendChild(style);
  }

  function requestSession(config, onSuccess, onError) {
    if (!config.sessionUrl) {
      onError(new Error('sessionUrl is required'));
      return;
    }
    var payload = typeof config.payload === 'function' ? config.payload() : config.payload;
    var method = String(config.method || 'POST').toUpperCase();
    var url = method === 'GET' ? appendQuery(config.sessionUrl, payload) : config.sessionUrl;
    var xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.withCredentials = !!config.withCredentials;
    xhr.setRequestHeader('Accept', 'application/json');
    if (method !== 'GET') {
      xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    }
    for (var key in config.headers) {
      if (Object.prototype.hasOwnProperty.call(config.headers, key)) {
        xhr.setRequestHeader(key, config.headers[key]);
      }
    }
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) {
        return;
      }
      var response = parseResponse(xhr.responseText);
      if (xhr.status < 200 || xhr.status >= 300) {
        onError(new Error((response && response.msg) || config.errorText));
        return;
      }
      if (response && response.code !== undefined && Number(response.code) !== 0) {
        onError(new Error(response.msg || config.errorText));
        return;
      }
      var url = pickUrl(response);
      if (!url) {
        onError(new Error('response url is empty'));
        return;
      }
      onSuccess(url, response);
    };
    xhr.onerror = function () {
      onError(new Error(config.errorText));
    };
    xhr.send(method === 'GET' ? null : JSON.stringify(payload || {}));
  }

  function init(options) {
    var config = extend({}, defaults, options || {});
    injectStyle(config.zIndex);

    var root = createEl('div', 'imcs-root imcs-' + (config.position === 'left' ? 'left' : 'right'));
    var button = createEl('button', 'imcs-button', config.buttonText);
    var panel = createEl('div', 'imcs-panel');
    var header = createEl('div', 'imcs-header');
    var title = createEl('div', 'imcs-title', config.title);
    var close = createEl('button', 'imcs-close', '×');
    var body = createEl('div', 'imcs-body');
    var frame = createEl('iframe', 'imcs-frame');
    var loading = createEl('div', 'imcs-state imcs-state-loading', config.loadingText);
    var error = createEl('div', 'imcs-state imcs-state-error');
    var errorInner = createEl('div', '', config.errorText);
    var retry = createEl('button', 'imcs-retry', '重新连接');
    var loaded = false;
    var loadingSession = false;

    button.type = 'button';
    close.type = 'button';
    close.setAttribute('aria-label', '关闭客服');
    panel.style.width = sizeValue(config.width, '420px');
    panel.style.height = sizeValue(config.height, '680px');
    frame.setAttribute('title', config.title);

    errorInner.appendChild(document.createElement('br'));
    errorInner.appendChild(retry);
    error.appendChild(errorInner);
    header.appendChild(title);
    header.appendChild(close);
    body.appendChild(frame);
    body.appendChild(loading);
    body.appendChild(error);
    panel.appendChild(header);
    panel.appendChild(body);
    root.appendChild(button);
    root.appendChild(panel);
    document.body.appendChild(root);

    function setState(name) {
      body.className = 'imcs-body' + (name ? ' imcs-' + name : '');
    }

    function loadSession(force) {
      if (loadingSession) {
        return;
      }
      if (loaded && !force) {
        return;
      }
      loadingSession = true;
      setState('loading');
      requestSession(config, function (url) {
        loaded = true;
        loadingSession = false;
        frame.onload = function () {
          setState('');
        };
        frame.src = url;
      }, function () {
        loadingSession = false;
        setState('error');
      });
    }

    var api = {
      open: function () {
        root.className = root.className.replace(/\s?imcs-open/g, '') + ' imcs-open';
        loadSession(false);
        return api;
      },
      close: function () {
        root.className = root.className.replace(/\s?imcs-open/g, '');
        return api;
      },
      toggle: function () {
        if (root.className.indexOf('imcs-open') === -1) {
          api.open();
        } else {
          api.close();
        }
        return api;
      },
      reload: function () {
        loaded = false;
        frame.src = 'about:blank';
        loadSession(true);
        return api;
      },
      setPayload: function (payload) {
        config.payload = payload;
        return api;
      },
      destroy: function () {
        if (root.parentNode) {
          root.parentNode.removeChild(root);
        }
      }
    };

    button.onclick = api.toggle;
    close.onclick = api.close;
    retry.onclick = api.reload;
    if (config.autoOpen) {
      api.open();
    }
    return api;
  }

  window.ImCustomerService = {
    init: init
  };

  var currentScript = document.currentScript;
  if (currentScript && currentScript.getAttribute('data-session-url')) {
    var autoOptions = {
      sessionUrl: currentScript.getAttribute('data-session-url'),
      title: currentScript.getAttribute('data-title') || defaults.title,
      buttonText: currentScript.getAttribute('data-button-text') || defaults.buttonText,
      position: currentScript.getAttribute('data-position') || defaults.position,
      autoOpen: currentScript.getAttribute('data-auto-open') === 'true'
    };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        init(autoOptions);
      });
    } else {
      init(autoOptions);
    }
  }
})(window, document);
