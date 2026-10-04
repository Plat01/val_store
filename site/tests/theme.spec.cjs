const {test,expect}=require('@playwright/test');
const product='/catalog/dvigateli/shagovye-dvigateli/dvigatel-57hs56-3004/';

test('Фильтр атрибута и цены, сброс, сортировка и поиск артикула',async({page})=>{
  await page.goto('/catalog/dvigateli/shagovye-dvigateli/');
  await page.locator('.ss-filters').evaluate(e=>e.open=true);
  const select=page.locator('.ss-filters select[name="filter_tiporazmer"]');
  await select.selectOption({label:'NEMA23'});
  await page.getByRole('button',{name:'Применить',exact:true}).click();
  await expect(page).toHaveURL(/filter_tiporazmer=nema23/);
  const before=await page.locator('ul.products li.product').count();
  expect(before).toBeGreaterThan(0);
  await expect(page.locator('ul.products')).not.toContainText('86HS');
  await page.locator('.ss-filters').evaluate(e=>e.open=true);
  await page.getByRole('spinbutton',{name:'Цена до, ₽',exact:true}).fill('1600');
  await page.getByRole('button',{name:'Применить',exact:true}).click();
  await expect(page.locator('ul.products')).not.toContainText('86HS');
  const prices=await page.locator('ul.products .price').allTextContents();
  expect(prices.length).toBeGreaterThan(0);
  for(const price of prices)expect(Number(price.replace(/[^0-9]/g,''))).toBeLessThanOrEqual(1600);
  await page.getByRole('link',{name:'Сбросить фильтры',exact:true}).click();
  await expect(page).not.toHaveURL(/filter_tiporazmer/);
  await expect(page.locator('select.orderby option[value="menu_order"]')).toHaveCount(0);
  await expect(page.locator('select.orderby')).toHaveValue('date');
  await page.locator('select.orderby').selectOption('price');
  await expect(page).toHaveURL(/orderby=price/);
  const sortedPrices=await page.locator('ul.products .price .woocommerce-Price-amount').allTextContents();
  const numbers=sortedPrices.map(price=>Number(price.replace(/[^0-9]/g,'')));
  expect(numbers.length).toBeGreaterThan(1);
  expect(numbers).toEqual([...numbers].sort((a,b)=>a-b));
  await page.locator('#ss-search-input').fill('57HS56-3004');
  await page.getByRole('button',{name:'Найти товар',exact:true}).click();
  await expect(page.getByRole('link',{name:'Шаговый двигатель 57HS56-3004',exact:true}).first()).toBeVisible();
  await page.getByRole('link',{name:'Шаговый двигатель 57HS56-3004',exact:true}).first().click();
  await expect(page).toHaveURL(new RegExp(product));
});

test('Сортировка цены сохраняет фильтр и работает в обе стороны',async({page})=>{
  await page.goto('/catalog/dvigateli/shagovye-dvigateli/?filter_tiporazmer=nema23&max_price=2600');
  for(const order of ['price-desc','price']){
    await page.locator('select.orderby').selectOption(order);
    await expect(page).toHaveURL(new RegExp(`orderby=${order}(?:&|$)`));
    await expect(page).toHaveURL(/filter_tiporazmer=nema23/);
    await expect(page).toHaveURL(/max_price=2600/);
    await expect(page.locator('select.orderby')).toHaveValue(order);
    await expect(page.locator('ul.products')).not.toContainText('86HS');
    const amounts=await page.locator('ul.products .price .woocommerce-Price-amount').allTextContents();
    const prices=amounts.map(amount=>Number(amount.replace(/[^0-9]/g,'')));
    expect(prices.length).toBeGreaterThan(1);
    expect(prices.every(price=>price<=2600)).toBe(true);
    expect(prices).toEqual([...prices].sort((a,b)=>order==='price'?a-b:b-a));
  }
  await page.locator('.ss-filters').evaluate(e=>e.open=true);
  await page.getByRole('spinbutton',{name:'Цена до, ₽',exact:true}).fill('1600');
  await page.getByRole('button',{name:'Применить',exact:true}).click();
  await expect(page).toHaveURL(/orderby=price/);
  await expect(page.locator('select.orderby')).toHaveValue('price');
});

