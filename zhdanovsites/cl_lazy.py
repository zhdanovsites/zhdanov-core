# Clarity и PostHog на английских страницах: грузятся после первого действия человека
# (касание, прокрутка, колесо мыши, клавиша) или через 10 секунд, что раньше.
# Первая отрисовка и оценка PageSpeed их больше не ждут. Ключи те же, что в ym.py.
CL_LAZY = ('<script>(function(){var done=0;function go(){if(done)return;done=1;'
 '["pointerdown","scroll","keydown","touchstart","wheel"].forEach(function(e){removeEventListener(e,go,{passive:true})});'
 # Clarity
 '(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)})(window,document,"clarity","script","z7rzzvnjwe");'
 # PostHog: тот же загрузчик, что в PH, но вызывается здесь
 'var s=document.createElement("script");s.async=1;s.crossOrigin="anonymous";s.src="https://eu-assets.i.posthog.com/static/array.js";'
 's.onload=function(){if(window.posthog&&window.posthog.init)window.posthog.init("' + PH_KEY + '",{api_host:"https://eu.i.posthog.com",person_profiles:"identified_only"})};document.head.appendChild(s)}'
 '["pointerdown","scroll","keydown","touchstart","wheel"].forEach(function(e){addEventListener(e,go,{passive:true,once:true})});setTimeout(go,10000)})();</script>')
# В gen.py на EN-страницах вместо CL (= Clarity + PostHog) вставлять CL_LAZY.
