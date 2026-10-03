const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const credentials=JSON.parse(fs.readFileSync('.runtime/browser-user.json','utf8'));
test('connexion, recherche, lecture, favoris, révision, quiz et panne IA',async({page})=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto('/');
 await expect(page.getByRole('heading',{name:'Se connecter',exact:true})).toBeVisible();
 await page.locator('#user_login').fill(credentials.login);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();
 await expect(page.getByRole('heading',{name:'Bonjour Étudiante Démo.'})).toBeVisible();
 await expect(page.locator('#oi-results .oi-card')).toHaveCount(10);
 await page.locator('#oi-query').fill('Furosémide');await page.getByRole('button',{name:'Rechercher',exact:true}).click();await expect(page.locator('#oi-results .oi-card')).toHaveCount(1);
 await page.locator('#oi-results .oi-card').click();
 await expect(page.getByRole('heading',{name:'Furosémide',exact:true})).toBeVisible();await expect(page.locator('#oi-toc')).toContainText('Vigilance IDE');
 await expect(page.locator('.oi-actions')).toBeVisible();
 const favorite=page.locator('[data-state="favorite"]');if(await favorite.getAttribute('aria-pressed')==='true'){await favorite.click();await expect(favorite).toHaveAttribute('aria-pressed','false');}await favorite.click();await expect(favorite).toHaveAttribute('aria-pressed','true');
 const revised=page.locator('[data-state="revised"]');if(await revised.getAttribute('aria-pressed')!=='true'){await revised.click();}await expect(revised).toHaveAttribute('aria-pressed','true');
 await page.locator('#oi-quiz-form input[value="0"]').check();await page.getByRole('button',{name:'Voir ma correction'}).click();await expect(page.locator('#oi-quiz-result')).toContainText('1 / 1');
 const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);expect(overflow).toBeFalsy();
 await page.getByRole('button',{name:'Poser une question sur cette fiche'}).click();await page.locator('#oi-question').fill('Pourquoi surveiller la kaliémie ?');await page.getByRole('button',{name:'Poser ma question',exact:true}).click();await expect(page.locator('#oi-status')).toContainText('pas encore configuré');
 await page.getByRole('button',{name:'Mes révisions',exact:false}).click();await expect(page.locator('#oi-results .oi-card')).toHaveCount(10);
 await page.getByRole('button',{name:'Mes favoris'}).click();await expect(page.locator('#oi-results .oi-card')).toHaveCount(1);
 expect(errors).toEqual([]);
 await page.screenshot({path:`.runtime/${test.info().project.name}.png`,fullPage:true});
});
test('routes protégées refusées sans connexion',async({request})=>{
 const list=await request.get('/?rest_route=/oi/v1/fiches');expect(list.status()).toBe(401);
 const native=await request.get('/?rest_route=/wp/v2/oi_fiche');expect(native.status()).toBe(404);
 const state=await request.post('/?rest_route=/oi/v1/fiches/1/state',{data:{field:'favorite',value:true}});expect(state.status()).toBe(401);
});
test('nonce REST, cache privé et absence de contournement par URL',async({page})=>{
 await page.goto('/');await page.locator('#user_login').fill(credentials.login);await page.locator('#user_pass').fill(credentials.password);await page.locator('#wp-submit').click();await expect(page.locator('#oi-results .oi-card')).toHaveCount(10);
 const nonce=await page.evaluate(()=>OI.nonce);
 const allowed=await page.request.get('/?rest_route=/oi/v1/fiches',{headers:{'X-WP-Nonce':nonce}});expect(allowed.status()).toBe(200);expect(allowed.headers()['cache-control']).toContain('no-store');
 const first=(await allowed.json()).items[0].id;
 const missing=await page.request.get(`/?rest_route=/oi/v1/fiches/${first}`);expect(missing.status()).toBe(401);
 const forged=await page.request.get(`/?rest_route=/oi/v1/fiches/${first}`,{headers:{'X-WP-Nonce':'forged'}});expect(forged.status()).toBe(403);
 const forbidden=await page.request.get('/?rest_route=/oi/v1/fiches/999999',{headers:{'X-WP-Nonce':nonce}});expect(forbidden.status()).toBe(403);
 const direct=await page.request.get(`/?post_type=oi_fiche&p=${first}`);expect(await direct.text()).not.toContain('<h2>Vigilance IDE</h2>');
});
