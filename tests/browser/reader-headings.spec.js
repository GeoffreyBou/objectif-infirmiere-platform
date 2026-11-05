const{test,expect}=require('@playwright/test');const{execFileSync}=require('node:child_process');
const enhance=async(page,source)=>{await page.setContent('<main>'+source+'</main>');await page.addScriptTag({path:'plugin/objectif-infirmiere/assets/reader-headings.js'});return page.evaluate(()=>OIReaderHeadings.enhance(document.querySelectorAll('h2,h3')));};
const titles=['Repères essentiels','Points clés','Mise en pratique','Sécurité et limites','Suivi et réévaluation','Rôle infirmier','Schéma de synthèse'];
const source=titles.map((t,i)=>`<h2>${i<6?(i+1)+'  ':''}${t}</h2><p>Contenu conservé.</p>`).join('');
test('numérotation Word sans ponctuation : séquence unique, liens conservés et traitement idempotent',async({page})=>{
 const labels=await enhance(page,source.replace('1  Repères essentiels',' <strong>1</strong><span>  </span><a href="https://example.org/source">Repères essentiels</a>'));
 expect(labels.map(h=>h.title)).toEqual(titles);expect(labels.map(h=>h.number)).toEqual(['01','02','03','04','05','06','07']);await expect(page.locator('h2 .oi-section-title-text')).toHaveText(titles);await expect(page.locator('h2 a')).toHaveAttribute('href','https://example.org/source');await expect(page.locator('p')).toHaveText(titles.map(()=>'Contenu conservé.'));
 const before=await page.locator('main').innerHTML();expect(await page.evaluate(()=>OIReaderHeadings.enhance(document.querySelectorAll('h2,h3')))).toEqual(labels);expect(await page.locator('main').innerHTML()).toBe(before);
});
test('nombres significatifs, années, dosages et titres ambigus restent dans le texte',async({page})=>{
 const text=['1 personne sur 2','2 types de douleur','3 phases de cicatrisation','4 jours de surveillance','5 étapes du soin','6 secondes','30 secondes pour agir','2026 : le référentiel','1.5 mg de traitement','2 à 3 prises par jour','3/4 de dose'];
 const labels=await enhance(page,text.map(t=>`<h2>${t}</h2>`).join(''));expect(labels.map(h=>h.title)).toEqual(text);
 const isolated=await enhance(page,'<h2>1 priorité clinique</h2><h2>Surveillance</h2>');expect(isolated[0].title).toBe('1 priorité clinique');
});
test('sections et sous-sections : formats mixtes et nombres cliniques conservés',async({page})=>{
 const labels=await enhance(page,'<h2>1. Définition</h2><h3>1.1  Repères</h3><h3>1.2  Surveillance</h3><h2>2  Pratique</h2><h3>1  Évaluation</h3><h3>2  Transmission</h3><h2>3 — Synthèse</h2><h3>3.1 mg de traitement</h3>');
 expect(labels.map(h=>h.title)).toEqual(['Définition','Repères','Surveillance','Pratique','Évaluation','Transmission','Synthèse','3.1 mg de traitement']);expect(labels.map(h=>h.number)).toEqual(['01','1.1','1.2','02','01','02','03','3.1']);
});
test('fiche : même libellé nettoyé dans le cours et le sommaire, persistant après F5',async({page})=>{
 test.setTimeout(90000);const c=JSON.parse(execFileSync('scripts/dc.sh',['wp','eval-file','/tests/browser-qcm.php','setup'],{encoding:'utf8'}));
 try{
  await page.route(url=>{const p=url.searchParams.get('rest_route')||url.pathname.replace(/^.*\/wp-json/,'');return p==='/oi/v1/fiches/'+c.fiches[0];},async route=>{const r=await route.fetch();await route.fulfill({response:r,json:{...await r.json(),content:source,quiz:[],quiz_count:0}});});
  await page.goto('/connexion/');await page.locator('#user_login').fill(c.login);await page.locator('#user_pass').fill(c.password);await page.locator('#wp-submit').click();await expect(page.locator('#oi-library-title')).toBeVisible();await page.goto('/espace-revision/?view=fiches&fiche='+c.fiches[0]);
  await expect(page.locator('.oi-section-title-text')).toHaveText(titles);await expect(page.locator('#oi-toc .oi-toc-title')).toHaveText(titles);await expect(page.locator('.oi-section-number')).toHaveText(['01','02','03','04','05','06','07']);await page.locator('#oi-toc a').nth(2).click();await expect(page).toHaveURL(/#oi-section-2$/);await page.reload();await expect(page.locator('.oi-section-title-text')).toHaveText(titles);await expect(page.locator('#oi-toc .oi-toc-title')).toHaveText(titles);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
 }finally{execFileSync('scripts/dc.sh',['wp','eval-file','/tests/browser-qcm.php','cleanup',String(c.id),String(c.student.id)],{encoding:'utf8'});}
});
