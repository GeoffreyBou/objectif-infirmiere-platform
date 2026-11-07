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
  await expect(panel).toContainText('Un patient sous précautions contact');
  await expect(panel.locator('[data-demo-validate]')).toBeDisabled();
  await expect(panel.locator('.oi-try-excerpt')).toHaveCount(0);
  await panel.locator('input[value="0"]').check();
  await panel.locator('[data-demo-validate]').click();
  await expect(panel.locator('.oi-try-answer.is-wrong')).toHaveCount(1);
  await expect(panel.locator('.oi-try-answer.is-right')).toHaveCount(1);
  await expect(panel.locator('input:disabled')).toHaveCount(4);
  await expect(panel.locator('.oi-try-answer.is-wrong')).toHaveCSS('background-color','rgb(255, 242, 239)');
  await expect(panel.locator('.oi-try-answer.is-right')).toHaveCSS('background-color','rgb(237, 249, 243)');
  await expect(panel.locator('.oi-try-correction')).toContainText('Le matériel partagé doit être traité');
  await panel.locator('.oi-try-excerpt summary').click();
  await expect(panel.locator('.oi-try-excerpt p')).toContainText('du matériel dédié');
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


test('démo : neuf questions, corrections verrouillées, bilans et reprise par sujet',async({page})=>{
  await page.goto('/');
  const panel=page.locator('#oi-demo-panel');
  const scenarios=[
    {topic:'hygiene',answers:[[1],[0],[3]],score:2},
    {topic:'calculs',answers:[[1],[2],[0]],score:3},
    {topic:'avc',answers:[[0],[1,3],[0]],score:2},
  ];
  for(const {topic,answers,score} of scenarios){
    await page.locator(`[data-demo-topic="${topic}"]`).click();
    await expect(page.locator('[data-demo-topic][aria-pressed="true"]')).toHaveCount(1);
    for(let i=0;i<3;i++){
      await expect(panel.locator('.oi-try-progress')).toContainText(`0${i+1} / 03`);
      await expect(panel.locator('[data-demo-validate]')).toBeDisabled();
      await expect(panel.locator('.oi-try-excerpt')).toHaveCount(0);
      for(const answer of answers[i]) await panel.locator(`input[value="${answer}"]`).check();
      await panel.locator('[data-demo-validate]').click();
      await expect(panel.locator('input:disabled')).toHaveCount(4);
      await expect(panel.locator('.oi-try-correction')).toBeVisible();
      await expect(panel.locator('[data-demo-validate]')).toHaveCount(0);
      if(topic==='hygiene' && i===1){
        // Selecting only one of the two correct choices is not a correct response.
        await expect(panel.locator('.oi-try-correction h4')).toHaveText('Un repère à retenir.');
        await expect(panel.locator('.oi-try-answer.is-right')).toHaveCount(2);
      }
      await panel.locator('[data-demo-next]').click();
    }
    await expect(panel.locator('.oi-try-score')).toHaveAttribute('aria-label',`${score} bonnes réponses sur 3`);
    await expect(panel.locator('.oi-try-review details')).toHaveCount(3);
    await expect(panel.getByRole('link',{name:'Continuer gratuitement'})).toBeVisible();
    await panel.locator('.oi-try-review summary').first().click();
    await expect(panel.locator('.oi-try-review details').first()).toContainText('Réponse attendue');
    await noOverflow(page);
  }
  await page.locator('[data-demo-topic="calculs"]').click();
  await expect(panel.locator('.oi-try-score')).toHaveAttribute('aria-label','3 bonnes réponses sur 3');
  await panel.locator('[data-demo-restart]').click();
  await expect(panel.locator('.oi-try-progress')).toContainText('01 / 03');
  await expect(panel.locator('input:checked')).toHaveCount(0);
  await expect(panel.locator('.oi-try-excerpt')).toHaveCount(0);
  await page.locator('[data-demo-topic="avc"]').click();
  await expect(panel.locator('.oi-try-score')).toHaveAttribute('aria-label','2 bonnes réponses sur 3');
});

test('démo : sélection conservée au changement de sujet et navigation au clavier',async({page})=>{
  await page.goto('/');
  const panel=page.locator('#oi-demo-panel');
  const choice=panel.locator('input[value="1"]');
  await choice.focus();
  await page.keyboard.press('Space');
  await page.locator('[data-demo-topic="avc"]').click();
  await expect(panel).toContainText('diagnostic pouvant imiter un AVC');
  await page.locator('[data-demo-topic="hygiene"]').click();
  await expect(choice).toBeChecked();
  await panel.locator('[data-demo-validate]').focus();
  await page.keyboard.press('Enter');
  await expect(panel.locator('#oi-demo-correction')).toBeFocused();
  await expect(panel.locator('#oi-demo-correction')).toHaveText('✓ Bien vu !');
  await panel.locator('.oi-try-excerpt summary').focus();
  await page.keyboard.press('Enter');
  await expect(panel.locator('.oi-try-excerpt')).toHaveAttribute('open','');
});
