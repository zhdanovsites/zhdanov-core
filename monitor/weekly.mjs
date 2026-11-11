// Еженедельный отчёт по сайтам: оценка 0..100, главная проблема, сравнение с прошлой неделей.
// Запускает .github/workflows/weekly.yml. Использует /api/check и /api/geo на zhdanovsites.com
// и тот же подсчёт оценки, что бот @zhdanov_audit_bot (zhdanovsites/functions/api/tgbot.js).
import fs from 'node:fs';
import { buildReport } from '/tmp/tgbot.mjs';

const API = process.env.API || 'https://zhdanovsites.com';
const LIST = process.env.SITES_WEEKLY || fs.readFileSync('monitor/weekly.txt', 'utf8');
const LAST = 'monitor/weekly-last.json';
const last = fs.existsSync(LAST) ? JSON.parse(fs.readFileSync(LAST, 'utf8')) : {};
const sites = LIST.split('\n').map((l) => l.trim()).filter((l) => l && !l.startsWith('#'))
  .map((l) => { const [u, n] = l.split('|').map((x) => x.trim()); return { url: u, name: n || u.replace(/^https?:\/\//, '').replace(/\/$/, '') }; });

async function get(u, ms) {
  const c = new AbortController(); const t = setTimeout(() => c.abort(), ms);
  try { const r = await fetch(u, { signal: c.signal, headers: { 'user-agent': 'Mozilla/5.0 (compatible; ZhdanovWeekly/1.0)', accept: 'application/json' } }); return r.ok ? await r.json() : null; }
  catch (e) { return null; } finally { clearTimeout(t); }
}

const now = {}, rows = [];
for (const s of sites) {
  const q = encodeURIComponent(s.url);
  const [chk, geo] = await Promise.all([get(API + '/api/check?lang=ru&url=' + q, 90000), get(API + '/api/geo?lang=ru&url=' + q, 40000)]);
  if (!chk && !geo) { rows.push({ ...s, fail: true }); console.log('нет ответа', s.url); continue; }
  const r = buildReport(s.url, chk, geo);
  const prev = last[s.url] ? last[s.url].total : null;
  now[s.url] = { total: r.total, site: r.site, geo: r.geo, law: r.law, top: r.top[0] || '' };
  rows.push({ ...s, ...now[s.url], prev });
  console.log(r.total, s.url, r.top[0] || '');
}

const d = new Date();
const date = String(d.getUTCDate()).padStart(2, '0') + '.' + String(d.getUTCMonth() + 1).padStart(2, '0');
const ok = rows.filter((r) => !r.fail);
const avg = ok.length ? Math.round(ok.reduce((a, r) => a + r.total, 0) / ok.length) : 0;
const avgPrev = ok.filter((r) => r.prev != null);
const dAvg = avgPrev.length ? avg - Math.round(avgPrev.reduce((a, r) => a + r.prev, 0) / avgPrev.length) : null;
const sign = (n) => (n > 0 ? '+' + n : String(n));
const L = [];
L.push('📊 Еженедельная проверка сайтов · ' + date);
L.push('Сайтов: ' + ok.length + ', средняя оценка ' + avg + (dAvg != null && dAvg !== 0 ? ' (' + sign(dAvg) + ' за неделю)' : '') + '.');
const worse = ok.filter((r) => r.prev != null && r.total <= r.prev - 5).sort((a, b) => (a.total - a.prev) - (b.total - b.prev));
if (worse.length) { L.push(''); L.push('⬇ Стало хуже:'); worse.forEach((r) => L.push('• ' + r.name + ': ' + r.prev + ' → ' + r.total + (r.top ? '. ' + r.top : ''))); }
const bad = ok.filter((r) => r.total < 60 && !worse.includes(r)).sort((a, b) => a.total - b.total);
if (bad.length) { L.push(''); L.push('🔴 Ниже 60, стоит поправить:'); bad.forEach((r) => L.push('• ' + r.name + ': ' + r.total + (r.top ? '. ' + r.top : ''))); }
const fails = rows.filter((r) => r.fail);
if (fails.length) { L.push(''); L.push('⛔ Проверка не открыла сайт:'); fails.forEach((r) => L.push('• ' + r.name + ' ' + r.url)); }
const good = ok.filter((r) => !worse.includes(r) && !bad.includes(r)).sort((a, b) => b.total - a.total);
if (good.length) { L.push(''); L.push('✅ В порядке:'); L.push(good.map((r) => r.name + ' ' + r.total + (r.prev != null && r.total !== r.prev ? ' (' + sign(r.total - r.prev) + ')' : '')).join(' · ')); }
L.push(''); L.push('Подробно по любому сайту: ' + API + '/?audit=АДРЕС#check');
const text = L.join('\n');
console.log('\n' + text);
fs.writeFileSync(LAST, JSON.stringify(now, null, 1));

const { TG_TOKEN, TG_CHAT } = process.env;
if (TG_TOKEN && TG_CHAT) {
  // Telegram: не больше 4096 знаков в сообщении
  const parts = []; let cur = '';
  for (const line of text.split('\n')) { if ((cur + line).length > 3800) { parts.push(cur); cur = ''; } cur += line + '\n'; }
  if (cur) parts.push(cur);
  for (const p of parts) {
    const r = await fetch('https://api.telegram.org/bot' + TG_TOKEN + '/sendMessage', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ chat_id: TG_CHAT, text: p, disable_web_page_preview: true }) }).then((x) => x.json());
    if (!r.ok) { console.error('Telegram:', r.description); process.exitCode = 1; }
  }
}
