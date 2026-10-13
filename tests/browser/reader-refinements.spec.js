const {test, expect} = require('@playwright/test');
const fs = require('node:fs');

const credentials = JSON.parse(fs.readFileSync('.runtime/browser-user.json', 'utf8'));
const restPath = url => url.searchParams.get('rest_route') || url.pathname.replace(/^.*\/wp-json/, '');

async function login(page) {
  const user = credentials.projects[test.info().project.name];
  await page.goto('/connexion/');
  await page.locator('#user_login').fill(user.login);
  await page.locator('#user_pass').fill(user.password);
  await page.locator('#wp-submit').click();
  await expect(page.locator('#oi-results .oi-folder-card').first()).toBeVisible();
}

async function search(page, title) {
  await page.locator('#oi-query').fill(title);
  await page.locator('#oi-search').getByRole('button', {name:'Rechercher', exact:true}).click();
  await expect(page.locator('#oi-results .oi-card')).toHaveCount(1);
  return page.locator('#oi-results .oi-card');
}

async function api(page, path) {
  const [route, parameters = ''] = path.split('?');
  const response = await page.request.get(`/?rest_route=/oi/v1/${route}${parameters ? '&' + parameters : ''}`, {
    headers:{'X-WP-Nonce':await page.evaluate(() => OI.nonce)},
  });
  expect(response.ok()).toBeTruthy();
  return response.json();
}

