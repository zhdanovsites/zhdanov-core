// Cloudflare Pages Function: POST /api/lead
// Свой обработчик заявок Zhdanov Sites вместо formsubmit.co.
// Принимает FormData или JSON, проверяет Turnstile, отправляет заявку Алексею в Telegram
// и (если подключено) письмом через Cloudflare Email Routing. Сторонний сервис форм не нужен.
//
// Переменные (Cloudflare Pages → Settings → Variables, Secret):
//   TG_BOT_TOKEN, TG_NOTIFY   уже есть (бот аудита и чат Алексея), заявки идут туда же
//   TURNSTILE_SECRET          уже есть; если токен капчи пришёл, он проверяется здесь же
//   LEAD_TG_TOKEN, LEAD_TG_CHAT   необязательно: отдельный бот или чат только для заявок
// Необязательно, письмо на почту:
//   привязка Send Email с именем SEND_EMAIL (Settings → Bindings → Send Email),
//   переменные LEAD_MAIL_FROM (например leads@zhdanovsites.com, домен на Cloudflare с Email Routing)
//   и LEAD_MAIL_TO (zhdanov.official@gmail.com, адрес подтверждён в Email Routing).
//
// Ответ: {"ok":true} или {"ok":false,"error":"…"}. Zhdanov Sites, 2026.

const FIELDS = {
  name: 'Имя', contact: 'Контакт', phone: 'Телефон', email: 'Почта', website: 'Сайт',
  need: 'Что нужно', audit_language: 'Язык аудита', message: 'Сообщение', style: 'Стиль',
  goal: 'Цель', tour: 'Тур', kind: 'Тип', via: 'Как связаться', page: 'Страница', _subject: 'Тема',
};
const MAX = 2000;

function esc(s) { return String(s).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }
function j(o, status = 200) { return new Response(JSON.stringify(o), { status, headers: { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' } }); }

async function readBody(request) {
  const ct = request.headers.get('content-type') || '';
  if (ct.includes('application/json')) return (await request.json().catch(() => ({}))) || {};
  const fd = await request.formData().catch(() => null);
  const o = {};
  if (fd) for (const [k, v] of fd.entries()) if (typeof v === 'string') o[k] = v;
  return o;
}

async function turnstileOk(env, token, ip) {
  if (!token || !env.TURNSTILE_SECRET) return null; // null = не проверяли
  const r = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', {
    method: 'POST', body: new URLSearchParams({ secret: env.TURNSTILE_SECRET, response: token, remoteip: ip || '' }),
  }).then((x) => x.json()).catch(() => ({}));
  return !!r.success;
}

async function tooMany(ip) {
  // не больше 5 заявок с одного адреса за 10 минут
  try {
    const c = caches.default, key = new Request('https://lead-rate.local/' + encodeURIComponent(ip || 'x'));
    const hit = await c.match(key);
    const n = hit ? parseInt(await hit.text(), 10) || 0 : 0;
    if (n >= 5) return true;
    await c.put(key, new Response(String(n + 1), { headers: { 'cache-control': 'max-age=600' } }));
  } catch (e) {}
  return false;
}

function b64(s) { return btoa(unescape(encodeURIComponent(s))); }

async function sendMail(env, subject, text) {
  if (!env.SEND_EMAIL || !env.LEAD_MAIL_FROM || !env.LEAD_MAIL_TO) return false;
  const { EmailMessage } = await import('cloudflare:email');
  const raw = [
    'From: Zhdanov Sites <' + env.LEAD_MAIL_FROM + '>',
    'To: ' + env.LEAD_MAIL_TO,
    'Subject: =?UTF-8?B?' + b64(subject) + '?=',
    'Message-ID: <' + crypto.randomUUID() + '@' + env.LEAD_MAIL_FROM.split('@')[1] + '>',
    'Date: ' + new Date().toUTCString(),
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    '',
    b64(text).replace(/.{76}/g, '$&\r\n'),
  ].join('\r\n');
  await env.SEND_EMAIL.send(new EmailMessage(env.LEAD_MAIL_FROM, env.LEAD_MAIL_TO, raw));
  return true;
}

export async function onRequestPost({ request, env }) {
  const ip = request.headers.get('cf-connecting-ip') || '';
  const d = await readBody(request);
  if (d._honey) return j({ ok: true }); // бот заполнил скрытое поле: молча «принимаем»
  const has = (k) => d[k] && String(d[k]).trim();
  if (!has('name') && !has('contact') && !has('phone') && !has('email')) return j({ ok: false, error: 'Пустая заявка' }, 400);
  if (d.consent === undefined && d.agree === undefined) return j({ ok: false, error: 'Нет согласия на обработку данных' }, 400);
  if (await tooMany(ip)) return j({ ok: false, error: 'Слишком много заявок, попробуйте через 10 минут' }, 429);

  const ts = await turnstileOk(env, d['cf-turnstile-response'] || d.t, ip);
  if (ts === false) return j({ ok: false, error: 'Проверка «не робот» не прошла' }, 403);

  const site = new URL(request.url).host;
  const lines = [];
  for (const k of Object.keys(FIELDS)) if (has(k)) lines.push(FIELDS[k] + ': ' + String(d[k]).trim().slice(0, MAX));
  const ref = request.headers.get('referer');
  if (!has('page') && ref) lines.push('Страница: ' + ref);
  lines.push('Согласие на обработку данных: да');
  if (ts === null) lines.push('⚠ без проверки «не робот»');
  const subject = 'Заявка с ' + site + (has('name') ? ': ' + String(d.name).trim().slice(0, 60) : '');

  const tok = env.LEAD_TG_TOKEN || env.TG_BOT_TOKEN, chat = env.LEAD_TG_CHAT || env.TG_NOTIFY;
  let sent = false;
  if (tok && chat) {
    const r = await fetch('https://api.telegram.org/bot' + tok + '/sendMessage', {
      method: 'POST', headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ chat_id: chat, parse_mode: 'HTML', disable_web_page_preview: true, text: '📩 <b>' + esc(subject) + '</b>\n\n' + esc(lines.join('\n')) }),
    }).then((x) => x.json()).catch(() => ({}));
    sent = !!r.ok;
  }
  try { if (await sendMail(env, subject, lines.join('\n'))) sent = true; } catch (e) {}
  return sent ? j({ ok: true }) : j({ ok: false, error: 'Не получилось отправить' }, 502);
}

export function onRequestGet() { return j({ ok: false, error: 'Только POST' }, 405); }
