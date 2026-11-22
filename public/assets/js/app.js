/* Panteen Rent: small progressive enhancements. Every page works without this file. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
  var BASE = ($('meta[name="base-path"]') || {}).content || '';
  var THAI_SHORT = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  var THAI_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

  function getJSON(url, opts) {
    opts = opts || {};
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'fetch' };
    if (opts.method === 'POST') headers['X-CSRF-TOKEN'] = csrf;
    return fetch(url, { method: opts.method || 'GET', headers: headers, body: opts.body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  function parseDate(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function addDays(s, n) { var d = parseDate(s); d.setDate(d.getDate() + n); return iso(d); }
  function beDate(s) { var d = parseDate(s); return 'วัน' + THAI_DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + THAI_SHORT[d.getMonth()] + ' ' + (d.getFullYear() + 543); }
  function fmt(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); }

  /* ---------- chrome ---------- */
  // the bar is clear over the opening photograph and turns solid once the page moves
  (function () {
    var bar = $('[data-topbar].is-over');
    if (!bar) return;
    var queued = false;
    function paint() { queued = false; bar.classList.toggle('is-stuck', window.scrollY > 24); }
    paint();
    window.addEventListener('scroll', function () { if (!queued) { queued = true; requestAnimationFrame(paint); } }, { passive: true });
  })();
  document.addEventListener('click', function (e) {
    var d = e.target.closest('[data-dismiss]');
    if (d) d.closest('[data-flash]').remove();
    // close any open <details class="menu"> when clicking outside
    $$('details.menu[open]').forEach(function (m) { if (!m.contains(e.target)) m.removeAttribute('open'); });
    var pr = e.target.closest('[data-print]');
    if (pr) window.print();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    $$('details.menu[open], details[data-sheet][open]').forEach(function (m) { m.removeAttribute('open'); });
  });

  // a box that scrolls sideways (wide tables, the week strip) must be reachable by keyboard to be scrolled
  (function () {
    function mark() {
      $$('.tablewrap, .week').forEach(function (w) {
        var scrolls = w.scrollWidth > w.clientWidth + 1;
        if (scrolls && !w.hasAttribute('tabindex')) {
          // named after the caption, or the heading nearest above it, so two tables in one section read apart
          var cap = $('caption', w);
          var heads = $$('h1, h2, h3').filter(function (h) { return h.compareDocumentPosition(w) & Node.DOCUMENT_POSITION_FOLLOWING; });
          var name = (cap ? cap.textContent : heads.length ? heads[heads.length - 1].textContent : '').trim();
          w.setAttribute('tabindex', '0');
          w.setAttribute('role', 'region');
          w.setAttribute('aria-label', (name ? name + ' ' : '') + '(เลื่อนดูด้านข้างได้)');
        } else if (!scrolls && w.getAttribute('role') === 'region') {
          w.removeAttribute('tabindex'); w.removeAttribute('role'); w.removeAttribute('aria-label');
        }
      });
    }
    mark();
    var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(mark, 200); });
  })();

  /* confirm before destructive submits */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    var btn = e.submitter || f.querySelector('[type="submit"]');
    if (btn && !f.hasAttribute('data-fav') && !f.hasAttribute('target')) {
      // keep the button from double-submitting; restore if the page comes back from bfcache
      setTimeout(function () { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }, 0);
    }
  });
  window.addEventListener('pageshow', function () {
    $$('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.removeAttribute('aria-busy'); });
  });

  /* ---------- sheets and drawers ---------- */
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary';
  function keepFocusIn(root, e) {
    if (e.key !== 'Tab') return;
    var f = $$(FOCUSABLE, root).filter(function (el) { return el.getClientRects().length > 0; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }
  $$('[data-sheet]').forEach(function (sheet) {
    var summary = $('summary', sheet);
    $$('[data-sheet-close]', sheet).forEach(function (c) {
      c.addEventListener('click', function (e) { e.preventDefault(); sheet.removeAttribute('open'); });
    });
    sheet.addEventListener('toggle', function () {
      document.body.style.overflow = sheet.open ? 'hidden' : '';
      var close = $('[data-sheet-close]', sheet);
      if (sheet.open && close) close.focus(); else if (summary) summary.focus();
    });
    sheet.addEventListener('keydown', function (e) {
      var panel = sheet.open && sheet.lastElementChild;
      if (panel) keepFocusIn(panel, e);
    });
  });

  /* ---------- the date desk: the signature interaction ----------
     A date goes in and the floor is re-lit in place: free dresses stand under a solid rule,
     taken ones recede under a broken one. Nothing moves; only the light changes. */
  var THAI_DOW = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
  function deskDate(s) { var d = parseDate(s); return THAI_DOW[d.getDay()] + '. ' + d.getDate() + ' ' + THAI_SHORT[d.getMonth()] + ' ' + (d.getFullYear() + 543); }
  function paintDesk(desk, date) {
    var text = $('[data-desk-text]', desk);
    if (!text) return;
    text.textContent = date ? deskDate(date) : (text.dataset.empty || 'เลือกวันงาน');
    text.classList.toggle('is-empty', !date);
  }
  /* Same dresses in the same order, new date: change each card where it stands. A different set or order is hung fresh. */
  function relight(grid, html) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var incoming = $$('.vit', tmp), hung = $$('.vit', grid);
    var same = incoming.length > 0 && incoming.length === hung.length && incoming.every(function (v, i) { return hung[i].dataset.code === v.dataset.code; });
    if (same) {
      incoming.forEach(function (v, i) {
        var old = hung[i];
        old.className = v.className;
        $('.vit-link', old).href = $('.vit-link', v).href;
        $('.vit-cap', old).innerHTML = $('.vit-cap', v).innerHTML;
      });
    } else {
      grid.innerHTML = html;
    }
    grid.classList.remove('is-refreshing', 'is-relit');
    void grid.offsetWidth;                       // let the light come on again from the start
    grid.classList.add('is-relit');
  }

  $$('[data-desk]').forEach(function (desk) {
    var input = $('[data-desk-input]', desk);
    if (!input) return;
    var cell = input.closest('.desk-date');
    // typing a date by keyboard shows the native field; picking from the calendar keeps the Thai reading
    input.addEventListener('keydown', function (e) { if (/^[0-9]$/.test(e.key) || e.key.indexOf('Arrow') === 0) cell.classList.add('is-typing'); });
    input.addEventListener('blur', function () { cell.classList.remove('is-typing'); paintDesk(desk, input.value); });
    input.addEventListener('change', function () { paintDesk(desk, input.value); });
    /* on a dress page the date is a question for that one dress: ask it as soon as the date is whole */
    if (desk.hasAttribute('data-desk-go')) {
      var asked = input.value;
      var ask = function () {
        if (input.value === asked || (input.value !== '' && !input.checkValidity())) return;
        asked = input.value;
        desk.submit();
      };
      input.addEventListener('change', function () { if (!cell.classList.contains('is-typing')) ask(); });
      input.addEventListener('blur', ask);
    }
  });

  /* on the home page the desk re-lights the new arrivals and the directory where they stand */
  var homeDesk = $('[data-desk-home]');
  if (homeDesk) {
    var homeInput = $('[data-desk-input]', homeDesk), homeGrid = $('[data-home-grid]'), deskStatus = $('[data-desk-status]');
    homeInput.addEventListener('change', function () {
      var u = new URL(homeDesk.dataset.deskHome, window.location.href);
      u.searchParams.set('date', homeInput.value);
      if (homeGrid) homeGrid.classList.add('is-refreshing');
      getJSON(u.toString()).then(function (res) {
        if (!res.ok) throw new Error('home');
        if (homeGrid) relight(homeGrid, res.html);
        (res.floors || []).forEach(function (fl) {
          var n = $('[data-floor-count="' + fl.slug + '"]');
          if (n) n.textContent = fl.text;
        });
        if (deskStatus) deskStatus.textContent = res.date ? 'แสดงชุดที่ว่างสำหรับวันงาน ' + deskDate(res.date) : 'ล้างวันงานแล้ว';
      }).catch(function () { if (homeGrid) homeGrid.classList.remove('is-refreshing'); });
    });
  }

  /* ---------- listing: filters down the side, the desk across the top ---------- */
  var feed = $('[data-feed]'), filters = $('[data-filters]');
  if (feed && filters) {
    var grid = $('[data-grid]', feed), more = $('[data-more]', feed), emptyBox = $('[data-empty]', feed);
    var listDesk = $('[data-desk]'), dateInput = $('[data-desk-input]'), freeBox = $('[data-desk-free]'), clearLink = $('[data-desk-clear]');
    var countBox = $('[data-desk-count]'), gridCount = $('[data-grid-count]'), chipsBox = $('[data-chips]'), opener = $('[data-filters-open]');
    var shown = '';

    var formURL = function (page) {
      var u = new URL(feed.dataset.base, window.location.href);
      new FormData(filters).forEach(function (v, k) { if (v !== '' && !(k === 'sort' && v === 'new')) u.searchParams.set(k, v); });
      if (!u.searchParams.get('date')) u.searchParams.delete('free');
      if (page > 1) u.searchParams.set('page', page);
      return u;
    };
    /* an empty date in the request tells the shop to forget the one it was holding */
    var requestURL = function (u) {
      var r = new URL(u.toString());
      if (!r.searchParams.get('date')) r.searchParams.set('date', '');
      return r.toString();
    };
    var syncControls = function (p, date) {
      Array.prototype.forEach.call(filters.elements, function (el) {
        if (!el.name || el.type === 'submit' || el.type === 'button') return;
        var v = el.name === 'date' ? (date || '') : (p.get(el.name) || '');
        if (el.type === 'radio') el.checked = el.value === v;
        else if (el.type === 'checkbox') el.checked = v !== '';
        else if (el.tagName === 'SELECT') el.value = v || el.options[0].value;
        else el.value = v;
      });
      if (freeBox) { freeBox.disabled = !date; if (!date) freeBox.checked = false; }
      if (clearLink) clearLink.hidden = !date;
      if (listDesk) paintDesk(listDesk, date);
    };
    var paintList = function (res, u) {
      relight(grid, res.html);
      if (gridCount && res.gcount) gridCount.innerHTML = res.gcount;
      $$('[data-total], [data-apply-total]').forEach(function (n) { n.textContent = res.total; });
      if (countBox) countBox.innerHTML = res.count;
      if (chipsBox) chipsBox.innerHTML = res.chips;
      if (emptyBox) emptyBox.innerHTML = res.empty;
      if (more) {
        more.hidden = !res.more;
        more.dataset.page = '2';
        var left = $('[data-left]', more); if (left) left.textContent = res.left;
        var next = new URL(u.toString()); next.searchParams.set('page', '2'); more.href = next.pathname + next.search;
      }
    };
    var refresh = function (u) {
      u.searchParams.delete('page');
      var key = u.pathname + u.search;
      if (key === shown) return;
      shown = key;
      grid.classList.add('is-refreshing');
      getJSON(requestURL(u)).then(function (res) {
        if (!res.ok) throw new Error('list');
        if (res.date) u.searchParams.set('date', res.date); else { u.searchParams.delete('date'); u.searchParams.delete('free'); }
        history.replaceState(null, '', u.pathname + u.search);
        shown = u.pathname + u.search;
        syncControls(u.searchParams, res.date);
        paintList(res, u);
      }).catch(function () { window.location.href = requestURL(u); });
    };
    shown = formURL(1).pathname + formURL(1).search;

    var openFilters = function () {
      document.body.classList.add('filters-on');
      document.body.style.overflow = 'hidden';
      if (opener) opener.setAttribute('aria-expanded', 'true');
      var c = $('[data-filters-close]', filters); if (c) c.focus();
    };
    var closeFilters = function () {
      if (!document.body.classList.contains('filters-on')) return;
      document.body.classList.remove('filters-on');
      document.body.style.overflow = '';
      if (opener) { opener.setAttribute('aria-expanded', 'false'); opener.focus(); }
    };
    document.addEventListener('change', function (e) {
      if (e.target.form === filters) refresh(formURL(1));
    });
    filters.addEventListener('submit', function (e) { e.preventDefault(); refresh(formURL(1)); closeFilters(); });
    document.addEventListener('click', function (e) {
      var chip = e.target.closest('a[data-chip]');
      if (chip) { e.preventDefault(); refresh(new URL(chip.href, window.location.href)); return; }
      if (clearLink && e.target.closest('[data-desk-clear]')) {
        e.preventDefault();
        dateInput.value = '';
        refresh(formURL(1));
        dateInput.focus();
        return;
      }
      var m = e.target.closest('[data-more]');
      if (m) {
        e.preventDefault();
        var page = +m.dataset.page || 2, u = formURL(page);
        m.classList.add('is-loading');
        getJSON(requestURL(u)).then(function (res) {
          var before = $$('.vit', grid).length;
          grid.classList.remove('is-relit');
          grid.insertAdjacentHTML('beforeend', res.html);
          m.classList.remove('is-loading');
          m.dataset.page = page + 1;
          m.hidden = !res.more;
          var left = $('[data-left]', m); if (left) left.textContent = res.left;
          var firstNew = $$('.vit-link', grid)[before]; if (firstNew) firstNew.focus({ preventScroll: true });
        }).catch(function () { window.location.href = m.href; });
      }
    });

    /* below desktop width the filters wait in a drawer */
    if (opener) opener.addEventListener('click', openFilters);
    $$('[data-filters-close]').forEach(function (c) { c.addEventListener('click', closeFilters); });
    document.addEventListener('keydown', function (e) {
      if (!document.body.classList.contains('filters-on')) return;
      if (e.key === 'Escape') closeFilters(); else keepFocusIn(filters, e);
    });
  }

  /* ---------- dress page: gallery, calendar months, favourite, share ---------- */
  var gallery = $('[data-gallery]');
  if (gallery) {
    $$('[data-gallery-src]', gallery).forEach(function (b) {
      b.addEventListener('click', function () {
        var main = $('[data-gallery-main]', gallery);
        main.removeAttribute('srcset');           // the opening picture's candidates no longer apply
        main.src = b.dataset.gallerySrc;
        $$('[data-gallery-src]', gallery).forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        var cr = $('[data-gallery-credit]', gallery);
        if (cr) { cr.textContent = (b.dataset.credit || '').split(' · ')[0]; cr.hidden = !b.dataset.credit; }
      });
    });
  }

  document.addEventListener('click', function (e) {
    var go = e.target.closest('[data-cal-go]');
    if (!go) return;
    var cal = go.closest('[data-cal]');
    if (!cal) return;
    e.preventDefault();
    if (go.classList.contains('is-disabled')) return;
    cal.classList.add('cal-loading');
    var u = new URL(cal.dataset.api, window.location.href);
    u.searchParams.set('ym', go.dataset.calGo);
    u.searchParams.set('link', cal.dataset.link);
    if (cal.dataset.selected) u.searchParams.set('date', cal.dataset.selected);
    getJSON(u.toString()).then(function (res) {
      var wrap = document.createElement('div');
      wrap.innerHTML = res.html;
      var fresh = wrap.firstElementChild;
      cal.replaceWith(fresh);
      document.dispatchEvent(new CustomEvent('cal:swap'));
      // keep keyboard users on the same arrow after the month swaps
      var same = go.getAttribute('aria-label') && fresh.querySelector('[aria-label="' + go.getAttribute('aria-label') + '"]');
      if (same && !same.classList.contains('is-disabled')) same.focus(); else { var h = $('h2', fresh); if (h) { h.setAttribute('tabindex', '-1'); h.focus(); } }
    }).catch(function () { window.location.href = go.href; });
  });

  /* favourites: delegated, because cards arrive after the page has loaded */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest ? e.target.closest('form[data-fav]') : null;
    if (!f) return;
    e.preventDefault();
    var btn = $('button', f);
    getJSON(f.action, { method: 'POST', body: new FormData(f) }).then(function (res) {
      if (!res.ok) return;
      btn.setAttribute('aria-pressed', res.fav ? 'true' : 'false');
      btn.setAttribute('aria-label', (res.fav ? 'นำออกจากชุดที่ถูกใจ' : 'เก็บไว้ในชุดที่ถูกใจ') + (btn.dataset.name ? ': ' + btn.dataset.name : ''));
      $('svg', btn).classList.toggle('is-filled', res.fav);
      toast(res.fav ? 'เก็บไว้ในชุดที่ถูกใจแล้ว' : 'นำออกจากชุดที่ถูกใจแล้ว');
    }).catch(function () { f.submit(); });
  });

  document.addEventListener('click', function (e) {
    var s = e.target.closest('[data-share]');
    if (!s) return;
    var data = { title: s.dataset.title, url: window.location.href.split('#')[0] };
    if (navigator.share) { navigator.share(data).catch(function () {}); return; }
    copyText(data.url, s);
  });

  function copyText(text, el) {
    var done = function () {
      var old = el.getAttribute('aria-label');
      el.setAttribute('data-copied', '');
      toast('คัดลอกแล้ว');
      setTimeout(function () { el.removeAttribute('data-copied'); if (old) el.setAttribute('aria-label', old); }, 1600);
    };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () {});
    else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
  }
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]');
    if (c) { e.preventDefault(); copyText(c.dataset.copy, c); }
  });

  var toastEl;
  function toast(msg) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      toastEl.setAttribute('role', 'status');
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.classList.add('is-on');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.classList.remove('is-on'); }, 1800);
  }

  /* ---------- booking form: live quote ---------- */
  var bf = $('[data-booking-form]');
  if (bf) {
    var ev = $('[name="event_date"]', bf), pu = $('[name="pickup_date"]', bf), rt = $('[name="return_date"]', bf);
    var out = $('[data-quote-out]', bf), state = $('[data-quote-state]', bf), submit = $('[data-booking-submit]', bf);
    var timer, lastEvent = ev.value;
    // How the dress travels, and the province it goes to (a saved address carries it in data-province).
    var ship = function () {
      var pick = function (n, d) { var r = $('[name="' + n + '"]:checked', bf) || $('input[type="hidden"][name="' + n + '"]', bf); return r ? r.value : d; };
      var delivery = pick('delivery_method', 'pickup'), back = pick('return_method', 'store'), province = '';
      if (delivery !== 'pickup' || back !== 'store') {
        var saved = $('[name="address_id"]:checked', bf);
        if (saved && saved.dataset.province) province = saved.dataset.province;
        else { var sel = $('[data-province]', bf); province = sel && sel.tagName === 'SELECT' ? sel.value : ''; }
      }
      bf.dataset.delivery = delivery; bf.dataset.back = back;
      return { delivery: delivery, back: back, province: province };
    };
    var check = function () {
      if (!ev.value || !pu.value || !rt.value) return;
      var u = new URL(bf.dataset.quoteApi, window.location.href);
      u.searchParams.set('event', ev.value); u.searchParams.set('pickup', pu.value); u.searchParams.set('return', rt.value);
      if (bf.dataset.ignore) u.searchParams.set('ignore', bf.dataset.ignore);
      var way = ship();
      u.searchParams.set('delivery', way.delivery); u.searchParams.set('back', way.back);
      if (way.province) u.searchParams.set('province', way.province);
      state.className = 'quote-state'; state.textContent = 'กำลังตรวจวันว่าง…';
      getJSON(u.toString()).then(function (res) {
        if (!res.ok) {
          state.className = 'quote-state is-busy';
          state.innerHTML = '<svg class="ic" aria-hidden="true"><use href="' + BASE + '/assets/img/icons.svg#i-circle-alert"></use></svg>' + res.message;
          if (submit) submit.disabled = true;
          return;
        }
        if (out) out.innerHTML = res.html;
        state.className = 'quote-state ' + (res.free ? 'is-free' : 'is-busy');
        state.innerHTML = '<svg class="ic" aria-hidden="true"><use href="' + BASE + '/assets/img/icons.svg#i-' + (res.free ? 'circle-check' : 'calendar-x') + '"></use></svg>' + res.message;
        if (submit) submit.disabled = !res.free;
      }).catch(function () { state.textContent = 'ตรวจวันว่างไม่สำเร็จ ลองอีกครั้ง'; });
    };
    ev.addEventListener('change', function () {
      // moving the event date moves the default window with it
      if (ev.value && (!pu.value || pu.value === addDays(lastEvent, -1) || pu.value === lastEvent)) {
        var today = pu.min || iso(new Date());
        var p = addDays(ev.value, -1); if (p < today) p = today;
        pu.value = p; rt.value = addDays(ev.value, 1);
      }
      lastEvent = ev.value;
      check();
    });
    [pu, rt].forEach(function (i) { i.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 150); }); });
    bf.addEventListener('change', function (e) {
      if (e.target.matches('[name="delivery_method"], [name="return_method"], [name="address_id"], [data-province]')) { clearTimeout(timer); timer = setTimeout(check, 150); }
    });
    ship();

    // Native date inputs show Christian-era years. In the shop each one already carries its Thai reading (the date
    // desk); the counter forms get the B.E. date echoed under the field, so "2027" never reads unlike "2570".
    [ev, pu, rt].forEach(function (i) {
      var desk = i.closest('[data-desk]');
      if (desk) { i._be = function () { paintDesk(desk, i.value); }; return; }
      var be = document.createElement('small');
      be.className = 'be-date'; be.setAttribute('aria-live', 'polite');
      i.insertAdjacentElement('afterend', be);
      i._be = function () { be.textContent = i.value ? beDate(i.value) : ''; };
      i.addEventListener('input', i._be); i._be();
    });
    // Shade pick-up to return on the calendar; the event day keeps its solid mark.
    var paintRange = function () {
      $$('[data-cal-wrap] .cal-day[data-date]').forEach(function (d) {
        var x = d.dataset.date, inRange = pu.value && rt.value && x >= pu.value && x <= rt.value;
        d.classList.toggle('cal-range', !!inRange && x !== ev.value);
        d.classList.toggle('cal-range-end', !!inRange && (x === pu.value || x === rt.value) && x !== ev.value);
      });
    };
    [ev, pu, rt].forEach(function (i) { i.addEventListener('change', function () { ev._be(); pu._be(); rt._be(); paintRange(); }); });
    document.addEventListener('cal:swap', paintRange);
    paintRange();
  }

  /* ---------- payment: QR, countdown, slip preview + downscale ---------- */
  // QR codes: PromptPay on the payment page, booking numbers on packing slips, dress codes on tags
  if (window.qrcode) {
    $$('[data-qr]').forEach(function (qr) {
      try {
        var q = window.qrcode(0, 'M');
        q.addData(qr.dataset.qr);
        q.make();
        qr.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
        var svg = $('svg', qr); svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', qr.dataset.qrLabel || 'QR พร้อมเพย์สำหรับชำระ ' + qr.dataset.amount + ' บาท');
      } catch { qr.textContent = qr.dataset.qrLabel ? qr.dataset.qr : 'สร้าง QR ไม่ได้ ใช้เลขพร้อมเพย์ด้านข้างแทน'; }
    });
  }

  // scan desk: a phone camera reads the QR on a dress tag or packing slip (where the browser can)
  var sc = $('[data-scan]');
  if (sc && 'BarcodeDetector' in window && navigator.mediaDevices) {
    var cam = $('[data-scan-cam]', sc), video = $('[data-scan-video]', sc), msg = $('[data-scan-msg]', sc), scanBox = $('#scan-q', sc);
    cam.hidden = false;
    $('[data-scan-start]', sc).addEventListener('click', function () {
      var det = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39'] });
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
        video.srcObject = stream; video.hidden = false; video.play();
        msg.textContent = 'เล็งกล้องไปที่ QR บนป้ายชุดหรือใบแพ็ก';
        var look = function () {
          det.detect(video).then(function (codes) {
            if (codes.length) {
              stream.getTracks().forEach(function (t) { t.stop(); });
              scanBox.value = codes[0].rawValue; scanBox.form.submit();
            } else { requestAnimationFrame(look); }
          }).catch(function () { requestAnimationFrame(look); });
        };
        look();
      }).catch(function () { msg.textContent = 'เปิดกล้องไม่ได้ อนุญาตการใช้กล้อง หรือพิมพ์รหัสแทน'; });
    });
  }

  $$('[data-countdown]').forEach(function (el) {
    var end = new Date(el.dataset.countdown.replace(' ', 'T') + '+07:00').getTime();
    var label = $('[data-countdown-text]', el) || el;
    var tick = function () {
      var s = Math.max(0, Math.floor((end - Date.now()) / 1000));
      if (s <= 0) { label.textContent = 'หมดเวลาชำระแล้ว'; setTimeout(function () { window.location.reload(); }, 1500); return; }
      var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
      label.textContent = 'เหลือเวลาชำระ ' + (h ? h + ' ชม. ' : '') + String(m).padStart(2, '0') + ':' + String(x).padStart(2, '0');
      setTimeout(tick, 1000);
    };
    tick();
  });

  $$('[data-drop]').forEach(function (drop) {
    var file = $('input[type="file"]', drop);
    var prev = $('[data-drop-preview]', drop.parentNode);
    var label = $('[data-drop-label]', drop);
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; file.dispatchEvent(new Event('change')); }
    });
    file.addEventListener('change', function () {
      var f = file.files[0];
      if (!f) return;
      if (file.multiple) {
        // condition photos: several at once, each downscaled so a set of phone photos stays a quick upload
        var list = Array.prototype.slice.call(file.files), max = 6;
        if (label) label.textContent = list.length > max ? 'เลือกได้ไม่เกิน ' + max + ' รูป (เลือกไว้ ' + list.length + ')' : 'เลือกแล้ว ' + list.length + ' รูป';
        drop.classList.toggle('is-bad', list.length > max);
        if (list.length > max || !window.DataTransfer) return;
        Promise.all(list.map(function (x) { return x.size > 1.5 * 1024 * 1024 && x.type.match(/image\/(jpeg|png|webp)/) ? downscale(x, 1800).then(function (y) { return y || x; }) : Promise.resolve(x); }))
          .then(function (done) { var dt = new DataTransfer(); done.forEach(function (x) { dt.items.add(x); }); file.files = dt.files; });
        return;
      }
      if (label) label.textContent = f.name + ' · ' + Math.round(f.size / 1024) + ' KB';
      drop.classList.remove('is-bad');
      if (prev && f.type.indexOf('image/') === 0) {
        prev.onerror = function () {
          // the browser could not decode it: not a real image (or a damaged file)
          prev.hidden = true; file.value = ''; drop.classList.add('is-bad');
          if (label) label.textContent = 'ไฟล์นี้เปิดเป็นรูปไม่ได้ ลองเลือกรูปสลิปใหม่';
        };
        prev.onload = function () { prev.hidden = false; };
        prev.src = URL.createObjectURL(f);
      }
      // phone photos can be 8–12 MB: downscale big images before upload
      if (!file.multiple && f.size > 1.5 * 1024 * 1024 && f.type.match(/image\/(jpeg|png|webp)/) && window.DataTransfer) {
        downscale(f, 1800).then(function (small) {
          if (!small) return;
          var dt = new DataTransfer(); dt.items.add(small); file.files = dt.files;
          if (label) label.textContent = f.name + ' · ย่อเหลือ ' + Math.round(small.size / 1024) + ' KB';
        });
      }
    });
  });

  function downscale(f, max) {
    return new Promise(function (resolve) {
      var img = new Image();
      img.onload = function () {
        var s = Math.min(1, max / Math.max(img.width, img.height));
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) { resolve(b ? new File([b], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : null); }, 'image/jpeg', 0.86);
      };
      img.onerror = function () { resolve(null); };
      img.src = URL.createObjectURL(f);
    });
  }

  /* ---------- fitting slots ---------- */
  var slotForm = $('[data-slots]');
  if (slotForm) {
    var sd = $('[name="date"]', slotForm), box = $('[data-slot-box]', slotForm);
    sd.addEventListener('change', function () {
      box.classList.add('cal-loading');
      getJSON(slotForm.dataset.slots + '?date=' + encodeURIComponent(sd.value)).then(function (res) {
        box.classList.remove('cal-loading');
        if (!res.ok) { box.innerHTML = '<p class="field-error">' + res.error + '</p>'; return; }
        if (!res.slots.length) { box.innerHTML = '<p class="muted">วันนี้ร้านปิดหรือไม่มีช่วงเวลาว่าง ลองเลือกวันอื่น</p>'; return; }
        box.innerHTML = '<div class="slots">' + res.slots.map(function (s) {
          return '<label><input type="radio" name="time" value="' + s.time + '"' + (s.left > 0 ? '' : ' disabled') + ' required><span>' + s.time + '</span></label>';
        }).join('') + '</div>';
      });
    });
  }

  /* ---------- password reveal + strength ---------- */
  $$('[data-reveal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.dataset.reveal);
      var show = i.type === 'password';
      i.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
      b.setAttribute('aria-label', show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
      $('use', b).setAttribute('href', BASE + '/assets/img/icons.svg#i-' + (show ? 'eye-off' : 'eye'));
    });
  });
  $$('[data-pw-meter]').forEach(function (m) {
    var i = document.getElementById(m.dataset.pwMeter), bar = $('i', m), txt = document.getElementById(m.dataset.pwMeter + '-hint');
    i.addEventListener('input', function () {
      var v = i.value, s = 0;
      if (v.length >= 8) s++; if (/[a-z]/i.test(v) && /\d/.test(v)) s++; if (v.length >= 12) s++; if (/[^a-z0-9]/i.test(v)) s++;
      bar.style.width = (v ? Math.max(12, s * 25) : 0) + '%';
      if (txt) txt.textContent = !v ? 'อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข' : (s < 2 ? 'ยังสั้นหรือง่ายไป: ต้องมีอย่างน้อย 8 ตัว ทั้งตัวอักษรและตัวเลข' : (s < 3 ? 'ใช้ได้' : 'รหัสผ่านแข็งแรง'));
    });
  });
  $$('[data-fill-login]').forEach(function (b) {
    b.addEventListener('click', function () {
      $('#email').value = b.dataset.fillLogin; $('#password').value = b.dataset.pw; $('#password').focus();
    });
  });

  /* ---------- back office ---------- */
  // member lookup on the counter booking form
  var ml = $('[data-member-lookup]');
  if (ml) {
    var q2 = $('input[type="search"]', ml), list = $('[data-member-results]', ml), hid = $('[name="user_id"]', ml.closest('form') || document);
    var mt, found = {};
    q2.addEventListener('input', function () {
      clearTimeout(mt);
      if (q2.value.trim().length < 2) { list.innerHTML = ''; return; }
      mt = setTimeout(function () {
        getJSON(ml.dataset.memberLookup + '?q=' + encodeURIComponent(q2.value.trim())).then(function (res) {
          found = {}; res.items.forEach(function (m) { found[m.id] = m.addresses || []; });
          list.innerHTML = res.items.length ? res.items.map(function (m) {
            return '<button type="button" class="lookup-item" data-pick="' + m.id + '" data-label="' + esc(m.name) + '"' + (m.suspended ? ' disabled' : '') + '><b>' + esc(m.name) + (m.suspended ? ' · ระงับบัญชี' : '') + '</b><span>' + esc(m.meta) + '</span></button>';
          }).join('') : '<p class="small muted">ไม่พบสมาชิก ลองค้นด้วยเบอร์โทรหรืออีเมล หรือสมัครให้ลูกค้าใหม่</p>';
        });
      }, 220);
    });
    list.addEventListener('click', function (e) {
      var p = e.target.closest('[data-pick]');
      if (!p) return;
      hid.value = p.dataset.pick;
      q2.value = p.dataset.label;
      list.innerHTML = '';
      var chosen = $('[data-member-chosen]', ml); if (chosen) { chosen.textContent = 'เลือกแล้ว: ' + p.dataset.label; chosen.hidden = false; }
      // counter booking: offer this customer's saved addresses, the first one picked
      var saved = $('[data-saved-addresses]');
      if (saved) {
        var rows = found[p.dataset.pick] || [];
        saved.innerHTML = rows.map(function (a, i) {
          return '<label class="way"><input type="radio" name="address_id" value="' + a.id + '" data-province="' + esc(a.province) + '"' + (i ? '' : ' checked') + '>'
            + '<span class="way-body"><b>' + esc(a.title) + '</b><small>' + esc(a.text) + '</small></span><span class="way-fee">' + esc(a.zone) + '</span></label>';
        }).join('') + '<label class="way"><input type="radio" name="address_id" value="new"' + (rows.length ? '' : ' checked') + '><span class="way-body"><b>' + (rows.length ? 'ส่งที่อยู่อื่น' : 'ลูกค้ายังไม่มีที่อยู่ในระบบ') + '</b><small>กรอกที่อยู่ใหม่ด้านล่าง</small></span></label>';
        saved.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // return inspection: checklist, damage rows from the shop's table, live settlement
  var ins = $('[data-inspect]');
  if (ins) {
    var due = ins.dataset.due, fee = +ins.dataset.fee, dep = +ins.dataset.deposit, cap = ins.dataset.value === '' ? Infinity : +ins.dataset.value, acc = +ins.dataset.acc;
    var rd = $('[data-insp-date]', ins), rows = $$('[data-dmg-row]', ins), addRow = $('[data-dmg-more]', ins);
    var rowSum = function (r) {
      var sel = $('[data-dmg-type]', r), opt = sel.options[sel.selectedIndex];
      if (!sel.value) return 0;
      var own = $('[data-dmg-amount]', r).value.trim();
      if (own !== '') return Math.max(0, +own || 0);
      var qty = Math.max(1, +$('[data-dmg-qty]', r).value || 1);
      return opt.dataset.unit === '' ? 0 : +opt.dataset.unit * qty;
    };
    var paint = function () {
      var late = Math.max(0, Math.round((parseDate(rd.value || due) - parseDate(due)) / 86400000)), lateFee = late * fee;
      var dmg = rows.reduce(function (t, r) { return t + rowSum(r); }, 0) + $$('[data-missing]:checked', ins).length * acc;
      var d = Math.min(dmg, cap), fines = lateFee + d;
      $('[data-rp-late]', ins).textContent = late ? late + ' วัน × ' + fmt(fee) + ' = ' + fmt(lateFee) + ' บาท' : 'ไม่มี (คืนตรงเวลา)';
      $('[data-rp-damage]', ins).textContent = fmt(d) + ' บาท';
      $('[data-rp-refund]', ins).textContent = fmt(Math.max(0, dep - fines)) + ' บาท';
      $('[data-rp-extra]', ins).textContent = fmt(Math.max(0, fines - dep)) + ' บาท';
      $('[data-rp-cap]', ins).hidden = dmg <= cap;
      ins.querySelectorAll('[data-rp-extra-row]').forEach(function (n) { n.hidden = fines <= dep; });
    };
    // show two empty rows; "add" reveals the next one
    rows.forEach(function (r, i) { if (i > 1) r.hidden = true; });
    addRow.hidden = false;
    addRow.addEventListener('click', function () {
      var next = rows.filter(function (r) { return r.hidden; })[0];
      if (next) { next.hidden = false; $('select', next).focus(); }
      addRow.hidden = !rows.some(function (r) { return r.hidden; });
    });
    $('[data-check-all]', ins).addEventListener('click', function () { $$('[data-check]', ins).forEach(function (c) { c.checked = true; }); });
    ins.addEventListener('input', paint);
    ins.addEventListener('change', paint);
    paint();
  }

  // admin: mobile nav
  var nb = $('button[data-nav-toggle]');
  document.querySelectorAll('[data-nav-toggle]').forEach(function (t) {
    t.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      if (nb) nb.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { document.body.classList.remove('nav-open'); if (nb) { nb.setAttribute('aria-expanded', 'false'); nb.focus(); } }
  });

  // auto-submit selects in filter bars
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });
})();

/* admin: suggest the next code when the category changes on a new costume */
(function () {
  var sel = document.querySelector('[data-code-map]');
  if (!sel) return;
  var map = JSON.parse(sel.dataset.codeMap || '{}'), code = document.getElementById('code'), touched = false;
  code.addEventListener('input', function () { touched = true; });
  sel.addEventListener('change', function () { if (!touched && map[sel.value]) code.value = map[sel.value]; });
})();
