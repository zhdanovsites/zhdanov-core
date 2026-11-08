/* Замечания клиента на сайте. Конфиг: window.zhFb {api, nonce, name, manage, admin} */
(function () {
	'use strict';
	var C = window.zhFb;
	if (!C || window.zhFbLoaded) return;
	window.zhFbLoaded = true;

	var css = '' +
		'.zhfb,.zhfb *{box-sizing:border-box;font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;letter-spacing:0;text-transform:none}' +
		'.zhfb-bar{position:fixed;left:16px;bottom:16px;z-index:2147483600;display:flex;gap:8px;align-items:center}' +
		'.zhfb-btn{appearance:none;border:0;border-radius:999px;padding:10px 16px;background:#1d2327;color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);font-weight:600}' +
		'.zhfb-btn:focus-visible{outline:3px solid #72aee6;outline-offset:2px}' +
		'.zhfb-btn--light{background:#fff;color:#1d2327}' +
		'.zhfb-btn--on{background:#d63638}' +
		'.zhfb-count{display:inline-block;min-width:20px;padding:0 6px;margin-left:6px;border-radius:10px;background:#d63638;color:#fff;font-size:12px;text-align:center}' +
		'.zhfb-hint{position:fixed;left:50%;top:12px;transform:translateX(-50%);z-index:2147483600;background:#1d2327;color:#fff;padding:8px 14px;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.25)}' +
		'.zhfb-hl{position:absolute;z-index:2147483500;pointer-events:none;border:2px solid #d63638;background:rgba(214,54,56,.08);border-radius:3px;transition:all .05s}' +
		'.zhfb-pin{position:absolute;z-index:2147483550;width:28px;height:28px;margin:-14px 0 0 -14px;border-radius:50%;background:#d63638;color:#fff;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);cursor:pointer;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;padding:0}' +
		'.zhfb-pin--work{background:#dba617}.zhfb-pin--done{background:#00a32a}' +
		'.zhfb-pop{position:absolute;z-index:2147483601;width:300px;max-width:calc(100vw - 24px);background:#fff;color:#1d2327;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.3);padding:12px}' +
		'.zhfb-pop textarea,.zhfb-pop input{width:100%;border:1px solid #c3c4c7;border-radius:6px;padding:8px;margin:0 0 8px;color:#1d2327;background:#fff}' +
		'.zhfb-pop textarea{min-height:90px;resize:vertical}' +
		'.zhfb-row{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}' +
		'.zhfb-small{font-size:12px;color:#646970;margin:0 0 6px}' +
		'.zhfb-pop .zhfb-btn{box-shadow:none;padding:8px 12px}' +
		'.zhfb-toast{position:fixed;left:16px;bottom:70px;z-index:2147483602;background:#00a32a;color:#fff;padding:10px 14px;border-radius:8px}' +
		'.zhfb-mode, .zhfb-mode *{cursor:crosshair!important}' +
		'@media (max-width:480px){.zhfb-bar{left:10px;bottom:10px;right:10px;flex-wrap:wrap;gap:6px}.zhfb-bar .zhfb-btn{padding:9px 12px;font-size:13px}.zhfb-toast{left:10px;bottom:64px}}';
	var st = document.createElement('style');
	st.textContent = css;
	document.head.appendChild(st);

	function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function api(path, opts) {
		opts = opts || {};
		var h = { 'Content-Type': 'application/json' };
		if (C.nonce) h['X-WP-Nonce'] = C.nonce;
		return fetch(C.api + (path || ''), { method: opts.method || 'GET', headers: h, credentials: 'same-origin', body: opts.data ? JSON.stringify(opts.data) : undefined })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j && j.message ? j.message : 'Ошибка ' + r.status); return j; }); });
	}
	function toast(t, bad) { var d = el('div', 'zhfb zhfb-toast', esc(t)); if (bad) d.style.background = '#d63638'; document.body.appendChild(d); setTimeout(function () { d.remove(); }, 3500); }
	function device() { var w = window.innerWidth; return (w < 768 ? 'Телефон' : w < 1100 ? 'Планшет' : 'Компьютер') + ', ' + w + 'px'; }
	function isOurs(n) { return n && n.closest && n.closest('.zhfb'); }
	function selector(n) {
		var parts = [];
		while (n && n.nodeType === 1 && n !== document.body && parts.length < 12) {
			if (n.id && /^[A-Za-z][\w-]*$/.test(n.id)) { parts.unshift('#' + n.id); break; }
			var i = 1, s = n;
			while ((s = s.previousElementSibling)) if (s.tagName === n.tagName) i++;
			parts.unshift(n.tagName.toLowerCase() + ':nth-of-type(' + i + ')');
			n = n.parentElement;
		}
		if (parts.length && parts[0].charAt(0) !== '#') parts.unshift('body');
		return parts.join(' > ');
	}
	function snippet(n) {
		var t = (n.getAttribute && (n.getAttribute('alt') || n.getAttribute('aria-label'))) || n.innerText || '';
		if (!t && n.tagName === 'IMG') t = 'фото ' + (n.getAttribute('src') || '').split('/').pop();
		return String(t).replace(/\s+/g, ' ').trim().slice(0, 100);
	}
	function pageKey() { return location.pathname; }
	var who = C.name || '';
	try { who = who || localStorage.getItem('zhfb_who') || ''; } catch (e) {}

	/* Панель */
	var bar = el('div', 'zhfb zhfb-bar');
	var bNote = el('button', 'zhfb-btn', '✎ Замечание<span class="zhfb-count" hidden></span>');
	bNote.type = 'button'; bNote.setAttribute('aria-label', 'Оставить замечание к этой странице');
	var bOk = el('button', 'zhfb-btn zhfb-btn--light', '✓ Согласовать');
	bOk.type = 'button'; bOk.title = 'Страница устраивает, можно публиковать';
	var bHide = el('button', 'zhfb-btn zhfb-btn--light', '👁');
	bHide.type = 'button'; bHide.title = 'Скрыть или показать метки замечаний'; bHide.setAttribute('aria-label', 'Скрыть или показать метки замечаний');
	bar.appendChild(bNote); bar.appendChild(bOk); bar.appendChild(bHide);
	if (C.manage) { var a = el('a', 'zhfb-btn zhfb-btn--light', 'Все'); a.href = C.admin; a.title = 'Список замечаний в админке'; a.style.textDecoration = 'none'; bar.appendChild(a); }
	document.body.appendChild(bar);

	var mode = false, hl = null, hint = null, pop = null, notes = [], pins = [], pinsOn = true;

	function closePop() { if (pop) { pop.remove(); pop = null; } }
	function placePop(x, y) {
		var w = 300, sx = window.scrollX, vw = document.documentElement.clientWidth;
		var left = Math.min(Math.max(sx + 12, x + 10), sx + vw - w - 12);
		pop.style.left = left + 'px'; pop.style.top = (y + 14) + 'px';
	}
	function startMode() {
		mode = true; bNote.classList.add('zhfb-btn--on'); bNote.firstChild.nodeValue = '✕ Отмена';
		document.documentElement.classList.add('zhfb-mode');
		hl = el('div', 'zhfb zhfb-hl'); document.body.appendChild(hl);
		hint = el('div', 'zhfb zhfb-hint', 'Кликните по месту, которое нужно поменять. Esc: отмена'); document.body.appendChild(hint);
	}
	function stopMode() {
		mode = false; bNote.classList.remove('zhfb-btn--on'); bNote.firstChild.nodeValue = '✎ Замечание';
		document.documentElement.classList.remove('zhfb-mode');
		if (hl) { hl.remove(); hl = null; } if (hint) { hint.remove(); hint = null; }
	}
	bNote.addEventListener('click', function () { closePop(); mode ? stopMode() : startMode(); });

	document.addEventListener('mousemove', function (e) {
		if (!mode || !hl) return;
		var t = e.target; if (isOurs(t)) { hl.style.display = 'none'; return; }
		var r = t.getBoundingClientRect(); hl.style.display = 'block';
		hl.style.left = (r.left + window.scrollX) + 'px'; hl.style.top = (r.top + window.scrollY) + 'px';
		hl.style.width = r.width + 'px'; hl.style.height = r.height + 'px';
	}, true);

	document.addEventListener('click', function (e) {
		if (!mode || isOurs(e.target)) return;
		e.preventDefault(); e.stopPropagation();
		var t = e.target, r = t.getBoundingClientRect();
		var data = {
			sel: selector(t), snip: snippet(t),
			x: r.width ? Math.round((e.clientX - r.left) / r.width * 1000) / 10 : 50,
			y: r.height ? Math.round((e.clientY - r.top) / r.height * 1000) / 10 : 50
		};
		stopMode();
		askText(e.pageX, e.pageY, data);
	}, true);

	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { stopMode(); closePop(); } });

	function askText(x, y, data, approve) {
		closePop();
		pop = el('div', 'zhfb zhfb-pop');
		pop.setAttribute('role', 'dialog');
		pop.innerHTML = (approve ? '<p class="zhfb-small"><b>Согласовать страницу</b>: она вас устраивает. Можно добавить комментарий.</p>' : (data.snip ? '<p class="zhfb-small">Где: «' + esc(data.snip) + '»</p>' : '')) +
			'<textarea aria-label="Текст замечания" placeholder="' + (approve ? 'Комментарий (необязательно)' : 'Что поменять? Например: заменить фото, исправить цену, другой текст') + '"></textarea>' +
			(C.name ? '' : '<input type="text" aria-label="Ваше имя" placeholder="Ваше имя" value="' + esc(who) + '">') +
			'<div class="zhfb-row"><button type="button" class="zhfb-btn zhfb-btn--light">Отмена</button><button type="button" class="zhfb-btn">' + (approve ? 'Согласовать' : 'Отправить') + '</button></div>';
		document.body.appendChild(pop); placePop(x, y);
		var ta = pop.querySelector('textarea'), nm = pop.querySelector('input'), bs = pop.querySelectorAll('.zhfb-row button');
		ta.focus();
		bs[0].onclick = closePop;
		bs[1].onclick = function () {
			var txt = ta.value.trim();
			if (!approve && !txt) { ta.focus(); ta.style.borderColor = '#d63638'; return; }
			if (nm) { who = nm.value.trim(); try { localStorage.setItem('zhfb_who', who); } catch (e) {} }
			bs[1].disabled = true; bs[1].textContent = 'Отправляю…';
			api('', { method: 'POST', data: Object.assign({ page: pageKey(), text: txt, who: who, dev: device(), kind: approve ? 'approve' : 'note' }, data || {}) })
				.then(function (n) { closePop(); toast(approve ? 'Спасибо! Страница согласована.' : 'Замечание отправлено. Спасибо!'); notes.push(n); drawPins(); })
				.catch(function (err) { bs[1].disabled = false; bs[1].textContent = 'Отправить'; toast(err.message, true); });
		};
	}
	bOk.addEventListener('click', function () { stopMode(); var r = bOk.getBoundingClientRect(); askText(r.left + window.scrollX, r.top + window.scrollY - 260, {}, true); });

	/* Метки */
	function target(n) { try { return n.sel ? document.querySelector(n.sel) : null; } catch (e) { return null; } }
	function drawPins() {
		pins.forEach(function (p) { p.remove(); }); pins = [];
		var open = 0, k = 0;
		notes.forEach(function (n) {
			if (n.kind !== 'note') return;
			k++;
			if (n.status !== 'done') open++;
			if (!pinsOn || (n.status === 'done' && !C.manage)) return;
			var t = target(n), x, y;
			if (t && t.getClientRects().length) { var r = t.getBoundingClientRect(); x = r.left + window.scrollX + r.width * n.x / 100; y = r.top + window.scrollY + r.height * n.y / 100; }
			else { x = 40; y = 80 + k * 34 + window.scrollY * 0; }
			var p = el('button', 'zhfb zhfb-pin' + (n.status !== 'new' ? ' zhfb-pin--' + n.status : ''), String(k));
			p.type = 'button'; p.id = 'zh-note-' + n.id; p.setAttribute('aria-label', 'Замечание ' + k + ': ' + n.text);
			p.style.left = x + 'px'; p.style.top = y + 'px';
			p.addEventListener('click', function (e) { e.stopPropagation(); showNote(n, k, x, y); });
			document.body.appendChild(p); pins.push(p);
		});
		var c = bNote.querySelector('.zhfb-count');
		c.hidden = !open; c.textContent = open;
	}
	var labels = { 'new': 'Новое', work: 'В работе', done: 'Готово' };
	function showNote(n, k, x, y) {
		closePop();
		pop = el('div', 'zhfb zhfb-pop');
		pop.innerHTML = '<p class="zhfb-small">№' + k + ' · ' + esc(n.who) + ' · ' + esc(n.date) + ' · <b>' + labels[n.status] + '</b></p><p style="margin:0 0 10px;white-space:pre-wrap">' + esc(n.text) + '</p>' +
			'<div class="zhfb-row">' + (C.manage ? '<button type="button" class="zhfb-btn zhfb-btn--light" data-s="work">В работе</button><button type="button" class="zhfb-btn" data-s="done">Готово</button>' : '') + '<button type="button" class="zhfb-btn zhfb-btn--light" data-s="">Закрыть</button></div>';
		document.body.appendChild(pop); placePop(x, y);
		pop.querySelectorAll('button').forEach(function (b) {
			b.onclick = function () {
				if (!b.dataset.s) return closePop();
				api('/' + n.id, { method: 'POST', data: { status: b.dataset.s } }).then(function (m) { n.status = m.status; closePop(); drawPins(); }).catch(function (e) { toast(e.message, true); });
			};
		});
	}
	bHide.addEventListener('click', function () { pinsOn = !pinsOn; drawPins(); });
	var tm;
	window.addEventListener('resize', function () { clearTimeout(tm); tm = setTimeout(drawPins, 200); });
	window.addEventListener('load', drawPins);

	api('?page=' + encodeURIComponent(pageKey())).then(function (list) {
		notes = list || []; drawPins();
		var m = location.hash.match(/^#zh-note-(\d+)/);
		if (m) { var p = document.getElementById('zh-note-' + m[1]); if (p) { p.scrollIntoView({ block: 'center' }); setTimeout(function () { p.click(); }, 400); } }
	}).catch(function () {});
})();
