// Реальный HTTPS smoke-тест staging. Секреты читаются из игнорируемого файла.
const { chromium, request, expect } = require('@playwright/test');
const fs = require('fs');
const assert = require('node:assert/strict');
const path = require('path');
const c = JSON.parse(fs.readFileSync(path.join(__dirname, '../data/server-access.json')));
const base = `https://${c.SS_DOMAIN}`;
(async () => {
  const anonymous = await request.newContext();
  for (const uri of ['/', '/catalog/', '/wp-login.php']) {
    const r = await anonymous.get(base + uri);
    assert.equal(r.status(), 200, `Открыто без nginx-пароля: ${uri}`);
    assert.ok(!r.headers()['www-authenticate']);
  }
  const admin = await anonymous.get(base+'/wp-admin/',{maxRedirects:0});
  assert.equal(admin.status(),302); assert.match(admin.headers().location,/wp-login\.php/);
  const api = await request.newContext();
  for (const uri of ['/', '/catalog/', '/cart/', '/checkout/', '/my-account/', '/wp-login.php']) {
    const r = await api.get(base + uri);
    assert.equal(r.status(), 200, `HTTPS: ${uri}`);
    assert.match(r.headers()['x-robots-tag'], /noindex/);
    if (/cart|checkout|my-account|wp-login/.test(uri)) assert.equal(r.headers()['x-fastcgi-cache'], 'BYPASS', uri);
  }
  for (const uri of ['/wp-config.php','/xmlrpc.php','/wp-content/debug.log','/.env','/wp-content/uploads/test.php']) {
    const r = await api.get(base + uri); assert.equal(r.status(),403, `Закрытый путь: ${uri}`);
  }
  let hit;
  for (let i=0;i<3;i++) hit=(await api.get(base+'/')).headers()['x-fastcgi-cache'];
  assert.equal(hit,'HIT');
  const session = await request.newContext({extraHTTPHeaders:{Cookie:'wp_woocommerce_session_probe=1'}});
  assert.equal((await session.get(base+'/')).headers()['x-fastcgi-cache'],'BYPASS');
  const robots = await api.get(base+'/robots.txt'); assert.match(await robots.text(), /Disallow: \/\s/);
  const html = await (await api.get(base+'/catalog/')).text(); assert.ok(!html.includes('localhost:8080'));
  const image = html.match(/https:\/\/[^" ]+\/wp-content\/uploads\/[^" ]+\.(?:jpg|png)/)?.[0];
  assert.ok(image,'Изображение каталога');
  assert.match((await api.get(image,{headers:{Accept:'image/webp'}})).headers()['content-type'],/image\/webp/);
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({});
    const page = await context.newPage();
    await page.goto(base+'/wp-login.php');
    if (process.env.SS_TEST_WRONG_LOGIN === '1') {
      await page.locator('#user_login').fill('admin');
      await page.locator('#user_pass').fill('admin');
      await page.locator('#wp-submit').click();
      await page.locator('#login_error').waitFor();
      assert.ok(!page.url().includes('/wp-admin/'),'admin/admin не допускается');
      await page.goto(base+'/wp-login.php');
    }
    await page.locator('#user_login').fill(c.WP_ADMIN_USER);
    await page.locator('#user_pass').fill(c.WP_ADMIN_PASSWORD);
    await page.locator('#wp-submit').click();
    await page.waitForTimeout(2000);
    if (!page.url().includes('/wp-admin/')) {
      console.log('Вход: URL',page.url());
      console.log('Сообщение входа:',await page.locator('#login_error').textContent().catch(()=>''));
    }
    await page.waitForURL('**/wp-admin/**');
    await page.locator('#wpadminbar').waitFor();
    const loginCookies = (await context.cookies()).filter(v=>v.name.startsWith('wordpress_logged_in')||v.name.startsWith('wordpress_sec'));
    assert.ok(loginCookies.length); assert.ok(loginCookies.every(v=>v.secure&&v.httpOnly));
    console.log('HTTPS: заданный вход работает; cookies Secure/HttpOnly.');
    const guest = await browser.newContext({});
    const shop = await guest.newPage();
    for (const width of [375,768,1440]) {
      await shop.setViewportSize({width,height:900}); await shop.goto(base+'/catalog/');
      assert.ok(await shop.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Ширина ${width}`);
      assert.equal(await shop.locator('h1').count(),1);
    }
    console.log('Каталог: HTTPS, 375/768/1440, один H1, без горизонтального скролла.');
    if (process.env.SS_SKIP_CHECKOUT !== '1') {
    await shop.goto(base+'/catalog/dvigateli/shagovye-dvigateli/dvigatel-57hs56-3004/');
    await shop.getByRole('button',{name:'В корзину',exact:true}).click();
    const cart = await shop.goto(base+'/cart/');
    assert.equal(cart.headers()['x-fastcgi-cache'],'BYPASS');
    await expect(shop.getByText('Шаговый двигатель 57HS56-3004',{exact:true}).first()).toBeVisible();
    await shop.getByRole('link',{name:'Перейти к оформлению заказа'}).click();
    await shop.locator('#email').fill(`playwright-${Date.now()}@example.test`);
    await shop.locator('#billing-first_name').fill('Тест');
    await shop.locator('#billing-last_name').fill('Playwright');
    await shop.locator('#billing-phone').fill('+79000000000');
    await shop.locator('#terms-and-conditions').check();
    await shop.locator('#order-soberi-stanok-personal-data-consent').check();
    await shop.getByRole('button',{name:'Оформить заказ',exact:true}).click();
    await expect(shop).toHaveURL(/order-received/, {timeout:45000});
    await expect(shop.getByRole('heading',{name:'Заказ принят',exact:true})).toBeVisible();
    const orderId=new URL(shop.url()).pathname.match(/order-received\/(\d+)/)[1];
    fs.writeFileSync(path.join(__dirname,'../data/server-test-order.json'),JSON.stringify({orderId:Number(orderId)}));
    console.log('VPS: товар → корзина → самовывоз/оплата при получении → заказ',orderId,'— OK (почта staging отключена).');
    }
  } finally { await browser.close(); }
  console.log('Витрина: открыта без nginx-пароля, админка требует WordPress, noindex, cache HIT/BYPASS, закрытые файлы, WebP — OK.');
  await Promise.all([anonymous.dispose(),api.dispose(),session.dispose()]);
})().catch(e=>{console.error(e.message);process.exitCode=1;});
