(function () {
  const config = window.ssAnalytics;
  if (!config) return;
  let enabled = false;
  const storageKey = 'ss-analytics-consent-v1';
  function read(key) { try { return localStorage.getItem(key); } catch (_) { return null; } }
  function write(key, value) { try { localStorage.setItem(key, value); } catch (_) {} }
  function goal(name) { if (enabled) window.ym(config.id, 'reachGoal', name); }
  function enable() {
    if (enabled) return;
    enabled = true;
    window.dataLayer = window.dataLayer || [];
    window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
    window.ym.l = Date.now();
    const tag = document.createElement('script');
    tag.async = true; tag.src = 'https://mc.yandex.ru/metrika/tag.js'; document.head.append(tag);
    window.ym(config.id, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true, ecommerce: 'dataLayer' });
    config.events.forEach(function (event) {
      const purchase = event.ecommerce.purchase;
      if (purchase) {
        const key = 'ss-purchase-' + config.id + '-' + purchase.actionField.id;
        // Перезагрузка страницы благодарности не создаёт повторную покупку.
        if (read(key)) return;
        write(key, '1'); goal('order');
      }
      window.dataLayer.push(event);
    });
  }
  const consent = read(storageKey);
  if (consent === 'yes') enable();
  function showConsent() {
    const old = document.getElementById('ss-cookie-banner'); if (old) old.remove();
    const banner = document.createElement('section');
    banner.id = 'ss-cookie-banner'; banner.className = 'ss-cookie-banner';
    banner.setAttribute('aria-label', 'Настройки аналитики');
    const text = document.createElement('p'); text.textContent = 'Разрешить Яндекс.Метрике собирать статистику посещений и покупок?';
    const link = document.createElement('a'); link.href = config.privacy; link.textContent = 'Политика конфиденциальности';
    banner.append(text, link);
    ['Разрешить', 'Отклонить'].forEach(function (label, i) {
      const button = document.createElement('button'); button.type = 'button'; button.className = i === 0 ? 'ss-button' : 'ss-button ss-button-outline'; button.textContent = label;
      button.onclick = function () { write(storageKey, i === 0 ? 'yes' : 'no'); banner.remove(); if (i === 0) enable(); else if (enabled) location.reload(); };
      banner.append(button);
    });
    document.body.append(banner);
  }
  if (!consent) showConsent();
  const settings = document.createElement('button'); settings.type = 'button'; settings.className = 'ss-analytics-settings'; settings.textContent = 'Настройки аналитики'; settings.onclick = showConsent;
  (document.querySelector('.ss-footer') || document.body).append(settings);
  document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href]'); if (!link) return;
    if (link.protocol === 'tel:') goal('phone');
    else if (/^(t\.me|wa\.me|api\.whatsapp\.com|vk\.com|max\.ru)$/.test(link.hostname)) goal('messenger');
  });
  document.addEventListener('wpcf7mailsent', function () { goal('form'); });
})();
