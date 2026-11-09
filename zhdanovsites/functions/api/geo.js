// Cloudflare Pages Function: /api/geo?url=https://site.ru/&lang=ru
// «Видят ли вас Алиса и нейросети»: 10 технических признаков, по которым Алиса AI, GigaChat,
// ChatGPT и Perplexity находят сайт и берут его в ответ. Ответ: { url, score 0..100, items[], top[] }.
// Zhdanov Sites, 2026. Без внешних библиотек.

const UA = 'Mozilla/5.0 (compatible; ZhdanovAudit/1.0; +https://zhdanovsites.com/)';
const AI_BOTS = [
  ['YandexBot', 'Яндекс и Алиса'],
  ['Googlebot', 'Google и Gemini'],
  ['OAI-SearchBot', 'поиск ChatGPT'],
  ['GPTBot', 'ChatGPT'],
  ['PerplexityBot', 'Perplexity'],
  ['ClaudeBot', 'Claude'],
];

const T = {
  ru: {
    robots_ok: ['Роботы нейросетей допущены', 'robots.txt не закрывает сайт от Яндекса, Google и ботов ChatGPT, Perplexity, Claude.', ''],
    robots_bad: ['Сайт закрыт от части нейросетей', 'В robots.txt закрыт доступ: {list}. Эти системы не смогут прочитать сайт и не назовут вас в ответе.', 'Убрать запрет Disallow: / для этих роботов в robots.txt (если закрыли не специально).'],
    noindex: ['Сайт просит поисковики его не показывать', 'На главной стоит запрет индексации (noindex). Ни поиск, ни нейросети её не возьмут.', 'Убрать noindex из настроек сайта (часто остаётся после разработки).'],
    schema_ok: ['Есть карточка компании для роботов', 'На странице есть разметка Schema.org ({type}): название, контакты, адрес. Нейросети берут факты отсюда.', ''],
    schema_bad: ['Нет карточки компании для роботов', 'Нет разметки Schema.org (Organization или LocalBusiness). Нейросети угадывают, кто вы, где и как связаться, и часто ошибаются.', 'Добавить разметку JSON-LD: название, телефон, адрес, часы, ссылки на соцсети.'],
    faq_ok: ['Есть ответы на частые вопросы', 'Найдено вопросов: {n}. Нейросети охотно цитируют короткие ответы на вопросы клиентов.', ''],
    faq_bad: ['Нет блока «вопрос-ответ»', 'Нейросети отвечают людям на вопросы. Если на сайте нет готовых ответов («сколько стоит», «как записаться», «где вы»), они берут их у конкурентов.', 'Добавить 5–8 вопросов клиентов с короткими ответами и разметкой FAQPage.'],
    llms_ok: ['Есть файл llms.txt', 'Сайт даёт нейросетям краткое описание и список главных страниц.', ''],
    llms_bad: ['Нет файла llms.txt', 'Новый стандарт: короткий файл для нейросетей «кто мы и какие страницы главные». Делается за 10 минут, конкуренты его почти не делают.', 'Положить в корень сайта llms.txt с описанием и ссылками на главные страницы.'],
    clear_ok: ['Понятно, кто вы и что делаете', 'Есть главный заголовок (H1) и описание страницы. Нейросети по ним понимают суть сайта.', ''],
    clear_bad: ['Нейросети не поймут, о чём сайт', '{why}. Это первое, что читают поиск и нейросети, чтобы понять, чем вы занимаетесь.', 'Сделать один понятный заголовок H1 («Стоматология в Казани») и описание страницы на 1–2 предложения.'],
    nap_ok: ['Контакты видны роботам', 'Телефон {phone} есть в тексте страницы. Нейросеть сможет назвать, как с вами связаться.', ''],
    nap_bad: ['Контакты не видны роботам', 'Телефон не найден текстом на главной (возможно, он только на картинке или в кнопке). Нейросеть не сможет его назвать.', 'Написать телефон и адрес текстом в шапке или подвале.'],
    map_ok: ['Есть ссылка на карточку в Картах', 'Алиса и другие нейросети берут адрес, часы и отзывы из Яндекс Карт и 2ГИС.', ''],
    map_bad: ['Нет связи с Яндекс Картами или 2ГИС', 'Алиса берёт адрес, часы и отзывы из карточки в Яндекс Бизнесе. Без ссылки с сайта ей сложнее связать карточку с вами.', 'Создать или проверить карточку в Яндекс Бизнесе и поставить на неё ссылку с сайта.'],
    reviews_ok: ['Есть отзывы', 'На сайте есть отзывы: нейросети чаще рекомендуют тех, у кого есть подтверждение от клиентов.', ''],
    reviews_bad: ['Нет отзывов на сайте', 'Нейросети ищут подтверждения, что вам доверяют. Без отзывов вас реже рекомендуют.', 'Добавить 3–6 отзывов текстом с именами и ссылку на отзывы в Яндекс Картах.'],
    sitemap_ok: ['Есть карта сайта', 'Роботы быстро находят все страницы.', ''],
    sitemap_bad: ['Нет карты сайта', 'Не найден sitemap.xml: роботы могут не увидеть часть страниц.', 'Сделать sitemap.xml и указать его в robots.txt.'],
    err: 'Не получилось открыть сайт',
  },
};

