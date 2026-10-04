const {test,expect}=require('@playwright/test');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const wp=(...args)=>execFileSync(path.resolve('site/bin/wp'),args,{cwd:path.resolve('site'),encoding:'utf8'}).trim();
const product='/catalog/dvigateli/shagovye-dvigateli/dvigatel-57hs56-3004/';
const category='/catalog/dvigateli/shagovye-dvigateli/';
let original;
test.beforeAll(()=>{original=wp('option','get','blog_public');wp('option','update','blog_public','1');});
test.afterAll(()=>{if(original!==undefined)wp('option','update','blog_public',original);});
test('SEO запуска: meta, canonical, пагинация, noindex и sitemap',async({page,request})=>{
  for(const url of ['/', '/catalog/',category,product,'/about/','/contacts/','/delivery/']){
    await page.goto(url);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content',/.{60,}/);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href','http://localhost:8080'+url);
    expect(await page.locator('meta[name="robots"]').getAttribute('content')).not.toContain('noindex');
    for(const key of ['title','description','image','type'])await expect(page.locator(`meta[property="og:${key}"]`)).toHaveAttribute('content',/.+/);
  }
  for(const url of ['/cart/','/checkout/','/my-account/','/?s=motor&post_type=product',category+'?orderby=price',category+'?filter_tiporazmer=nema23',category+'?max_price=1600']){
    await page.goto(url);await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content',/noindex, follow/);
  }
  await page.goto('/catalog/page/2/');
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href','http://localhost:8080/catalog/page/2/');
  expect(await page.locator('meta[name="robots"]').getAttribute('content')).not.toContain('noindex');
  await expect(page.locator('.ss-category-description')).toHaveCount(0);
  const robots=await (await request.get('/robots.txt')).text();expect(robots).toContain('Clean-param:');expect(robots).toContain('filter_tiporazmer');for(const line of robots.split('\n').filter(line=>line.startsWith('Clean-param:')))expect(line.length).toBeLessThanOrEqual(500);expect(robots).toContain('Sitemap: http://localhost:8080/sitemap_index.xml');
  const index=await request.get('/sitemap_index.xml');expect(index.status()).toBe(200);expect(await index.text()).toContain('product-sitemap.xml');
  const pages=await (await request.get('/page-sitemap.xml')).text();
  for(const url of ['cart','checkout','my-account'])expect(pages).not.toContain(`<loc>http://localhost:8080/${url}/</loc>`);
});
test('JSON-LD: реальные Product/Offer, крошки, единая Organization и FAQ',async({page,request})=>{
  await page.goto(product);
  const graph=await page.locator('script[type="application/ld+json"]').evaluateAll(nodes=>nodes.flatMap(n=>JSON.parse(n.textContent)['@graph']||[]));
  expect(graph.filter(n=>n['@type']==='Organization')).toHaveLength(1);
  const p=graph.find(n=>n['@type']==='Product');expect(p.sku).toBe('57HS56-3004A');expect(p.offers.priceCurrency).toBe('RUB');expect(Number(p.offers.price)).toBe(1250);expect(p.image.length).toBeGreaterThan(0);
  expect(graph.find(n=>n['@type']==='Organization').logo.url).toContain('apple-touch-icon.png');
  expect(graph.find(n=>n['@type']==='BreadcrumbList').itemListElement.map(n=>n.name)).toContain('Шаговые двигатели');
  await page.goto('/');
  const faq=await page.locator('script[type="application/ld+json"]').evaluateAll(nodes=>nodes.flatMap(n=>JSON.parse(n.textContent)['@graph']||[]).find(n=>n['@type']==='FAQPage'));
  for(const item of faq.mainEntity){await expect(page.locator('#faq')).toContainText(item.name);await expect(page.locator('#faq')).toContainText(item.acceptedAnswer.text);}
  expect(await page.locator('img').evaluateAll(imgs=>imgs.filter(n=>!n.alt.trim()).length)).toBe(0);
  const image=await request.get('/wp-content/uploads/2026/10/dvigatel-57hs56-3004.png',{headers:{Accept:'image/webp'}});expect(image.headers()['content-type']).toBe('image/webp');
});
test('Метрика: согласие, цели и защищённая покупка без дублей',async({page})=>{
  const data=wp('option','get','ss_shop','--format=json');
  let testOrderId;
  try {
    wp('option','update','ss_shop',JSON.stringify({...JSON.parse(data),metrika_id:'12345678',webmaster_meta:'0123456789abcdef'}),'--format=json');
    await page.route('https://mc.yandex.ru/**',route=>route.fulfill({contentType:'application/javascript',body:''}));
    await page.goto(product);
    await expect(page.locator('meta[name="yandex-verification"]')).toHaveAttribute('content','0123456789abcdef');
    await expect(page.locator('#ss-cookie-banner')).toBeVisible();
    expect(await page.evaluate(()=>typeof window.ym)).toBe('undefined');
    for(const width of [375,768,1440]){
      await page.setViewportSize({width,height:900});
      expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      await page.screenshot({path:`site/data/screenshots/stage6/consent-${width}.png`});
    }
    await page.getByRole('button',{name:'Разрешить',exact:true}).click();
    expect(await page.evaluate(()=>window.dataLayer[0].ecommerce.detail.products[0].name)).toContain('57HS56');
    await page.evaluate(()=>{const a=document.createElement('a');a.href='tel:+79000000000';document.body.append(a);a.addEventListener('click',e=>e.preventDefault());a.click();const messenger=document.createElement('a');messenger.href='https://t.me/example';document.body.append(messenger);messenger.addEventListener('click',e=>e.preventDefault());messenger.click();document.dispatchEvent(new CustomEvent('wpcf7mailsent'));});
    const goals=await page.evaluate(()=>window.ym.a.map(a=>Array.from(a)).filter(a=>a[1]==='reachGoal').map(a=>a[2]));
    expect(goals).toEqual(expect.arrayContaining(['phone','messenger','form']));
    await page.getByRole('button',{name:'В корзину',exact:true}).click();
    await expect.poll(()=>page.evaluate(()=>window.dataLayer?.some(e=>e.ecommerce.add) || false)).toBe(true);
    const order=JSON.parse(wp('eval','$o=wc_create_order();$o->add_product(wc_get_product(wc_get_product_id_by_sku("57HS56-3004A")),1);$o->calculate_totals();$o->set_payment_method("cod");$o->set_status("on-hold");$o->save();echo json_encode(["id"=>$o->get_id(),"url"=>$o->get_checkout_order_received_url()]);'));
    testOrderId=order.id;
    await page.goto(order.url);
    expect(await page.evaluate(()=>window.dataLayer.find(e=>e.ecommerce.purchase).ecommerce.purchase.actionField.id)).toBe(String(order.id));
    await page.reload();expect(await page.evaluate(()=>window.dataLayer.filter(e=>e.ecommerce.purchase).length)).toBe(0);
    await page.goto('/checkout/order-received/'+order.id+'/?key=invalid');
    expect(await page.evaluate(()=>window.ssAnalytics.events.filter(e=>e.ecommerce.purchase).length)).toBe(0);
    await page.getByRole('button',{name:'Настройки аналитики',exact:true}).click();
    await page.getByRole('button',{name:'Отклонить',exact:true}).click();
    await page.waitForLoadState();
    await page.goto('/');expect(await page.evaluate(()=>typeof window.ym)).toBe('undefined');
  } finally {wp('option','update','ss_shop',data,'--format=json');if(testOrderId)wp('eval',`wc_get_order(${Number(testOrderId)})->delete(true);`);}
});
test('Самовывоз: реальные данные магазина и часы в LocalBusiness',async({page})=>{
  const data=wp('option','get','ss_shop','--format=json');
  const placeholders=wp('option','get','ss_shop_placeholders');
  try {
    wp('option','update','ss_shop',JSON.stringify({...JSON.parse(data),brand:'собери станок',city:'Самара',pickup_address:'Тестовая улица, 10',hours:'Пн–Пт: 10:00–19:00\nСб–Вс: выходной'}),'--format=json');
    wp('option','update','ss_shop_placeholders','0');
    await page.goto('/contacts/');
    const graph=await page.locator('script[type="application/ld+json"]').evaluateAll(nodes=>nodes.flatMap(n=>JSON.parse(n.textContent)['@graph']||[]));
    const store=graph.find(n=>n['@type']==='Store');
    expect(store.address.addressLocality).toBe('Самара');expect(store.address.streetAddress).toBe('Тестовая улица, 10');
    expect(store.openingHours).toEqual(['Mo-Fr 10:00-19:00']);
    await expect(page.locator('main')).toContainText(store.address.streetAddress);
  } finally {wp('option','update','ss_shop',data,'--format=json');wp('option','update','ss_shop_placeholders',placeholders);}
});
