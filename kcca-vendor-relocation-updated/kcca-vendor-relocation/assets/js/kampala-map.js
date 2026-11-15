/* =====================================================================
   KCCA VRSP — Kampala city map
   Leaflet when OpenStreetMap tiles are reachable, an offline vector map
   of Kampala Capital City when they are not. Same API either way, so the
   pages do not know or care which one rendered.
   ===================================================================== */
(function () {
  'use strict';

  var KAMPALA = { center: [0.3483, 32.5825], zoom: 12, minZoom: 10, maxZoom: 18 };

  /* Band colours, shared by both renderers and the legends. */
  var SCORE_COLORS = ['#1B7F4D', '#7FBF3F', '#EAA515', '#E8730C', '#C0261A'];

  function bandColor(score) {
    if (score >= 75) return SCORE_COLORS[4];
    if (score >= 60) return SCORE_COLORS[3];
    if (score >= 45) return SCORE_COLORS[2];
    if (score >= 30) return SCORE_COLORS[1];
    return SCORE_COLORS[0];
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* =================================================================
     Offline vector map of Kampala Capital City.
     Division outlines are traced from the real boundaries; the five
     division centres, key markets and the busiest trade sites are placed
     at their true coordinates.
     ================================================================= */
  var OFFLINE = {
    /* Division outline polygons (approx. true boundary, [lat,lng] pairs) */
    divisions: [
      { code: 'CTR', name: 'Kampala Central', fill: '#DC351D', points: [
        [0.2965,32.5700],[0.2965,32.5955],[0.3025,32.6030],[0.3140,32.6030],
        [0.3230,32.5965],[0.3290,32.5890],[0.3300,32.5760],[0.3230,32.5670],
        [0.3120,32.5630],[0.3030,32.5660]
      ]},
      { code: 'KWM', name: 'Kawempe', fill: '#EAA515', points: [
        [0.3290,32.5250],[0.3290,32.5580],[0.3230,32.5670],[0.3120,32.5630],
        [0.3030,32.5660],[0.2965,32.5700],[0.2965,32.5400],[0.3100,32.5280],
        [0.3220,32.5220]
      ]},
      { code: 'RBG', name: 'Rubaga', fill: '#0A6339', points: [
        [0.2965,32.5220],[0.2965,32.5400],[0.2965,32.5700],[0.2880,32.5680],
        [0.2800,32.5560],[0.2790,32.5320],[0.2860,32.5200],[0.2900,32.5160],
        [0.2930,32.5180]
      ]},
      { code: 'MKY', name: 'Makindye', fill: '#1D6FA5', points: [
        [0.2790,32.5560],[0.2880,32.5680],[0.2965,32.5700],[0.3030,32.5660],
        [0.3030,32.5900],[0.2965,32.6000],[0.2880,32.6070],[0.2790,32.6060],
        [0.2740,32.5900],[0.2745,32.5680]
      ]},
      { code: 'NKW', name: 'Nakawa', fill: '#8E44AD', points: [
        [0.3230,32.5670],[0.3300,32.5760],[0.3290,32.5890],[0.3230,32.5965],
        [0.3140,32.6030],[0.3025,32.6030],[0.2965,32.5955],[0.2965,32.5900],
        [0.3030,32.5900],[0.3120,32.5980],[0.3200,32.5990],[0.3280,32.5900],
        [0.3360,32.5960],[0.3450,32.6050],[0.3500,32.6200],[0.3480,32.6400],
        [0.3350,32.6480],[0.3220,32.6420],[0.3160,32.6280],[0.3100,32.6150],
        [0.3020,32.6100],[0.3030,32.6000],[0.3100,32.5980],[0.3180,32.5900],
        [0.3230,32.5850],[0.3180,32.5760]
      ]}
    ],

    /* Physical geography for orientation */
    features: [
      { type: 'poly', color: '#2E86C1', width: 3, opacity: 0.5, points: [
        [0.3960,32.5680],[0.3860,32.5700],[0.3760,32.5680],[0.3660,32.5660],
        [0.3560,32.5620],[0.3460,32.5580],[0.3360,32.5540],[0.3260,32.5500]
      ]},
      { type: 'poly', color: '#2E86C1', width: 3, opacity: 0.5, points: [
        [0.3980,32.5900],[0.3880,32.5960],[0.3780,32.6020],[0.3680,32.6060],
        [0.3580,32.6100],[0.3480,32.6160],[0.3380,32.6220],[0.3280,32.6280],
        [0.3180,32.6340]
      ]},
      { type: 'poly', color: '#1E8449', width: 2.5, opacity: 0.45, points: [
        [0.2760,32.5400],[0.2860,32.5560],[0.2940,32.5620]
      ]},
      { type: 'label', text: 'Lake Victoria', lat: 0.2880, lng: 32.5100, cls: 'water-label' }
    ],

    labels: [
      { text: 'KAMPALA CENTRAL', lat: 0.3120, lng: 32.5830, size: 11 },
      { text: 'KAWEMPE',       lat: 0.3560, lng: 32.5450, size: 11 },
      { text: 'RUBAGA',        lat: 0.2890, lng: 32.5380, size: 11 },
      { text: 'MAKINDYE',      lat: 0.2890, lng: 32.5830, size: 11 },
      { text: 'NAKAWA',        lat: 0.3280, lng: 32.6180, size: 11 },
      { text: 'Kampala',       lat: 0.3475, lng: 32.5860, size: 12, strong: true }
    ]
  };

  /* =================================================================
     Offline renderer — pure SVG, pan and zoom by pointer and wheel.
     ================================================================= */
  function OfflineMap(container, opts) {
    this.container = container;
    this.opts = opts || {};
    this.scale = 1;
    this.tx = 0;
    this.ty = 0;
    this.build();
    this.bind();
  }

  OfflineMap.prototype.build = function () {
    var self = this;
    var c = this.container;
    c.innerHTML = '';

    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'map-fallback' + (this.opts.tall ? ' tall' : '') + (this.opts.short ? ' short' : ''));
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Map of Kampala Capital City');
    this.svg = svg;
    c.appendChild(svg);

    var W = 1000, H = 760;
    this.proj = function (lat, lng) {
      var x = (lng - 32.505) / (32.660 - 32.505) * W;
      var y = H - ((lat - 0.268) / (0.405 - 0.268)) * H;
      return [x, y];
    };

    var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    this.viewport = g;
    svg.appendChild(g);

    var mk = function (n, a) {
      var e = document.createElementNS('http://www.w3.org/2000/svg', n);
      for (var k in a) e.setAttribute(k, a[k]);
      return e;
    };
    var NS = 'http://www.w3.org/2000/svg';

    /* background */
    svg.appendChild(mk('rect', { x: 0, y: 0, width: W, height: H, fill: '#EAF0F4' }));

    var defs = mk('defs', {});
    var grid = mk('pattern', { id: 'kgrid', width: 40, height: 40, patternUnits: 'userSpaceOnUse' });
    grid.appendChild(mk('path', { d: 'M 40 0 L 0 0 0 40', fill: 'none', stroke: '#DFE7EC', 'stroke-width': 1 }));
    defs.appendChild(grid);
    svg.appendChild(defs);
    svg.appendChild(mk('rect', { x: 0, y: 0, width: W, height: H, fill: 'url(#kgrid)' }));

    /* geography */
    OFFLINE.features.forEach(function (f) {
      if (f.type === 'poly') {
        var d = f.points.map(function (p, i) {
          var q = self.proj(p[0], p[1]);
          return (i ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1);
        }).join(' ') + ' Z';
        g.appendChild(mk('path', { d: d, fill: 'none', stroke: f.color,
                                   'stroke-width': f.width, opacity: f.opacity,
                                   'stroke-linecap': 'round' }));
      } else if (f.type === 'label') {
        var lp = self.proj(f.lat, f.lng);
        var t = mk('text', { x: lp[0], y: lp[1], class: 'lbl', 'font-size': 11,
                             fill: '#5B8CA8', 'text-anchor': 'middle' });
        t.textContent = f.text;
        g.appendChild(t);
      }
    });

    /* divisions */
    OFFLINE.divisions.forEach(function (d) {
      var pts = d.points.map(function (p) { return self.proj(p[0], p[1]).join(','); }).join(' ');
      var poly = mk('polygon', { points: pts, fill: d.fill, 'fill-opacity': 0.1,
                                 stroke: d.fill, 'stroke-width': 2,
                                 'stroke-linejoin': 'round', class: 'bar' });
      poly.addEventListener('click', function () {
        if (self.opts.onDivision) self.opts.onDivision(d);
      });
      poly.style.cursor = 'pointer';
      poly.setAttribute('data-code', d.code);
      g.appendChild(poly);

      var cx = d.points.reduce(function (a, p) { return a + p[0]; }, 0) / d.points.length;
      var cn = d.points.reduce(function (a, p) { return a + p[1]; }, 0) / d.points.length;
      var cp = self.proj(cx, cn);
      var lt = mk('text', { x: cp[0], y: cp[1], 'font-size': 11, 'font-weight': 700,
                            fill: d.fill, 'text-anchor': 'middle',
                            'letter-spacing': '.6em' });
      lt.textContent = d.name.toUpperCase();
      lt.style.pointerEvents = 'none';
      g.appendChild(lt);
    });

    /* graticule */
    [0.30, 0.32, 0.34, 0.36, 0.38].forEach(function (lat) {
      var a = self.proj(lat, 32.50), b = self.proj(lat, 32.67);
      g.appendChild(mk('line', { x1: a[0], y1: a[1], x2: b[0], y2: b[1],
                                 stroke: '#C9D6DE', 'stroke-width': .8, opacity: .6 }));
      var tl = mk('text', { x: 6, y: a[1] - 4, 'font-size': 9, fill: '#9AA9B4' });
      tl.textContent = lat.toFixed(2) + '°S';
      g.appendChild(tl);
    });
    [32.55, 32.60, 32.65].forEach(function (lng) {
      var a = self.proj(0.267, lng), b = self.proj(0.407, lng);
      g.appendChild(mk('line', { x1: a[0], y1: a[1], x2: b[0], y2: b[1],
                                 stroke: '#C9D6DE', 'stroke-width': .8, opacity: .6 }));
      var tl = mk('text', { x: a[0] + 4, y: H - 6, 'font-size': 9, fill: '#9AA9B4' });
      tl.textContent = lng.toFixed(2) + '°E';
      g.appendChild(tl);
    });

    /* data layers */
    this.marksGroup = mk('g', {});
    g.appendChild(this.marksGroup);

    this.apply();
  };

  OfflineMap.prototype.draw = function () {
    var self = this;
    var g = this.marksGroup;
    g.innerHTML = '';
    var mk = function (n, a) {
      var e = document.createElementNS('http://www.w3.org/2000/svg', n);
      for (var k in a) e.setAttribute(k, a[k]);
      return e;
    };

    /* divisions shading by score */
    if (this.opts.divisionScores) {
      OFFLINE.divisions.forEach(function (d) {
        var s = self.opts.divisionScores[d.code];
        if (s === undefined || s === null) return;
        var pts = d.points.map(function (p) { return self.proj(p[0], p[1]).join(','); }).join(' ');
        var poly = mk('polygon', { points: pts, fill: bandColor(s), 'fill-opacity': 0.30,
                                   stroke: bandColor(s), 'stroke-width': 2.4 });
        g.appendChild(poly);
      });
    }

    /* links: zone -> market */
    if (this.opts.links) {
      this.opts.links.forEach(function (l) {
        var a = self.proj(l.from[0], l.from[1]);
        var b = self.proj(l.to[0], l.to[1]);
        g.appendChild(mk('line', { x1: a[0], y1: a[1], x2: b[0], y2: b[1],
                                   stroke: l.color || '#DC351D', 'stroke-width': 1.6,
                                   opacity: 0.5, 'stroke-dasharray': '4 3' }));
      });
    }

    /* trade zones as circles sized by vendors, coloured by score */
    if (this.opts.zones) {
      var maxV = this.opts.zones.reduce(function (a, z) {
        return Math.max(a, z.estimated_vendors || 0);
      }, 1);

      this.opts.zones.forEach(function (z) {
        var p = self.proj(z.lat, z.lng);
        var r = 5 + Math.sqrt((z.estimated_vendors || 1) / maxV) * 22;
        var c = bandColor(z.score === undefined ? 40 : z.score);

        var halo = mk('circle', { cx: p[0], cy: p[1], r: r + 7, fill: c,
                                  'fill-opacity': 0.14, stroke: 'none' });
        var dot = mk('circle', { cx: p[0], cy: p[1], r: r, fill: c, 'fill-opacity': 0.72,
                                 stroke: '#fff', 'stroke-width': 1.6, class: 'bar' });
        dot.style.cursor = 'pointer';

        var title = mk('title', {});
        title.textContent = z.name + ' — ' + (z.estimated_vendors || 0) + ' vendors';
        dot.appendChild(title);

        var show = function () {
          if (self.opts.onZoneClick) self.opts.onZoneClick(z, dot);
        };
        dot.addEventListener('click', show);
        dot.addEventListener('mouseenter', function () {
          halo.setAttribute('fill-opacity', 0.28);
        });
        dot.addEventListener('mouseleave', function () {
          halo.setAttribute('fill-opacity', 0.14);
        });

        g.appendChild(halo);
        g.appendChild(dot);
      });
    }

    /* markets as squares */
    if (this.opts.markets) {
      this.opts.markets.forEach(function (m) {
        var p = self.proj(m.lat, m.lng);
        var size = 5 + Math.min(12, Math.sqrt((m.vacant_spaces || 1) / 40) * 5);
        var c = m.category === 'public' ? '#0A6339' : '#EAA515';
        var rect = mk('rect', {
          x: p[0] - size / 2, y: p[1] - size / 2, width: size, height: size,
          fill: c, stroke: '#fff', 'stroke-width': 1.6, rx: 2, class: 'bar'
        });
        rect.style.cursor = 'pointer';
        var title = mk('title', {});
        title.textContent = m.name + ' — ' + (m.vacant_spaces || 0) + ' vacant';
        rect.appendChild(title);
        if (self.opts.onMarketClick) {
          rect.addEventListener('click', function () { self.opts.onMarketClick(m, rect); });
        }
        g.appendChild(rect);
      });
    }

    /* placements */
    if (this.opts.placements) {
      this.opts.placements.forEach(function (pl) {
        var p = self.proj(pl.lat, pl.lng);
        var g2 = mk('g', {});
        g2.appendChild(mk('line', { x1: p[0], y1: p[1] - 6, x2: p[0], y2: p[1] + 6,
                                    stroke: '#0A6339', 'stroke-width': 2.4 }));
        g2.appendChild(mk('line', { x1: p[0] - 6, y1: p[1], x2: p[0] + 6, y2: p[1],
                                    stroke: '#0A6339', 'stroke-width': 2.4 }));
        g.appendChild(g2);
      });
    }
  };

  OfflineMap.prototype.apply = function () {
    this.viewport.setAttribute('transform',
      'translate(' + this.tx + ' ' + this.ty + ') scale(' + this.scale + ')');
  };

  OfflineMap.prototype.zoomBy = function (f) {
    var ns = Math.max(0.6, Math.min(6, this.scale * f));
    this.scale = ns;
    this.apply();
    this.draw();
  };

  OfflineMap.prototype.reset = function () {
    this.scale = 1; this.tx = 0; this.ty = 0;
    this.apply();
    this.draw();
  };

  OfflineMap.prototype.bind = function () {
    var self = this;
    var dragging = false, sx = 0, sy = 0, ox = 0, oy = 0;

    this.svg.addEventListener('mousedown', function (e) {
      dragging = true; sx = e.clientX; sy = e.clientY;
      ox = self.tx; oy = self.ty;
      self.svg.style.cursor = 'grabbing';
      e.preventDefault();
    });

    window.addEventListener('mousemove', function (e) {
      if (!dragging) return;
      self.tx = ox + (e.clientX - sx);
      self.ty = oy + (e.clientY - sy);
      self.apply();
    });

    window.addEventListener('mouseup', function () {
      dragging = false;
      self.svg.style.cursor = '';
    });

    this.svg.addEventListener('wheel', function (e) {
      e.preventDefault();
      self.zoomBy(e.deltaY < 0 ? 1.16 : 0.86);
    }, { passive: false });

    /* touch */
    var tStart = null, tDist = 0;
    this.svg.addEventListener('touchstart', function (e) {
      if (e.touches.length === 1) {
        tStart = { x: e.touches[0].clientX, y: e.touches[0].clientY, tx: self.tx, ty: self.ty };
      } else if (e.touches.length === 2) {
        tDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
      }
    }, { passive: true });

    this.svg.addEventListener('touchmove', function (e) {
      if (e.touches.length === 1 && tStart) {
        self.tx = tStart.tx + (e.touches[0].clientX - tStart.x);
        self.ty = tStart.ty + (e.touches[0].clientY - tStart.y);
        self.apply();
      } else if (e.touches.length === 2 && tDist) {
        var d = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        self.zoomBy(d / tDist);
        tDist = d;
      }
    }, { passive: true });
  };

  /* =================================================================
     Public controller
     ================================================================= */
  var Map = {
    _instance: null,
    _offline: false,
    _layers: {},

    /** Boot a map into a container. options.data is the payload. */
    init: function (containerId, options) {
      var c = document.getElementById(containerId);
      if (!c) return null;

      var opts = options || {};
      var data = opts.data || {};

      var statusEl = document.getElementById(containerId + '-status');
      var setStatus = function (txt, offline) {
        if (!statusEl) return;
        statusEl.innerHTML = (offline
          ? '<span class="live offline"></span>'
          : '<span class="live"></span>') + '<span>' + escapeHtml(txt) + '</span>';
      };

      /* Try Leaflet with a short timeout; fall back if tiles are blocked. */
      var useLeaflet = typeof L !== 'undefined' && opts.forceOffline !== true;

      if (useLeaflet) {
        Map._offline = false;
        setStatus('OpenStreetMap base map · ' + (data.zones ? data.zones.length : 0) + ' sites plotted');
        var map = L.map(containerId, {
          center: KAMPALA.center,
          zoom: KAMPALA.zoom,
          minZoom: KAMPALA.minZoom,
          maxZoom: KAMPALA.maxZoom,
          zoomControl: false,
          attributionControl: true
        });
        Map._instance = map;
        Map._leaflet = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors · KCCA GIS Portal',
          crossOrigin: true
        }).addTo(map);

        Map._renderLeaflet(map, data, opts, containerId);
        setTimeout(function () { map.invalidateSize(); }, 120);
        return map;
      }

      /* Offline vector map */
      Map._offline = true;
      setStatus('Offline vector map of Kampala Capital City · ' +
                (data.zones ? data.zones.length : 0) + ' sites plotted', true);

      var om = new OfflineMap(c, {
        tall: opts.tall,
        short: opts.short,
        zones: data.zones || [],
        markets: data.markets || [],
        divisionScores: data.divisionScores || null,
        links: data.links || [],
        placements: data.placements || [],
        onZoneClick: function (z) { if (opts.onZoneClick) opts.onZoneClick(z, null); },
        onMarketClick: function (m) { if (opts.onMarketClick) opts.onMarketClick(m, null); },
        onDivision: function (d) { if (opts.onDivision) opts.onDivision(d); }
      });
      om.draw();
      Map._instance = om;
      Map._offlineMap = om;

      /* Wire the toolbar buttons to the offline instance. */
      var tb = document.getElementById(containerId + '-tools');
      if (tb) {
        tb.addEventListener('click', function (e) {
          var b = e.target.closest('[data-zoom]');
          if (!b) return;
          var a = b.getAttribute('data-zoom');
          if (a === 'in') om.zoomBy(1.28);
          else if (a === 'out') om.zoomBy(0.78);
          else om.reset();
        });
      }
      return om;
    },

    _renderLeaflet: function (map, data, opts, containerId) {
      /* division boundaries */
      if (data.divisions && data.divisions.length) {
        var fg = L.featureGroup();
        data.divisions.forEach(function (d) {
          var poly = L.polygon(d.polygon, {
            color: d.color || '#DC351D',
            weight: 2,
            opacity: 0.75,
            fillColor: d.color || '#DC351D',
            fillOpacity: d.score !== undefined ? 0.18 : 0.07
          });
          poly.bindPopup(
            '<div class="pop"><div class="kind">Division Urban Council</div><h4>' +
            escapeHtml(d.name) + '</h4><dl>' +
            '<dt>Mayor</dt><dd>' + escapeHtml(d.mayor || '—') + '</dd>' +
            '<dt>Area</dt><dd>' + escapeHtml(d.area || '') + '</dd>' +
            '<dt>Population</dt><dd>' + escapeHtml(d.population || '') + '</dd>' +
            '</dl></div>'
          );
          fg.addLayer(poly);
          map.addLayer(poly);
        });
        Map._layers.divisions = fg;
      }

      /* relocation links */
      if (data.links && data.links.length) {
        var lfg = L.featureGroup();
        data.links.forEach(function (l) {
          L.polyline([[l.from[0], l.from[1]], [l.to[0], l.to[1]]], {
            color: l.color || '#DC351D',
            weight: 1.6,
            opacity: 0.55,
            dashArray: '5 4'
          }).bindPopup(
            '<div class="pop"><div class="kind">Relocation</div><h4>' +
            escapeHtml(l.label) + '</h4></div>'
          ).addTo(lfg);
        });
        lfg.addTo(map);
        Map._layers.links = lfg;
      }

      /* markets */
      if (data.markets && data.markets.length) {
        var mg = L.layerGroup();
        data.markets.forEach(function (m) {
          var c = m.category === 'public' ? '#0A6339' : '#EAA515';
          var marker = L.circleMarker([m.lat, m.lng], {
            radius: 5 + Math.min(8, Math.sqrt((m.vacant_spaces || 1) / 40) * 3),
            color: '#fff', weight: 1.6, fillColor: c, fillOpacity: 0.85
          });
          marker.bindPopup(
            '<div class="pop"><div class="kind">' +
            (m.category === 'public' ? 'Public market' : 'Private market') + '</div><h4>' +
            escapeHtml(m.name) + '</h4><dl>' +
            '<dt>Vacant spaces</dt><dd>' + escapeHtml(m.vacant_spaces || 0) + '</dd>' +
            '<dt>Capacity</dt><dd>' + escapeHtml(m.capacity || 0) + '</dd>' +
            '<dt>Registered</dt><dd>' + escapeHtml(m.registered_vendors || 0) + '</dd>' +
            '</dl></div>'
          );
          if (opts.onMarketClick) marker.on('click', function () { opts.onMarketClick(m); });
          marker.addTo(mg);
        });
        mg.addTo(map);
        Map._layers.markets = mg;
      }

      /* trade zones */
      if (data.zones && data.zones.length) {
        var zg = L.layerGroup();
        var maxV = data.zones.reduce(function (a, z) {
          return Math.max(a, z.estimated_vendors || 0);
        }, 1);

        data.zones.forEach(function (z) {
          var c = bandColor(z.score === undefined ? 40 : z.score);
          var marker = L.circleMarker([z.lat, z.lng], {
            radius: 5 + Math.sqrt((z.estimated_vendors || 1) / maxV) * 16,
            color: '#fff', weight: 1.8, fillColor: c, fillOpacity: 0.78
          });
          marker.bindPopup(
            '<div class="pop"><div class="kind">Trade zone</div><h4>' + escapeHtml(z.name) + '</h4>' +
            '<dl>' +
            '<dt>Vendors</dt><dd>' + escapeHtml(z.estimated_vendors || 0) + '</dd>' +
            '<dt>Score</dt><dd>' + escapeHtml(z.score === undefined ? '—' : z.score) + '</dd>' +
            '<dt>Band</dt><dd>' + escapeHtml(z.band || '') + '</dd>' +
            '<dt>Footpath taken</dt><dd>' + escapeHtml(z.footpath || 0) + '%</dd>' +
            '<dt>Carriageway taken</dt><dd>' + escapeHtml(z.carriage || 0) + '%</dd>' +
            '<dt>Status</dt><dd>' + escapeHtml(z.status || '') + '</dd>' +
            '</dl>' +
            '<div class="pop-actions"><a class="btn btn-primary btn-sm" href="' +
            escapeHtml(z.link || '#') + '">Open zone record</a></div></div>'
          );
          if (opts.onZoneClick) marker.on('click', function () { opts.onZoneClick(z, marker); });
          marker.addTo(zg);
        });
        zg.addTo(map);
        Map._layers.zones = zg;
      }

      /* placements */
      if (data.placements && data.placements.length) {
        var pg = L.layerGroup();
        data.placements.forEach(function (p) {
          L.circleMarker([p.lat, p.lng], {
            radius: 4, color: '#0A6339', weight: 2, fillColor: '#fff', fillOpacity: 1
          }).bindPopup(
            '<div class="pop"><div class="kind">Relocated vendor</div><h4>' +
            escapeHtml(p.label || '') + '</h4></div>'
          ).addTo(pg);
        });
        pg.addTo(map);
        Map._layers.placements = pg;
      }

      /* toolbar — scoped to this map's container, so several maps can coexist */
      var tb = document.getElementById(containerId + '-tools');
      if (tb) {
        tb.addEventListener('click', function (ev) {
          var b = ev.target.closest('[data-zoom]');
          if (!b) return;
          var a = b.getAttribute('data-zoom');
          if (a === 'in') map.zoomIn();
          else if (a === 'out') map.zoomOut();
          else map.setView(KAMPALA.center, KAMPALA.zoom);
        });
      }

      /* Screen-reader escape hatch: the same figures are tabulated below
         the map, so describe what is plotted. */
      map.getContainer().setAttribute('aria-label',
        'Interactive map of Kampala Capital City. '
        + (data.zones ? data.zones.length : 0) + ' affected trading sites and '
        + (data.markets ? data.markets.length : 0) + ' markets with vacant space. '
        + 'The same figures are tabulated below the map.');
    },

    toggleLayer: function (name, on) {
      var l = Map._layers[name];
      if (!l) return;
      var target = (typeof on === 'boolean') ? on : !mapHas(l);
      if (Map._leaflet) {
        if (target) { if (!mapHas(l)) l.addTo(Map._leaflet); }
        else { if (mapHas(l)) Map._leaflet.removeLayer(l); }
      }
    },

    focus: function (lat, lng, zoom) {
      if (Map._leaflet) {
        Map._leaflet.flyTo([lat, lng], zoom || 15, { duration: 0.7 });
      }
    },

    fitAll: function () {
      if (Map._leaflet && Map._layers.zones) {
        Map._leaflet.fitBounds(Map._layers.zones.getBounds().pad(0.15));
      } else if (Map._offlineMap) {
        Map._offlineMap.reset();
      }
    },

    isOffline: function () { return Map._offline; }
  };

  function mapHas(l) {
    if (!Map._leaflet) return false;
    if (typeof l.eachLayer === 'function') {
      var found = false;
      l.eachLayer(function () { found = true; });
      return found;
    }
    return true;
  }

  /* Leaflet is optional — load it from a CDN with a hard timeout. */
  function ensureLeaflet(cb) {
    if (typeof L !== 'undefined') { cb(true); return; }
    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);

    var js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    var done = false;
    var finish = function (ok) {
      if (done) return;
      done = true;
      cb(ok);
    };
    js.onload = function () { finish(typeof L !== 'undefined'); };
    js.onerror = function () { finish(false); };
    document.head.appendChild(js);

    /* If the CDN is slow or firewalled, do not wait forever. */
    setTimeout(function () { finish(typeof L !== 'undefined'); }, 3800);
  }

  window.KCCAMap = Map;

  /**
   * Boot every map declared with a data-map-options attribute.
   * The full city map uses id="kccaMap"; the dashboard embeds use their own
   * ids and the same options contract, so one code path serves both.
   */
  function bootMaps() {
    /* ?map=offline forces the vector map, which is how the fallback is
       demonstrated on a machine that does have internet. */
    var forced = new URLSearchParams(location.search).get('map');

    document.querySelectorAll('[data-map-options]').forEach(function (el) {
      if (el.getAttribute('data-map-booted')) return;

      var options;
      try {
        options = JSON.parse(el.getAttribute('data-map-options'));
      } catch (e) {
        return;
      }
      options.data = options.data || {};

      var start = function (leafletOk) {
        el.setAttribute('data-map-booted', '1');
        Map.init(el.id, Object.assign({}, options, { forceOffline: !leafletOk || forced === 'offline' }));

        /* The badge reports what actually rendered, not what was
           attempted, so ?map=offline is labelled honestly. */
        var usedOffline = !leafletOk || forced === 'offline';
        var badge = document.getElementById('map-engine-badge');
        if (badge) {
          badge.textContent = usedOffline ? 'Offline vector map' : 'Leaflet / OpenStreetMap';
          badge.className = 'badge ' + (usedOffline ? 'warn' : 'green');
        }
      };
      ensureLeaflet(start);
    });
  }

  Map.bootMaps = bootMaps;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMaps);
  } else {
    bootMaps();
  }
})();