test('fiche : les liens UE et thème réinitialisent les filtres depuis la recherche et les lectures récentes', async ({page}) => {
  test.setTimeout(90000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page);
  const catalogue = await api(page, 'fiches?q=Hypokali%C3%A9mie');
  const item = catalogue.items.find(fiche => fiche.title === 'Hypokaliémie');
  expect(item).toBeTruthy();
  const unit = item.terms.enseignement[0];
  const theme = item.terms.theme.find(term => /^B1 - 01 /.test(term.name));
  expect(theme).toBeTruthy();

  const matchingSemester = item.terms.semestre[0];
  await page.locator('#oi-semester').selectOption(String(matchingSemester.id));
  await (await search(page, item.title)).click();
  await expect(page.locator('.oi-content')).toBeVisible();
  await expect(page.locator('.oi-reader-heading .oi-reader-symbol')).toHaveCount(0);
  await expect(page.locator(`.oi-reader-path [data-reader-unit="${unit.id}"]`)).toBeVisible();
  const themeLink = page.locator(`.oi-reader-path [data-reader-theme="${theme.id}"]`);
  await expect(themeLink).toHaveText(theme.name);
  await themeLink.click();
  await expect(page.locator('#oi-query')).toHaveValue('');
  await expect(page.locator('#oi-semester')).toHaveValue('');
  await expect(page.locator('.oi-folder-trail [aria-current="page"]')).toHaveText(theme.name);
  const inTheme = await api(page, `fiches?enseignement=${unit.id}&theme=${theme.id}`);
  await expect(page.locator('#oi-results .oi-card')).toHaveCount(inTheme.items.length);
  expect(inTheme.items.length).toBeGreaterThan(1);
  const displayedTitles = await page.locator('#oi-results .oi-card h3').allTextContents();
  expect(displayedTitles.sort()).toEqual(inTheme.items.map(fiche => fiche.title).sort());

  await page.locator('.oi-main-nav [data-tab="fiches"]').click();
  await expect(page.locator('#oi-results .oi-folder-card')).toHaveCount(15);
  await page.locator('.oi-recent .oi-card').filter({has:page.getByRole('heading', {name:item.title, exact:true})}).click();
  await expect(page.locator('.oi-content')).toBeVisible();
  await page.locator(`.oi-reader-path [data-reader-unit="${unit.id}"]`).click();
  await expect(page.locator('#oi-query')).toHaveValue('');
  await expect(page.locator('#oi-semester')).toHaveValue('');
  await expect(page.locator('.oi-folder-trail [aria-current="page"]')).toHaveText(unit.name);
  const unitFolders = await api(page, `library?enseignement=${unit.id}`);
  await expect(page.locator('#oi-results .oi-folder-card')).toHaveCount(unitFolders.folders.length);
  await expect(page.locator(`#oi-results [data-folder="${theme.id}"]`)).toHaveText(new RegExp(theme.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
  expect(errors).toEqual([]);
});

test('fiche : encarts, numéros uniques du sommaire et impression du cours sans interface', async ({page}) => {
  test.setTimeout(90000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(() => {
    window.__oiPrintCalls = 0;
    window.print = () => { window.__oiPrintCalls += 1; };
  });
  const original = {};
  const titles = ['Épidémiologie et prévention', 'Signes d’alerte', 'Surveillance infirmière'];
  const essential = 'Repérer les signes d’alerte et transmettre les observations.';
  const source = `<p><strong>Objectifs :</strong> Comprendre les repères et identifier les priorités.</p>
    <table><tbody><tr><td><p><strong>L’ESSENTIEL EN 30 SECONDES</strong></p><p>${essential}</p></td></tr></tbody></table>
    <h2><strong>1. ${titles[0]}</strong></h2><p>Premier contenu de révision, conservé intégralement.</p>
    <h2>2) ${titles[1]}</h2><p>Deuxième contenu de révision, conservé intégralement.</p>
    <h2>${titles[2]}</h2><p>Troisième contenu de révision, conservé intégralement.</p>`;
  await page.route(url => /^\/oi\/v1\/fiches\/\d+$/.test(restPath(url)), async route => {
    const response = await route.fetch();
    const fiche = await response.json();
    Object.assign(original, {id:fiche.id, content:fiche.content});
    await route.fulfill({response, json:{...fiche, content:source, watermark:'Objectif Infirmière — Compte de test', quiz:[], quiz_count:0}});
  });

  await login(page);
  await expect(page.locator('[data-print-fiche]')).toHaveCount(0);
  await (await search(page, 'Hypokaliémie')).click();
  await expect(page.locator('.oi-content')).toBeVisible();
  await expect(page.locator('.oi-word-objectives .oi-callout-icon')).toHaveCount(1);
  await expect(page.locator('.oi-word-essential .oi-callout-icon')).toHaveCount(1);
  await expect(page.locator('.oi-word-essential')).toContainText(essential);
  await expect(page.locator('.oi-content h2 .oi-section-number')).toHaveText(['01', '02', '03']);
  await expect(page.locator('#oi-toc a')).toHaveCount(3);
  for (let index = 0; index < titles.length; index++) {
    const link = page.locator('#oi-toc a').nth(index);
    await expect(link).toHaveAttribute('href', `#oi-section-${index}`);
    // Ignore the visual number to ensure the imported heading number is not repeated.
    expect(await link.evaluate(node => {
      const copy = node.cloneNode(true);
      copy.firstElementChild.remove();
      return copy.textContent.trim();
    })).toBe(titles[index]);
    await expect(page.locator('.oi-content h2').nth(index)).toContainText(titles[index]);
  }
  await page.locator('#oi-toc a').nth(1).click();
  await expect(page.locator('#oi-toc a').nth(1)).toHaveAttribute('aria-current', 'location');
  await expect(page).toHaveURL(/#oi-section-1$/);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();

  const print = page.locator('.oi-actions [data-print-fiche]');
  await expect(print).toHaveText('Imprimer');
  await print.click();
  await expect.poll(() => page.evaluate(() => window.__oiPrintCalls)).toBe(1);
  await page.emulateMedia({media:'print'});
  await expect(page.locator('.oi-content')).toBeVisible();
  await expect(page.locator('#oi-watermark')).toBeVisible();
  await expect(page.locator('#oi-watermark')).toHaveText('Objectif Infirmière — Compte de test');
  for (const selector of ['.oi-sidebar', '.oi-actions', '#oi-toc', '.oi-ai-banner', '.oi-related', '.oi-quiz-section']) {
    await expect(page.locator(selector)).toBeHidden();
  }
  expect(await page.locator('body').evaluate(node => getComputedStyle(node, ':after').display)).toBe('none');
  await page.emulateMedia({media:'screen'});
  await expect(print).toBeVisible();
  // Rendering and printing must never modify the Word-derived HTML stored by WordPress.
  expect((await api(page, `fiches/${original.id}`)).content).toBe(original.content);
  await page.screenshot({path:`.runtime/reader-refinements-${test.info().project.name}.png`, fullPage:true});

  await page.locator('.oi-main-nav [data-tab="qcm"]').click();
  await (await search(page, 'Hypokaliémie')).click();
  await expect(page.locator('.oi-reader')).toBeVisible();
  await expect(page.locator('.oi-content')).toHaveCount(0);
  await expect(page.locator('[data-print-fiche]')).toHaveCount(0);
  expect(errors).toEqual([]);
});
