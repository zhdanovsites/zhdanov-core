/* Портфолио zhdanovsites.com: сначала 6 лучших проектов, остальные по кнопке (Zhdanov Sites, 2026).
   Подключение: <script src="/work-more.js" defer></script> перед </body> на RU и EN главной, после основного скрипта.
   Порядок проектов прежний (ORDER в gen.py): первые 6 и есть «лучшие», поменять их = поменять ORDER.
   В категории (не «Все») видны все проекты этой категории. Если открыли ссылку на карточку (#id),
   список раскрывается сам. Без JS видны все проекты, как раньше. */
(function () {
  'use strict';
  var LIMIT = 6;
  var list = document.getElementById('plist'); if (!list) return;
  var EN = document.documentElement.lang === 'en';
  var cards = [].slice.call(list.querySelectorAll('.proj'));
  if (cards.length <= LIMIT) return;
  var open = false;

  var st = document.createElement('style');
  st.textContent = '.proj.mh{display:none!important}.wm{display:flex;justify-content:center;margin:28px 0 0}.wm .btn{min-width:min(100%,320px);justify-content:center}';
  document.head.appendChild(st);
  var box = document.createElement('div'); box.className = 'wm';
  var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn btn-line'; btn.setAttribute('aria-controls', 'plist');
  box.appendChild(btn); list.parentNode.insertBefore(box, list.nextSibling);

  function activeCat() { var c = document.querySelector('.chip.on'); return c ? c.dataset.cat : 'all'; }
  function apply() {
    var all = activeCat() === 'all', shown = 0, total = 0;
    cards.forEach(function (p) {
      if (p.hidden) { p.classList.remove('mh'); return; }
      total++;
      var hide = all && !open && shown >= LIMIT;
      p.classList.toggle('mh', hide);
      if (!hide) shown++;
    });
    var rest = total - shown;
    box.hidden = rest <= 0;
    btn.setAttribute('aria-expanded', rest <= 0 ? 'true' : 'false');
    btn.textContent = EN ? 'Show all projects (' + total + ')' : 'Показать все проекты (' + total + ')';
  }
  btn.addEventListener('click', function () {
    open = true; var first = cards.filter(function (p) { return p.classList.contains('mh'); })[0];
    apply();
    if (first) { var a = first.querySelector('a.shots, a'); if (a) a.focus({ preventScroll: true }); }
  });
  // фильтры сайта сами переключают hidden у карточек: пересчитываем после них
  document.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('.chip')) setTimeout(apply, 0); });
  ['fq', 'fc'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.addEventListener('input', function () { setTimeout(apply, 0); }); el.addEventListener('change', function () { setTimeout(apply, 0); }); } });
  // ссылка вида /#bkz на карточку из-за лимита: раскрываем
  function hash() { var h = location.hash.slice(1); if (h && cards.some(function (p) { return p.id === h; })) { open = true; apply(); var el = document.getElementById(h); if (el) el.scrollIntoView(); } }
  apply(); hash(); addEventListener('hashchange', hash);
})();
