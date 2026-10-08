const {test,expect}=require('@playwright/test');const fs=require('node:fs');const {confirmEmail}=require('./mail-helper');
test('freemium : vérifier l’e-mail, choisir fiches et QCM, conserver crédits et accès',async({page})=>{
 // Full registration, mail confirmation and two credit-backed paths include multiple page loads.
 test.setTimeout(90000);
 test.skip(test.info().project.name!=='desktop'&&test.info().project.name!=='iPhone','Parcours complet sur ordinateur et téléphone.');
 const email=`oi_e2e_freemium_${Date.now()}@example.invalid`;const tracking='.runtime/freemium-test-users.json';
 const fixtures=fs.existsSync(tracking)?JSON.parse(fs.readFileSync(tracking,'utf8')):[];fixtures.push(email);fs.writeFileSync(tracking,JSON.stringify(fixtures),{mode:0o600});
 await page.goto('/inscription/');await page.locator('#oi-register-first-name').fill('Camille Freemium');await page.locator('#oi-register-email').fill(email);await page.locator('#oi-register-password').fill('Une-phrase-locale-2026-fiable!');await page.locator('input[name=consent]').check();await page.getByRole('button',{name:'Créer mon compte gratuit',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Vérifie ton adresse e-mail.'})).toBeVisible();
 const nonce=await page.evaluate(()=>OI.nonce);let response=await page.request.get('/?rest_route=/oi/v1/fiches',{headers:{'X-WP-Nonce':nonce}});expect(response.status()).toBe(403);
 const verifyUrl=await confirmEmail(page,email);
 await expect(page.locator('[data-balance=FICHE]')).toHaveText('5');await expect(page.locator('[data-balance=QCM]')).toHaveText('5');await expect(page.locator('[data-balance=IA]')).toHaveText('5');
 await page.screenshot({path:`.runtime/freemium-dashboard-${test.info().project.name}.png`,fullPage:true});
 await page.goto(verifyUrl);await page.getByRole('button',{name:'Confirmer mon adresse',exact:true}).click();await expect(page.locator('[data-balance=FICHE]')).toHaveText('5');
 const first=page.locator('#oi-results [data-unlock][data-kind=FICHE]').first();const id=await first.getAttribute('data-unlock');await first.click();await expect(page.locator('.oi-content')).toBeVisible();await expect(page.locator('#oi-quiz-form')).toHaveCount(0);await page.getByRole('button',{name:'Mes révisions',exact:false}).click();await expect(page.locator('[data-balance=FICHE]')).toHaveText('4');
 await page.locator(`#oi-results [data-fiche="${id}"]`).click();await page.getByRole('button',{name:'Mes révisions',exact:false}).click();await expect(page.locator('[data-balance=FICHE]')).toHaveText('4');
 await page.locator('.oi-main-nav [data-tab=qcm]').click();await page.locator('#oi-results [data-unlock][data-kind=QCM]').first().click();await expect(page.locator('#oi-quiz-form')).toBeVisible();await expect(page.locator('.oi-content')).toHaveCount(0);await page.locator('#oi-quiz-form input[value="0"]').check();await page.getByRole('button',{name:'Voir ma correction'}).click();await expect(page.locator('#oi-quiz-result')).not.toBeEmpty();
 await page.getByRole('button',{name:'Tous les QCM',exact:false}).click();await expect(page.locator('[data-balance=QCM]')).toHaveText('4');
 await page.locator('#oi-results [data-quiz-fiche]').first().click();await page.getByRole('button',{name:'Tous les QCM',exact:false}).click();await expect(page.locator('[data-balance=QCM]')).toHaveText('4');
 await page.locator('.oi-main-nav [data-tab=ai]').click();await expect(page.locator('[data-balance=IA]')).toHaveText('5');await expect(page.locator('.oi-chat-availability')).toBeVisible();await expect(page.getByRole('button',{name:'Poser ma question',exact:true})).toBeDisabled();
 await page.locator('.oi-wallet-actions [data-tab=premium]').click();await expect(page.locator('.oi-premium-price')).toContainText('59 €');await expect(page.locator('.oi-premium-page')).toContainText('100 crédits IA supplémentaires');
 await page.screenshot({path:`.runtime/freemium-premium-${test.info().project.name}.png`,fullPage:true});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
});
