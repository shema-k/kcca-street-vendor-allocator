/**
 * KCCA VRSP — market stall map.
 *
 * Plots every individual trading pitch in a market at its real position.
 * Online it draws Leaflet over OpenStreetMap tiles; if tiles are
 * unreachable it falls back to a vector grid drawn from the market's own
 * pitch coordinates, so the map is never an empty frame.
 *
 * Stall colours:
 *   vacant            green   — the trader may request it
 *   reserved          amber   — held by a live offer
 *   occupied          grey    — somebody is trading there
 *   under-maintenance red     — not available, being worked on
 *   requested         purple  — an open request from another trader
 */
(function () {
  'use strict';

  var escHtml = function (t) {
    return String(t === null || t === undefined ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  };

  var COLOURS = {
    vacant:            '#1B7F4D',
    reserved:          '#EAA515',
    occupied:          '#8C9199',
    'under-maintenance':'#C0261A',
    requested:         '#7B3FA0'
  };

  var Map = {
    _leaflet: null,
    _offline: false,
    _stalls: [],
    _layer: null,
    _container: null,
    _all: [],
    _data: null,

    /** Boot a stall map. options.stalls is the pitch array. */
    init: function (containerId, options) {
      var c = document.getElementById(containerId);
      if (!c) return null;

      var opts = options || {};
      var data = opts.data || {};
      var stalls = data.stalls || [];
      Map._stalls = stalls;
      Map._all = stalls;
      Map._data = data;
      Map._container = c;

      var forced = null;
      try { forced = new URLSearchParams(location.search).get('map'); } catch (e) { forced = null; }

      var leafletOk = (typeof L !== 'undefined') && forced !== 'offline';

      if (leafletOk) {
        try {
          Map._leaflet = Map._renderLeaflet(c, data);
          Map._offline = false;
        } catch (e) {
          Map._leaflet = null;
        }
      }

      if (!Map._leaflet) {
        Map._offline = true;
        Map._renderOffline(c, data);
      }

      Map._setStatus(containerId, data, stalls.length);
      Map._bindLegend(containerId);

      return Map._leaflet || true;
    },

    /* ---------------------------------------------------------------- */
    /* Leaflet                                                          */
    /* ---------------------------------------------------------------- */
    _renderLeaflet: function (c, data) {
      var map = L.map(c, {
        zoomControl: false,
        attributionControl: true,
        preferCanvas: true
      });

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(map);

      var bounds = [];
      var group = L.layerGroup().addTo(map);

      Map._stalls.forEach(function (s) {
        var lat = parseFloat(s.lat), lng = parseFloat(s.lng);
        if (!isFinite(lat) || !isFinite(lng)) return;
        bounds.push([lat, lng]);

        var colour = COLOURS[s.status] || COLOURS.occupied;

        var m = L.circleMarker([lat, lng], {
          radius: 5,
          fillColor: colour,
          fillOpacity: 0.92,
          color: '#ffffff',
          weight: 1,
          opacity: 0.9
        }).addTo(group);

        m.bindTooltip(s.space_code + ' · ' + s.status, { direction: 'top', offset: [0, -6] });
        m.on('click', function () { Map._select(s, true); });
      });

      if (bounds.length) {
        map.fitBounds(bounds, { padding: [36, 36] });
      }

      var tb = document.getElementById(c.id + '-tools');
      if (tb) {
        tb.addEventListener('click', function (ev) {
          var b = ev.target.closest('[data-zoom]');
          if (!b) return;
          var a = b.getAttribute('data-zoom');
          if (a === 'in') map.zoomIn();
          else if (a === 'out') map.zoomOut();
          else if (bounds.length) map.fitBounds(bounds, { padding: [36, 36] });
        });
      }

      c.setAttribute('aria-label',
        'Map of ' + (data.market || 'this market') + ' showing ' +
        bounds.length + ' individual trading pitches, colour coded by status.');

      return map;
    },

    /* ---------------------------------------------------------------- */
    /* Offline vector                                                    */
    /* ---------------------------------------------------------------- */
    _renderOffline: function (c, data) {
      var width = 940, height = 620;
      var ns = 'http://www.w3.org/2000/svg';

      /* Work out the grid from the data itself rather than assuming a
         spacing, so a market with any pitch layout comes out right. */
      var lats = {}, lngs = {};
      var n = 0, i;

      Map._stalls.forEach(function (s) {
        var lat = parseFloat(s.lat), lng = parseFloat(s.lng);
        if (!isFinite(lat) || !isFinite(lng)) return;
        lats[lat] = true; lngs[lng] = true; n++;
      });

      if (!n) {
        c.innerHTML = '<div class="empty"><h3>No pitches to plot</h3>'
                    + '<p>Nothing matches the current filter.</p></div>';
        return;
      }

      var latKeys = Object.keys(lats).sort();
      var lngKeys = Object.keys(lngs).sort();
      var lat0 = parseFloat(latKeys[0]), lat1 = parseFloat(latKeys[latKeys.length - 1]);
      var lng0 = parseFloat(lngKeys[0]), lng1 = parseFloat(lngKeys[lngKeys.length - 1]);
      var rows = latKeys.length, cols = lngKeys.length;

      /* Scale to the container, keeping the footprint's shape. Pitch squares
         are drawn from the measured cell size, not from a guessed one. */
      var pad = 34;
      var innerW = width - pad * 2;
      var innerH = height - pad * 2;
      var cell = Math.floor(Math.min(innerW / cols, innerH / rows));
      var planW = cell * cols, planH = cell * rows;
      var ox = (width - planW) / 2, oy = (height - planH) / 2;

      var sx = function (lng) { return ox + (lng - lng0) / ((lng1 - lng0) || 1e-9) * planW; };
      var sy = function (lat) { return oy + (1 - (lat - lat0) / ((lat1 - lat0) || 1e-9)) * planH; };

      var out = [];
      out.push('<svg viewBox="0 0 ' + width + ' ' + height + '" class="stall-svg" '
             + 'preserveAspectRatio="xMidYMid meet" role="img" aria-label="Pitch plan of '
             + (data.market || 'this market') + ', ' + n + ' pitches in '
             + Object.keys(Map._sections()).length + ' sections, colour coded by status">');
      out.push('<rect x="0" y="0" width="' + width + '" height="' + height + '" fill="#FFFFFF"/>');

      /* Section bands behind the pitches, labelled — this is how a market
         plan is actually read. */
      var sectionRows = Map._sectionRows();
      var sectionLabels = Map._sectionOrder();
      var lastLabel = '';

      sectionLabels.forEach(function (label) {
        var first = sectionRows[label];
        if (!first) return;
        var top = sy(first.maxLat);
        var bottom = sy(first.minLat);
        if (bottom - top < 14) return;   // too thin to label usefully
        out.push('<rect x="' + ox + '" y="' + top.toFixed(1) + '" width="' + planW.toFixed(1)
               + '" height="' + (bottom - top).toFixed(1) + '" fill="#EEF1F4" stroke="#C9D6DE" '
               + 'stroke-width="1" rx="3"/>');
        out.push('<text x="' + (ox + 6) + '" y="' + (top + 14).toFixed(1)
               + '" class="stall-section-label">' + escHtml(label) + '</text>');
      });

      out.push('</svg>');
      c.innerHTML = out.join('');

      var svg = c.querySelector('svg');

      /* Each pitch as a square, sized from the measured cell. At 3,000
         pitches the squares are the only legible way to show them all. */
      var size = Math.max(2.4, Math.min(9, cell * 0.78));
      var frag = document.createDocumentFragment();

      Map._stalls.forEach(function (s) {
        var lat = parseFloat(s.lat), lng = parseFloat(s.lng);
        if (!isFinite(lat) || !isFinite(lng)) return;

        var g = document.createElementNS(ns, 'g');
        g.setAttribute('class', 'stall-dot stall-' + s.status);
        g.setAttribute('tabindex', '0');
        g.setAttribute('role', 'button');
        g.setAttribute('data-stall', s.id);
        g.setAttribute('aria-label', s.space_code + ', ' + s.section + ', ' + s.status);

        var r = document.createElementNS(ns, 'rect');
        r.setAttribute('x', (sx(lng) - size / 2).toFixed(1));
        r.setAttribute('y', (sy(lat) - size / 2).toFixed(1));
        r.setAttribute('width', size.toFixed(1));
        r.setAttribute('height', size.toFixed(1));
        r.setAttribute('rx', '0.6');
        r.setAttribute('fill', COLOURS[s.status] || COLOURS.occupied);
        r.setAttribute('stroke', s.status === 'vacant' ? '#0A6339' : '#FFFFFF');
        r.setAttribute('stroke-width', s.status === 'vacant' ? '0.7' : '0.25');
        r.setAttribute('opacity', '0.95');
        g.appendChild(r);

        var t = document.createElementNS(ns, 'title');
        t.textContent = s.space_code + ' \u00b7 ' + s.section + ' \u00b7 ' + s.status;
        g.appendChild(t);

        g.addEventListener('click', function () { Map._select(s, true); });
        g.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); Map._select(s, true); }
        });

        frag.appendChild(g);
      });

      svg.appendChild(frag);
    },

    /* Latitude bands per section, and the order sections appear top-down. */
    _sectionRows: function () {
      var bands = {};
      Map._stalls.forEach(function (s) {
        var lat = parseFloat(s.lat);
        if (!isFinite(lat)) return;
        var b = bands[s.section] || (bands[s.section] = { minLat: lat, maxLat: lat });
        if (lat < b.minLat) b.minLat = lat;
        if (lat > b.maxLat) b.maxLat = lat;
      });
      return bands;
    },

    _sectionOrder: function () {
      var bands = Map._sectionRows();
      return Object.keys(bands).sort(function (a, b) { return bands[b].minLat - bands[a].minLat; });
    },

    _sections: function () { return Map._sectionRows(); },

    /* Escape for an SVG text node. */
    _esc: function (t) {
      return String(t === null || t === undefined ? '' : t)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },

    /* ---------------------------------------------------------------- */
    /* Shared                                                           */
    /* ---------------------------------------------------------------- */
    _setStatus: function (containerId, data, count) {
      var el = document.getElementById(containerId + '-status');
      if (!el) return;
      el.innerHTML = (Map._offline
        ? '<span class="live offline"></span>'
        : '<span class="live"></span>') +
        '<span>' + (Map._offline ? 'Offline vector plan' : 'OpenStreetMap base map') +
        ' · ' + count + ' pitches plotted</span>';
    },

    /**
     * Show the stalls of one status; null shows them all.
     *
     * The full set is kept separately from what is drawn. Filtering
     * _stalls in place is the obvious way to write this and it is wrong:
     * the removed pitches are gone, so "show all" can never bring them
     * back and the plan silently loses data on the first click.
     */
    filter: function (status, section) {
      var source = Map._all || Map._stalls;

      Map._stalls = source.filter(function (s) {
        return (!status || s.status === status) && (!section || s.section === section);
      });

      // Re-render. With up to 3,000 pitches in one market, rebuilding the
      // layer is cheaper than tracking a set reference per marker.
      Map._container.innerHTML = '';
      if (Map._leaflet) {
        Map._leaflet.remove();
        Map._leaflet = null;
        Map._renderLeaflet(Map._container, Map._data);
      } else {
        Map._renderOffline(Map._container, Map._data);
      }
      Map._setStatus(Map._container.id, Map._data, Map._stalls.length);
    },

    _bindLegend: function (containerId) {
      var el = document.getElementById(containerId + '-legend');
      if (!el) return;
      el.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-status-filter]');
        if (!b) return;
        var status = b.getAttribute('data-status-filter');
        Map.filter(status === 'all' ? null : status,
                    (document.getElementById('stall-section') || {}).value || null);
      });
    },

    /** A stall was clicked: publish it to the page. */
    _select: function (stall, pan) {
      if (pan && Map._leaflet) {
        Map._leaflet.flyTo([parseFloat(stall.lat), parseFloat(stall.lng)],
                           Math.max(17, Map._leaflet.getZoom()), { duration: 0.6 });
      }
      document.dispatchEvent(new CustomEvent('stall:selected', { detail: stall }));
    },

    focus: function (lat, lng, zoom) {
      if (Map._leaflet) {
        Map._leaflet.flyTo([lat, lng], zoom || 18, { duration: 0.6 });
      }
    },

    /** Called by the page after a filter change re-fetches stalls. */
    setStalls: function (stalls, data) {
      Map._stalls = stalls;
      Map._data = data || Map._data;
    },

    isOffline: function () { return Map._offline; }
  };

  window.KCCAStallMap = Map;
})();