test('Плашки вариаций выбирают штатную модель WooCommerce',async({page})=>{
  await page.goto('/catalog/elektronika/drayvery-shagovyh-dvigateley/drayvera-shagovyh-dvigateley/');
  await page.getByRole('button',{name:'DM422',exact:true}).click();
  await expect(page.locator('#model')).toHaveValue('DM422');
  await expect(page.locator('.ss-variation-options button[aria-pressed="true"]')).toHaveText('DM422');
  await expect(page.locator('input.variation_id')).toHaveValue(/^[1-9][0-9]*$/);
  await expect(page.locator('.single_add_to_cart_button')).toBeEnabled();
});

test('Форма связи: согласие обязательно, письмо доставляется в Mailpit',async({page,request})=>{
  await page.goto('/contacts/');
  const marker='Вопрос этапа 5 '+Date.now();
  await page.getByRole('textbox',{name:'Ваше имя'}).fill('Тест');
  await page.getByRole('textbox',{name:'Email',exact:true}).fill('stage5@example.test');
  await page.getByRole('textbox',{name:'Ваш вопрос'}).fill(marker);
  const consent=page.locator('input[name="personal-data"]');
  await expect(consent).not.toBeChecked();
  await page.getByRole('button',{name:'Отправить вопрос',exact:true}).click();
  await expect(page.locator('.wpcf7-not-valid-tip')).toBeVisible();
  await consent.check();
  await page.getByRole('button',{name:'Отправить вопрос',exact:true}).click();
  await expect(page.locator('.wpcf7-response-output')).toContainText('Спасибо',{timeout:15000});
  await expect.poll(async()=>{
    const data=await (await request.get('http://localhost:8025/api/v1/messages')).json();
    return data.messages.some(m=>m.Subject==='Вопрос в магазин «собери станок»'&&m.To.some(t=>t.Address==='info@example.test'));
  }).toBe(true);
});

for(const width of [375,768,1440])test(`Все шаблоны: H1, изображения и ширина ${width}`,async({page})=>{
  await page.setViewportSize({width,height:900});
  await page.goto(product);
  await page.getByRole('button',{name:'В корзину',exact:true}).click();
  const pages=[['home','/'],['catalog','/catalog/'],['category','/catalog/dvigateli/shagovye-dvigateli/'],['product',product],['variable','/catalog/elektronika/drayvery-shagovyh-dvigateley/drayvera-shagovyh-dvigateley/'],['cart','/cart/'],['checkout','/checkout/'],['search','/?s=57HS56-3004&post_type=product'],['search-empty','/?s=missing-ss-search&post_type=product'],['404','/missing-ss-page/'],['about','/about/'],['contacts','/contacts/'],['delivery','/delivery/'],['privacy','/privacy-policy/'],['consent','/personal-data-consent/'],['offer','/offer/']];
  for(const [name,url] of pages){
    const response=await page.goto(url);expect(response.status()).toBe(name==='404'?404:200);
    if(name==='cart')await expect(page.getByText('Шаговый двигатель 57HS56-3004',{exact:true}).first()).toBeVisible();
    if(name==='checkout')await expect(page.getByText('Оплата при получении',{exact:true})).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.ss-header')).toHaveCount(1);
    await page.evaluate(async()=>{await document.fonts.ready;await Promise.all([...document.images].map(i=>{i.loading='eager';return i.decode().catch(()=>{});}));});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    expect(await page.locator('img').evaluateAll(imgs=>imgs.filter(i=>!i.naturalWidth).length)).toBe(0);
    await page.screenshot({path:`site/data/screenshots/stage5/${name}-${width}.png`,fullPage:true});
  }
});
