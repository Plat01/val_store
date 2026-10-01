---
name: seo-check
description: SEO-чек-лист магазина «собери станок» под Яндекс (meta, H1, canonical, noindex, schema.org, sitemap, robots, скорость, URL). Использовать при создании/изменении шаблонов, страниц, категорий, товаров и перед запуском.
---

# SEO под Яндекс — чек-лист

## Каждая индексируемая страница
- [ ] Ровно один `<h1>`, логичная иерархия h2/h3 (без пропусков ради стиля).
- [ ] `<title>` 50–70 символов, уникальный; `meta description` 120–160, уникальный.
- [ ] `<link rel="canonical">` на себя (без GET-параметров).
- [ ] Open Graph: `og:title`, `og:description`, `og:image` (1200×630), `og:type`.
- [ ] Хлебные крошки (видимые + BreadcrumbList).
- [ ] `<html lang="ru">`, картинки с осмысленным `alt`, `width`/`height` (нет CLS).
- [ ] Контент доступен без JS (WooCommerce-блоки рендерятся на сервере).

## Шаблоны title (Rank Math)
- Товар: `%title% — купить за %wc_price% | собери станок`
- Категория: `%term% — купить в интернет-магазине | собери станок`, H1 = название категории.
- Описание категории (SEO-текст) — под листингом товаров, не над ним.

## Закрыть от индекса (`noindex, follow`)
Корзина, оформление, аккаунт, поиск (`?s=`), сортировка/фильтры (`?orderby`, `?filter_*`,
`?min_price`), страницы пагинации — индексировать (canonical на себя), но без SEO-текста.
В robots.txt: `Disallow` для `/wp-admin/` (кроме admin-ajax), `/cart/`, `/checkout/`,
`/my-account/`, `/*?s=`, `/*add-to-cart=`; `Clean-param` для utm/yclid/orderby/filter_*;
строка `Sitemap:`.

## Микроразметка (schema.org, JSON-LD)
- Organization (name, logo, url, contactPoint) — на всех страницах.
- LocalBusiness / Store — самовывоз: адрес, часы, телефон (из «Данных магазина»).
- Product + Offer (price, priceCurrency RUB, availability, sku, brand, image) — товар.
- BreadcrumbList; FAQPage — только где реально есть FAQ.
- Проверка: https://webmaster.yandex.ru/tools/microtest/ и validator.schema.org.

## URL
- Латиница, транслит, нижний регистр, дефисы. Слаги старых товаров/категорий сохранять.
- Смена URL → 301 (Rank Math → Редиректы). Никаких цепочек редиректов.
- Слэш на конце — единообразно (со слэшем).

## Скорость (Lighthouse mobile ≥ 90 Performance/SEO, Accessibility ≥ 95)
- WebP, `loading="lazy"` кроме первого экрана, `fetchpriority="high"` у LCP-картинки.
- Шрифты локально, woff2, subset кириллица+латиница, `font-display: swap`, preload основного.
- Минимум JS: не подключать jQuery/скрипты WooCommerce там, где они не нужны.

## Яндекс
- Метрика: вебвизор, e-commerce dataLayer, цели (заказ, клик по телефону/мессенджеру, форма).
- Вебмастер: подтверждение meta-тегом, sitemap, регион сайта, переезд при смене домена.

## Как проверять
- `curl -s URL | grep -E '<title>|<h1|canonical|robots|og:'` — быстрая проверка.
- `npx lighthouse URL --only-categories=performance,seo,accessibility --form-factor=mobile --quiet --chrome-flags=--headless`
- Playwright MCP — визуально и по DOM.
