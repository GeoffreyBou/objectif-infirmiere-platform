/* global OI */
(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const view = document.querySelector('#oi-view');
  const publicCatalog = document.querySelector('#oi-catalog');
  const paths = {
    book: '<path d="M4 5.5c3-1 5-.7 8 1 3-1.7 5-2 8-1v14c-3-1-5-.7-8 1-3-1.7-5-2-8-1z"/><path d="M12 6.5v14"/>',
    quiz: '<rect x="5" y="3" width="14" height="18" rx="3"/><path d="m8 8 1 1 2-2m2 1h3M8 13h8m-8 4h5"/>',
    spark: '<path d="m12 3 2.2 6.8L21 12l-6.8 2.2L12 21l-2.2-6.8L3 12l6.8-2.2z"/><path d="M20 2v4m-2-2h4"/>',
    heart: '<path d="M20.5 4.7a5.4 5.4 0 0 0-7.6 0L12 5.6l-.9-.9a5.4 5.4 0 0 0-7.6 7.6L12 21l8.5-8.7a5.4 5.4 0 0 0 0-7.6Z"/>',
    arrow: '<path d="M5 12h14m-6-6 6 6-6 6"/>',
    back: '<path d="M19 12H5m6-6-6 6 6 6"/>',
    search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    check: '<path d="m5 12 4 4L19 6"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    pulse: '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
    shield: '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
    pill: '<path d="M8 3a5 5 0 0 1 7 0l6 6a5 5 0 0 1-7 7l-6-6a5 5 0 0 1 0-7Z" transform="rotate(90 12 10)"/><path d="m8 8 8 8"/>',
    send: '<path d="m3 3 18 9-18 9 4-9zM7 12h14"/>',
    leaf: '<path d="M20 4C8 2 3 7 5 14s13 6 15-10Z"/><path d="m4 21 11-12"/>',
  };
  const icon = (name, cls='') => `<svg class="oi-icon ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.book}</svg>`;
  let page = 1;
  let favoritesOnly = false;
  let currentTab = 'fiches';
  let activeFiche = null;
  let query = '';
  let semester = '';
  let routeSequence = 0;
  let searchSequence = 0;
  const api = async (path, data) => {
    const [route, params=''] = path.split('?');
    const url = new URL(OI.api, window.location.href);
    if (url.searchParams.has('rest_route')) url.searchParams.set('rest_route', url.searchParams.get('rest_route') + route);
    else url.pathname += route;
    for (const [key,value] of new URLSearchParams(params)) url.searchParams.set(key,value);
    const response = await fetch(url, {credentials:'same-origin', headers:{'X-WP-Nonce':OI.nonce, ...(data ? {'Content-Type':'application/json'} : {})}, ...(data ? {method:'POST',body:JSON.stringify(data)} : {})});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Une erreur est survenue. Réessayez.');
    return result;
  };
  function error(e) {
    const target = document.querySelector('#oi-status') || view || publicCatalog;
    if (target) { target.textContent = e.message; target.setAttribute('role','alert'); }
  }
  function status(message='') {
    const target = document.querySelector('#oi-status');
    if (target) { target.setAttribute('role','status'); target.textContent = message; }
  }
  function navButton(tab, label, glyph) {
    return `<button type="button" data-tab="${tab}" ${currentTab===tab?'aria-current="page"':''}>${icon(glyph)}<span>${label}</span>${tab==='ai'?'<span class="oi-nav-tag">IA</span>':''}</button>`;
  }
  function shell() {
    if (document.querySelector('#oi-screen')) return;
    view.removeAttribute('aria-live');
    view.innerHTML = `<div class="oi-workspace"><aside class="oi-sidebar"><div class="oi-sidebar-heading">TON CAMPUS, PARTOUT.</div><nav class="oi-main-nav" aria-label="Mon espace membre">${navButton('fiches','Mes fiches','book')}${navButton('qcm','QCM & partiels','quiz')}${navButton('ai','Mon assistant','spark')}${navButton('favorites','Mes favoris','heart')}</nav><div class="oi-sidebar-note">${icon('leaf')}<strong>Un peu chaque jour.<br>Plus solide demain.</strong><p>Ton diplôme se construit une notion à la fois.</p></div><div class="oi-student"><span class="oi-avatar">${esc((OI.name || 'É').slice(0,1).toUpperCase())}</span><span><strong>${esc(OI.name)}</strong><small>Espace personnel</small></span><span class="oi-online-dot" title="Connecté"></span></div></aside><div id="oi-screen" class="oi-screen"><p id="oi-status" role="status">On prépare ton espace…</p></div></div>`;
  }
  function setTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.oi-main-nav [data-tab]').forEach(button=>{
      if (button.dataset.tab===tab) button.setAttribute('aria-current','page');
      else button.removeAttribute('aria-current');
    });
  }
  const screen = () => document.querySelector('#oi-screen');
  function subject(item) {
    const name = item.terms?.theme?.map(t=>t.name).join(' · ') || item.terms?.ue?.map(t=>t.name).join(' · ') || 'Essentiels IFSI';
    const pharmacology = /pharmaco|médicament|furosémide|paracétamol|héparine|insuline|morphine/i.test(name+' '+item.title);
    return {name, icon:pharmacology?'pill':/cardio|vital|constante/i.test(name+' '+item.title)?'pulse':/soin|hygiène|sécurité/i.test(name+' '+item.title)?'shield':'book', color:pharmacology?'peach':/soin|hygiène/i.test(name+' '+item.title)?'blue':'mint'};
  }
  function card(item, quizMode=false) {
    const topic = subject(item);
    const count = Number(item.quiz_count || 0);
    return `<button type="button" class="oi-card oi-fiche-card oi-tone-${topic.color}" data-${quizMode?'quiz-fiche':'fiche'}="${Number(item.id)}"><span class="oi-card-top"><span class="oi-subject-icon">${icon(quizMode?'quiz':topic.icon)}</span><span class="oi-card-badge">${esc(item.terms?.semestre?.map(t=>t.name).join(' · ') || 'IFSI')}</span></span><span class="oi-eyebrow">${esc(topic.name)}</span><h3>${esc(item.title)}</h3><span class="oi-card-description">${quizMode?`${count} question${count>1?'s':''} · Correction expliquée`:'Les repères pour comprendre et retenir.'}</span><span class="oi-card-bottom"><span>${quizMode?'Commencer le QCM':item.state?.revised?'Révisée': 'Ouvrir la fiche'}${item.state?.revised&&!quizMode?icon('check'):''}</span>${icon('arrow')}</span>${item.state?.favorite?'<span class="oi-card-favorite" aria-label="En favori">'+icon('heart')+'</span>':''}</button>`;
  }
  function progress(p) {
    const total = Number(p?.total || 0);
    const revised = Number(p?.revised || 0);
    const percentage = total ? Math.round(revised/total*100) : 0;
    return `<section class="oi-progress-panel" aria-label="Ma progression"><div class="oi-progress-title"><span>${icon('pulse')} Ma progression</span><strong>${percentage}%</strong></div><progress value="${revised}" max="${Math.max(1,total)}" aria-label="Fiches révisées"></progress><p><strong>${revised} / ${total}</strong> fiches révisées<span>${Number(p?.quizzes || 0)} QCM réalisé${Number(p?.quizzes || 0)>1?'s':''}</span></p></section>`;
  }
  function searchForm(data, quizMode) {
    return `<form id="oi-search" class="oi-search"><div class="oi-search-field">${icon('search')}<label class="oi-sr-only" for="oi-query">Rechercher dans mes fiches</label><input id="oi-query" type="search" value="${esc(query)}" placeholder="${quizMode?'Quel sujet veux-tu travailler ?':'Une notion, un médicament, une UE…'}" maxlength="150"><button type="submit" class="oi-search-submit">Rechercher</button></div><div class="oi-semester-field"><label class="oi-sr-only" for="oi-semester">Semestre</label><select id="oi-semester"><option value="">Tous les semestres</option>${(data.semesters||[]).map(s=>`<option value="${Number(s.id)}" ${String(s.id)===semester?'selected':''}>${esc(s.name)}</option>`).join('')}</select></div></form>`;
  }
  function resultCards(data, quizMode) {
    const items = quizMode ? data.items.filter(item=>Number(item.quiz_count)>0) : data.items;
    return items.map(item=>card(item,quizMode)).join('') || `<div class="oi-empty">${icon(quizMode?'quiz':favoritesOnly?'heart':'book')}<h3>${favoritesOnly?'Tes essentiels, au même endroit.':quizMode?'Aucun QCM pour cette sélection.':'Aucune fiche pour cette sélection.'}</h3><p>${favoritesOnly?'Ajoute une fiche aux favoris depuis sa page pour la retrouver ici.':data.total?'Essaie un autre mot-clé ou un autre semestre.':'Tes contenus apparaîtront ici dès qu’un pack sera activé sur ton compte.'}</p>${query||semester?'<button type="button" data-clear-search>Effacer les filtres</button>':''}</div>`;
  }
  function pagination(data) {
    const target = document.querySelector('#oi-pagination');
    if (!target) return;
    target.innerHTML = `<span>${Number(data.total)} fiche${Number(data.total)>1?'s':''}${currentTab==='qcm'?' à explorer':''}${Number(data.pages)>1?` · Page ${page} sur ${Number(data.pages)}`:''}</span><div>${page>1?'<button type="button" data-page="-1">'+icon('back')+' Précédent</button>':''}${page<Number(data.pages)?'<button type="button" data-page="1">Suivant '+icon('arrow')+'</button>':''}</div>`;
  }
  async function dashboard(tab='fiches', reset=true) {
    shell();
    const sequence = ++routeSequence;
    setTab(tab);
    activeFiche = null;
    favoritesOnly = tab==='favorites';
    if (reset) { page=1; query=''; semester=''; }
    screen().setAttribute('aria-busy','true');
    let data;
    try { data=await api(`fiches?page=${page}&q=${encodeURIComponent(query)}&semestre=${encodeURIComponent(semester)}${favoritesOnly?'&favorites=1':''}`); }
    finally { screen().removeAttribute('aria-busy'); }
    if (sequence!==routeSequence) return;
    const quizMode = tab==='qcm';
    const intro = favoritesOnly ? `<div class="oi-page-intro"><span class="oi-eyebrow">TA SÉLECTION PERSONNELLE</span><h1>À garder sous la main.</h1><p>Les notions que tu veux retrouver en un instant.</p></div>` : quizMode ? `<div class="oi-page-intro"><span class="oi-eyebrow">PLACE À LA PRATIQUE</span><h1>Tu sais. Maintenant,<br><em>prouve-le-toi.</em></h1><p>Teste tes connaissances, comprends tes erreurs et avance vers tes partiels.</p></div>` : `<div class="oi-dashboard-intro"><div><span class="oi-eyebrow">C’EST UN BON JOUR POUR APPRENDRE</span><h1>Bonjour ${esc(OI.name)}.</h1><p>Tes notions de soins infirmiers, un peu plus claires chaque jour.</p></div><span class="oi-space-tag">${icon('shield')} Mon espace de révision</span></div><div class="oi-dashboard-hero"><div><span class="oi-pill-label">DE TES COURS IFSI AUX SOINS</span><h2>Comprendre tes cours.<br><em>Construire tes réflexes.</em></h2><p>Hygiène, anatomie, pharmacologie…<br>Une fiche, un QCM, un repère de plus pour tes études.</p><button type="button" data-tab="qcm">M’entraîner avec un QCM ${icon('arrow')}</button></div><div class="oi-hero-illustration" aria-hidden="true"><span class="oi-orbit oi-orbit-one"></span><div class="oi-nursing-note"><span>${icon('pulse')} MON CAP</span><strong>Diplôme<br>d’État<br><em>infirmier.</em></strong><span class="oi-nursing-note-rule"></span><small>Une UE après l’autre.</small></div><img class="oi-member-mascot" src="${esc(OI.brandUrl)}mascot.png" alt="" width="500" height="500"><span class="oi-nursing-badge">${icon('heart')} Prendre soin demain</span></div></div>${progress(data.progress)}`;
    screen().innerHTML = `${intro}${quizMode?'<div class="oi-qcm-notice">'+icon('quiz')+'<div><strong>Le bon réflexe avant les partiels.</strong><span>Des QCM par sujet, à ton rythme, avec une correction expliquée après chaque série.</span></div></div>':''}<section class="oi-library" aria-labelledby="oi-library-title"><div class="oi-section-title"><div><span class="oi-eyebrow">${quizMode?'APPRENDRE EN S’ENTRAÎNANT':favoritesOnly?'LE MEILLEUR DE TES RÉVISIONS':'TA BIBLIOTHÈQUE'}</span><h2 id="oi-library-title">${quizMode?'Choisis ton prochain défi.':favoritesOnly?'Mes favoris':'Qu’est-ce qu’on révise ?'}</h2></div>${!favoritesOnly&&!quizMode?'<button type="button" class="oi-text-button" data-tab="favorites">'+icon('heart')+' Mes favoris</button>':''}</div>${searchForm(data,quizMode)}<p id="oi-status" role="status"></p><div id="oi-results" class="oi-grid">${resultCards(data,quizMode)}</div><div id="oi-pagination" class="oi-pagination"></div></section>${!quizMode&&!favoritesOnly?`<section class="oi-ai-banner"><span class="oi-ai-banner-icon">${icon('spark')}</span><div><span class="oi-eyebrow">UN COUP DE POUCE QUAND ÇA BLOQUE</span><h2>Et si on te l’expliquait autrement ?</h2><p>Ton assistant personnel t’aide à faire le lien entre les notions.</p></div><button type="button" data-ai>Ouvrir mon assistant ${icon('arrow')}</button></section><details class="oi-access-details"><summary>Mes accès et les packs disponibles</summary><p class="oi-muted">${data.packs.map(p=>esc(p.title)).join(' · ') || 'Aucun pack actif pour le moment.'}</p><div id="oi-catalog"></div></details>`:''}<footer class="oi-member-footer"><span>Objectif Infirmière</span><span>Un pas de plus vers la blouse.</span></footer>`;
    pagination(data);
    document.querySelector('#oi-search').addEventListener('submit', e=>{e.preventDefault();page=1;search().catch(error);});
    document.querySelector('#oi-semester').addEventListener('change', ()=>{page=1;search().catch(error);});
    if (document.querySelector('#oi-catalog')) showCatalog(document.querySelector('#oi-catalog')).catch(error);
  }
  async function search() {
    query = document.querySelector('#oi-query')?.value || '';
    semester = document.querySelector('#oi-semester')?.value || '';
    const sequence = ++searchSequence;
    const route = routeSequence;
    const results = document.querySelector('#oi-results');
    results?.setAttribute('aria-busy','true');
    status('Recherche en cours…');
    try {
      const data = await api(`fiches?page=${page}&q=${encodeURIComponent(query)}&semestre=${encodeURIComponent(semester)}${favoritesOnly?'&favorites=1':''}`);
      if (sequence!==searchSequence || route!==routeSequence) return;
      results.innerHTML = resultCards(data,currentTab==='qcm');
      pagination(data);
      status(data.total ? '' : 'Aucun résultat pour cette recherche.');
    } finally { results?.removeAttribute('aria-busy'); }
  }
  async function fiche(id, quizOnly=false) {
    const sequence = ++routeSequence;
    status('Ouverture de la fiche…');
    const data = await api(`fiches/${id}`);
    if (sequence!==routeSequence) return;
    activeFiche = data;
    setTab(quizOnly?'qcm':'fiches');
    const topic = subject(data);
    screen().innerHTML = `<nav class="oi-breadcrumb" aria-label="Fil d’Ariane"><button type="button" data-return="${quizOnly?'qcm':'fiches'}">${icon('back')} ${quizOnly?'Tous les QCM':'Mes révisions'}</button><span>${esc(data.terms?.semestre?.map(t=>t.name).join(' · ') || 'IFSI')}</span></nav><article class="oi-reader"><header class="oi-reader-heading"><span class="oi-reader-symbol oi-tone-${topic.color}">${icon(quizOnly?'quiz':topic.icon)}</span><span class="oi-eyebrow">${quizOnly?'QCM · ':''}${esc(topic.name)}</span><h1>${esc(data.title)}</h1><p class="oi-muted">${quizOnly?'Prends le temps de réfléchir. La correction t’attend à la fin.':`Mise à jour le ${esc(new Date(data.updated+'Z').toLocaleDateString('fr-FR'))}`}</p></header>${!quizOnly?`<div class="oi-actions"><button type="button" data-state="favorite" aria-pressed="${!!data.state?.favorite}">${icon('heart')}${data.state?.favorite?'Favori':'Ajouter aux favoris'}</button><button type="button" data-state="revised" aria-pressed="${!!data.state?.revised}">${icon('check')}${data.state?.revised?'Révisée':'Marquer comme révisée'}</button><button type="button" class="oi-ask-button" data-ai>${icon('spark')}Poser une question sur cette fiche</button></div>`:''}<p id="oi-status" role="status"></p>${!quizOnly?'<div class="oi-reading-layout"><nav id="oi-toc" aria-label="Sommaire"></nav><div class="oi-content">'+data.content+'</div></div><div id="oi-watermark"></div>':''}<section id="oi-quiz" class="oi-quiz-section"></section>${!quizOnly?`<section class="oi-related"><span class="oi-eyebrow">GARDE TON ÉLAN</span><h2>On continue ?</h2><div class="oi-grid">${data.related.map(item=>card(item)).join('')}</div></section>`:''}</article>`;
    if (!quizOnly) {
      const headings = [...screen().querySelectorAll('.oi-content h2, .oi-content h3')];
      headings.forEach((h,i)=>{h.id=`oi-section-${i}`;});
      document.querySelector('#oi-toc').innerHTML = headings.length ? `<span class="oi-eyebrow">DANS CETTE FICHE</span>${headings.map((h,i)=>`<a href="#${h.id}"><span>${String(i+1).padStart(2,'0')}</span>${esc(h.textContent)}</a>`).join('')}` : '';
      if (data.watermark) document.querySelector('#oi-watermark').textContent = data.watermark;
    }
    if (data.quiz?.length) renderQuiz(data.quiz);
    else if (quizOnly) document.querySelector('#oi-quiz').innerHTML='<div class="oi-empty"><h2>Ce QCM arrive bientôt.</h2><p>Tu peux déjà réviser le cours associé.</p><button type="button" data-fiche="'+Number(id)+'">Ouvrir la fiche</button></div>';
    window.scrollTo({top:0,behavior:'smooth'});
  }
  async function showCatalog(target) {
    if (!target) return;
    const packs = await api('catalog');
    if (!target.isConnected) return;
    target.innerHTML = packs.length ? `<div class="oi-pack-grid">${packs.map(p=>`<section class="oi-pack-card"><span class="oi-eyebrow">PACK DE RÉVISION</span><h3>${esc(p.title)}</h3><p>${esc(p.description)}</p>${p.available?`<button type="button" data-buy="${Number(p.id)}">Acheter en mode TEST ${icon('arrow')}</button>`:`<span class="oi-muted">${p.free_demo?'Inclus dans la découverte gratuite':'Ce pack sera proposé prochainement'}</span>`}</section>`).join('')}</div>` : '';
  }
  function renderQuiz(quiz) {
    document.querySelector('#oi-quiz').innerHTML = `<div class="oi-quiz-heading"><span class="oi-subject-icon">${icon('quiz')}</span><div><span class="oi-eyebrow">À TOI DE JOUER</span><h2>Vérifier mes connaissances</h2><p>${quiz.length} question${quiz.length>1?'s':''} pour faire le point.</p></div></div><form id="oi-quiz-form">${quiz.map((q,i)=>`<fieldset><legend><span class="oi-question-number">${String(i+1).padStart(2,'0')}</span>${esc(q.question)}</legend><p class="oi-question-help">${q.multiple?'Plusieurs réponses possibles.':'Une seule réponse attendue.'}</p>${q.options.map((o,j)=>`<label class="oi-quiz-option"><input type="${q.multiple?'checkbox':'radio'}" name="q${i}" value="${j}" ${!q.multiple&&j===0?'required':''}><span class="oi-option-letter">${String.fromCharCode(65+j)}</span><span>${esc(o)}</span></label>`).join('')}</fieldset>`).join('')}<div class="oi-quiz-submit"><span>Comprendre compte autant que répondre juste.</span><button type="submit">Voir ma correction ${icon('arrow')}</button></div></form><div id="oi-quiz-result" role="status"></div>`;
    document.querySelector('#oi-quiz-form').addEventListener('submit', async e=>{
      e.preventDefault();
      const button = e.target.querySelector('button[type=submit]');
      const sequence = routeSequence;
      const ficheId = activeFiche.id;
      button.disabled = true;
      try {
        const answers = quiz.map((q,i)=>[...e.target.querySelectorAll(`[name="q${i}"]:checked`)].map(el=>Number(el.value)));
        const result = await api(`fiches/${ficheId}/quiz`, {answers});
        if (sequence !== routeSequence) return;
        const target = document.querySelector('#oi-quiz-result');
        target.innerHTML = `<div class="oi-score-card"><span>${icon('check')}</span><div><span class="oi-eyebrow">TA CORRECTION</span><h3>${Number(result.score)} / ${Number(result.total)}</h3><p>${result.score===result.total?'Bien joué. Ces repères sont acquis !':'Chaque erreur est une occasion de comprendre.'}</p></div></div>${result.corrections.map((c,i)=>`<div class="oi-correction ${c.correct?'is-correct':'is-review'}"><strong>${c.correct?'✓ Bonne réponse':'À revoir'} · Question ${i+1}</strong><p>${esc(c.explanation)}</p></div>`).join('')}`;
        target.scrollIntoView({behavior:'smooth',block:'nearest'});
      } catch(e) { if (sequence === routeSequence) error(e); } finally { button.disabled=false; }
    });
  }
  function chat(withContext=true) {
    ++routeSequence;
    shell();
    const context = withContext?activeFiche:null;
    activeFiche = null;
    setTab('ai');
    screen().innerHTML = `<nav class="oi-breadcrumb"><button type="button" data-home>${icon('back')} Mes révisions</button><span>Ton espace pour comprendre</span></nav><section class="oi-chat-page"><div class="oi-chat-orb">${icon('spark')}</div><span class="oi-eyebrow">TON ASSISTANT PERSONNEL SOIGNANT</span><h1>Une question.<br><em>Un nouveau déclic.</em></h1>${!OI.aiConfigured?'<p class="oi-chat-availability">L’assistant est en préparation. Découvre son espace ; les réponses seront disponibles prochainement.</p>':''}<p class="oi-chat-intro">Reformuler une notion, relier deux idées, préparer une révision.<br>Tu n’as plus à rester bloqué devant ton cours.</p>${context?`<div class="oi-chat-context">${icon('book')} On parle de : <strong>${esc(context.title)}</strong></div>`:''}<div class="oi-chat-suggestions"><button type="button" data-prompt="Explique-moi simplement le rôle de la surveillance infirmière.">${icon('book')} Comprendre une notion ${icon('arrow')}</button><button type="button" data-prompt="Aide-moi à réviser les points clés de la surveillance des traitements.">${icon('quiz')} Préparer ma révision ${icon('arrow')}</button><button type="button" data-prompt="Comment relier les effets d’un médicament à sa surveillance infirmière ?">${icon('pulse')} Faire le lien avec les soins ${icon('arrow')}</button></div><div id="oi-answer" class="oi-conversation" aria-live="polite"></div><form id="oi-chat" class="oi-chat-composer"><label for="oi-question">Qu’est-ce que tu veux éclaircir ?</label><textarea id="oi-question" required maxlength="2000" rows="3" placeholder="Explique-moi cette notion simplement…"></textarea><div><span>Un assistant pour apprendre, à ton rythme.</span><button type="submit">Poser ma question ${icon('send')}</button></div></form><p id="oi-status" role="status"></p><p class="oi-ai-privacy">${icon('shield')} Assistant automatisé dédié aux révisions. Aucune donnée de patient. Les réponses sont à vérifier avec tes cours et les protocoles ; elles ne remplacent pas un avis professionnel.</p></section>`;
    document.querySelector('#oi-chat').addEventListener('submit', async e=>{
      e.preventDefault();
      const button=e.target.querySelector('button[type=submit]');
      const question=document.querySelector('#oi-question').value.trim();
      if (!question) return;
      const sequence=routeSequence;
      button.disabled=true;
      status('Ton assistant cherche dans tes fiches…');
      try {
        const answer=await api('ai',{question,fiche_id:context?.id || 0});
        if (sequence!==routeSequence) return;
        status();
        const output=document.querySelector('#oi-answer');
        output.insertAdjacentHTML('beforeend',`<div class="oi-message oi-message-user"><span>TOI</span><p>${esc(question)}</p></div><div class="oi-message oi-message-assistant"><span>${icon('spark')} TON ASSISTANT</span><p class="oi-answer-text">${esc(answer.text)}</p>${answer.sources?.length?`<div class="oi-answer-sources"><strong>Pour aller plus loin</strong>${answer.sources.map(s=>`<button type="button" data-fiche="${Number(s.id)}">${icon('book')}${esc(s.title)}${icon('arrow')}</button>`).join('')}</div>`:''}</div>`);
        document.querySelector('#oi-question').value='';
        output.lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
      } catch(e) {if(sequence===routeSequence) error(e);} finally {button.disabled=false;}
    });
    window.scrollTo({top:0,behavior:'smooth'});
  }
  document.addEventListener('click', async e=>{
    const b=e.target.closest('button');
    if (!b || !b.closest('.oi-app')) return;
    try {
      if (b.dataset.tab) {if(b.dataset.tab==='ai') chat(false);else await dashboard(b.dataset.tab);}
      if (b.hasAttribute('data-home')) await dashboard();
      if (b.dataset.return) await dashboard(b.dataset.return,false);
      if (b.dataset.fiche) await fiche(Number(b.dataset.fiche));
      if (b.dataset.quizFiche) await fiche(Number(b.dataset.quizFiche),true);
      if (b.dataset.page) {page+=Number(b.dataset.page);await search();document.querySelector('#oi-library-title')?.scrollIntoView({behavior:'smooth'});}
      if (b.hasAttribute('data-favorites')) await dashboard('favorites');
      if (b.hasAttribute('data-ai')) chat();
      if (b.dataset.prompt) {document.querySelector('#oi-question').value=b.dataset.prompt;document.querySelector('#oi-question').focus();}
      if (b.hasAttribute('data-clear-search')) {document.querySelector('#oi-query').value='';document.querySelector('#oi-semester').value='';page=1;await search();}
      if (b.dataset.state && activeFiche) {
        b.disabled=true;
        const field=b.dataset.state;
        const ficheId=activeFiche.id;
        const value=!activeFiche.state?.[field];
        try {
          await api(`fiches/${ficheId}/state`,{field,value});
          if(activeFiche?.id===ficheId) {
            activeFiche.state={...activeFiche.state,[field]:value};
            b.setAttribute('aria-pressed',String(value));
            b.innerHTML=icon(field==='favorite'?'heart':'check')+(field==='favorite'?(value?'Favori':'Ajouter aux favoris'):(value?'Révisée':'Marquer comme révisée'));
            status(field==='favorite'?(value?'Fiche ajoutée à tes favoris.':'Fiche retirée de tes favoris.'):(value?'Ta progression est enregistrée.':'Fiche marquée comme à réviser.'));
          }
        } finally { b.disabled=false; }
      }
      if (b.dataset.buy) {
        b.disabled=true;
        try {const checkout=await api('checkout',{pack_id:Number(b.dataset.buy)});const url=new URL(checkout.url);if(url.protocol!=='https:' || url.hostname!=='checkout.stripe.com') throw new Error('Adresse de paiement invalide.');window.location.assign(url.href);}
        finally {b.disabled=false;}
      }
    } catch(e) {error(e);}
  });
  if (view && OI.loggedIn) dashboard().catch(error);
  else if(publicCatalog) showCatalog(publicCatalog).catch(error);
})();
