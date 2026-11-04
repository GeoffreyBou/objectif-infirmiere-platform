const {test,expect}=require('@playwright/test');
const box=text=>`<table><tbody><tr><td>${text}</td></tr></tbody></table>`;
test('schémas : conserver les tableaux comparatifs, les branches et les flèches dans le texte',async({page})=>{
 await page.setContent(`<main>${box('<p>Isolé</p>')}<p>Un texte → à conserver.</p><table><tr><td>Branche A</td><td>Branche B</td></tr></table><p>↓</p>${box('<p>Suite du tableau</p>')}<p>Fonction ↓</p>${box('<p>Autre encart</p>')}<p>→</p>${box('<p>Orientation horizontale</p>')}</main>`);
 await page.addScriptTag({path:'plugin/objectif-infirmiere/assets/reader-diagrams.js'});const before=await page.locator('main').innerHTML();expect(await page.evaluate(()=>OIReaderDiagrams.enhance(document.querySelector('main')))).toBe(0);expect(await page.locator('main').innerHTML()).toBe(before);
});
test('schémas : suites séparées, texte préservé et formatage idempotent',async({page})=>{
 await page.setContent(`<main>${box('<p><strong>Étape A</strong></p><p>Précision.</p>')}<p><span>↓</span></p>${box('<p>Étape B</p>')}<h2>Deuxième schéma</h2>${box('<p>Étape C</p>')}<p>⬇️</p>${box('<p>Étape D</p>')}</main>`);await page.addScriptTag({path:'plugin/objectif-infirmiere/assets/reader-diagrams.js'});
 expect(await page.evaluate(()=>OIReaderDiagrams.enhance(document.querySelector('main')))).toBe(2);await expect(page.locator('.oi-flow-number')).toHaveText(['01','02','01','02']);await expect(page.locator('.oi-flow-copy')).toHaveText(['Étape APrécision.','Étape B','Étape C','Étape D']);const before=await page.locator('main').innerHTML();expect(await page.evaluate(()=>OIReaderDiagrams.enhance(document.querySelector('main')))).toBe(0);expect(await page.locator('main').innerHTML()).toBe(before);
});
