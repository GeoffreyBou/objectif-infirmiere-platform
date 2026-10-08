const {test,expect}=require('@playwright/test');const fs=require('node:fs');const credentials=JSON.parse(fs.readFileSync('.runtime/browser-user.json','utf8'));
test('bibliothèque : UE, thèmes, fiches et historique personnel',async({page})=>{
 test.setTimeout(90000);const user=credentials.projects[test.info().project.name];const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/connexion/');await page.locator('#user_login').fill(user.login);await page.locator('#user_pass').fill(user.password);await page.locator('#wp-submit').click();
 await expect(page.locator('#oi-results .oi-folder-card')).toHaveCount(15);
 await expect(page.locator('.oi-main-nav [data-tab=credits],.oi-sidebar-heading,.oi-dashboard-hero,.oi-progress-panel,.oi-access-details')).toHaveCount(0);
 await expect(page.locator('.oi-library-welcome')).toHaveText('Tes notions de soins infirmiers, un peu plus claires chaque jour.');
 const b1=page.locator('#oi-results [data-folder-name="B1 — Sciences biomédicales"]');await b1.click();
 await expect(page.locator('#oi-results .oi-folder-card')).toHaveCount(17);
 const bio='B1 - 01 Fondements biologiques et développement humain';await page.locator(`#oi-results [data-folder-name="${bio}"]`).click();
 await expect(page.locator('#oi-results .oi-card')).toHaveCount(3);await expect(page.locator('#oi-results .oi-card .oi-eyebrow').first()).toHaveText(bio);
 const hypo=page.locator('#oi-results .oi-card').filter({has:page.getByRole('heading',{name:'Hypokaliémie',exact:true})});await hypo.click();await expect(page.locator('.oi-content')).toBeVisible();
 await expect(page.locator('.oi-reader .oi-ai-banner')).toContainText('Et si on te l’expliquait autrement ?');
 await page.locator('.oi-ai-banner [data-ai]').click();await expect(page.locator('.oi-chat-context')).toContainText('Hypokaliémie');
 await page.locator('.oi-main-nav [data-tab=fiches]').click();await expect(page.locator('#oi-results .oi-folder-card')).toHaveCount(15);
 await expect(page.locator('.oi-recent .oi-card h3').first()).toHaveText('Hypokaliémie');
 await page.locator('#oi-query').fill('Furosémide');await page.locator('#oi-search').getByRole('button',{name:'Rechercher',exact:true}).click();await expect(page.locator('#oi-results .oi-card')).toHaveCount(1);await page.locator('#oi-results .oi-card').click();await expect(page.locator('.oi-content')).toBeVisible();
 await page.locator('.oi-main-nav [data-tab=fiches]').click();await expect(page.locator('.oi-recent .oi-card h3').first()).toHaveText('Furosémide');
 await page.locator('.oi-recent .oi-card').filter({has:page.getByRole('heading',{name:'Hypokaliémie',exact:true})}).click();await expect(page.locator('.oi-content')).toBeVisible();
 await page.locator('.oi-main-nav [data-tab=fiches]').click();await expect(page.locator('.oi-recent .oi-card h3').first()).toHaveText('Hypokaliémie');await expect(page.locator('.oi-recent h3').filter({hasText:/^Hypokaliémie$/})).toHaveCount(1);
 await page.reload();await expect(page.locator('.oi-recent .oi-card h3').first()).toHaveText('Hypokaliémie');
 await page.locator('#oi-results [data-folder-name="A1 — Fondements des sciences infirmières et raisonnement clinique"]').click();await expect(page.locator('#oi-results [data-folder-name^="A1 - 01"]')).toBeVisible();await page.locator('#oi-results [data-folder-name^="A1 - 01"]').click();await expect(page.locator('#oi-results .oi-empty')).toBeVisible();await page.locator('[data-library-level=root]').click();await expect(page.locator('#oi-results .oi-folder-card')).toHaveCount(15);
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
 await page.screenshot({path:`.runtime/library-${test.info().project.name}.png`,fullPage:true});
 if(test.info().project.name==='iPhone'){await page.setViewportSize({width:320,height:740});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();await expect(page.locator('.oi-main-nav [data-tab=progress]')).toBeVisible();}
 expect(errors).toEqual([]);
});
