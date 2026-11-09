/* Живой аудит zhdanovsites.com (Zhdanov Sites, 2026).
   Подключение: <script src="/audit2.js" defer></script> в конце страницы (после основного скрипта).
   Берёт существующую форму #ckf / #cku / #ckr и показывает: выбор цели, шаги проверки с таймером,
   оценку 0–100, находки по очереди, «Видят ли вас нейросети» (/api/geo), законы, калькулятор денег,
   «Вы и 3 конкурента», «Сохранить отчёт» и отправку в мессенджер. Без библиотек. Только русская версия. */
(function () {
  'use strict';
  if ((document.documentElement.lang || '').slice(0, 2) !== 'ru') return;
  var form0 = document.getElementById('ckf'), out = document.getElementById('ckr');
  if (!form0 || !out) return;

  /* ---------- стили (переменные сайта: --gold, --card, --line, --ink, --soft, --mute, --serif) ---------- */
  var css = '' +
    '.a2-goals{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 0}' +
    '.a2-goals span{width:100%;font-size:14px;color:var(--mute,#85827b)}' +
    '.a2-goal{appearance:none;border:1px solid var(--line,#2e2f2d);background:transparent;color:var(--soft,#b9b4ab);border-radius:999px;padding:8px 14px;font:inherit;font-size:15px;cursor:pointer;min-height:40px}' +
    '.a2-goal[aria-pressed=true]{border-color:var(--gold,#e7b866);color:var(--ink,#f6efe2);background:rgba(231,184,102,.12)}' +
    '.a2-goal:focus-visible,.a2-btn:focus-visible,.a2 a:focus-visible,.a2 input:focus-visible{outline:2px solid var(--gold2,#f3d69f);outline-offset:2px}' +
    '.a2{margin-top:22px}' +
    '.a2-run{background:var(--card,#1f201f);border:1px solid var(--line,#2e2f2d);border-radius:20px;padding:20px 22px}' +
    '.a2-runh{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin:0 0 12px}' +
    '.a2-runh b{font-size:17px}.a2-time{font-variant-numeric:tabular-nums;color:var(--mute,#85827b);font-size:15px}' +
    '.a2-bar{height:6px;border-radius:3px;background:var(--line,#2e2f2d);overflow:hidden;margin:0 0 14px}.a2-bar i{display:block;height:100%;width:0;background:var(--gold,#e7b866);transition:width .6s ease}' +
    '.a2-steps{list-style:none;margin:0;padding:0;display:grid;gap:8px}' +
    '.a2-steps li{display:flex;gap:10px;align-items:center;color:var(--mute,#85827b);font-size:15px}' +
    '.a2-steps li i{flex:none;width:20px;height:20px;border-radius:50%;border:2px solid var(--line,#2e2f2d);display:inline-flex;align-items:center;justify-content:center;font-style:normal;font-size:12px}' +
    '.a2-steps li.on{color:var(--ink,#f6efe2)}.a2-steps li.on i{border-color:var(--gold,#e7b866);border-top-color:transparent;animation:a2s 0.9s linear infinite}' +
    '.a2-steps li.done{color:var(--soft,#b9b4ab)}.a2-steps li.done i{border-color:var(--gold,#e7b866);background:var(--gold,#e7b866);color:#121312;animation:none}' +
    '@keyframes a2s{to{transform:rotate(360deg)}}' +
    '.a2-score{display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:center;background:var(--card,#1f201f);border:1px solid var(--line,#2e2f2d);border-radius:20px;padding:22px}' +
    '.a2-ring{position:relative;width:132px;height:132px}.a2-ring svg{transform:rotate(-90deg)}' +
    '.a2-ring b{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;font:600 40px/1 var(--serif,Georgia,serif)}' +
    '.a2-ring small{font:500 13px/1.2 var(--sans,system-ui);color:var(--mute,#85827b);margin-top:4px}' +
    '.a2-sub{display:grid;gap:10px}.a2-sub h3{margin:0;font:600 24px/1.2 var(--serif,Georgia,serif)}.a2-sub p{margin:0;color:var(--soft,#b9b4ab);font-size:15px}' +
    '.a2-mini{display:grid;grid-template-columns:auto 1fr auto;gap:6px 10px;align-items:center;font-size:14px;color:var(--soft,#b9b4ab)}' +
    '.a2-mini u{display:block;height:5px;border-radius:3px;background:var(--line,#2e2f2d);overflow:hidden;text-decoration:none}.a2-mini u i{display:block;height:100%}' +
    '.a2-h{font:600 26px/1.2 var(--serif,Georgia,serif);margin:30px 0 12px}' +
    '.a2 .cki article{opacity:0;transform:translateY(8px);transition:opacity .45s ease,transform .45s ease}.a2 .cki article.in{opacity:1;transform:none}' +
    '.a2-geo{background:var(--card,#1f201f);border:1px solid var(--line,#2e2f2d);border-radius:20px;padding:20px 22px}' +
    '.a2-geo .a2-gt{display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap}' +
    '.a2-geo .a2-gt b{font-size:18px}.a2-geo .a2-gt span{font:600 28px/1 var(--serif,Georgia,serif);color:var(--gold,#e7b866)}' +
    '.a2-gl{list-style:none;margin:12px 0 0;padding:0;display:grid;gap:10px}' +
    '.a2-gl li{display:grid;grid-template-columns:22px 1fr;gap:8px;font-size:15px;color:var(--soft,#b9b4ab)}.a2-gl li b{color:var(--ink,#f6efe2);font-weight:600}' +
    '.a2-gl li em{font-style:normal;display:block;color:var(--mute,#85827b);font-size:14px;margin-top:2px}' +
    '.a2-ok{color:#7fc98a}.a2-no{color:#e48a7a}' +
    '.a2 details{margin-top:10px}.a2 summary{cursor:pointer;color:var(--gold,#e7b866);font-size:15px}' +
    '.a2-share{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;align-items:center}' +
    '.a2-btn{appearance:none;border:1px solid var(--line,#2e2f2d);background:transparent;color:var(--ink,#f6efe2);border-radius:999px;padding:10px 18px;font:inherit;font-size:15px;cursor:pointer;text-decoration:none;min-height:44px;display:inline-flex;align-items:center;gap:6px}' +
    '.a2-btn.gold{background:var(--gold,#e7b866);color:#121312;border-color:var(--gold,#e7b866);font-weight:700}' +
    '.a2-cmp{background:var(--card,#1f201f);border:1px solid var(--line,#2e2f2d);border-radius:20px;padding:20px 22px}' +
    '.a2-cmpf{display:grid;grid-template-columns:repeat(3,1fr) auto;gap:8px;margin:10px 0 0}' +
    '.a2-cmpf input{min-width:0;border:1px solid var(--line,#2e2f2d);background:var(--bg2,#1a1b1a);color:var(--ink,#f6efe2);border-radius:999px;padding:10px 16px;font:inherit;font-size:15px}' +
    '.a2-tw{overflow-x:auto;margin-top:14px}.a2-t{border-collapse:collapse;width:100%;min-width:520px;font-size:14px}' +
    '.a2-t th,.a2-t td{padding:9px 10px;border-bottom:1px solid var(--line,#2e2f2d);text-align:left;vertical-align:top}' +
    '.a2-t th{color:var(--mute,#85827b);font-weight:500}.a2-t td.me{color:var(--gold2,#f3d69f)}.a2-t .best{color:#7fc98a;font-weight:700}' +
    '.a2-note{font-size:13px;color:var(--mute,#85827b);margin-top:8px}' +
    '.a2-toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);background:var(--gold,#e7b866);color:#121312;padding:10px 18px;border-radius:999px;font-weight:700;z-index:99}' +
    '@media (max-width:600px){.a2-score{grid-template-columns:1fr;justify-items:center;text-align:center}.a2-mini{text-align:left;width:100%}.a2-cmpf{grid-template-columns:1fr}.a2-run,.a2-score,.a2-geo,.a2-cmp{padding:16px}}' +
    '@media (prefers-reduced-motion:reduce){.a2 *{transition:none!important;animation:none!important}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  /* ---------- тексты ---------- */
  var GOALS = [
    { id: 'leads', label: 'Заявки и звонки', cta: 'Получить план, как увеличить заявки', price: 'Средний чек одного клиента' },
    { id: 'booking', label: 'Запись на услуги', cta: 'Получить план, как увеличить запись', price: 'Стоимость услуги' },
    { id: 'sales', label: 'Продажи онлайн', cta: 'Получить план, как увеличить продажи', price: 'Средний чек заказа' },
    { id: 'trust', label: 'Доверие и имидж', cta: 'Получить план, как сайту выглядеть надёжнее', price: 'Средний чек клиента' }
  ];
  var SEV = { c: 'Критично', i: 'Важно', l: 'Мелочь', t: 'Совет' };
  var STEPS = ['Открываем сайт', 'Смотрим версию для телефона', 'Спрашиваем у Google скорость', 'Ищем ошибки и юридические риски', 'Проверяем, видят ли сайт Алиса и нейросети', 'Считаем, сколько денег теряет сайт'];

  function esc(x) { return String(x == null ? '' : x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function plural(n, a, b, c) { var x = n % 10, y = n % 100; return x === 1 && y !== 11 ? a : (x >= 2 && x <= 4 && (y < 10 || y >= 20) ? b : c); }
  function fmt(n, cu) { var s = Math.round(n).toLocaleString('ru-RU'); return /[$£€]/.test(cu) ? cu + s : s + ' ' + cu; }
  function toast(t) { var d = document.createElement('div'); d.className = 'a2-toast'; d.setAttribute('role', 'status'); d.textContent = t; document.body.appendChild(d); setTimeout(function () { d.remove(); }, 2600); }
  function color(s) { return s >= 80 ? '#7fc98a' : s >= 50 ? '#e7b866' : '#e48a7a'; }
  function getJSON(u) { return fetch(u).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); }); }

  /* Оценка сайта 0–100 из ответа /api/check */
  function siteScore(j) {
    var s = 100, w = { c: 18, i: 10, l: 4, t: 0 };
    (j.issues || []).forEach(function (x) { s -= w[x.sev] || 0; });
    var it = j.legal ? (j.legal.items || (j.legal.ok ? [] : [j.legal])) : [];
    s -= Math.min(24, it.length * 6);
    var sp = j.speed && typeof j.speed === 'object' ? (j.speed.mobile || j.speed.score) : j.speed;
    if (typeof sp === 'number') s -= sp < 50 ? 12 : sp < 90 ? 5 : 0;
    return Math.max(5, Math.min(100, Math.round(s)));
  }
  function lawScore(j) { var it = j.legal ? (j.legal.items || (j.legal.ok ? [] : [j.legal])) : []; return Math.max(0, 100 - it.length * 25); }

  /* ---------- новая форма: снимаем старый обработчик, добавляем выбор цели ---------- */
  var form = form0.cloneNode(true); form0.parentNode.replaceChild(form, form0);
  var inp = form.querySelector('#cku'), btn = form.querySelector('button');
  var goal = 'leads';
  var gw = document.createElement('div'); gw.className = 'a2-goals'; gw.setAttribute('role', 'group'); gw.setAttribute('aria-label', 'Что для сайта главное');
  gw.innerHTML = '<span>Что для сайта главное?</span>' + GOALS.map(function (g, i) { return '<button type="button" class="a2-goal" data-g="' + g.id + '" aria-pressed="' + (i === 0) + '">' + g.label + '</button>'; }).join('');
  form.parentNode.insertBefore(gw, form.nextSibling);
  gw.addEventListener('click', function (e) { var b = e.target.closest('.a2-goal'); if (!b) return; goal = b.dataset.g; gw.querySelectorAll('.a2-goal').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); });
  function G() { return GOALS.filter(function (g) { return g.id === goal; })[0] || GOALS[0]; }

  var running = false;
  form.addEventListener('submit', function (ev) { ev.preventDefault(); var u = inp.value.trim(); if (!u) { inp.focus(); return; } run(u); });

  /* ---------- проверка ---------- */
  function run(u) {
    if (running) return; running = true; btn.disabled = true;
    try { if (window.ym) window.ym(window.ZH_METRIKA || 0, 'reachGoal', 'audit_start'); } catch (e) {}
    var t0 = Date.now(), step = 0, res = { check: null, geo: null }, ended = false;
    out.innerHTML = '<div class="a2"><div class="a2-run" role="status" aria-live="polite"><div class="a2-runh"><b>Проверяем ' + esc(u.replace(/^https?:\/\//, '').replace(/\/$/, '')) + '</b><span class="a2-time">0:00</span></div><div class="a2-bar"><i></i></div><ol class="a2-steps">' + STEPS.map(function (s) { return '<li><i aria-hidden="true"></i>' + s + '</li>'; }).join('') + '</ol></div></div>';
    var lis = out.querySelectorAll('.a2-steps li'), bar = out.querySelector('.a2-bar i'), tm = out.querySelector('.a2-time');
    function mark(n) { for (var i = 0; i < lis.length; i++) { lis[i].className = i < n ? 'done' : (i === n ? 'on' : ''); } bar.style.width = Math.min(96, Math.round(100 * n / lis.length)) + '%'; }
    mark(0);
    var clock = setInterval(function () { var s = Math.floor((Date.now() - t0) / 1000); tm.textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2); }, 500);
    var walk = setInterval(function () { var lim = res.check ? 5 : 3; if (step < lim) { step++; mark(step); } }, 2600);
    var pC = getJSON('/api/check?lang=ru&url=' + encodeURIComponent(u)).then(function (j) { res.check = j; }).catch(function () { res.check = { error: 'net' }; });
    var pG = getJSON('/api/geo?lang=ru&url=' + encodeURIComponent(u)).then(function (j) { res.geo = j; }).catch(function () { res.geo = null; });
    Promise.all([pC, pG]).then(function () {
      clearInterval(walk); mark(lis.length); bar.style.width = '100%';
      setTimeout(function () { clearInterval(clock); running = false; btn.disabled = false; render(u, res.check, res.geo && !res.geo.error ? res.geo : null); }, 450);
    });
  }

  /* ---------- результат ---------- */
  function render(u, j, geo) {
    if (!j || j.error) {
      var unr = j && j.error === 'unreachable';
      out.innerHTML = '<p class="ckl">' + (unr ? 'Сайт не пустил автоматическую проверку: так бывает, когда он защищён от ботов. Пришлите адрес в заявке ниже, и мы проверим его вручную.' : 'Не получилось открыть этот адрес. Проверьте, правильно ли он написан.') + '</p>' + (unr ? '<a class="btn" href="#order">Получить полный аудит</a>' : '');
      return;
    }
    var url = j.url || u, host = url.replace(/^https?:\/\//, '').replace(/\/$/, '');
    var ss = siteScore(j), ls = lawScore(j), gs = geo ? geo.score : null;
    var total = Math.round(gs == null ? ss * 0.8 + ls * 0.2 : ss * 0.6 + ls * 0.15 + gs * 0.25);
    var verdict = total >= 80 ? 'Сайт в хорошей форме, есть что улучшить.' : total >= 50 ? 'Сайт работает, но заметно теряет клиентов.' : 'Сайт серьёзно мешает получать клиентов.';
    var R = 58, C = 2 * Math.PI * R;
    var h = '<div class="a2">';
    h += '<div class="a2-score"><div class="a2-ring" role="img" aria-label="Оценка сайта ' + total + ' из 100"><svg width="132" height="132" viewBox="0 0 132 132" aria-hidden="true"><circle cx="66" cy="66" r="' + R + '" fill="none" stroke="var(--line,#2e2f2d)" stroke-width="10"/><circle class="a2-arc" cx="66" cy="66" r="' + R + '" fill="none" stroke="' + color(total) + '" stroke-width="10" stroke-linecap="round" stroke-dasharray="' + C.toFixed(1) + '" stroke-dashoffset="' + C.toFixed(1) + '" style="transition:stroke-dashoffset 1.4s ease"/></svg><b><span class="a2-num">0</span><small>из 100</small></b></div>';
    h += '<div class="a2-sub"><h3>' + esc(host) + '</h3><p>' + verdict + ' Цель: ' + esc(G().label.toLowerCase()) + '.</p><div class="a2-mini">' +
      mini('Сайт и телефон', ss) + mini('Законы', ls) + (gs == null ? '' : mini('Нейросети', gs)) + '</div></div></div>';

    var top = (j.issues || []).filter(function (x) { return x.sev !== 't'; }).slice(0, 3);
    if (!top.length) top = (j.issues || []).slice(0, 3);
    if (top.length) {
      h += '<h3 class="a2-h">' + (top.length < 3 ? 'Главное, что стоит исправить' : 'Три главные ошибки') + '</h3><div class="cki">' + top.map(function (x, n) {
        return '<article class="ck-' + x.sev + '"><span class="cks">' + (SEV[x.sev] || '') + '</span><b>' + (n + 1) + '. ' + esc(x.title) + '</b><p>' + esc(x.text) + '</p><p class="ckx"><i>Как исправить:</i> ' + esc(x.fix) + '</p></article>';
      }).join('') + '</div>';
      var rest = (j.total || 0) - top.length;
      if (rest > 0) h += '<p class="ckm">Мы нашли ещё ' + rest + ' ' + plural(rest, 'проблему', 'проблемы', 'проблем') + '. В полном разборе по каждой: что не так, чем мешает клиентам и как исправить. Вместе с новым дизайном за 24 часа.</p>';
    }

    if (geo) {
      var bad = geo.items.filter(function (x) { return !x.ok; });
      var tops = geo.top.map(function (id) { return geo.items.filter(function (x) { return x.id === id; })[0]; }).filter(Boolean);
      h += '<h3 class="a2-h">Видят ли вас Алиса и нейросети</h3><div class="a2-geo"><div class="a2-gt"><b>Люди всё чаще спрашивают Алису, GigaChat и ChatGPT, а не листают поиск.</b><span>' + geo.score + '/100</span></div>';
      h += tops.length ? '<ul class="a2-gl">' + tops.map(gli).join('') + '</ul>' : '<p class="a2-ok">Сайт хорошо подготовлен для нейросетей.</p>';
      h += '<details><summary>Все ' + geo.items.length + ' пунктов (' + (geo.items.length - bad.length) + ' в порядке)</summary><ul class="a2-gl">' + geo.items.map(gli).join('') + '</ul></details></div>';
    }

    var lg = j.legal, mo = j.money;
    if (lg) {
      var it = lg.items || [lg];
      h += '<h3 class="a2-h">Юридические риски</h3><div class="cki cki2"><article class="ck-law' + (lg.ok ? ' ok' : '') + '"><span class="cks">Закон</span>' + it.map(function (x, n) {
        return '<div class="cklaw"><b>' + (it.length > 1 ? (n + 1) + '. ' : '') + esc(x.title) + '</b><p>' + esc(x.text) + '</p>' + (x.ev ? '<p class="ckev">' + esc(x.ev) + '</p>' : '') + '<p class="ckx"><i>Как исправить:</i> ' + esc(x.fix) + '</p></div>';
      }).join('') + (lg.fine ? '<p class="ckfine">Возможные штрафы по найденным пунктам: до <b>' + fmt(lg.fine, lg.region === 'ru' ? '₽' : '$') + '</b>.</p>' : '') + (lg.more ? '<p class="ckm">Ещё рисков на главной: ' + lg.more + '. Все разберём в полном аудите.</p>' : '') + '</article></div>';
    }
    if (mo) {
      h += '<h3 class="a2-h">Сколько денег вы теряете</h3><div class="cki cki2"><article class="ck-money"><span class="cks">Деньги</span><div class="ckcalc">' +
        '<label>Посетителей сайта в месяц<input id="a2v" type="number" min="0" inputmode="numeric" value="' + (mo.visits || 0) + '"></label>' +
        '<label>' + G().price + ', ' + esc(mo.cur) + '<input id="a2c" type="number" min="0" inputmode="numeric" value="' + (mo.check || 0) + '"></label>' +
        '<label>Сколько ещё клиентов в месяц вы можете взять<input id="a2p" type="number" min="0" inputmode="numeric" value="' + (mo.cap || 10) + '"></label></div><p id="a2mr"></p>' +
        '<p class="cknote">Как считали: посетители × ' + (mo.conv || 2) + '% заявок × доля потерь, но не больше клиентов, чем вы можете взять, × стоимость. Это ориентир, а не обещание: подставьте свои цифры.</p></article></div><p class="cksum" id="a2sum"></p>';
    }

    h += '<h3 class="a2-h">Вы и конкуренты</h3><div class="a2-cmp"><p style="margin:0;color:var(--soft,#b9b4ab)">Впишите сайты 1–3 конкурентов: сравним оценку, телефон, законы и нейросети в одной таблице.</p>' +
      '<form class="a2-cmpf" novalidate><input aria-label="Сайт конкурента 1" placeholder="конкурент1.ru"><input aria-label="Сайт конкурента 2" placeholder="конкурент2.ru"><input aria-label="Сайт конкурента 3" placeholder="конкурент3.ru"><button class="a2-btn gold" type="submit">Сравнить</button></form><div class="a2-tw" aria-live="polite"></div></div>';

    h += '<div class="a2-share"><a class="btn" href="#order" id="a2go">' + esc(G().cta) + '</a>' +
      '<button type="button" class="a2-btn" id="a2copy">Сохранить ссылку на отчёт</button>' +
      '<a class="a2-btn" id="a2tg" target="_blank" rel="noopener">Отправить в Telegram</a>' +
      '<a class="a2-btn" id="a2wa" target="_blank" rel="noopener">WhatsApp</a></div>';
    h += '</div>';
    out.innerHTML = h;

    // анимация оценки
    var arc = out.querySelector('.a2-arc'), num = out.querySelector('.a2-num');
    requestAnimationFrame(function () { arc.style.strokeDashoffset = (C * (1 - total / 100)).toFixed(1); });
    var k = 0, ti = setInterval(function () { k += Math.max(1, Math.round(total / 40)); if (k >= total) { k = total; clearInterval(ti); } num.textContent = k; }, 30);
    out.querySelectorAll('.cki article').forEach(function (a, i) { setTimeout(function () { a.classList.add('in'); }, 250 + i * 380); });

    // деньги
    var money = 0;
    if (mo) {
      var calc = function () {
        var v = +document.getElementById('a2v').value || 0, c = +document.getElementById('a2c').value || 0, pe = document.getElementById('a2p'), p = +pe.value;
        var n = v * (mo.conv || 2) / 100 * (mo.pct || 0) / 100; if (pe.value !== '' && p >= 0) n = Math.min(n, p);
        money = n * c; var nn = n < 10 ? Math.round(n * 10) / 10 : Math.round(n);
        document.getElementById('a2mr').innerHTML = 'Найденные ошибки стоят сайту около ' + (mo.pct || 0) + '% заявок: это примерно <b>' + String(nn).replace('.', ',') + '</b> ' + (nn % 1 ? 'клиента' : plural(nn, 'клиент', 'клиента', 'клиентов')) + ' в месяц. По вашей цене это <b>' + fmt(money, mo.cur) + '</b> в месяц и <b>' + fmt(money * 12, mo.cur) + '</b> в год, которые можно вернуть, исправив сайт.';
        document.getElementById('a2sum').innerHTML = 'Итог: сайт недополучает около <b>' + fmt(money, mo.cur) + '</b> в месяц' + (lg && lg.fine ? ' и рискует штрафами до <b>' + fmt(lg.fine, lg.region === 'ru' ? '₽' : '$') + '</b>' : '') + '.';
        share();
      };
      ['a2v', 'a2c', 'a2p'].forEach(function (id) { document.getElementById(id).addEventListener('input', calc); });
      calc();
    }

    // поделиться и сохранить
    var link = location.origin + location.pathname + '?audit=' + encodeURIComponent(url) + '&goal=' + goal + '#check';
    function share() {
      var txt = 'Проверка сайта ' + host + ': ' + total + ' из 100' + (gs != null ? ', нейросети ' + gs + '/100' : '') + (money ? ', теряет около ' + fmt(money, mo.cur) + ' в месяц' : '') + '.';
      document.getElementById('a2tg').href = 'https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(txt);
      document.getElementById('a2wa').href = 'https://wa.me/?text=' + encodeURIComponent(txt + ' ' + link);
      var msg = document.querySelector('#oform [name=message]');
      out._summary = txt + ' Цель: ' + G().label.toLowerCase() + '. Отчёт: ' + link;
      if (msg && (!msg.value || msg.dataset.a2)) { msg.value = out._summary; msg.dataset.a2 = '1'; }
    }
    share();
    try { history.replaceState(null, '', link); } catch (e) {}
    document.getElementById('a2copy').addEventListener('click', function () {
      var done = function () { toast('Ссылка на отчёт скопирована'); };
      if (navigator.clipboard) navigator.clipboard.writeText(link).then(done, function () { prompt('Скопируйте ссылку:', link); });
      else prompt('Скопируйте ссылку:', link);
    });
    document.getElementById('a2go').addEventListener('click', function () {
      var w = document.querySelector('#oform [name=website]'); if (w) w.value = url;
      var need = document.querySelector('#oform [name=need]'); if (need && need.options.length > 1) need.selectedIndex = 1;
      try { if (window.ym) window.ym(window.ZH_METRIKA || 0, 'reachGoal', 'audit_order'); } catch (e) {}
    });

    // конкуренты
    var cf = out.querySelector('.a2-cmpf'), tw = out.querySelector('.a2-tw');
    var me = { host: host, total: total, ss: ss, ls: ls, gs: gs, j: j, geo: geo };
    cf.addEventListener('submit', function (e) {
      e.preventDefault();
      var urls = [].map.call(cf.querySelectorAll('input'), function (x) { return x.value.trim(); }).filter(Boolean).slice(0, 3);
      if (!urls.length) { cf.querySelector('input').focus(); return; }
      var b = cf.querySelector('button'); b.disabled = true; tw.innerHTML = '<p class="ckl">Проверяем ' + urls.length + ' ' + plural(urls.length, 'сайт', 'сайта', 'сайтов') + ' конкурентов… это займёт до минуты.</p>';
      Promise.all(urls.map(function (cu) {
        return Promise.all([getJSON('/api/check?lang=ru&url=' + encodeURIComponent(cu)).catch(function () { return { error: 1 }; }), getJSON('/api/geo?lang=ru&url=' + encodeURIComponent(cu)).catch(function () { return null; })]).then(function (r) {
          var cj = r[0], cg = r[1] && !r[1].error ? r[1] : null;
          if (!cj || cj.error) return { host: cu.replace(/^https?:\/\//, '').replace(/\/$/, ''), fail: true };
          var a = siteScore(cj), l = lawScore(cj), g = cg ? cg.score : null;
          return { host: (cj.url || cu).replace(/^https?:\/\//, '').replace(/\/$/, ''), total: Math.round(g == null ? a * 0.8 + l * 0.2 : a * 0.6 + l * 0.15 + g * 0.25), ss: a, ls: l, gs: g, j: cj, geo: cg };
        });
      })).then(function (rows) { b.disabled = false; tw.innerHTML = table([me].concat(rows)); try { if (window.ym) window.ym(window.ZH_METRIKA || 0, 'reachGoal', 'audit_compare'); } catch (e2) {} });
    });
  }

  function mini(label, v) { return '<span>' + label + '</span><u><i style="width:' + v + '%;background:' + color(v) + '"></i></u><span>' + v + '</span>'; }
  function gli(x) { return '<li><span class="' + (x.ok ? 'a2-ok' : 'a2-no') + '" aria-hidden="true">' + (x.ok ? '✓' : '✕') + '</span><span><b>' + esc(x.title) + '</b>' + (x.ok ? '' : '<em>' + esc(x.text) + (x.fix ? ' ' + esc(x.fix) : '') + '</em>') + '</span></li>'; }

  function table(rows) {
    var gi = function (r, id) { if (!r.geo) return null; var x = r.geo.items.filter(function (i) { return i.id === id; })[0]; return x ? x.ok : null; };
    var yn = function (v) { return v == null ? '<span style="color:var(--mute,#85827b)">?</span>' : (v ? '<span class="a2-ok">есть</span>' : '<span class="a2-no">нет</span>'); };
    var crit = function (r) { return (r.j.issues || []).filter(function (x) { return x.sev === 'c' || x.sev === 'i'; }).length; };
    var lines = [
      ['Общая оценка', function (r) { return r.total; }, 'max'],
      ['Сайт и телефон', function (r) { return r.ss; }, 'max'],
      ['Серьёзных ошибок', function (r) { return crit(r); }, 'min'],
      ['Законы', function (r) { return r.ls; }, 'max'],
      ['Нейросети', function (r) { return r.gs == null ? '?' : r.gs; }, 'max'],
      ['Телефон текстом', function (r) { return yn(gi(r, 'nap')); }],
      ['Вопрос-ответ', function (r) { return yn(gi(r, 'faq')); }],
      ['Отзывы', function (r) { return yn(gi(r, 'reviews')); }],
      ['Яндекс Карты', function (r) { return yn(gi(r, 'map')); }]
    ];
    var ok = rows.filter(function (r) { return !r.fail; });
    var h = '<table class="a2-t"><thead><tr><th scope="col"></th>' + rows.map(function (r, i) { return '<th scope="col"' + (i === 0 ? ' class="me"' : '') + '>' + esc(r.host) + (i === 0 ? ' (вы)' : '') + '</th>'; }).join('') + '</tr></thead><tbody>';
    lines.forEach(function (l) {
      var vals = rows.map(function (r) { return r.fail ? null : l[1](r); });
      var nums = vals.filter(function (v) { return typeof v === 'number'; });
      var best = l[2] && nums.length > 1 ? (l[2] === 'max' ? Math.max.apply(null, nums) : Math.min.apply(null, nums)) : null;
      h += '<tr><th scope="row">' + l[0] + '</th>' + vals.map(function (v, i) { return '<td' + (i === 0 ? ' class="me"' : '') + '>' + (v == null ? '<span style="color:var(--mute,#85827b)">не открылся</span>' : '<span' + (best != null && v === best ? ' class="best"' : '') + '>' + v + '</span>') + '</td>'; }).join('') + '</tr>';
    });
    h += '</tbody></table>';
    var me = rows[0], better = ok.slice(1).filter(function (r) { return r.total > me.total; }).length;
    h += '<p class="a2-note">' + (ok.length < 2 ? 'Сайты конкурентов не открылись для проверки.' : better ? 'Сайты ' + better + ' ' + plural(better, 'конкурента', 'конкурентов', 'конкурентов') + ' оценены выше вашего: клиенты, сравнивая, чаще выбирают их. Полный аудит покажет, в чём именно они сильнее.' : 'Ваш сайт оценён не ниже конкурентов. Значит, исправив найденное, вы будете заметно впереди.') + ' Оценка автоматическая, по главной странице.</p>';
    return h;
  }

  /* ---------- открыли ссылку на отчёт: проверка запускается сама ---------- */
  var qs = new URLSearchParams(location.search), au = qs.get('audit');
  if (au) {
    var g0 = qs.get('goal'); if (g0) { var b0 = gw.querySelector('[data-g="' + g0 + '"]'); if (b0) b0.click(); }
    inp.value = au;
    var sec = document.getElementById('check'); if (sec && sec.scrollIntoView) setTimeout(function () { sec.scrollIntoView({ block: 'start' }); }, 300);
    setTimeout(function () { run(au); }, 500);
  }
})();
