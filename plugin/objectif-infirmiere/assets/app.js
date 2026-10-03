/* global OI */
(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const view = document.querySelector('#oi-view');
  const catalog = document.querySelector('#oi-catalog');
  let page = 1;
  let activeFiche = null;
  const api = async (path, data) => {
    const response = await fetch(OI.api + path, {credentials:'same-origin', headers:{'X-WP-Nonce':OI.nonce, ...(data ? {'Content-Type':'application/json'} : {})}, ...(data ? {method:'POST',body:JSON.stringify(data)} : {})});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Une erreur est survenue. Réessayez.');
    return result;
  };
  const error = e => { const target = document.querySelector('#oi-status') || view || catalog; if (target) { target.textContent = e.message; target.setAttribute('role','alert'); } };
  const card = item => `<button class="oi-card" data-fiche="${Number(item.id)}"><span class="oi-eyebrow">${esc(item.terms?.semestre?.map(t=>t.name).join(' · ') || 'FICHE DE RÉVISION')}</span><h3>${esc(item.title)}</h3><span>Ouvrir la fiche →</span></button>`;
  async function dashboard() {
    activeFiche = null;
    const data = await api('fiches');
    view.innerHTML = `<section class="oi-welcome"><span class="oi-eyebrow">MON ESPACE ÉTUDIANT</span><h1>Bonjour ${esc(OI.name)}.</h1><p>Avancez à votre rythme, une notion à la fois.</p></section><div id="oi-progress"></div><nav class="oi-tabs"><button data-home>Mes fiches</button><button data-favorites>Mes favoris</button><button data-ai>Conseiller IA</button></nav><form id="oi-search" class="oi-search"><label for="oi-query">Rechercher dans mes fiches</label><div><input id="oi-query" type="search" placeholder="Une notion, un médicament…" maxlength="150"><button>Rechercher</button></div><label for="oi-semester">Semestre</label><select id="oi-semester"><option value="">Tous les semestres</option>${data.semesters.map(s=>`<option value="${Number(s.id)}">${esc(s.name)}</option>`).join('')}</select></form><p id="oi-status" role="status"></p><div id="oi-results" class="oi-grid">${data.items.map(card).join('') || '<p>Aucune fiche accessible. Votre pack sera visible après confirmation de votre achat.</p>'}</div><div id="oi-pagination"></div><div id="oi-catalog"></div>`;
    pagination(data);
    if (data.progress) progress(data.progress);
    document.querySelector('#oi-search').addEventListener('submit', e=>{e.preventDefault(); page=1; search().catch(error);});
    showCatalog(document.querySelector('#oi-catalog')).catch(error);
  }
  function progress(p) {
    document.querySelector('#oi-progress').innerHTML = `<div class="oi-progress"><span>Ma progression · ${Number(p.revised)} / ${Number(p.total)} fiches révisées</span><progress value="${Number(p.revised)}" max="${Math.max(1,Number(p.total))}"></progress></div>`;
  }
  function pagination(data) {
    document.querySelector('#oi-pagination').innerHTML = `<p>${Number(data.total)} fiches · Page ${page} / ${Math.max(1,Number(data.pages))}</p>${page>1?'<button data-page="-1">Précédent</button>':''}${page<data.pages?'<button data-page="1">Suivant</button>':''}`;
  }
  async function search(favorites=false) {
    const q = document.querySelector('#oi-query')?.value || '';
    const semester = document.querySelector('#oi-semester')?.value || '';
    const data = await api(`fiches?page=${page}&q=${encodeURIComponent(q)}&semestre=${encodeURIComponent(semester)}${favorites?'&favorites=1':''}`);
    document.querySelector('#oi-results').innerHTML = data.items.map(card).join('') || '<p>Aucune fiche trouvée.</p>';
    pagination(data);
  }
  async function fiche(id) {
    const data = await api(`fiches/${id}`);
    activeFiche = data;
    view.innerHTML = `<nav class="oi-breadcrumb"><button data-home>Mes révisions</button><span> / ${esc(data.terms.semestre.map(t=>t.name).join(', '))}</span></nav><article class="oi-reader"><span class="oi-eyebrow">${esc(data.terms.theme.map(t=>t.name).join(' · '))}</span><h1>${esc(data.title)}</h1><p class="oi-muted">Mise à jour : ${esc(data.updated.slice(0,10))}</p><div class="oi-actions"><button data-state="favorite" aria-pressed="${!!data.state?.favorite}">${data.state?.favorite?'★ Favori':'☆ Ajouter aux favoris'}</button><button data-state="revised" aria-pressed="${!!data.state?.revised}">${data.state?.revised?'✓ Révisée':'Marquer comme révisée'}</button><button data-ai>Poser une question sur cette fiche</button></div><p id="oi-status" role="status"></p><nav id="oi-toc" aria-label="Sommaire"></nav><div class="oi-content">${data.content}</div><div id="oi-watermark"></div><div id="oi-quiz"></div><h2>Continuer mes révisions</h2><div class="oi-grid">${data.related.map(card).join('')}</div></article>`;
    const headings = [...view.querySelectorAll('.oi-content h2, .oi-content h3')];
    headings.forEach((h,i)=>{h.id=`oi-section-${i}`;});
    document.querySelector('#oi-toc').innerHTML = headings.length ? `<h2>Dans cette fiche</h2>${headings.map(h=>`<a href="#${h.id}">${esc(h.textContent)}</a>`).join('')}` : '';
    if (data.watermark) document.querySelector('#oi-watermark').textContent = data.watermark;
    if (data.quiz) renderQuiz(data.quiz);
    window.scrollTo({top:0,behavior:'smooth'});
  }
  async function showCatalog(target) {
    if (!target) return;
    const packs = await api('catalog');
    target.innerHTML = packs.length ? `<h2>Les packs disponibles</h2><div class="oi-grid">${packs.map(p=>`<section class="oi-card"><h3>${esc(p.title)}</h3><p>${esc(p.description)}</p><button data-buy="${Number(p.id)}" ${p.available?'':'disabled'}>Acheter en mode TEST</button></section>`).join('')}</div>` : '';
  }
  function renderQuiz(quiz) {
    document.querySelector('#oi-quiz').innerHTML = `<h2>Vérifier mes connaissances</h2><form id="oi-quiz-form">${quiz.map((q,i)=>`<fieldset><legend>${esc(q.question)}</legend>${q.options.map((o,j)=>`<label><input type="${q.multiple?'checkbox':'radio'}" name="q${i}" value="${j}"> ${esc(o)}</label>`).join('')}</fieldset>`).join('')}<button>Voir ma correction</button></form><div id="oi-quiz-result" role="status"></div>`;
    document.querySelector('#oi-quiz-form').addEventListener('submit', async e=>{
      e.preventDefault();
      try {
        const answers = quiz.map((q,i)=>[...e.target.querySelectorAll(`[name="q${i}"]:checked`)].map(el=>Number(el.value)));
        const result = await api(`fiches/${activeFiche.id}/quiz`, {answers});
        document.querySelector('#oi-quiz-result').innerHTML = `<h3>${Number(result.score)} / ${Number(result.total)}</h3>${result.corrections.map(c=>`<p>${c.correct?'✓':'À revoir'} · ${esc(c.explanation)}</p>`).join('')}`;
      } catch(e) { error(e); }
    });
  }
  function chat() {
    const context = activeFiche;
    view.innerHTML = `<button data-home>← Mes révisions</button><section class="oi-reader"><span class="oi-eyebrow">APPRENDRE ET COMPRENDRE</span><h1>Conseiller IA Soignant</h1><p>Un assistant automatisé pour vos révisions. Ne transmettez aucune donnée de patient. Il ne remplace pas les protocoles ni les professionnels responsables.</p>${context?`<p>Votre fiche : ${esc(context.title)}</p>`:''}<form id="oi-chat"><label for="oi-question">Votre question</label><textarea id="oi-question" required maxlength="2000" rows="4" placeholder="Explique-moi cette notion simplement…"></textarea><button>Poser ma question</button></form><p id="oi-status" role="status"></p><div id="oi-answer" class="oi-content"></div></section>`;
    document.querySelector('#oi-chat').addEventListener('submit', async e=>{
      e.preventDefault(); const button=e.target.querySelector('button'); button.disabled=true;
      document.querySelector('#oi-status').textContent='Recherche dans vos fiches…';
      try {
        const answer=await api('ai',{question:document.querySelector('#oi-question').value,fiche_id:context?.id || 0});
        document.querySelector('#oi-status').textContent='';
        document.querySelector('#oi-answer').innerHTML=`<p class="oi-answer-text">${esc(answer.text)}</p><h2>Sources Objectif Infirmière</h2>${answer.sources.map(s=>`<button data-fiche="${Number(s.id)}">${esc(s.title)}</button>`).join('')}`;
      } catch(e) { error(e); } finally { button.disabled=false; }
    });
  }
  document.addEventListener('click', async e=>{
    const b=e.target.closest('button'); if (!b) return;
    try {
      if (b.hasAttribute('data-home')) await dashboard();
      if (b.dataset.fiche) await fiche(Number(b.dataset.fiche));
      if (b.dataset.page) {page+=Number(b.dataset.page);await search();}
      if (b.hasAttribute('data-favorites')) {page=1;await search(true);}
      if (b.hasAttribute('data-ai')) chat();
      if (b.dataset.state && activeFiche) {
        await api(`fiches/${activeFiche.id}/state`,{field:b.dataset.state,value:!activeFiche.state?.[b.dataset.state]});
        await fiche(activeFiche.id);
      }
      if (b.dataset.buy) {
        b.disabled=true;
        try { const checkout=await api('checkout',{pack_id:Number(b.dataset.buy)}); const url=new URL(checkout.url); if(url.protocol!=='https:' || url.hostname!=='checkout.stripe.com') throw new Error('Adresse de paiement invalide.'); window.location.assign(url.href); }
        finally {b.disabled=false;}
      }
    } catch(e) {error(e);}
  });
  if (view && OI.loggedIn) dashboard().catch(error);
  else if(catalog) showCatalog(catalog).catch(error);
})();
