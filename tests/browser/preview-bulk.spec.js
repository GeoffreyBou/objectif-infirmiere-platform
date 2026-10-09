const {test,expect}=require('@playwright/test');const {execFileSync}=require('node:child_process');
test('100 aperçus réels : erreur isolée, reprise et comparaisons à la demande',async({page})=>{
 test.setTimeout(180000);const wp=(...args)=>execFileSync('scripts/dc.sh',['wp','eval-file','/tests/browser-imports.php',...args],{encoding:'utf8'});const c=JSON.parse(wp('setup'));
 try{
 const f=JSON.parse(wp('bulk',String(c.id)));let calls=0;page.on('request',r=>{if(r.method()==='POST'&&decodeURIComponent(r.url()).includes('/preview'))calls++;});
 await page.goto('/connexion/');await page.locator('#user_login').fill(c.login);await page.locator('#user_pass').fill(c.password);await page.locator('#wp-submit').click();await expect(page.locator('#oi-library-title')).toBeVisible({timeout:30000});await page.locator('[data-tab=imports]').click();await expect(page.locator('[data-stage]')).toHaveCount(100,{timeout:30000});await page.locator('[data-select-all]').click();await page.locator('[data-preview-selected]').click();await expect(page.locator('#oi-import-status')).toContainText('99 aperçu(s) prêt(s) sur 100',{timeout:120000});expect(calls).toBe(100);await expect(page.locator('[data-preview-output] iframe')).toHaveCount(0);await expect(page.locator('[data-publish-one]:enabled')).toHaveCount(100);
 const bad=page.locator(`[data-stage="${f.ids[99]}"]`);await expect(bad.locator('[role=alert]')).toContainText('UE');await bad.locator('[data-field=unit]').selectOption(String(f.unit));await bad.locator('[data-field=theme]').selectOption(String(f.theme));await page.locator('[data-preview-selected]').click();await expect(page.locator('#oi-import-status')).toContainText('100 aperçu(s) prêt(s) sur 100');expect(calls).toBe(101);
 await expect(page.locator('[data-publish-selected]')).toBeEnabled();await expect(page.locator('[data-publish-selected]')).toHaveText('Publier la sélection (100)');await page.locator('[data-publish-selected]').click();await expect(page.locator('#oi-import-status')).toContainText('100 fiche(s) publiée(s).',{timeout:120000});await expect(page.locator('[data-stage]')).toHaveCount(0);

 }finally{wp('delete',String(c.id));}
});
