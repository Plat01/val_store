const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const path=require('node:path');
const product='/catalog/dvigateli/shagovye-dvigateli/dvigatel-57hs56-3004/';

test('Все импортированные URL открываются; старые пути дают один 301',async({request})=>{
  const rows=fs.readFileSync(process.env.URL_MAP || path.join(__dirname,'../data/url-map.csv'),'utf8').trim().split('\n').slice(1);
  expect(rows.length).toBeGreaterThan(100);
  for(const row of rows){
    const [type,oldId,newId,url,oldUrl]=row.split(',');
    const response=await request.get(url,{maxRedirects:0});
    expect(response.status(),url).toBe(200);
    const oldResponse=await request.get(oldUrl,{maxRedirects:0});
    expect(oldResponse.status(),oldUrl).toBe(301);
    expect(new URL(oldResponse.headers().location).pathname,oldUrl).toBe(url);
  }
});

test('Каталог → товар → корзина → самовывоз/оплата при получении → письмо',async({page,request})=>{
  await page.goto('/catalog/dvigateli/shagovye-dvigateli/');
  await page.getByRole('link',{name:'Шаговый двигатель 57HS56-3004',exact:true}).first().click();
  await expect(page).toHaveURL(new RegExp(product));
  await page.getByRole('button',{name:'В корзину',exact:true}).click();
  await page.goto('/cart/');
  await expect(page.getByText('Шаговый двигатель 57HS56-3004',{exact:true}).first()).toBeVisible();
  await page.getByRole('link',{name:'Перейти к оформлению заказа'}).click();
  await page.locator('#email').fill(`playwright-${Date.now()}@example.test`);
  await page.locator('#billing-first_name').fill('Тест');
  await page.locator('#billing-last_name').fill('Playwright');
  await page.locator('#billing-phone').fill('+79000000000');
  await expect(page.getByText('Оплата при получении',{exact:true})).toBeVisible();
  await expect(page.getByText('Самовывоз (ул. Примерная, д. 1)',{exact:true})).toBeVisible();
  await expect(page.locator('#order-soberi-stanok-personal-data-consent')).not.toBeChecked();
  await page.locator('#terms-and-conditions').check();
  await page.getByRole('button',{name:'Оформить заказ',exact:true}).click();
  await expect(page.getByText('Подтвердите отдельное согласие на обработку персональных данных.',{exact:true})).toBeVisible();
  await expect(page).not.toHaveURL(/order-received/);
  await page.locator('#order-soberi-stanok-personal-data-consent').check();
  await page.getByRole('button',{name:'Оформить заказ',exact:true}).click();
  await expect(page).toHaveURL(/order-received/, {timeout:45000});
  await expect(page.getByRole('heading',{name:'Заказ принят',exact:true})).toBeVisible();
  const orderId=new URL(page.url()).pathname.match(/order-received\/(\d+)/)[1];
  await expect.poll(async()=>{
    const response=await request.get((process.env.MAILPIT_URL || 'http://localhost:8025') + '/api/v1/messages');
    const data=await response.json();
    return data.messages.some(m=>m.Subject.toLowerCase().includes('заказ') && m.Subject.includes(orderId) && m.To.some(t=>t.Address==='orders@example.test'));
  },{timeout:15000}).toBe(true);
});

test('Импортированная вариация выбирается и добавляется в корзину',async({page})=>{
  await page.goto('/catalog/elektronika/drayvery-shagovyh-dvigateley/drayvera-shagovyh-dvigateley/');
  await page.locator('#model').selectOption({label:'DM422'});
  await expect(page.locator('input.variation_id')).toHaveValue(/^[1-9][0-9]*$/);
  await expect(page.locator('.single_variation')).toBeVisible();
  await expect(page.locator('button.single_add_to_cart_button')).toBeEnabled();
  await expect(page.locator('button.single_add_to_cart_button')).not.toHaveClass(/disabled/);
  await page.locator('button.single_add_to_cart_button').click();
  await expect(page.locator('.woocommerce-notices-wrapper')).toContainText('корзину');
  await page.goto('/cart/');
  await expect(page.getByText('DM422',{exact:false}).first()).toBeVisible();
});

for(const width of [375,768,1440]){
 test(`Playwright: каталог/товар/корзина при ширине ${width}`,async({page})=>{
   await page.setViewportSize({width,height:900});
   for(const [name,url] of [['catalog','/catalog/'],['product',product],['cart','/cart/']]){
     const response=await page.goto(url); expect(response.status()).toBe(200);
     await expect(page.locator('body')).not.toContainText('Fatal error');
     await page.locator('img').evaluateAll(images=>Promise.all(images.map(img=>img.decode().catch(()=>{}))));
     await page.screenshot({path:`site/data/screenshots/${name}-${width}.png`,fullPage:true});
   }
 });
}
