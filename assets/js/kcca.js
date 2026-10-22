/* =====================================================================
   KCCA VRSP — shared front-end behaviour
   Vanilla ES2018, no build step, no framework.
   ===================================================================== */
(function () {
  'use strict';

  /* ---------------------------------------------------------------- */
  /* Icon set — inline SVG so there is no external icon dependency     */
  /* ---------------------------------------------------------------- */
  var ICONS = {
    gauge:      '<path d="M12 14a2 2 0 100-4 2 2 0 000 4z"/><path d="M13.4 10.6L19 5"/><path d="M20.7 17A9 9 0 103.3 17"/>',
    map:        '<path d="M9 3L3 5.5v15L9 18l6 3 6-2.5v-15L15 6 9 3z"/><path d="M9 3v15M15 6v15"/>',
    pulse:      '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    grid:       '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    swap:       '<path d="M16 3l4 4-4 4"/><path d="M20 7H4"/><path d="M8 21l-4-4 4-4"/><path d="M4 17h16"/>',
    list:       '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    store:      '<path d="M3 9l1-5h16l1 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
    people:     '<path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 00-3-3.87M16 3.13A4 4 0 0119 7"/>',
    shield:     '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
    doc:        '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
    chart:      '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
    log:        '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    download:   '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 21h16"/>',
    link:       '<path d="M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7l1.7-1.7"/>',
    user:       '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>',
    menu:       '<path d="M3 6h18M3 12h18M3 18h18"/>',
    x:          '<path d="M18 6L6 18M6 6l12 12"/>',
    external:   '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6M10 14L21 3"/>',
    print:      '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="1"/><path d="M6 15h12v6H6z"/>',
    phone:      '<path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6A19.8 19.8 0 012 4.2 2 2 0 014 2h3a2 2 0 012 1.7c.1 1 .4 1.9.7 2.8a2 2 0 01-.5 2.1L8 9.9a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.8.6 2.8.7A2 2 0 0122 16.9z"/>',
    mail:       '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/>',
    clock:      '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    alert:      '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>',
    check:      '<path d="M20 6L9 17l-5-5"/>',
    search:     '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    plus:       '<path d="M12 5v14M5 12h14"/>',
    info:       '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
    building:   '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/>',
    layers:     '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>',
    cog:        '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1A1.7 1.7 0 008 19.4a1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H2a2 2 0 110-4h.1A1.7 1.7 0 003.6 8a1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H8a1.7 1.7 0 001-1.5V2a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V8a1.7 1.7 0 001.5 1H22a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
    trend:      '<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/><path d="M19 7h-4M19 7v4"/>'
  };

  window.KCCA = window.KCCA || {};

  /* ---------------------------------------------------------------- */
  /* URL resolution                                                    */
  /* ---------------------------------------------------------------- */
  /* Every page sits at a different depth (pages/, auth/), so a script
     that posts to "api/vendor_save.php" would resolve against whichever
     page happened to be open. Resolve against the application root
     instead.

     Defined at script scope rather than inside boot(), because a page's
     own inline script may run before boot() has — DOMContentLoaded
     listeners fire in registration order, and this file loads in the
     footer, after any inline script in the body.                   */
  var rootMeta = document.querySelector('meta[name="kcca-root"]');
  KCCA.root = rootMeta ? rootMeta.getAttribute('content') : '';

  KCCA.api = function (path) {
    var p = path || '';
    if (/^(https?:)?\/\//.test(p) || p.charAt(0) === '/') return p;
    return KCCA.root + p.replace(/^\.?\//, '');
  };

  KCCA.icon = function (name, size) {
    var p = ICONS[name] || ICONS.info;
    var s = size || 18;
    return '<svg class="ico" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" ' +
           'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' +
           'aria-hidden="true">' + p + '</svg>';
  };

  /* ---------------------------------------------------------------- */
  /* Role dashboard drawer                                             */
  /* ---------------------------------------------------------------- */
  /* The menu button in the top bar opens this. It is the same dashboard
     the dashboard page renders, fetched from one endpoint and drawn by the
     same partials, so there is never a second copy of the figures. */
  var roledashLoaded = false;

  KCCA.openRoleDash = function () {
    var d = document.getElementById('roledash');
    if (!d) return;
    d.hidden = false;
    document.body.style.overflow = 'hidden';
    var btn = document.querySelector('[data-rail-toggle]');
    if (btn) btn.setAttribute('aria-expanded', 'true');

    if (roledashLoaded) return;

    var body = document.getElementById('roledash-body');
    if (!body) return;
    body.innerHTML = '<div class="empty"><div class="big">' + KCCA.icon('gauge', 26) + '</div>'
                   + '<h3>Loading your dashboard…</h3></div>';

    fetch(KCCA.api('api/role_panel.php'), { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) {
          body.innerHTML = '<div class="alert alert-bad">Your dashboard could not be loaded.</div>';
          return;
        }
        var html = '';

        if (data.decisions && data.decisions.length && data.decisions[0].tone !== 'ok') {
          html += '<div class="rd-card"><h4>Waiting on you</h4><div class="stack-s">';
          data.decisions.forEach(function (d2) {
            html += '<a class="criterion" href="' + d2.href + '">'
                  + '<span class="cl">' + esc(d2.label) + '</span>'
                  + '<span class="cv"><span class="badge ' + d2.tone + '">&rarr;</span></span></a>';
          });
          html += '</div></div>';
        }

        html += '<div class="rd-card"><h4>At a glance</h4><div class="rd-stats">';
        (data.stats || []).forEach(function (s) {
          html += '<div class="rd-stat ' + (s.tone || '') + '">'
                + '<span class="l">' + esc(s.label) + '</span>'
                + '<span class="v">' + esc(s.value) + '</span>'
                + '<span class="m">' + esc(s.meta) + '</span></div>';
        });
        html += '</div></div>';

        html += data.sections || '';
        html += '<div class="rd-foot"><a class="btn btn-primary btn-sm btn-block" href="dashboard.php">'
              + 'Open the full dashboard</a></div>';

        body.innerHTML = html;
        roledashLoaded = true;

        /* Charts inside injected markup need drawing by hand. */
        if (window.KCCACharts && window.KCCACharts.renderAll) {
          window.KCCACharts.renderAll();
        }
      })
      .catch(function () {
        body.innerHTML = '<div class="alert alert-bad">'
          + 'Your dashboard could not be loaded. Check the connection and try again.</div>';
      });
  };

  KCCA.closeRoleDash = function () {
    var d = document.getElementById('roledash');
    if (d) d.hidden = true;
    document.body.style.overflow = '';
    var btn = document.querySelector('[data-rail-toggle]');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  };

  function esc(t) {
    return String(t === null || t === undefined ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function initRoleDash() {
    var toggle = document.querySelector('[data-rail-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var d = document.getElementById('roledash');
        if (d && !d.hidden) KCCA.closeRoleDash(); else KCCA.openRoleDash();
      });
    }

    document.querySelectorAll('[data-roledash-close]').forEach(function (b) {
      b.addEventListener('click', KCCA.closeRoleDash);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var d = document.getElementById('roledash');
        if (d && !d.hidden) KCCA.closeRoleDash();
        var acct = document.getElementById('acct');
        if (acct && acct.classList.contains('open')) acct.classList.remove('open');
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /* Rail toggle                                                       */
  /* ---------------------------------------------------------------- */
  function initRail() {
    var rail = document.querySelector('.rail');
    if (!rail) return;

    /* Closes when a link is chosen on a narrow screen, where the rail
       covers the content. On a wide screen it stays open, which is what
       the layout expects. */
    rail.addEventListener('click', function (e) {
      if (window.innerWidth <= 1024 && e.target.closest('a')) {
        rail.classList.remove('open');
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /* Account menu                                                      */
  /* ---------------------------------------------------------------- */
  function initAccount() {
    var wrap = document.getElementById('acct');
    if (!wrap) return;
    var btn = wrap.querySelector('.acct-toggle');
    var menu = wrap.querySelector('.acct-menu');

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = wrap.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) wrap.classList.remove('open');
    });
  }

  KCCA.closeModal = function () {
    var b = document.querySelector('.modal-back');
    if (b) { b.remove(); document.body.style.overflow = ''; }
  };

  /* ---------------------------------------------------------------- */
  /* Ask for a short piece of text                                     */
  /* ---------------------------------------------------------------- */
  /**
   * Ask the user for a line of text — a rejection reason, a count, a note.
   *
   * Replaces window.prompt(), which is the wrong tool here: a browser that
   * suppresses dialogs makes prompt() return null, so the button appears
   * to do nothing at all. It also cannot be styled, cannot be validated,
   * and gives no room for a reason that has to be read by a trader or by
   * Internal Audit.
   *
   * Resolves with the trimmed text, or null if the user cancels.
   *
   *   KCCA.ask('Reject this recommendation',
   *            'Why is it rejected? The reason is recorded and shown to the trader.',
   *            { button: btn, busyLabel: 'Recording…', required: true })
   *     .then(function (reason) { if (reason === null) return; /* … *\/ });
   */
  KCCA.ask = function (title, message, opts) {
    opts = opts || {};
    var required = opts.required !== false;
    var placeholder = opts.placeholder || '';
    var value = opts.value || '';
    var rows = opts.rows || 3;
    var maxlen = opts.maxLength || 500;
    var label = opts.label || '';
    var buttonLabel = opts.buttonLabel || 'Confirm';
    var numeric = opts.numeric === true;

    return new Promise(function (resolve) {
      var back = document.createElement('div');
      back.className = 'modal-back';
      back.setAttribute('data-ask', '1');

      var inputType = numeric ? 'number' : 'textarea';
      var input = numeric
        ? '<input type="number" id="kcca-ask-input" min="0" max="' + maxlen + '" value="'
          + escapeAttr(value) + '" placeholder="' + escapeAttr(placeholder) + '">'
        : '<textarea id="kcca-ask-input" rows="' + rows + '" maxlength="' + maxlen
          + '" placeholder="' + escapeAttr(placeholder) + '">' + escapeHtml(value) + '</textarea>';

      back.innerHTML =
        '<div class="modal">' +
          '<div class="modal-head">' +
            '<h3>' + escapeHtml(title) + '</h3>' +
            '<button type="button" class="close" data-ask-cancel aria-label="Close">&times;</button>' +
          '</div>' +
          '<div class="modal-body">' +
            (message ? '<p class="small muted">' + escapeHtml(message) + '</p>' : '') +
            (label ? '<label for="kcca-ask-input" class="strong small">' + escapeHtml(label) + '</label>' : '') +
            input +
            '<span class="hint error" id="kcca-ask-error" hidden></span>' +
          '</div>' +
          '<div class="modal-foot">' +
            '<button type="button" class="btn btn-ghost" data-ask-cancel>Cancel</button>' +
            '<button type="button" class="btn btn-primary" data-ask-ok>' + escapeHtml(buttonLabel) + '</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(back);
      document.body.style.overflow = 'hidden';

      var field = back.querySelector('#kcca-ask-input');
      var err = back.querySelector('#kcca-ask-error');
      var okBtn = back.querySelector('[data-ask-ok]');
      field.focus();
      if (field.select && numeric) field.select();

      function finish(result) {
        back.remove();
        document.body.style.overflow = '';
        resolve(result);
      }

      function confirm() {
        var text = (field.value || '').trim();
        if (required && text === '') {
          err.textContent = numeric
            ? 'Enter a number — zero is a valid answer.'
            : opts.requiredMessage || 'This is recorded, so it cannot be left blank.';
          err.hidden = false;
          field.focus();
          return;
        }
        if (numeric) {
          if (!/^\d+$/.test(text)) {
            err.textContent = 'Enter a whole number.';
            err.hidden = false;
            field.focus();
            return;
          }
          if (parseInt(text, 10) > maxlen) {
            err.textContent = 'Cannot be more than ' + maxlen + '.';
            err.hidden = false;
            return;
          }
        }
        finish(text);
      }

      okBtn.addEventListener('click', confirm);
      back.querySelectorAll('[data-ask-cancel]').forEach(function (b) {
        b.addEventListener('click', function () { finish(null); });
      });
      field.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (numeric || e.metaKey || e.ctrlKey)) { e.preventDefault(); confirm(); }
        if (e.key === 'Escape') { e.preventDefault(); finish(null); }
      });
      back.addEventListener('click', function (e) {
        if (e.target === back) finish(null);
      });
    });
  };

  function escapeHtml(t) {
    return String(t === null || t === undefined ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function escapeAttr(t) {
    return escapeHtml(t).replace(/"/g, '&quot;');
  }

  function initModals() {
    document.addEventListener('click', function (e) {
      var open = e.target.closest('[data-modal]');
      if (open) {
        var url = open.getAttribute('data-modal');
        var target = document.getElementById(open.getAttribute('data-modal-target') || '');
        if (target) {
          document.body.appendChild(target);
          document.body.style.overflow = 'hidden';
        } else if (url) {
          /* Modal URLs are written application-relative ("api/form.php?…")
             and resolved against the app root, so the same attribute works
             from a page in pages/, auth/, or the root itself. */
          KCCA.modalFrom(KCCA.api ? KCCA.api(url) : url);
        }
        return;
      }
      if (e.target.closest('[data-modal-close]') ||
          e.target.classList.contains('modal-back')) {
        KCCA.closeModal();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') KCCA.closeModal();
    });
  }

  KCCA.modalFrom = function (url, opts) {
    opts = opts || {};
    fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) {
        if (!r.ok) {
          /* Say why, in the server's own words, instead of a status number. */
          return r.json().catch(function () { return {}; }).then(function (j) {
            throw new Error((j && j.error) || ('The server answered ' + r.status + '.'));
          });
        }
        return r.text();
      })
      .then(function (html) {
        var back = document.createElement('div');
        back.className = 'modal-back';
        back.innerHTML = html;
        document.body.appendChild(back);
        document.body.style.overflow = 'hidden';
        if (opts.onReady) opts.onReady(back);
      })
      .catch(function (err) {
        var back = document.createElement('div');
        back.className = 'modal-back';
        back.innerHTML = '<div class="modal"><div class="modal-body">' +
          '<div class="alert alert-bad">' + KCCA.icon('alert') + ' ' + escapeHtml(err.message) + '</div>' +
          '<div class="row mt-2"><button type="button" class="btn btn-ghost" data-modal-close>Close</button></div>' +
          '</div></div>';
        document.body.appendChild(back);
      });
  };

  /* ---------------------------------------------------------------- */
  /* Post to an API endpoint and reload / re-render                    */
  /* ---------------------------------------------------------------- */
  /**
   * POST to an API endpoint and parse the JSON reply.
   *
   * A 4xx reply from these endpoints carries a JSON body explaining the
   * refusal ("An allocation for this vendor to this market already
   * exists"). That is business logic, not a transport failure, so it is
   * handed back to the caller rather than thrown. Only a genuine network
   * or parse failure throws, and the caller shows "could not reach the
   * server" for that.
   */
  KCCA.post = function (url, data) {
    var body = new FormData();
    body.append('csrf', KCCA.csrf || '');
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });

    return fetch(url, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'fetch' }
    }).then(function (r) {
      return r.json().catch(function () {
        throw new Error('The server returned an unreadable response (HTTP ' + r.status + ').');
      });
    });
  };

  /* ---------------------------------------------------------------- */
  /* Copy helper                                                       */
  /* ---------------------------------------------------------------- */
  /* ---------------------------------------------------------------- */
  /* Transient status message                                          */
  /* ---------------------------------------------------------------- */
  KCCA.flash = function (message, tone, ms) {
    tone = tone || 'info';
    var el = document.createElement('div');
    el.className = 'flash flash-' + tone;
    el.setAttribute('role', tone === 'bad' ? 'alert' : 'status');
    /* The message is text. It often carries a trader's name, and a name is
       data, never markup. */
    var icon = document.createElement('span');
    icon.className = 'flash-icon';
    icon.textContent = tone === 'ok' ? '\u2713' : '!';
    var text = document.createElement('span');
    text.textContent = String(message === null || message === undefined ? '' : message);
    el.appendChild(icon);
    el.appendChild(text);
    document.body.appendChild(el);

    setTimeout(function () { el.classList.add('out'); }, ms || 4200);
    setTimeout(function () { el.remove(); }, (ms || 4200) + 400);
  };

  /* ---------------------------------------------------------------- */
  /* Confirm-before-post for the state-changing forms                 */
  /* ---------------------------------------------------------------- */
  KCCA.postAction = function (url, data, opts) {
    opts = opts || {};
    if (opts.confirm && !window.confirm(opts.confirm)) {
      return Promise.resolve(null);
    }

    var btn = opts.button;
    var label = btn ? btn.textContent : null;
    if (btn) { btn.disabled = true; if (opts.busyLabel) btn.textContent = opts.busyLabel; }

    return KCCA.post(url, data)
      .then(function (res) {
        if (btn) { btn.disabled = false; if (label !== null) btn.textContent = label; }
        if (res && res.ok) {
          KCCA.flash(res.message || 'Done.', 'ok');
          if (opts.reload !== false) {
            setTimeout(function () { location.reload(); }, opts.reloadDelay || 900);
          }
        } else {
          KCCA.flash((res && res.error) || 'That action could not be completed.', 'bad');
        }
        return res;
      })
      .catch(function () {
        if (btn) { btn.disabled = false; if (label !== null) btn.textContent = label; }
        KCCA.flash('Could not reach the server. Check your connection and try again.', 'bad');
      });
  };

  KCCA.copy = function (text, btn) {
    var done = function () {
      if (!btn) return;
      var old = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(function () { btn.textContent = old; }, 1400);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      document.body.removeChild(ta);
    }
  };

  /* ---------------------------------------------------------------- */
  /* Auto-submit filters                                               */
  /* ---------------------------------------------------------------- */
  function initFilters() {
    document.addEventListener('change', function (e) {
      if (e.target.matches('[data-autosubmit]')) {
        e.target.form.submit();
      }
    });

    // Debounced search inputs
    var timer = null;
    document.addEventListener('input', function (e) {
      if (!e.target.matches('[data-search-submit]')) return;
      clearTimeout(timer);
      timer = setTimeout(function () { e.target.form.submit(); }, 420);
    });
  }

  /* ---------------------------------------------------------------- */
  /* Modal forms: save in place, never leave the page                  */
  /* ---------------------------------------------------------------- */
  /**
   * The forms served by api/form.php (register or edit a trader, record a
   * footfall count, edit a pitch, record a land-use restriction) arrive as
   * HTML and are put into the page with innerHTML. A browser does not run a
   * <script> that arrives that way, so the script each form carried to catch
   * its own save never ran, and the form fell back to an ordinary submit:
   * the save happened, then the browser navigated to the API address and
   * showed the raw reply, {"ok":true,...}, on a blank page.
   *
   * One handler, here, in the page's own script, which does run. It posts
   * the form in the background and then either
   *   - closes the panel, confirms, and refreshes the page behind it, when
   *     the endpoint answers with JSON that says ok;
   *   - tells the user why, when it answers with JSON that says it failed;
   *   - or swaps the form in place, with its messages beside the fields that
   *     caused them, when the endpoint answers with the form again. What the
   *     person typed is kept.
   * Only forms that post to api/form.php are handled here; a page that has
   * its own handler for its own form is left alone.
   */
  function initModalForms() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.matches || !form.matches('form.modal-form')) return;
      if (String(form.getAttribute('action') || '').indexOf('api/form.php') === -1) return;
      if (e.defaultPrevented) return;

      e.preventDefault();
      if (form.getAttribute('data-saving') === '1') return;     // a double click saves once
      form.setAttribute('data-saving', '1');

      var btn = form.querySelector('[type="submit"]');
      var label = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

      function reopen() {
        form.removeAttribute('data-saving');
        if (btn) { btn.disabled = false; btn.innerHTML = label; }
      }

      fetch(form.getAttribute('action'), {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json, text/html' }
      })
        .then(function (r) {
          var type = r.headers.get('content-type') || '';
          if (type.indexOf('application/json') !== -1) {
            return r.json().then(function (j) { return { json: j, status: r.status }; });
          }
          return r.text().then(function (html) { return { html: html, status: r.status }; });
        })
        .then(function (res) {
          if (res.json) {
            if (res.json.ok) {
              KCCA.closeModal();
              KCCA.flash(res.json.message || 'Saved.', 'ok');
              setTimeout(function () { location.reload(); }, 800);
              return;
            }
            KCCA.flash(res.json.error || 'That could not be saved. Nothing was changed.', 'bad');
            reopen();
            return;
          }
          /* The endpoint sent the form back with its messages. */
          var host = form.closest('.modal-back');
          if (host && res.html && res.html.indexOf('modal') !== -1) {
            host.innerHTML = res.html;
            var bad = host.querySelector('.alert-bad, .field-error, .err');
            if (bad && bad.scrollIntoView) bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
          }
          KCCA.flash('The server answered unexpectedly. Please try again.', 'bad');
          reopen();
        })
        .catch(function () {
          KCCA.flash('Could not reach the server. Nothing was saved.', 'bad');
          reopen();
        });
    });
  }

  /* ---------------------------------------------------------------- */
  /* Confirm before a destructive POST                                 */
  /* ---------------------------------------------------------------- */
  function initConfirms() {
    document.addEventListener('submit', function (e) {
      var msg = e.target.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) e.preventDefault();
    });

    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-confirm-link]');
      if (b && !window.confirm(b.getAttribute('data-confirm-link'))) {
        e.preventDefault();
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /* Select all in a table                                             */
  /* ---------------------------------------------------------------- */
  function initSelectAll() {
    document.addEventListener('change', function (e) {
      if (!e.target.matches('[data-check-all]')) return;
      var scope = e.target.closest('table') || document;
      scope.querySelectorAll('[data-check-row]').forEach(function (cb) {
        cb.checked = e.target.checked;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /* Chips as filter links                                             */
  /* ---------------------------------------------------------------- */
  function initChips() {
    document.addEventListener('click', function (e) {
      var chip = e.target.closest('.chip');
      if (!chip || chip.tagName !== 'A') return;
      e.preventDefault();
      var url = new URL(chip.href, location.href);
      var params = new URLSearchParams(url.search);
      params.delete('page');
      location.href = url.pathname + '?' + params.toString();
    });
  }

  /* ---------------------------------------------------------------- */
  /* Print                                                            */
  /* ---------------------------------------------------------------- */
  function initPrint() {
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-print]')) {
        e.preventDefault();
        window.print();
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /* Boot                                                             */
  /* ---------------------------------------------------------------- */
  function boot() {
    var m = document.querySelector('meta[name="kcca-csrf"]');
    KCCA.csrf = m ? m.getAttribute('content') : '';

    initRoleDash();
    initRail();
    initAccount();
    initModals();
    initFilters();
    initModalForms();
    initConfirms();
    initSelectAll();
    initChips();
    initPrint();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();