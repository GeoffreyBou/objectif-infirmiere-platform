const {test, expect}=require('@playwright/test');
const fs=require('node:fs');
const credentials=JSON.parse(fs.readFileSync('.runtime/browser-user.json','utf8'));

async function login(page) {
  const account=credentials.projects?.[test.info().project.name] || credentials;
  await page.goto('/connexion/');
  await page.locator('#user_login').fill(account.login);
  await page.locator('#user_pass').fill(account.password);
  await page.locator('#wp-submit').click();
  await expect(page).toHaveURL(/\/espace-revision\/$/);
  await expect(page.getByRole('heading',{name:'Bonjour Étudiante Démo.',exact:true})).toBeVisible();
  await expect(page.locator('#oi-results .oi-card')).toHaveCount(10);
}

async function noOverflow(page) {
  expect(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)).toBeFalsy();
}

function memberTab(page, tab) {
  return page.locator(`.oi-main-nav button[data-tab="${tab}"]`);
}

test('espace membre : recherche, favoris, progression, QCM et assistant', async({page})=>{
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await login(page);
  await noOverflow(page);
  await page.screenshot({path:`.runtime/member-${test.info().project.name}.png`,fullPage:true});

  await page.locator('#oi-query').fill('Furosémide');
  await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(page.locator('#oi-results .oi-card')).toHaveCount(1);
  await page.locator('#oi-results .oi-card').click();
  await expect(page.getByRole('heading',{name:'Furosémide',exact:true})).toBeVisible();
  await expect(page.locator('#oi-toc')).toContainText('Vigilance IDE');
  await expect(page.locator('.oi-actions')).toBeVisible();

  const favorite=page.locator('[data-state="favorite"]');
  if (await favorite.getAttribute('aria-pressed')==='true') {
    await favorite.click();
    await expect(favorite).toHaveAttribute('aria-pressed','false');
  }
  await favorite.click();
  await expect(favorite).toHaveAttribute('aria-pressed','true');
  const revised=page.locator('[data-state="revised"]');
  if (await revised.getAttribute('aria-pressed')!=='true') await revised.click();
  await expect(revised).toHaveAttribute('aria-pressed','true');

  await page.locator('#oi-quiz-form input[value="0"]').check();
  await page.getByRole('button',{name:'Voir ma correction'}).click();
  await expect(page.locator('#oi-quiz-result')).toContainText('1 / 1');
  await noOverflow(page);

  // The browser suite exercises a controlled API failure without sending paid AI requests.
  await page.route(url=>url.pathname.endsWith('/oi/v1/ai') || url.searchParams.get('rest_route')==='/oi/v1/ai',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({code:'oi_ai_config',message:'Le Conseiller IA n’est pas encore configuré. Vos fiches restent accessibles.'})}));
  await page.getByRole('button',{name:'Poser une question sur cette fiche'}).click();
  await expect(memberTab(page,'ai')).toHaveAttribute('aria-current','page');
  await expect(page.locator('.oi-chat-context')).toContainText('Furosémide');
  await page.locator('#oi-question').fill('Pourquoi surveiller la kaliémie ?');
  await page.getByRole('button',{name:'Poser ma question',exact:true}).click();
  await expect(page.locator('#oi-status')).toContainText('pas encore configuré');
  await expect(page.getByRole('button',{name:'Poser ma question',exact:true})).toBeEnabled();
  await noOverflow(page);
  await page.screenshot({path:`.runtime/assistant-${test.info().project.name}.png`,fullPage:true});

  await page.getByRole('button',{name:'Mes révisions',exact:false}).click();
  await expect(page.locator('#oi-results .oi-card')).toHaveCount(10);
  await memberTab(page,'favorites').click();
  await expect(page.locator('#oi-results .oi-card')).toHaveCount(1);
  await expect(page.locator('#oi-results .oi-card')).toContainText('Furosémide');

  await memberTab(page,'qcm').click();
  await expect(memberTab(page,'qcm')).toHaveAttribute('aria-current','page');
  await expect(page.getByRole('heading',{name:'Choisis ton prochain défi.',exact:true})).toBeVisible();
  await page.locator('#oi-query').fill('Furosémide');
  await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await expect(page.locator('#oi-results [data-quiz-fiche]')).toHaveCount(1);
  await page.locator('#oi-results [data-quiz-fiche]').click();
  await expect(page.locator('#oi-quiz-form')).toBeVisible();
  await expect(page.locator('.oi-content')).toHaveCount(0);
  await page.locator('#oi-quiz-form input[value="0"]').check();
  await page.getByRole('button',{name:'Voir ma correction'}).click();
  await expect(page.locator('#oi-quiz-result')).toContainText('1 / 1');
  await noOverflow(page);
  await page.screenshot({path:`.runtime/qcm-${test.info().project.name}.png`,fullPage:true});
  await page.getByRole('button',{name:'Tous les QCM',exact:false}).click();
  await expect(page.locator('#oi-results [data-quiz-fiche]')).toHaveCount(1);
  expect(errors).toEqual([]);
});

