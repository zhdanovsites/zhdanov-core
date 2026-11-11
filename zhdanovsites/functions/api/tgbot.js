// Cloudflare Pages Function: /api/tgbot
// Telegram-бот аудита Zhdanov Sites. Человек присылает адрес сайта, бот отвечает:
// оценка 0..100, три главные проблемы, сколько денег может теряться, ссылка на полный аудит.
//
// Переменные окружения (Cloudflare Pages → Settings → Variables, тип Secret):
//   TG_BOT_TOKEN  токен бота от @BotFather (обязательно)
//   TG_SECRET     любая длинная строка, защищает адрес бота (обязательно)
//   TG_NOTIFY     chat_id студии: сюда приходит «кто что проверил» (необязательно)
//
// Глубокая ссылка: https://t.me/zhdanov_audit_bot?start=<base64url адреса без https://, до 64 знаков>,
//   например JS: btoa(unescape(encodeURIComponent(host))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')
//
// Подключение (один раз): открыть в браузере
//   https://zhdanovsites.com/api/tgbot?setup=<TG_SECRET>
// Функция сама зарегистрирует адрес у Telegram. Ответ {"ok":true} значит готово.
//
// Использует уже работающие /api/check и /api/geo этого же сайта. Zhdanov Sites, 2026.

const SITE = 'https://zhdanovsites.com';
const CONTACT = 'https://t.me/ZhdanovOfficial';
const SEV = { c: 18, i: 10, l: 4, t: 0 };
const SEV_ICON = { c: '🔴', i: '🟠', l: '🟡', t: '⚪' };

function siteScore(j) {
  let s = 100;
  (j.issues || []).forEach((x) => { s -= SEV[x.sev] || 0; });
  const it = legalItems(j);
  s -= Math.min(24, it.length * 6);
  const sp = j.speed && typeof j.speed === 'object' ? (j.speed.mobile || j.speed.score) : j.speed;
  if (typeof sp === 'number') s -= sp < 50 ? 12 : sp < 90 ? 5 : 0;
  return Math.max(5, Math.min(100, Math.round(s)));
}
function legalItems(j) { return j.legal ? (j.legal.items || (j.legal.ok ? [] : [j.legal])) : []; }
function lawScore(j) { return Math.max(0, 100 - legalItems(j).length * 25); }