function txt(lang, key, rep) {
  const L = T[lang] || T.ru;
  const [title, text, fix] = L[key];
  const f = (s) => (s || '').replace(/\{(\w+)\}/g, (_, k) => (rep && rep[k] != null ? rep[k] : ''));
  return { title: f(title), text: f(text), fix: f(fix) };
}

async function get(url, ms = 9000) {
  const c = new AbortController();
  const t = setTimeout(() => c.abort(), ms);
  try {
    const r = await fetch(url, { headers: { 'User-Agent': UA, 'Accept-Language': 'ru,en;q=0.8' }, redirect: 'follow', signal: c.signal });
    const body = await r.text();
    return { ok: r.ok, status: r.status, body: body.slice(0, 1500000), headers: r.headers, url: r.url };
  } catch (e) {
    return { ok: false, status: 0, body: '', headers: new Headers(), url };
  } finally {
    clearTimeout(t);
  }
}

// robots.txt: кто закрыт полностью (Disallow: /)
export function robotsBlocked(robots) {
  const groups = [];
  let cur = null;
  for (const raw of (robots || '').split(/\r?\n/)) {
    const line = raw.replace(/#.*/, '').trim();
    if (!line) continue;
    const m = line.match(/^([A-Za-z-]+)\s*:\s*(.*)$/);
    if (!m) continue;
    const k = m[1].toLowerCase(), v = m[2].trim();
    if (k === 'user-agent') {
      if (!cur || cur.rules.length) { cur = { agents: [], rules: [] }; groups.push(cur); }
      cur.agents.push(v.toLowerCase());
    } else if (cur && (k === 'disallow' || k === 'allow')) {
      cur.rules.push([k, v]);
    }
  }
  const blockedFor = (bot) => {
    const b = bot.toLowerCase();
    let g = groups.find((x) => x.agents.some((a) => a !== '*' && b.indexOf(a) === 0));
    if (!g) g = groups.find((x) => x.agents.includes('*'));
    if (!g) return false;
    const dis = g.rules.some(([k, v]) => k === 'disallow' && v === '/');
    const allowRoot = g.rules.some(([k, v]) => k === 'allow' && (v === '/' || v === '/$'));
    return dis && !allowRoot;
  };
  return AI_BOTS.filter(([bot]) => blockedFor(bot)).map(([, name]) => name);
}

function jsonld(html) {
  const out = [];
  const re = /<script[^>]+type=["']?application\/ld\+json["']?[^>]*>([\s\S]*?)<\/script>/gi;
  let m;
  while ((m = re.exec(html))) {
    try {
      const j = JSON.parse(m[1].trim());
      const walk = (x) => {
        if (!x || typeof x !== 'object') return;
        if (Array.isArray(x)) return x.forEach(walk);
        out.push(x);
        if (x['@graph']) walk(x['@graph']);
        if (x.mainEntity) walk(x.mainEntity);
      };
      walk(j);
    } catch (e) { /* битый JSON-LD пропускаем */ }
  }
  return out;
}

const typesOf = (o) => [].concat(o['@type'] || []).map(String);
const strip = (h) => h.replace(/<(script|style|noscript)[^>]*>[\s\S]*?<\/\1>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ');

export function analyze(html, robots, llmsOk, sitemapOk, headers, lang = 'ru') {
  const items = [];
  const add = (id, ok, key, rep, w, sev) => items.push(Object.assign({ id, ok, w, sev: ok ? 'ok' : sev }, txt(lang, key, rep)));
  const text = strip(html);
  const head = (html.match(/<head[\s\S]*?<\/head>/i) || [''])[0];

  // 1. noindex (самое важное)
  const noindex = /<meta[^>]+name=["']?robots["']?[^>]+content=["'][^"']*noindex/i.test(head) || /noindex/i.test((headers && headers.get && headers.get('x-robots-tag')) || '');
  if (noindex) add('noindex', false, 'noindex', {}, 3, 'c');

  // 2. robots.txt
  const blocked = robotsBlocked(robots);
  add('robots', !blocked.length, blocked.length ? 'robots_bad' : 'robots_ok', { list: blocked.join(', ') }, blocked.some((x) => /Яндекс|Google/.test(x)) ? 3 : 2, blocked.some((x) => /Яндекс|Google/.test(x)) ? 'c' : 'i');

  // 3. Schema.org: организация
  const ld = jsonld(html);
  const NOT_ORG = /^(WebSite|WebPage|BreadcrumbList|ItemList|ListItem|FAQPage|Question|Answer|ImageObject|VideoObject|SearchAction|Offer|AggregateOffer|Review|Rating|AggregateRating|SiteNavigationElement)$/i;
  const org = ld.find((o) => typesOf(o).some((t) => /Organization|Business|Store|Restaurant|Clinic|School|Hotel|Person|Service|Dentist|Physician|Agency|Salon|Studio|Club|Center|Shop/i.test(t)))
    || ld.find((o) => o.name && (o.telephone || o.address || o.sameAs) && !typesOf(o).every((t) => NOT_ORG.test(t)));
  add('schema', !!org, org ? 'schema_ok' : 'schema_bad', { type: org ? typesOf(org)[0] : '' }, 2, 'i');

  // 4. FAQ
  const faqLd = ld.find((o) => typesOf(o).some((t) => /FAQPage/i.test(t)));
  let nq = faqLd && Array.isArray(faqLd.mainEntity) ? faqLd.mainEntity.length : 0;
  if (!nq) {
    const hq = (html.match(/<(h2|h3|h4|summary|dt)[^>]*>[^<]{6,160}\?\s*<\/\1>/gi) || []).length;
    nq = hq;
  }
  add('faq', nq >= 3, nq >= 3 ? 'faq_ok' : 'faq_bad', { n: nq }, 2, 'i');

  // 5. llms.txt
  add('llms', !!llmsOk, llmsOk ? 'llms_ok' : 'llms_bad', {}, 1, 't');

  // 6. Понятно, кто вы
  const h1 = (html.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i) || [])[1];
  const h1t = h1 ? strip(h1).trim() : '';
  const desc = (head.match(/<meta[^>]+name=["']?description["']?[^>]+content=["']([^"']*)/i) || head.match(/<meta[^>]+content=["']([^"']*)["'][^>]+name=["']?description/i) || [])[1] || '';
  const why = [];
  if (!h1t) why.push('Нет главного заголовка H1');
  else if (h1t.length < 8) why.push('Главный заголовок слишком короткий («' + h1t.slice(0, 40) + '»)');
  if (desc.trim().length < 50) why.push(desc.trim() ? 'Описание страницы слишком короткое' : 'Нет описания страницы (meta description)');
  add('clear', !why.length, why.length ? 'clear_bad' : 'clear_ok', { why: why.join('. ') }, 2, 'i');

  // 7. Телефон текстом
  const ph = text.match(/(\+7|8|\+375|\+380|\+7\s?\()[\s\-()]*\d{3}[\s\-()]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}/) || text.match(/\+\d{1,3}[\s\-()]*\d{2,4}[\s\-()]*\d{3}[\s\-]*\d{2,4}[\s\-]*\d{0,4}/);
  add('nap', !!ph, ph ? 'nap_ok' : 'nap_bad', { phone: ph ? ph[0].trim() : '' }, 1, 'l');

  // 8. Карты
  const map = /(yandex\.(ru|com|by|kz)\/(maps|profile)|2gis\.(ru|com|by|kz)|maps\.app\.goo\.gl|google\.[a-z.]+\/maps)/i.test(html);
  add('map', map, map ? 'map_ok' : 'map_bad', {}, 1, 'l');

  // 9. Отзывы
  const rev = /отзыв|reviews?\b|testimonial/i.test(text) || ld.some((o) => typesOf(o).some((t) => /Review|AggregateRating/.test(t)) || o.aggregateRating);
  add('reviews', rev, rev ? 'reviews_ok' : 'reviews_bad', {}, 1, 'l');

  // 10. Карта сайта
  add('sitemap', !!sitemapOk, sitemapOk ? 'sitemap_ok' : 'sitemap_bad', {}, 1, 'l');

  const total = items.reduce((s, x) => s + x.w, 0);
  const got = items.reduce((s, x) => s + (x.ok ? x.w : 0), 0);
  let score = Math.round((100 * got) / total);
  if (noindex) score = Math.min(score, 15);
  const order = { c: 0, i: 1, l: 2, t: 3, ok: 9 };
  const top = items.filter((x) => !x.ok).sort((a, b) => order[a.sev] - order[b.sev] || b.w - a.w).slice(0, 3).map((x) => x.id);
  return { score, items, top };
}

function normUrl(u) {
  u = (u || '').trim();
  if (!u) return '';
  if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
  try {
    const x = new URL(u);
    if (!/\./.test(x.hostname) || /^(localhost|127\.|10\.|192\.168\.|169\.254\.)/.test(x.hostname)) return '';
    return x.origin + (x.pathname || '/');
  } catch (e) {
    return '';
  }
}

export async function onRequestGet({ request }) {
  const q = new URL(request.url).searchParams;
  const lang = 'ru'; // тексты пока только по-русски
  const url = normUrl(q.get('url'));
  const json = (o, st = 200) => new Response(JSON.stringify(o), { status: st, headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'public, max-age=900' } });
  if (!url) return json({ error: 'bad_url' }, 400);
  const cache = caches.default;
  const key = new Request('https://geo-cache.local/?' + encodeURIComponent(url));
  const hit = await cache.match(key);
  if (hit) return hit;
  const origin = new URL(url).origin;
  const [page, robots, llms, sm] = await Promise.all([get(url), get(origin + '/robots.txt', 6000), get(origin + '/llms.txt', 6000), get(origin + '/sitemap.xml', 6000)]);
  if (!page.ok || !page.body) return json({ error: 'unreachable', url }, 200);
  const llmsOk = llms.ok && llms.body.length > 30 && !/<html/i.test(llms.body.slice(0, 500));
  const smOk = (sm.ok && /<(urlset|sitemapindex)/i.test(sm.body)) || /^\s*sitemap\s*:/im.test(robots.ok ? robots.body : '');
  const res = Object.assign({ url: page.url || url }, analyze(page.body, robots.ok ? robots.body : '', llmsOk, smOk, page.headers, lang));
  const out = json(res);
  try { await cache.put(key, out.clone()); } catch (e) { /* кеш не обязателен */ }
  return out;
}