test('routes protégées refusées sans connexion',async({page, request})=>{
  await page.goto('/espace-revision/');
  await expect(page.locator('#user_login')).toBeVisible();
  await expect(page.locator('#oi-view')).toHaveCount(0);
  const list=await request.get('/?rest_route=/oi/v1/fiches');
  expect(list.status()).toBe(401);
  const native=await request.get('/?rest_route=/wp/v2/oi_fiche');
  expect(native.status()).toBe(404);
  const state=await request.post('/?rest_route=/oi/v1/fiches/1/state',{data:{field:'favorite',value:true}});
  expect(state.status()).toBe(401);
});

test('nonce REST, cache privé et absence de contournement par URL',async({page})=>{
  await login(page);
  const nonce=await page.evaluate(()=>OI.nonce);
  const allowed=await page.request.get('/?rest_route=/oi/v1/fiches',{headers:{'X-WP-Nonce':nonce}});
  expect(allowed.status()).toBe(200);
  expect(allowed.headers()['cache-control']).toContain('no-store');
  const first=(await allowed.json()).items[0].id;
  const missing=await page.request.get(`/?rest_route=/oi/v1/fiches/${first}`);
  expect(missing.status()).toBe(401);
  const forged=await page.request.get(`/?rest_route=/oi/v1/fiches/${first}`,{headers:{'X-WP-Nonce':'forged'}});
  expect(forged.status()).toBe(403);
  const forbidden=await page.request.get('/?rest_route=/oi/v1/fiches/999999',{headers:{'X-WP-Nonce':nonce}});
  expect(forbidden.status()).toBe(403);
  const direct=await page.request.get(`/?post_type=oi_fiche&p=${first}`);
  expect(await direct.text()).not.toContain('<h2>Vigilance IDE</h2>');
});

test('inscription réelle : session élève et accès démo sans élévation de privilèges',async({page})=>{
  test.skip(test.info().project.name!=='desktop','Un seul compte suffit pour vérifier le parcours complet.');
  const email=`oi_e2e_signup_${Date.now()}@example.invalid`;
  // The local test runner removes this exact fixture after the suite, including failed runs.
  fs.writeFileSync('.runtime/registration-test-user.json',JSON.stringify({email}),{mode:0o600});
  await page.goto('/inscription/');
  await page.locator('#oi-register-first-name').fill('Camille Test');
  await page.locator('#oi-register-email').fill(email);
  await page.locator('#oi-register-password').fill('Un-vrai-parcours-de-demo-2026!');
  await page.locator('input[name="consent"]').check();
  await page.locator('input[name="interest"][value="cardio"]').check();
  await page.route('**/wp-admin/admin-post.php',async route=>{
    const data=new URLSearchParams(route.request().postData() || '');
    data.set('role','administrator');
    data.append('oi_packs[]','999999');
    await route.continue({postData:data.toString()});
  });
  await page.getByRole('button',{name:'Créer mon compte gratuit'}).click();
  await expect(page).toHaveURL(/\/espace-revision\/$/);
  await expect(page.getByRole('heading',{name:'Bonjour Camille Test.',exact:true})).toBeVisible();
  await expect(page.locator('#oi-results .oi-card').first()).toBeVisible();
  const nonce=await page.evaluate(()=>OI.nonce);
  const me=await page.request.get('/?rest_route=/wp/v2/users/me&context=edit',{headers:{'X-WP-Nonce':nonce}});
  expect(me.status()).toBe(200);
  const account=await me.json();
  expect(account.roles).toEqual(['oi_etudiant']);
  expect(account.capabilities.manage_options).not.toBeTruthy();
  expect(account.capabilities.edit_posts).not.toBeTruthy();
  const library=await page.request.get('/?rest_route=/oi/v1/fiches',{headers:{'X-WP-Nonce':nonce}});
  expect(library.status()).toBe(200);
  const access=await library.json();
  expect(access.total).toBeGreaterThan(0);
  expect(access.packs.map(pack=>pack.id)).not.toContain(999999);
  await noOverflow(page);
});
