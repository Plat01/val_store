/** Проверка режима запуска с обязательным восстановлением blog_public. */
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fs=require('node:fs');
const root=path.resolve(__dirname,'../..');
const wp=(...args)=>execFileSync(path.join(root,'site/bin/wp'),args,{cwd:path.join(root,'site'),encoding:'utf8'}).trim();
const previous=wp('option','get','blog_public');
const pages={home:'/',category:'/catalog/dvigateli/shagovye-dvigateli/',product:'/catalog/dvigateli/shagovye-dvigateli/dvigatel-57hs56-3004/'};
fs.mkdirSync(path.join(root,'site/data/lighthouse'),{recursive:true});
try {
  wp('option','update','blog_public','1');
  for(const [name,url] of Object.entries(pages)) {
    const output=path.join(root,'site/data/lighthouse',name+'-launch.json');
    execFileSync(path.join(root,'node_modules/.bin/lighthouse'),[
      'http://localhost:8080'+url,'--only-categories=performance,seo,accessibility','--form-factor=mobile','--quiet','--chrome-flags=--headless --no-sandbox','--output=json','--output-path='+output
    ],{cwd:root,stdio:'inherit'});
    const report=JSON.parse(fs.readFileSync(output,'utf8'));
    const scores=Object.fromEntries(Object.entries(report.categories).map(([key,v])=>[key,Math.round(v.score*100)]));
    console.log(name,scores);
    if(scores.performance<90||scores.seo<90||scores.accessibility<95)process.exitCode=1;
  }
} finally { wp('option','update','blog_public',previous); }