export function normUrl(t) {
  const m = String(t || '').match(/(https?:\/\/)?([a-zа-яё0-9-]+\.)+[a-zа-яё]{2,}(\/[^\s]*)?/i);
  if (!m) return '';
  let u = m[0];
  if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
  try { const x = new URL(u); return x.origin + (x.pathname === '/' ? '/' : x.pathname); } catch (e) { return ''; }
}
function esc(s) { return String(s == null ? '' : s).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }
function money(n) { return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
function verdict(n) { return n >= 85 ? 'хорошо' : n >= 65 ? 'есть что улучшить' : n >= 45 ? 'заметные проблемы' : 'сайт теряет клиентов'; }

export function buildReport(url, chk, geo) {
  const ss = chk ? siteScore(chk) : null;
  const ls = chk ? lawScore(chk) : null;
  const gs = geo && typeof geo.score === 'number' ? geo.score : null;
  let total;
  if (ss != null && gs != null) total = Math.round(0.6 * ss + 0.15 * ls + 0.25 * gs);
  else if (ss != null) total = Math.round(0.8 * ss + 0.2 * ls);
  else total = gs;
  const host = url.replace(/^https?:\/\//, '').replace(/\/$/, '');
  const lines = [];
  lines.push('<b>' + esc(host) + '</b>');
  lines.push('Оценка: <b>' + total + ' из 100</b> (' + verdict(total) + ')');
  const parts = [];
  if (ss != null) parts.push('сайт ' + ss);
  if (gs != null) parts.push('нейросети ' + gs);
  if (ls != null) parts.push('законы ' + ls);
  if (parts.length) lines.push(esc(parts.join(' · ')));

  // три главные проблемы: сначала критичные из проверки, потом законы, потом нейросети
  const top = [];
  const order = { c: 0, i: 1, l: 2, t: 3 };
  (chk && chk.issues ? chk.issues.slice() : []).sort((a, b) => (order[a.sev] ?? 9) - (order[b.sev] ?? 9))
    .forEach((x) => { if (top.length < 2 && x.sev !== 't') top.push(SEV_ICON[x.sev] + ' ' + esc(x.title)); });
  legalItems(chk || {}).forEach((x) => { if (top.length < 3 && x.title) top.push('⚖️ ' + esc(x.title)); });
  if (geo && geo.items) {
    (geo.top || []).forEach((id) => {
      const it = geo.items.find((i) => i.id === id);
      if (it && top.length < 3) top.push('🤖 ' + esc(it.title));
    });
  }
  if (top.length) { lines.push(''); lines.push('<b>Главное, что мешает:</b>'); top.slice(0, 3).forEach((t, i) => lines.push((i + 1) + '. ' + t)); }

  const lg = chk && chk.legal;
  if (lg && lg.fine && legalItems(chk).length) { lines.push(''); lines.push('Возможные штрафы: до ' + esc(typeof lg.fine === 'number' ? money(lg.fine) + ' ₽' : lg.fine)); }
  const m = chk && chk.money;
  if (m && m.visits && m.check && m.conv && m.pct) {
    let n = m.visits * (m.conv / 100) * (m.pct / 100); // клиентов в месяц, как в калькуляторе на сайте
    if (m.cap) n = Math.min(n, m.cap);
    const lost = n * m.check;
    if (lost > 0) lines.push('Сайт может недополучать около ' + money(lost) + ' ' + esc(m.cur || '₽') + ' в месяц (оценка для 1 000 посещений и среднего чека ' + money(m.check) + ' ' + esc(m.cur || '₽') + ').');
  }
  lines.push('');
  lines.push('Полный отчёт с объяснениями и сравнением с конкурентами: по кнопке ниже.');
  return { text: lines.join('\n'), total, site: ss, geo: gs, law: ls, top: top.slice(0, 3).map((t) => t.replace(/<[^>]+>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&')) };
}

async function tg(env, method, body) {
  const r = await fetch('https://api.telegram.org/bot' + env.TG_BOT_TOKEN + '/' + method, {
    method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(body),
  });
  return r.json().catch(() => ({}));
}
async function getJson(u, ms) {
  const c = new AbortController(); const t = setTimeout(() => c.abort(), ms);
  try { const r = await fetch(u, { signal: c.signal, headers: { accept: 'application/json' } }); return r.ok ? await r.json() : null; }
  catch (e) { return null; } finally { clearTimeout(t); }
}

const HELLO = 'Здравствуйте! Я бесплатно проверю ваш сайт за минуту.\n\n' +
  'Пришлите адрес, например <b>mysite.ru</b>.\n\n' +
  'Проверю: скорость на телефоне, ошибки, требования законов РФ (152-ФЗ, куки, реклама) и видят ли сайт Алиса и нейросети. ' +
  'В ответ: оценка из 100 и три главные проблемы.';

async function handle(update, env, origin) {
  const msg = update.message || update.edited_message;
  if (!msg || !msg.chat) return;
  const chat = msg.chat.id;
  const text = (msg.text || msg.caption || '').trim();
  // глубокая ссылка с сайта: t.me/zhdanov_audit_bot?start=<адрес в base64url> → сразу проверяем
  const sp = text.match(/^\/start\s+([A-Za-z0-9_-]{4,64})$/);
  let fromStart = '';
  if (sp) { try { fromStart = normUrl(decodeURIComponent(escape(atob(sp[1].replace(/-/g, '+').replace(/_/g, '/'))))); } catch (e) {} }
  if (!fromStart && (!text || /^\/(start|help)/i.test(text))) { await tg(env, 'sendMessage', { chat_id: chat, text: HELLO, parse_mode: 'HTML' }); return; }
  const url = fromStart || normUrl(text);
  if (!url) { await tg(env, 'sendMessage', { chat_id: chat, text: 'Не вижу адреса сайта. Пришлите его так: <b>mysite.ru</b>', parse_mode: 'HTML' }); return; }

  await tg(env, 'sendChatAction', { chat_id: chat, action: 'typing' });
  const wait = await tg(env, 'sendMessage', { chat_id: chat, text: '⏳ Проверяю ' + url + ' … обычно 20–40 секунд.', disable_web_page_preview: true });

  const q = encodeURIComponent(url);
  const [chk, geo] = await Promise.all([
    getJson(origin + '/api/check?lang=ru&url=' + q, 55000),
    getJson(origin + '/api/geo?lang=ru&url=' + q, 25000),
  ]);
  if (wait && wait.result) await tg(env, 'deleteMessage', { chat_id: chat, message_id: wait.result.message_id });

  if (!chk && !geo) {
    await tg(env, 'sendMessage', { chat_id: chat, text: 'Не получилось открыть ' + url + '. Проверьте адрес или попробуйте через минуту. Если сайт открывается у вас, а у нас нет, напишите Алексею, посмотрим вручную.', disable_web_page_preview: true,
      reply_markup: { inline_keyboard: [[{ text: 'Написать Алексею', url: CONTACT }]] } });
    return;
  }
  const rep = buildReport(url, chk, geo);
  const full = SITE + '/?audit=' + q + '&utm_source=telegram&utm_medium=bot#check';
  await tg(env, 'sendMessage', {
    chat_id: chat, text: rep.text, parse_mode: 'HTML', disable_web_page_preview: true,
    reply_markup: { inline_keyboard: [
      [{ text: 'Полный отчёт', url: full }],
      [{ text: 'Обсудить с Алексеем', url: CONTACT }, { text: 'Подобрать стиль', url: SITE + '/style/' }],
    ] },
  });
  if (env.TG_NOTIFY) {
    const who = msg.from ? ((msg.from.username ? '@' + msg.from.username : '') + ' ' + (msg.from.first_name || '')).trim() : '';
    await tg(env, 'sendMessage', { chat_id: env.TG_NOTIFY, text: '🔎 Бот аудита: ' + url + ' → ' + rep.total + '/100' + (who ? '\nКто: ' + who : ''), disable_web_page_preview: true });
  }
}

export async function onRequestPost(context) {
  const { request, env } = context;
  if (!env.TG_BOT_TOKEN || !env.TG_SECRET) return new Response('not configured', { status: 503 });
  if (request.headers.get('X-Telegram-Bot-Api-Secret-Token') !== env.TG_SECRET) return new Response('forbidden', { status: 403 });
  const update = await request.json().catch(() => null);
  if (update) context.waitUntil(handle(update, env, new URL(request.url).origin).catch(() => {}));
  return new Response('ok'); // Telegram получает ответ сразу, проверка идёт в фоне
}

export async function onRequestGet(context) {
  const { request, env } = context;
  const u = new URL(request.url);
  if (!env.TG_BOT_TOKEN || !env.TG_SECRET) return Response.json({ ok: false, error: 'Нет TG_BOT_TOKEN или TG_SECRET в настройках Cloudflare' }, { status: 503 });
  if (u.searchParams.get('setup') !== env.TG_SECRET) return new Response('Бот аудита Zhdanov Sites', { status: 200 });
  const r = await tg(env, 'setWebhook', {
    url: u.origin + '/api/tgbot', secret_token: env.TG_SECRET, allowed_updates: ['message', 'edited_message'], drop_pending_updates: true,
  });
  await tg(env, 'setMyCommands', { commands: [{ command: 'start', description: 'Как проверить сайт' }] });
  await tg(env, 'setMyDescription', { description: 'Бесплатная проверка сайта за минуту: скорость, ошибки, законы РФ, видят ли вас Алиса и нейросети. Пришлите адрес сайта.' });
  return Response.json(r);
}
