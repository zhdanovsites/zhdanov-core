/* Живые карточки портфолио zhdanovsites.com (Zhdanov Sites, 2026).
   Подключение: <script src="/cards-video.js" defer></script> перед </body> (RU и EN главная).
   Видео лежат в /vid/<id>.mp4 (H.264, до 300 КБ), где <id> это id карточки article.proj.
   Новую карточку добавлять в список не нужно: достаточно положить /vid/<id>.mp4.
   Видео 4 секунды, без звука, грузится только когда карточка на экране, играет по кругу,
   ставится на паузу, когда карточка ушла с экрана. Картинка карточки остаётся под видео.
   Не включается: при «уменьшить движение», при экономии трафика (Save-Data) и на медленной сети. */
(function () {
  'use strict';
  // Список карточек находится сам: каждая article.proj с id. Если /vid/<id>.mp4 нет, видео тихо не появится.
  // Можно ограничить вручную: window.ZH_VIDS = ['bkz', ...] до подключения скрипта.
  var VIDS = window.ZH_VIDS || [].map.call(document.querySelectorAll('article.proj[id]'), function (a) { return a.id; });
  if (!VIDS.length || !('IntersectionObserver' in window)) return;
  try { if (matchMedia('(prefers-reduced-motion: reduce)').matches) return; } catch (e) {}
  var c = navigator.connection; if (c && (c.saveData || /(^|-)2g$/.test(c.effectiveType || ''))) return;

  var css = '.proj .browser{position:relative}.proj .zv{position:absolute;left:0;right:0;bottom:0;width:100%;object-fit:cover;object-position:top;opacity:0;transition:opacity .4s;pointer-events:none;background:transparent}.proj .zv.on{opacity:1}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      var art = e.target, v = art._zv; if (v === 'none') return;
      if (e.isIntersecting && e.intersectionRatio >= 0.45) {
        if (!v) {
          var img = art.querySelector('.browser img'), box = art.querySelector('.browser');
          if (!img || !box) return;
          v = document.createElement('video');
          v.className = 'zv'; v.muted = true; v.loop = true; v.playsInline = true; v.preload = 'auto';
          v.setAttribute('muted', ''); v.setAttribute('playsinline', ''); v.setAttribute('aria-hidden', 'true'); v.tabIndex = -1;
          v.style.top = img.offsetTop + 'px'; v.style.height = img.offsetHeight + 'px';
          v.src = '/vid/' + art.id + '.mp4';
          v.addEventListener('playing', function () { v.classList.add('on'); });
          v.addEventListener('error', function () { v.remove(); art._zv = 'none'; io.unobserve(art); }, true);
          box.appendChild(v); art._zv = v;
        }
        var pr = v.play(); if (pr && pr.catch) pr.catch(function () {});
      } else if (v) { v.pause(); }
    });
  }, { threshold: [0, 0.45, 0.8] });

  VIDS.forEach(function (id) { var a = document.getElementById(id); if (a && a.classList.contains('proj')) io.observe(a); });
  addEventListener('resize', function () {
    VIDS.forEach(function (id) {
      var a = document.getElementById(id); if (!a || !a._zv) return;
      var img = a.querySelector('.browser img'); if (img) { a._zv.style.top = img.offsetTop + 'px'; a._zv.style.height = img.offsetHeight + 'px'; }
    });
  });
})();
