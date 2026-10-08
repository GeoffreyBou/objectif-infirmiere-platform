const {test, expect}=require('@playwright/test');

async function noOverflow(page) {
  expect(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)).toBeFalsy();
}

test('accueil : offre, démonstration corrigée, FAQ et navigation publique',async({page})=>{
  test.setTimeout(90000);
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.goto('/');
  await expect(page.locator('h1')).toContainText('Tes cours d’IFSI');
  await expect(page.locator('.oi-offer-grid article')).toHaveCount(3);
  const nav=page.getByRole('navigation',{name:'Navigation principale',exact:true});
  await expect(nav.getByRole('link',{name:'Se connecter',exact:true})).toBeVisible();
  await expect(nav.getByRole('link',{name:'S’inscrire',exact:false})).toBeVisible();
  await noOverflow(page);
  await page.screenshot({path:`.runtime/landing-${test.info().project.name}.png`,fullPage:true});

  await page.getByRole('link',{name:'Tester une révision',exact:false}).click();
  await expect(page).toHaveURL(/#demo$/);
  const panel=page.locator('#oi-demo-panel');
  const feedback=panel.locator('.oi-demo-feedback');
  const topics=[
    {name:'hygiene',question:'Le port de gants remplace-t-il',wrong:'0',correct:'1',explanation:'Les gants ne dispensent pas'},
    {name:'cardio',question:'Quel est le rôle des artères',wrong:'1',correct:'0',explanation:'sens de circulation'},
    {name:'calculs',question:'0,5 litre correspond',wrong:'0',correct:'1',explanation:'500 mL'},
  ];
  for(const topic of topics) {
    const button=page.locator(`[data-demo-topic="${topic.name}"]`);
    await button.click();
    await expect(button).toHaveAttribute('aria-pressed','true');
    await expect(page.locator('[data-demo-topic][aria-pressed="true"]')).toHaveCount(1);
    await expect(panel).toContainText(topic.question);
    await expect(feedback).toBeEmpty();
    await panel.locator(`[data-demo-answer="${topic.wrong}"]`).click();
    await expect(feedback).toContainText('Pas tout à fait');
    await panel.locator(`[data-demo-answer="${topic.correct}"]`).click();
    await expect(feedback).toContainText(topic.explanation);
    await expect(panel.locator(`[data-demo-answer="${topic.correct}"]`)).toHaveClass(/is-correct/);
  }
  await noOverflow(page);

  const faq=page.locator('.oi-faq-items details').filter({has:page.locator('summary',{hasText:'Est-ce que l’inscription est gratuite ?'})});
  await faq.locator('summary').click();
  await expect(faq).toHaveAttribute('open','');
  await expect(faq.locator('p')).toBeVisible();
  await expect(faq.locator('p')).toContainText('création de ton compte est gratuite');
  await faq.locator('summary').click();
  await expect(faq.locator('p')).not.toBeVisible();
  await page.getByRole('navigation',{name:'Navigation de pied de page'}).getByRole('link',{name:'Les révisions',exact:true}).click();
  await expect(page).toHaveURL(/#reviser$/);
  await nav.getByRole('link',{name:'Se connecter',exact:true}).click();
  await expect(page).toHaveURL(/\/connexion\/$/);
  await expect(page.locator('#user_login')).toBeVisible();
  await expect(page.locator('#user_pass')).toBeVisible();
  await noOverflow(page);
  expect(errors).toEqual([]);
});

test('conversion : le sujet essayé accompagne la création du compte',async({page})=>{
  await page.goto('/');
  await page.locator('[data-demo-topic="calculs"]').click();
  const signup=page.locator('[data-demo-signup]').first();
  await expect(signup).toBeVisible();
  const destination=new URL(await signup.getAttribute('href'),page.url());
  expect(destination.pathname).toBe('/inscription/');
  expect(destination.searchParams.get('interest')).toBe('calculs');
  await signup.click();
  await expect(page).toHaveURL(/\/inscription\/\?interest=calculs$/);
  await expect(page.locator('input[name="interest"][value="calculs"]')).toBeChecked();
  await expect(page.locator('#oi-register-email')).toBeVisible();
  await expect(page.getByRole('button',{name:'Créer mon compte gratuit'})).toBeVisible();
  await noOverflow(page);
});
