const MODELS = [
  { m: "TDK65-0.8F-24K", kw: "0,8", br: "7002×2, 7000×2", col: "ER11", d: 65, price: 12000 },
  { m: "TDK65-1.2F-24K", kw: "1,2", br: "7002×2, 7000×2", col: "ER11", d: 65, price: 13000 },
  { m: "TDK80-1.5FC-24K", kw: "1,5", br: "7004×2, 7000×2", col: "ER16", d: 80, price: 14000 },
  { m: "TDK80-2.2FC-24K", kw: "2,2", br: "7005×2, 7002×2", col: "ER20", d: 80, price: 18000 },
];

const rub = n => n.toLocaleString("ru-RU") + " ₽";
const aOpts = document.getElementById("a-opts");
aOpts.innerHTML = MODELS.map((m, i) => `<button class="opt" aria-pressed="${i === 0}" data-i="${i}"><b>${m.m}</b><span>${m.kw} кВт · ${m.col} · ${rub(m.price)}</span></button>`).join("");
const aKeys = document.getElementById("a-keys");
const aTable = document.getElementById("a-table");
function setA(i) {
  const m = MODELS[i];
  aOpts.querySelectorAll(".opt").forEach(b => b.setAttribute("aria-pressed", b.dataset.i == i));
  document.getElementById("a-price").textContent = rub(m.price);
  document.getElementById("a-sku").textContent = m.m;
  aKeys.innerHTML = [["Мощность", m.kw + " кВт"], ["Цанга", m.col], ["Диаметр корпуса", m.d + " мм"], ["Частота вращения", "24 000 об/мин"], ["Подшипники", m.br]]
    .map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join("");
  aTable.innerHTML = `<thead><tr><th>Модель</th><th>Мощность, кВт</th><th>Подшипники</th><th>Напряжение, В</th><th>Цанга</th><th>Об/мин</th><th>Диаметр, мм</th><th>Цена</th></tr></thead><tbody>` +
    MODELS.map((r, j) => `<tr class="${j === i ? "on" : ""}"><td>${r.m}</td><td>${r.kw}</td><td>${r.br}</td><td>220</td><td>${r.col}</td><td>24 000</td><td>${r.d}</td><td>${rub(r.price)}</td></tr>`).join("") + `</tbody>`;
}
aOpts.addEventListener("click", e => { const b = e.target.closest(".opt"); if (b) setA(+b.dataset.i); });
setA(0);


let quantity = 1;
document.querySelectorAll('.qty button').forEach((b,i)=>b.addEventListener('click',()=>{quantity=Math.max(1,quantity+(i?1:-1));document.getElementById('quantity').textContent=quantity;document.querySelector('.qty button').disabled=quantity===1;}));
document.getElementById('demo-add').addEventListener('click',()=>{document.getElementById('demo-status').textContent=`В макете выбрано: ${document.getElementById('a-sku').textContent}, ${quantity} шт.`;});
const tabs=Array.from(document.querySelectorAll('[role="tab"]'));
function selectTab(t) {tabs.forEach(b=>{const on=b===t;b.setAttribute('aria-selected',on);b.tabIndex=on?0:-1;document.getElementById(b.getAttribute('aria-controls')).hidden=!on;});}
tabs.forEach((t,i)=>{t.addEventListener('click',()=>selectTab(t));t.addEventListener('keydown',e=>{let next;if(e.key==='ArrowRight')next=(i+1)%tabs.length;if(e.key==='ArrowLeft')next=(i+tabs.length-1)%tabs.length;if(e.key==='Home')next=0;if(e.key==='End')next=tabs.length-1;if(next!==undefined){e.preventDefault();selectTab(tabs[next]);tabs[next].focus();}});});
