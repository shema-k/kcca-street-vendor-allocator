/* =====================================================================
   KCCA VRSP — dependency-free SVG charts
   No library, no CDN. Every chart is drawn from live data passed in.
   ===================================================================== */
(function () {
  'use strict';

  var NS = 'http://www.w3.org/2000/svg';

  function el(name, attrs, parent) {
    var n = document.createElementNS(NS, name);
    if (attrs) {
      for (var k in attrs) {
        if (attrs[k] !== null && attrs[k] !== undefined) n.setAttribute(k, attrs[k]);
      }
    }
    if (parent) parent.appendChild(n);
    return n;
  }

  function text(parent, x, y, str, cls, anchor) {
    var t = el('text', {
      x: x, y: y,
      class: cls || 'lbl',
      'text-anchor': anchor || 'start'
    }, parent);
    t.textContent = str;
    return t;
  }

  /* Chart specs live in HTML attributes, so they name their formatter
     rather than carrying a function. These are the names available;
     a page may override any of them. */
  window.num = window.num || function (v) { return Math.round(v).toLocaleString('en-UG'); };
  window.pct = window.pct || function (v) { return (Math.round(v * 10) / 10).toLocaleString('en-UG') + '%'; };
  window.util = window.util || window.pct;
  window.ugx = window.ugx || function (v) {
    var n = Number(v) || 0;
    if (Math.abs(n) >= 1e9) return 'UGX ' + (n / 1e9).toFixed(1) + 'b';
    if (Math.abs(n) >= 1e6) return 'UGX ' + (n / 1e6).toFixed(1) + 'm';
    if (Math.abs(n) >= 1e3) return 'UGX ' + Math.round(n / 1e3) + 'k';
    return 'UGX ' + Math.round(n).toLocaleString('en-UG');
  };
  window.km = window.km || function (v) { return (Math.round(Number(v) * 10) / 10) + ' km'; };

  function formatter(v) {
    if (typeof v === 'function') return v;
    if (typeof v === 'string' && typeof window[v] === 'function') return window[v];
    return window.num;
  }

  function niceMax(v) {
    if (v <= 0) return 1;
    var exp = Math.pow(10, Math.floor(Math.log10(v)));
    var f = v / exp;
    var n = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
    return n * exp;
  }

  function palette() {
    return ['#DC351D', '#EAA515', '#0A6339', '#1D6FA5', '#8E44AD',
            '#2F8F7B', '#CE4759', '#7A5A10', '#3498DB', '#16A085'];
  }

  /* =================================================================
     Grouped / stacked vertical bar chart
     opts: { height, series:[{name,color,values:[]}], labels:[],
             stacked:bool, yFormat:fn, showValues:bool }
     ================================================================= */
  function barChart(svg, opts) {
    var W = svg.clientWidth || svg.parentNode.clientWidth || 640;
    var H = opts.height || 260;
    var padL = opts.padL || 46, padR = 12, padT = 14, padB = opts.padB || 44;

    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('height', H);
    svg.innerHTML = '';

    var labels = opts.labels || [];
    var series = opts.series || [];
    var n = labels.length;
    if (!n) return;

    var plotW = W - padL - padR;
    var plotH = H - padT - padB;

    var maxVal = 0;
    if (opts.stacked) {
      for (var i = 0; i < n; i++) {
        var t = 0;
        for (var s = 0; s < series.length; s++) t += (series[s].values[i] || 0);
        if (t > maxVal) maxVal = t;
      }
    } else {
      for (var i2 = 0; i2 < n; i2++) {
        for (var s2 = 0; s2 < series.length; s2++) {
          var v = series[s2].values[i2] || 0;
          if (v > maxVal) maxVal = v;
        }
      }
    }
    var max = niceMax(maxVal || 1);
    var fmt = formatter(opts.yFormat);

    /* gridlines + y labels */
    var ticks = 4;
    for (var g = 0; g <= ticks; g++) {
      var y = padT + plotH - (plotH * g / ticks);
      el('line', { x1: padL, y1: y, x2: W - padR, y2: y, class: 'grid-line' }, svg);
      text(svg, padL - 7, y + 3.5, fmt(max * g / ticks), 'lbl', 'end');
    }

    var bandW = plotW / n;
    var groupW = bandW * 0.68;
    var barW = opts.stacked
      ? groupW
      : groupW / Math.max(1, series.length);

    for (var i3 = 0; i3 < n; i3++) {
      var x0 = padL + bandW * i3 + (bandW - groupW) / 2;
      var acc = 0;

      for (var s3 = 0; s3 < series.length; s3++) {
        var val = series[s3].values[i3] || 0;
        var h = (val / max) * plotH;
        var x = opts.stacked ? x0 : x0 + barW * s3;
        var y = padT + plotH - h - (opts.stacked ? acc : 0);

        el('rect', {
          x: x.toFixed(1),
          y: (opts.stacked ? y + (acc ? 0 : 0) : y).toFixed(1),
          width: Math.max(1, barW - (opts.stacked ? 0 : 1.5)).toFixed(1),
          height: Math.max(val > 0 ? 1 : 0, h).toFixed(1),
          fill: series[s3].color || '#DC351D',
          class: 'bar',
          rx: 2
        }, svg);

        if (opts.stacked) {
          acc += h;
        } else if (opts.showValues && val > 0) {
          text(svg, x + barW / 2, y - 4, fmt(val), 'lbl', 'middle');
        }
      }

      /* x label, rotated if long */
      var lab = String(labels[i3]);
      var lx = padL + bandW * i3 + bandW / 2;
      if (lab.length > 9 && bandW < 90) {
        var t2 = text(svg, lx, padT + plotH + 12, lab, 'lbl', 'end');
        t2.setAttribute('transform', 'rotate(-35 ' + lx + ' ' + (padT + plotH + 12) + ')');
      } else {
        text(svg, lx, padT + plotH + 15, lab, 'lbl', 'middle');
      }
    }

    el('line', { x1: padL, y1: padT + plotH, x2: W - padR, y2: padT + plotH, class: 'axis' }, svg);

    if (opts.title) {
      text(svg, padL, 10, opts.title, 'lbl-strong');
    }
  }

  /* =================================================================
     Donut chart with centre label
     opts: { height, slices:[{label,value,color}], centre, centreSub }
     ================================================================= */
  function donut(svg, opts) {
    var H = opts.height || 220;
    var W = svg.clientWidth || 300;
    var R = Math.min(H / 2 - 6, 92);
    var cx = H / 2, cy = H / 2;
    var inner = R * 0.6;

    svg.setAttribute('viewBox', '0 0 ' + Math.max(W, H) + ' ' + H);
    svg.setAttribute('height', H);
    svg.innerHTML = '';

    var slices = opts.slices || [];
    var total = slices.reduce(function (a, s) { return a + (s.value || 0); }, 0);
    var pal = palette();

    if (total <= 0) {
      el('circle', { cx: cx, cy: cy, r: (R + inner) / 2, fill: 'none',
                     stroke: '#EFF1F4', 'stroke-width': R - inner }, svg);
      text(svg, cx, cy + 4, opts.emptyText || 'No data', 'lbl', 'middle');
      return;
    }

    var angle = -Math.PI / 2;
    slices.forEach(function (s, i) {
      var frac = (s.value || 0) / total;
      var a2 = angle + frac * Math.PI * 2;
      var large = frac > 0.5 ? 1 : 0;
      var x1 = cx + R * Math.cos(angle),  y1 = cy + R * Math.sin(angle);
      var x2 = cx + R * Math.cos(a2),    y2 = cy + R * Math.sin(a2);
      var x3 = cx + inner * Math.cos(a2), y3 = cy + inner * Math.sin(a2);
      var x4 = cx + inner * Math.cos(angle), y4 = cy + inner * Math.sin(angle);

      var d = frac >= 0.9999
        ? 'M ' + (cx - R) + ' ' + cy +
          ' A ' + R + ' ' + R + ' 0 1 1 ' + (cx + R) + ' ' + cy +
          ' A ' + R + ' ' + R + ' 0 1 1 ' + (cx - R) + ' ' + cy +
          ' M ' + (cx - inner) + ' ' + cy +
          ' A ' + inner + ' ' + inner + ' 0 1 0 ' + (cx + inner) + ' ' + cy +
          ' A ' + inner + ' ' + inner + ' 0 1 0 ' + (cx - inner) + ' ' + cy + ' Z'
        : 'M ' + x1 + ' ' + y1 +
          ' A ' + R + ' ' + R + ' 0 ' + large + ' 1 ' + x2 + ' ' + y2 +
          ' L ' + x3 + ' ' + y3 +
          ' A ' + inner + ' ' + inner + ' 0 ' + large + ' 0 ' + x4 + ' ' + y4 + ' Z';

      el('path', { d: d, fill: s.color || pal[i % pal.length], class: 'bar' }, svg);
      angle = a2;
    });

    text(svg, cx, cy - 1, opts.centre || String(total), 'donut-center', 'middle');
    if (opts.centreSub) {
      text(svg, cx, cy + 15, opts.centreSub, 'donut-sub', 'middle');
    }
  }

  /* =================================================================
     Horizontal bar chart
     opts: { height, items:[{label,value,color}], format:fn, max:number }
     ================================================================= */
  function hBar(svg, opts) {
    var W = svg.clientWidth || svg.parentNode.clientWidth || 480;
    var items = opts.items || [];
    var rowH = opts.rowH || 26;
    var labelW = opts.labelW || 168;
    var padR = 58;
    var H = Math.max(rowH * items.length + 16, 40);

    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('height', H);
    svg.innerHTML = '';

    var max = opts.max || niceMax(Math.max.apply(null, items.map(function (i) {
      return i.value || 0;
    }).concat([1])));
    var fmt = formatter(opts.format);
    var trackW = W - labelW - padR;
    var pal = palette();

    items.forEach(function (it, i) {
      var y = 6 + i * rowH;
      var t = text(svg, labelW - 8, y + rowH / 2 + 4, it.label, 'lbl', 'end');
      if (it.label.length > 30) t.textContent = it.label.slice(0, 29) + '…';

      el('rect', { x: labelW, y: y + 4, width: trackW, height: rowH - 12,
                   rx: 3, fill: '#F1F3F6' }, svg);

      var w = ((it.value || 0) / max) * trackW;
      el('rect', { x: labelW, y: y + 4, width: Math.max(1, w), height: rowH - 12,
                   rx: 3, fill: it.color || pal[i % pal.length], class: 'bar' }, svg);

      text(svg, W - 6, y + rowH / 2 + 4, fmt(it.value), 'lbl-strong', 'end');
    });
  }

  /* =================================================================
     Line chart (trend)
     opts: { height, series:[{name,color,values:[]}], labels:[], format:fn }
     ================================================================= */
  function lineChart(svg, opts) {
    var W = svg.clientWidth || svg.parentNode.clientWidth || 640;
    var H = opts.height || 240;
    var padL = 48, padR = 14, padT = 16, padB = 34;

    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('height', H);
    svg.innerHTML = '';

    var series = opts.series || [];
    var labels = opts.labels || [];
    var n = labels.length;
    if (!n) return;

    var plotW = W - padL - padR, plotH = H - padT - padB;
    var allVals = [];
    series.forEach(function (s) { allVals = allVals.concat(s.values.filter(function (v) { return v !== null; })); });
    var min = opts.zeroBase === false ? Math.min.apply(null, allVals) : 0;
    var max = niceMax(Math.max.apply(null, allVals.concat([1])));
    if (max === min) max = min + 1;
    var fmt = formatter(opts.format);

    for (var g = 0; g <= 4; g++) {
      var y = padT + plotH - (plotH * g / 4);
      el('line', { x1: padL, y1: y, x2: W - padR, y2: y, class: 'grid-line' }, svg);
      text(svg, padL - 7, y + 3.5, fmt(min + (max - min) * g / 4), 'lbl', 'end');
    }

    var xAt = function (i) { return padL + (n === 1 ? plotW / 2 : plotW * i / (n - 1)); };
    var yAt = function (v) { return padT + plotH - ((v - min) / (max - min)) * plotH; };

    series.forEach(function (s) {
      var d = '', started = false;
      s.values.forEach(function (v, i) {
        if (v === null) return;
        d += (started ? ' L ' : ' M ') + xAt(i).toFixed(1) + ' ' + yAt(v).toFixed(1);
        started = true;
      });
      el('path', { d: d.trim(), fill: 'none', stroke: s.color || '#DC351D',
                   'stroke-width': 2.2, 'stroke-linejoin': 'round',
                   'stroke-linecap': 'round' }, svg);

      s.values.forEach(function (v, i) {
        if (v === null) return;
        el('circle', { cx: xAt(i), cy: yAt(v), r: 3.4,
                       fill: '#fff', stroke: s.color || '#DC351D', 'stroke-width': 2 }, svg);
      });
    });

    labels.forEach(function (l, i) {
      if (n > 12 && i % 2 !== 0 && i !== n - 1) return;
      text(svg, xAt(i), padT + plotH + 16, l, 'lbl', 'middle');
    });

    el('line', { x1: padL, y1: padT + plotH, x2: W - padR, y2: padT + plotH, class: 'axis' }, svg);
  }

  /* =================================================================
     Horizontal stacked "capacity" bar
     opts: { width, height, segments:[{label,value,color}] }
     ================================================================= */
  function stackBar(svg, opts) {
    var W = svg.clientWidth || svg.parentNode.clientWidth || 400;
    var H = opts.height || 26;
    var total = (opts.segments || []).reduce(function (a, s) { return a + (s.value || 0); }, 0) || 1;

    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('height', H);
    svg.innerHTML = '';

    var x = 0;
    (opts.segments || []).forEach(function (s) {
      var w = (s.value || 0) / total * W;
      if (w > 0) {
        el('rect', { x: x.toFixed(1), y: 0, width: Math.max(1, w).toFixed(1),
                     height: H, fill: s.color, rx: 2 }, svg);
      }
      x += w;
    });

    el('rect', { x: 0, y: 0, width: W, height: H, fill: 'none',
                 stroke: '#E3E6EB', 'rx': 2 }, svg);
  }

  /* =================================================================
     Radar chart — used for division profiles
     opts: { height, axes:[...], series:[{name,color,values:[]}] }
     ================================================================= */
  function radar(svg, opts) {
    var size = opts.height || 260;
    var cx = size / 2, cy = size / 2, R = size / 2 - 34;
    var axes = opts.axes || [];
    var series = opts.series || [];
    var n = axes.length;

    svg.setAttribute('viewBox', '0 0 ' + size + ' ' + (size + 12));
    svg.setAttribute('height', size + 12);
    svg.innerHTML = '';

    if (!n) return;

    [0.25, 0.5, 0.75, 1].forEach(function (f) {
      var pts = [];
      for (var i = 0; i < n; i++) {
        var a = -Math.PI / 2 + (Math.PI * 2 * i / n);
        pts.push((cx + R * f * Math.cos(a)).toFixed(1) + ',' + (cy + R * f * Math.sin(a)).toFixed(1));
      }
      el('polygon', { points: pts.join(' '), fill: 'none', stroke: '#EFF1F4' }, svg);
    });

    var pal = palette();
    series.forEach(function (s, si) {
      var pts = [];
      for (var i = 0; i < n; i++) {
        var a = -Math.PI / 2 + (Math.PI * 2 * i / n);
        var v = Math.max(0, Math.min(1, (s.values[i] || 0) / 100));
        pts.push((cx + R * v * Math.cos(a)).toFixed(1) + ',' + (cy + R * v * Math.sin(a)).toFixed(1));
      }
      el('polygon', {
        points: pts.join(' '),
        fill: s.color || pal[si % pal.length],
        'fill-opacity': si === 0 ? 0.2 : 0.12,
        stroke: s.color || pal[si % pal.length],
        'stroke-width': 2
      }, svg);
    });

    axes.forEach(function (ax, i) {
      var a = -Math.PI / 2 + (Math.PI * 2 * i / n);
      var lx = cx + (R + 16) * Math.cos(a);
      var ly = cy + (R + 16) * Math.sin(a);
      var anchor = Math.abs(Math.cos(a)) < 0.25 ? 'middle' : (Math.cos(a) > 0 ? 'start' : 'end');
      text(svg, lx, ly + 3.5, ax, 'lbl', anchor);
    });
  }

  /* ---------------------------------------------------------------- */
  window.KCCACharts = {
    bar:    barChart,
    donut:  donut,
    hbar:   hBar,
    line:   lineChart,
    stack:  stackBar,
    radar:  radar,
    palette: palette,

    /** Render every chart declared on the page by data-chart attribute. */
    renderAll: function (root) {
      (root || document).querySelectorAll('[data-chart]').forEach(function (node) {
        var spec;
        try {
          spec = JSON.parse(node.getAttribute('data-chart'));
        } catch (e) {
          return;
        }
        var fn = window.KCCACharts[spec.type];
        if (!fn) return;

        // Redraw on resize so charts stay responsive.
        clearTimeout(node._t);
        var draw = function () { fn(node, spec.opts || {}); };
        draw();
        window.addEventListener('resize', function () {
          clearTimeout(node._t);
          node._t = setTimeout(draw, 160);
        });
      });
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.KCCACharts.renderAll();
  });
})();