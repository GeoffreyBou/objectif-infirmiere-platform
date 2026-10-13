const {test,expect}=require('@playwright/test');
const fs=require('node:fs');
const users=JSON.parse(fs.readFileSync('.runtime/browser-user.json','utf8'));
const path=url=>url.searchParams.get('rest_route')||url.pathname.replace(/^.*\/wp-json/,'');
async function login(page){const c=users.projects[test.info().project.name];await page.goto('/connexion/');await page.locator('#user_login').fill(c.login);await page.locator('#user_pass').fill(c.password);await page.locator('#wp-submit').click();await expect(page.locator('#oi-query')).toBeVisible();}
async function account(page,state){
 await page.route(url=>path(url)==='/oi/v1/account',async route=>{
  const response=await route.fetch(),data=await response.json();
  await route.fulfill({response,json:{...data,premium:state.premium,ai_available:true,balances:{...data.balances,IA:state.balance},recharge:{credits:100,amount:1000,currency:'EUR',payment_mode:'test',available:true}}});
 });
}
test('recharge : uniquement Premium à zéro, dernier crédit puis paiement confirmé',async({page})=>{
 test.setTimeout(90000);const state={premium:true,balance:1};await account(page,state);
 await page.route(url=>path(url)==='/oi/v1/ai',async route=>{state.balance=0;await route.fulfill({json:{text:'Une réponse pédagogique.',sources:[{id:1,title:'Source pédagogique'}],balance:0}});});
 await login(page);await expect(page.locator('.oi-sidebar-unlock')).toBeHidden();
 await page.goto('/espace-revision/?view=recharge');await expect(page.locator('#oi-chat')).toBeVisible();await expect(page.locator('.oi-recharge-page')).toHaveCount(0);
 await expect(page.locator('[data-recharge-shortcut]')).toBeHidden();await expect(page.locator('[data-balance=IA]')).toHaveCount(1);await expect(page.locator('[data-balance=IA]')).toHaveText('1');
 await page.locator('#oi-question').fill('Aide-moi à comprendre cette notion.');await page.locator('#oi-chat button[type=submit]').click();
 await expect(page.locator('[data-balance=IA]')).toHaveText('0');await expect(page.locator('[data-recharge-shortcut]')).toBeVisible();
 const nav=page.locator('.oi-sidebar-unlock');await expect(nav).toHaveAttribute('data-tab','recharge');await expect(nav).toContainText('Recharger mon assistant');await expect(nav).not.toContainText('Passer Premium');
 if(test.info().project.name==='desktop')await expect(nav).toBeVisible();
 await page.locator('[data-recharge-shortcut]').click();await expect(page.locator('.oi-recharge-page')).toBeVisible();await expect(page.locator('.oi-unlock-price strong')).toHaveText('10 €');await expect(page.locator('.oi-recharge-page')).toContainText('100 nouvelles questions.');
 await expect(page.locator('[data-buy-recharge]')).toBeEnabled();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();await page.screenshot({path:'.runtime/recharge-'+test.info().project.name+'.png',fullPage:true});
 let checkouts=0;await page.route(url=>path(url)==='/oi/v1/ai-recharge/checkout',async route=>{checkouts++;expect(route.request().method()).toBe('POST');await route.fulfill({json:{url:'https://checkout.stripe.com/c/pay/cs_test_browser'}});});
 await page.route('https://checkout.stripe.com/**',route=>route.fulfill({contentType:'text/html',body:'Checkout simulé, aucun paiement réel'}));
 await page.locator('[data-buy-recharge]').click();await expect(page).toHaveURL(/checkout\.stripe\.com/);expect(checkouts).toBe(1);
 let confirmed=false;await page.route(url=>path(url)==='/oi/v1/ai-recharge/status',async route=>{if(confirmed)state.balance=100;await route.fulfill({json:{status:confirmed?'paid':'pending',credits:100}});});
 await page.goto('/espace-revision/?view=ai&oi_payment=recharged&session_id=cs_test_browser');await expect(page.locator('.oi-payment-notice')).toContainText('attendons');
 // Already being Premium is not confirmation of this separate recharge.
 await expect(page.locator('[data-balance=IA]')).toHaveText('0');await expect(page.locator('.oi-payment-notice')).not.toContainText('Recharge confirmée');
 confirmed=true;await expect(page.locator('.oi-payment-notice')).toContainText('Recharge confirmée',{timeout:15000});await expect(page.locator('[data-balance=IA]')).toHaveText('100');await expect(page.locator('[data-balance=IA]')).toHaveCount(1);await expect(nav).toBeHidden();await expect(page.locator('[data-recharge-shortcut]')).toBeHidden();
 await page.goto('/espace-revision/?view=premium');await expect(page.locator('#oi-query')).toBeVisible();await expect(page.locator('.oi-premium-page')).toHaveCount(0);
});
test('compte gratuit sans crédits : Premium proposé, aucune recharge IA',async({page})=>{
 const state={premium:false,balance:0};await account(page,state);await login(page);await page.goto('/espace-revision/?view=ai');await expect(page.locator('[data-balance=IA]')).toHaveText('0');await expect(page.locator('[data-recharge-shortcut]')).toBeHidden();await expect(page.locator('.oi-sidebar-unlock')).toHaveAttribute('data-tab','premium');
 await page.goto('/espace-revision/?view=recharge');await expect(page.locator('.oi-unlock-hero h1')).toContainText('Accède à tout');await expect(page.locator('[data-buy-recharge]')).toHaveCount(0);
});
