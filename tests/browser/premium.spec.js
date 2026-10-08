const {test,expect}=require('@playwright/test');
const fs=require('node:fs');const {execFile}=require('node:child_process');const {promisify}=require('node:util');
const run=promisify(execFile);const credentials=JSON.parse(fs.readFileSync('.runtime/browser-user.json','utf8'));
test('retour de paiement : attendre le webhook, afficher le Premium et préserver la progression',async({page})=>{
 test.skip(!['desktop','iPhone'].includes(test.info().project.name),'Activation sur ordinateur et téléphone.');test.setTimeout(90000);
 const user=credentials.projects[test.info().project.name];
 const fixture=action=>run('scripts/dc.sh',['wp','eval-file','/tests/browser-premium.php',String(user.id),action],{timeout:60000});
 await page.goto('/connexion/');await page.locator('#user_login').fill(user.login);await page.locator('#user_pass').fill(user.password);await page.locator('#wp-submit').click();await expect(page.locator('#oi-results .oi-folder-card').first()).toBeVisible();await expect(page.locator('.oi-wallet')).toHaveCount(0);
 const nonce=await page.evaluate(()=>OI.nonce);const before=await(await page.request.get('/?rest_route=/oi/v1/account',{headers:{'X-WP-Nonce':nonce}})).json();expect(before.premium).toBe(false);
 try{
  await page.goto('/espace-revision/?oi_payment=received');await expect(page.locator('.oi-payment-notice')).toContainText('attendons');expect(before.balances.FICHE).toBe(5);
  await fixture('grant');
  await expect(page.locator('.oi-payment-notice')).toContainText('Premium est actif',{timeout:20000});
  await expect(page.locator('.oi-main-nav [data-tab=credits]')).toHaveCount(0);
  await expect(page.locator('.oi-main-nav [data-tab=premium]')).toHaveCount(0);
  await expect(page.locator('.oi-sidebar-unlock')).toBeHidden();
  const after=await(await page.request.get('/?rest_route=/oi/v1/account',{headers:{'X-WP-Nonce':nonce}})).json();expect(after.progress).toEqual(before.progress);expect(after.premium).toBe(true);expect(after.balances.IA).toBe(105);
  await page.screenshot({path:`.runtime/freemium-active-${test.info().project.name}.png`,fullPage:true});
 }finally{await fixture('cleanup');}
});
