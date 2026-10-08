/* global OI */
(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const view = document.querySelector('#oi-view');
  const publicCatalog = document.querySelector('#oi-catalog');
  const paths = {
    folder: '<path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
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
    lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>',
    shield: '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
    pill: '<path d="M8 3a5 5 0 0 1 7 0l6 6a5 5 0 0 1-7 7l-6-6a5 5 0 0 1 0-7Z" transform="rotate(90 12 10)"/><path d="m8 8 8 8"/>',
    send: '<path d="m3 3 18 9-18 9 4-9zM7 12h14"/>',
    leaf: '<path d="M20 4C8 2 3 7 5 14s13 6 15-10Z"/><path d="m4 21 11-12"/>',
  };
  const icon = (name, cls='') => `<svg class="oi-icon ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.book}</svg>`;
  let account = null;
  let aiRequestId = null;
  let page = 1;
  let favoritesOnly = false;
  let currentTab = 'fiches';
  let activeFiche = null;
  let query = '';
  let semester = '';
  let unit = null;
  let theme = null;
  const folderNames = {unit:'',theme:''};
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
    if (!response.ok) {const e=new Error(result.message || 'Une erreur est survenue. Réessayez.');e.status=response.status;e.code=result.code;throw e;}
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
    view.innerHTML = `<div class="oi-workspace"><aside class="oi-sidebar"><nav class="oi-main-nav" aria-label="Mon espace membre">${navButton('fiches','Mes fiches','book')}${navButton('qcm','QCM & partiels','quiz')}${navButton('ai','Mon assistant','spark')}${navButton('favorites','Mes favoris','heart')}${navButton('progress','Ma progression','pulse')}${OI.admin?navButton('imports','Mise à jour Fiches','folder')+navButton('qcm-imports','Gérer les QCM','quiz'):''}${navButton('premium','Découvrir le Premium','shield')}</nav><div class="oi-sidebar-note">${icon('leaf')}<strong>Un peu chaque jour.<br>Plus solide demain.</strong><p>Ton diplôme se construit une notion à la fois.</p></div><div class="oi-student"><span class="oi-avatar">${esc((OI.name || 'É').slice(0,1).toUpperCase())}</span><span><strong>${esc(OI.name)}</strong><small>Espace personnel</small></span><span class="oi-online-dot" title="Connecté"></span></div></aside><div id="oi-screen" class="oi-screen"><p id="oi-status" role="status">On prépare ton espace…</p></div></div>`;
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
    const name = item.terms?.theme?.filter(t=>/^[A-E][1-9] - \d+ /.test(t.name)).map(t=>t.name).join(' · ') || item.terms?.enseignement?.map(t=>t.name).join(' · ') || 'Unité d’enseignement à préciser';
    const pharmacology = /pharmaco|médicament|furosémide|paracétamol|héparine|insuline|morphine/i.test(name+' '+item.title);
    return {name, icon:pharmacology?'pill':/cardio|vital|constante/i.test(name+' '+item.title)?'pulse':/soin|hygiène|sécurité/i.test(name+' '+item.title)?'shield':'book', color:pharmacology?'peach':/soin|hygiène/i.test(name+' '+item.title)?'blue':'mint'};
  }
  function card(item, quizMode=false) {
    const topic = subject(item), kind=quizMode?'QCM':'FICHE';
    const accessible=!!item.access?.[kind], available=Number(account?.balances?.[kind]||0);
    const action=accessible?`data-${quizMode?'quiz-fiche':'fiche'}="${Number(item.id)}"`:`data-unlock="${Number(item.id)}" data-kind="${kind}"`;
    const label=accessible?(quizMode?'Commencer le QCM':item.state?.revised?'Révisée':'Ouvrir la fiche'):(available?`Débloquer ${quizMode?'cette série':'cette fiche'} — 1 crédit`:'Plus de crédits — voir le Premium');
    return `<button type="button" class="oi-card oi-fiche-card oi-tone-${topic.color} ${accessible?'is-unlocked':'is-locked'}" ${action}><span class="oi-card-top"><span class="oi-subject-icon">${icon(quizMode?'quiz':topic.icon)}</span><span class="oi-card-badge">${accessible?(account?.administrator?'Accès administrateur':account?.premium?'Inclus dans mon accès':'Débloquée'):icon('lock')+' Verrouillée'}</span></span><span class="oi-eyebrow">${esc(topic.name)}</span><h3>${esc(item.title)}</h3><span class="oi-card-description">${quizMode?`${Number(item.quiz_count)} question(s) · Correction expliquée`:'Les repères pour comprendre et retenir.'}${!accessible?`<br>${available} crédit(s) ${quizMode?'QCM':'Fiche'} restant(s)`:''}</span><span class="oi-card-bottom"><span>${label}</span>${icon(accessible?'arrow':'lock')}</span>${item.state?.favorite?'<span class="oi-card-favorite" aria-label="En favori">'+icon('heart')+'</span>':''}</button>`;
  }
  function verification() {
    screen().innerHTML=`<section class="oi-verification"><span class="oi-eyebrow">UNE DERNIÈRE ÉTAPE</span><h1>Vérifie ton adresse e-mail.</h1><p>${account.verification_email_sent?'Ouvre le message Objectif Infirmière reçu dans ta boîte mail, puis confirme ton adresse. Pense aussi aux indésirables.':'L’e-mail de confirmation n’a pas pu être envoyé. Tu peux demander un nouvel envoi ci-dessous.'}</p><p>Tu recevras ensuite 5 crédits Fiche, 5 crédits QCM et 5 crédits IA, une seule fois.</p><button type="button" data-resend>Renvoyer le lien de vérification</button><button type="button" data-refresh-account>J’ai confirmé mon adresse</button><p id="oi-status" role="status"></p></section>`;
  }
  async function loadAccount() {
    account=await api('account');
    const premiumNav=document.querySelector('.oi-main-nav [data-tab="premium"]');if(premiumNav)premiumNav.hidden=account.premium;
    if(account.verification_required){verification();return false;}return true;
  }
  async function premium() {
    const sequence=++routeSequence;shell();setTab('premium');if(!await loadAccount()||sequence!==routeSequence)return;
    const offer=account.offer;
    if(!account.premium)api('events',{event:'premium_view'}).catch(()=>{});
    screen().innerHTML=`<section class="oi-premium-page"><span class="oi-eyebrow">${account.premium?'MON ACCÈS':'PACK PREMIUM OBJECTIF INFIRMIÈRE'}</span><h1>${account.premium?'Ton Premium est actif.':'Toutes tes révisions.<br>Un seul paiement.'}</h1>${account.premium?'<p>Tes fiches et QCM du pack sont accessibles sans dépenser de crédits, sans expiration automatique en V1.</p>':`<div class="oi-premium-price"><strong>59 €</strong><span>Paiement unique · Aucun abonnement</span></div><ul><li>${Number(offer.fiches)} fiches publiées dans le pack</li><li>${Number(offer.qcm)} séries de QCM à recommencer</li><li>Favoris et progression conservés</li><li>100 crédits IA supplémentaires, non renouvelés automatiquement</li></ul><p>Les crédits IA gratuits restants sont conservés. Les contenus de démonstration ne remplacent pas tes cours ni les protocoles de soins.</p>${!account.ai_available?'<p>Le conseiller IA est encore en préparation : les crédits restent disponibles pour son activation.</p>':''}<button type="button" data-buy="${Number(offer.pack_id)}" ${!offer.available||!account.verified?'disabled':''}>Débloquer mon accès Premium — 59 €</button><p class="oi-muted">Paiement Stripe TEST uniquement. Prix et fiscalité à confirmer avant commercialisation.</p>${!offer.available?'<p>Le paiement de démonstration n’est pas encore configuré.</p>':''}`}<button type="button" data-tab="fiches">Explorer les fiches</button><p id="oi-status" role="status"></p></section>`;
  }
  async function qcmImports(){if(!OI.admin)return;const sequence=++routeSequence;shell();setTab('qcm-imports');const host=document.createElement('div');screen().replaceChildren(host);await window.OIQCMImports.render(host);if(sequence!==routeSequence)host.remove();}
  async function imports(){if(!OI.admin)return;const sequence=++routeSequence;shell();setTab('imports');screen().innerHTML='<p role="status">Chargement de ton atelier…</p>';const host=document.createElement('div');screen().replaceChildren(host);await window.OIImports.render(host);if(sequence!==routeSequence)host.remove();}
  async function progression() {
    const sequence=++routeSequence;shell();setTab('progress');if(!await loadAccount()||sequence!==routeSequence)return;
    screen().innerHTML=`<section class="oi-page-intro"><span class="oi-eyebrow">CHAQUE SÉANCE COMPTE</span><h1>Ma progression.</h1><p>Retrouve les fiches consultées, tes révisions et les séries travaillées.</p></section>${progress(account.progress)}<button type="button" data-tab="favorites">Retrouver mes favoris</button>${!account.premium?'<button type="button" data-tab="premium">Découvrir le Premium</button>':''}<p id="oi-status" role="status"></p>`;
  }
  function progress(p) {
    const total = Number(p?.total || 0);
    const revised = Number(p?.revised || 0);
    const percentage = total ? Math.round(revised/total*100) : 0;
    return `<section class="oi-progress-panel" aria-label="Ma progression"><div class="oi-progress-title"><span>${icon('pulse')} Ma progression</span><strong>${percentage}%</strong></div><progress value="${revised}" max="${Math.max(1,total)}" aria-label="Fiches révisées"></progress><p><strong>${revised} / ${total}</strong> fiches révisées<span>${Number(p?.quizzes || 0)} QCM réalisé${Number(p?.quizzes || 0)>1?'s':''}</span></p></section>`;
  }
  function aiBanner() {
    return `<section class="oi-ai-banner"><span class="oi-ai-banner-icon">${icon('spark')}</span><div><span class="oi-eyebrow">UN COUP DE POUCE QUAND ÇA BLOQUE</span><h2>Et si on te l’expliquait autrement ?</h2><p>Ton assistant personnel t’aide à faire le lien entre les notions.</p></div><button type="button" data-ai>Ouvrir mon assistant ${icon('arrow')}</button></section>`;
  }
  function recentSection(items) {
    return `<section class="oi-recent" aria-labelledby="oi-recent-title"><div class="oi-section-title"><div><span class="oi-eyebrow">REPRENDS LE FIL</span><h2 id="oi-recent-title">Dernières fiches consultées</h2><p>Les plus récentes en premier, pour reprendre là où tu en étais.</p></div></div>${items.length?`<div class="oi-grid">${items.map(item=>card(item)).join('')}</div>`:'<p class="oi-recent-empty">Tes dernières lectures apparaîtront ici dès que tu ouvriras une fiche.</p>'}</section>`;
  }
  function folderFilters() {
    return currentTab==='fiches'&&!query?(unit!==null?'&enseignement='+unit:'')+(theme!==null?'&theme='+theme:''):'';
  }
  function folderTrail() {
    if(currentTab!=='fiches')return '';
    return `<nav class="oi-folder-trail" aria-label="Parcours des fiches"><button type="button" data-library-level="root" ${unit===null&&!query?'aria-current="page"':''}>${icon('folder')} Toutes les unités</button>${query?'<span>Résultats de recherche</span>':unit!==null?`${icon('arrow')}<button type="button" data-library-level="unit" ${theme===null?'aria-current="page"':''}>${esc(folderNames.unit)}</button>${theme!==null?icon('arrow')+'<span aria-current="page">'+esc(folderNames.theme)+'</span>':''}`:''}</nav>`;
  }
  async function libraryResults(data,quizMode) {
    if(currentTab!=='fiches'||query||theme!==null)return resultCards(data,quizMode);
    const folders=await api(`library?semestre=${encodeURIComponent(semester)}${unit!==null?'&enseignement='+unit:''}`);
    return folders.folders.map(folder=>`<button type="button" class="oi-folder-card" data-folder="${Number(folder.id)}" data-folder-name="${esc(folder.name)}"><span class="oi-folder-symbol">${icon('folder')}</span><span class="oi-folder-copy"><span class="oi-eyebrow">${unit===null?'UNITÉ D’ENSEIGNEMENT':'THÈME'}</span><h3>${esc(folder.name)}</h3><span>${folder.count?Number(folder.count)+' fiche'+(folder.count>1?'s':''):'À venir'}</span></span>${icon('arrow')}</button>`).join('') || '<div class="oi-empty"><h3>Aucune fiche dans ce dossier pour le moment.</h3><p>Choisis une autre unité ou modifie le filtre de semestre.</p></div>';
  }
  function searchForm(data, quizMode) {
    return `<form id="oi-search" class="oi-search"><div class="oi-search-field">${icon('search')}<label class="oi-sr-only" for="oi-query">Rechercher dans mes fiches</label><input id="oi-query" type="search" value="${esc(query)}" placeholder="${quizMode?'Quel sujet veux-tu travailler ?':'Une notion, un médicament, une UE…'}" maxlength="150"><button type="submit" class="oi-search-submit">Rechercher</button></div><div class="oi-semester-field"><label class="oi-sr-only" for="oi-semester">Semestre</label><select id="oi-semester"><option value="">Tous les semestres</option>${(data.semesters||[]).map(s=>`<option value="${Number(s.id)}" ${String(s.id)===semester?'selected':''}>${esc(s.name)}</option>`).join('')}</select></div></form>`;
  }
  function resultCards(data, quizMode) {
    const items = quizMode ? data.items.filter(item=>Number(item.quiz_count)>0) : data.items;
    return items.map(item=>card(item,quizMode)).join('') || `<div class="oi-empty">${icon(quizMode?'quiz':favoritesOnly?'heart':'book')}<h3>${favoritesOnly?'Tes essentiels, au même endroit.':quizMode?'Aucun QCM pour cette sélection.':'Aucune fiche pour cette sélection.'}</h3><p>${favoritesOnly?'Ajoute une fiche aux favoris depuis sa page pour la retrouver ici.':data.total?'Essaie un autre mot-clé ou un autre semestre.':query?'Essaie un autre mot-clé.':'Les fiches de ce thème arrivent prochainement.'}</p>${query||semester?'<button type="button" data-clear-search>Effacer les filtres</button>':''}</div>`;
  }
  function pagination(data) {
    const target = document.querySelector('#oi-pagination');
    if (!target) return;
    if(currentTab==='fiches'&&!query&&theme===null){target.innerHTML='';return;}
    target.innerHTML = `<span>${Number(data.total)} fiche${Number(data.total)>1?'s':''}${currentTab==='qcm'?' à explorer':''}${Number(data.pages)>1?` · Page ${page} sur ${Number(data.pages)}`:''}</span><div>${page>1?'<button type="button" data-page="-1">'+icon('back')+' Précédent</button>':''}${page<Number(data.pages)?'<button type="button" data-page="1">Suivant '+icon('arrow')+'</button>':''}</div>`;
  }
  async function dashboard(tab='fiches', reset=true) {
    shell();
    const sequence = ++routeSequence;
    setTab(tab);
    activeFiche = null;
    if(!await loadAccount())return;
    if(sequence!==routeSequence)return;
    favoritesOnly = tab==='favorites';
    if (reset) { page=1; query=''; semester=''; unit=null; theme=null; }
    screen().setAttribute('aria-busy','true');
    let data;
    try { data=await api(`fiches?page=${page}&q=${encodeURIComponent(query)}&semestre=${encodeURIComponent(semester)}${favoritesOnly?'&favorites=1':''}${currentTab==='qcm'?'&kind=QCM':''}${folderFilters()}`); }
    finally { screen().removeAttribute('aria-busy'); }
    if (sequence!==routeSequence) return;
    account=data.account;
    const quizMode = tab==='qcm';
    const intro = favoritesOnly ? `<div class="oi-page-intro"><span class="oi-eyebrow">TA SÉLECTION PERSONNELLE</span><h1 id="oi-favorites-title">À garder sous la main.</h1></div>` : quizMode ? `<div class="oi-page-intro"><span class="oi-eyebrow">PLACE À LA PRATIQUE</span><h1>Tu sais. Maintenant,<br><em>prouve-le-toi.</em></h1><p>Teste tes connaissances, comprends tes erreurs et avance vers tes partiels.</p></div>` : `<div class="oi-dashboard-intro"><div><p class="oi-library-welcome">Tes notions de soins infirmiers, un peu plus claires chaque jour.</p><h1>Bonjour ${esc(OI.name)}.</h1></div></div>`;
    const [contents,recent] = await Promise.all([libraryResults(data,quizMode), tab==='fiches'?api('recent'):Promise.resolve([])]);
    if(sequence!==routeSequence)return;
    screen().innerHTML = `${intro}${quizMode?'<div id="oi-training"></div>':''}${quizMode?'<div class="oi-qcm-notice">'+icon('quiz')+'<div><strong>Le bon réflexe avant les partiels.</strong><span>Des QCM par sujet, à ton rythme, avec une correction expliquée après chaque série.</span></div></div>':''}<section class="oi-library" aria-labelledby="${favoritesOnly?'oi-favorites-title':'oi-library-title'}">${favoritesOnly?'':`<div class="oi-section-title"><div><span class="oi-eyebrow">${quizMode?'APPRENDRE EN S’ENTRAÎNANT':favoritesOnly?'LE MEILLEUR DE TES RÉVISIONS':'TA BIBLIOTHÈQUE'}</span><h2 id="oi-library-title">${quizMode?'Choisis ton prochain défi.':favoritesOnly?'Mes favoris':'Qu’est-ce qu’on révise ?'}</h2></div>${!favoritesOnly&&!quizMode?'<button type="button" class="oi-text-button" data-tab="favorites">'+icon('heart')+' Mes favoris</button>':''}</div>`}${searchForm(data,quizMode)}<p id="oi-status" role="status"></p><div id="oi-folder-trail">${folderTrail()}</div><div id="oi-results" class="oi-grid">${contents}</div><div id="oi-pagination" class="oi-pagination"></div></section>${!quizMode&&!favoritesOnly?recentSection(recent)+aiBanner():''}<footer class="oi-member-footer"><span>Objectif Infirmière</span><span>Un pas de plus vers la blouse.</span></footer>`;
    if(quizMode)await window.OITraining.render(document.querySelector('#oi-training'),id=>fiche(id));
    if(sequence!==routeSequence)return;
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
    results?.querySelectorAll('button').forEach(button=>button.disabled=true);
    status('Recherche en cours…');
    try {
      const data = await api(`fiches?page=${page}&q=${encodeURIComponent(query)}&semestre=${encodeURIComponent(semester)}${favoritesOnly?'&favorites=1':''}${currentTab==='qcm'?'&kind=QCM':''}${folderFilters()}`);
      if (sequence!==searchSequence || route!==routeSequence) return;
      const contents=await libraryResults(data,currentTab==='qcm');
      if(sequence!==searchSequence || route!==routeSequence)return;
      results.innerHTML = contents;
      document.querySelector('#oi-folder-trail').innerHTML=folderTrail();
      pagination(data);
      status(data.total || !query ? '' : 'Aucun résultat pour cette recherche.');
    } finally { if(sequence===searchSequence){results?.removeAttribute('aria-busy');results?.querySelectorAll('button').forEach(button=>button.disabled=false);} }
  }
  async function fiche(id, quizOnly=false) {
    const sequence = ++routeSequence;
    status('Ouverture de la fiche…');
    const data = await api(`${quizOnly?'qcm':'fiches'}/${id}`);
    if (sequence!==routeSequence) return;
    activeFiche = data;
    setTab(quizOnly?'qcm':'fiches');
    const topic = subject(data);
    screen().innerHTML = `<nav class="oi-breadcrumb" aria-label="Fil d’Ariane"><button type="button" data-return="${quizOnly?'qcm':'fiches'}">${icon('back')} ${quizOnly?'Tous les QCM':'Mes révisions'}</button><span>${esc(data.terms?.semestre?.map(t=>t.name).join(' · ') || 'IFSI')}</span></nav><article class="oi-reader"><header class="oi-reader-heading"><span class="oi-reader-symbol oi-tone-${topic.color}">${icon(quizOnly?'quiz':topic.icon)}</span><span class="oi-eyebrow">${quizOnly?'QCM · ':''}${esc(topic.name)}</span><h1>${esc(data.title)}</h1><p class="oi-muted">${quizOnly?'Prends le temps de réfléchir. La correction t’attend à la fin.':`Mise à jour le ${esc(new Date(data.updated+'Z').toLocaleDateString('fr-FR'))}`}</p></header>${!quizOnly?`<div class="oi-actions"><button type="button" data-state="favorite" aria-pressed="${!!data.state?.favorite}">${icon('heart')}${data.state?.favorite?'Favori':'Ajouter aux favoris'}</button><button type="button" data-state="revised" aria-pressed="${!!data.state?.revised}">${icon('check')}${data.state?.revised?'Révisée':'Marquer comme révisée'}</button><button type="button" class="oi-ask-button" data-ai>${icon('spark')}Poser une question sur cette fiche</button></div>`:''}<p id="oi-status" role="status"></p>${!quizOnly?'<div class="oi-reading-layout"><nav id="oi-toc" aria-label="Sommaire"></nav><div class="oi-content oi-protected" data-protected>'+data.content+'</div></div><div id="oi-watermark"></div>':''}<section id="oi-quiz" class="oi-quiz-section"></section>${!quizOnly?`<section class="oi-related"><span class="oi-eyebrow">GARDE TON ÉLAN</span><h2>On continue ?</h2><div class="oi-grid">${data.related.map(item=>card(item)).join('')}</div></section>${aiBanner()}`:''}</article>`;
    if (!quizOnly) {
      const headings = [...screen().querySelectorAll('.oi-content h2, .oi-content h3')];
      headings.forEach((h,i)=>{h.id=`oi-section-${i}`;});
      document.querySelector('#oi-toc').innerHTML = headings.length ? `<span class="oi-eyebrow">DANS CETTE FICHE</span>${headings.map((h,i)=>`<a href="#${h.id}"><span>${String(i+1).padStart(2,'0')}</span>${esc(h.textContent)}</a>`).join('')}` : '';
      if (data.watermark) {document.querySelector('#oi-watermark').textContent = data.watermark;document.querySelector('.oi-content').setAttribute('data-watermark',data.watermark);}
    }
    if (data.quiz?.length) await window.OITraining.start(document.querySelector('#oi-quiz'),{scope:'fiche',id:Number(id),count:20},id=>fiche(id));
    else if (!quizOnly && data.quiz_count>0) document.querySelector('#oi-quiz').innerHTML='<h2>La série de QCM associée</h2><p>Les fiches et séries de QCM se débloquent séparément.</p><button type="button" data-unlock="'+Number(id)+'" data-kind="QCM">Débloquer cette série — 1 crédit QCM</button>';
    else if (quizOnly) document.querySelector('#oi-quiz').innerHTML='<div class="oi-empty"><h2>Ce QCM arrive bientôt.</h2><p>Tu peux déjà réviser le cours associé.</p><button type="button" data-fiche="'+Number(id)+'">Ouvrir la fiche</button></div>';
    window.scrollTo({top:0,behavior:'smooth'});
  }
  async function showCatalog(target) {
    if (!target) return;
    const packs = (await api('catalog')).filter(p=>p.id===account?.offer?.pack_id);
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
  async function chat(withContext=true) {
    const openingSequence=++routeSequence;
    shell();
    const context = withContext?activeFiche:null;
    activeFiche = null;
    setTab('ai');
    if(!await loadAccount())return;
    if(openingSequence!==routeSequence)return;
    aiRequestId=null;
    screen().innerHTML = `<nav class="oi-breadcrumb"><button type="button" data-home>${icon('back')} Mes révisions</button><span>Ton espace pour comprendre</span></nav><section class="oi-chat-page"><div class="oi-chat-orb">${icon('spark')}</div><span class="oi-eyebrow">TON ASSISTANT PERSONNEL SOIGNANT</span><h1>Une question.<br><em>Un nouveau déclic.</em></h1>${!account.ai_available?'<p class="oi-chat-availability">L’assistant est en préparation. Découvre son espace ; les réponses seront disponibles prochainement.</p>':''}${Number(account.balances.IA)===0?`<p class="oi-credit-empty">${account.premium?'Tes crédits IA sont épuisés. Tes fiches et QCM restent accessibles.':'Tes questions gratuites sont épuisées. Le Premium inclut 100 questions supplémentaires.'}</p>`:''}<p class="oi-chat-intro">Reformuler une notion, relier deux idées, préparer une révision.<br>Tu n’as plus à rester bloqué devant ton cours.</p>${context?`<div class="oi-chat-context">${icon('book')} On parle de : <strong>${esc(context.title)}</strong></div>`:''}<div class="oi-chat-suggestions"><button type="button" data-prompt="Explique-moi simplement le rôle de la surveillance infirmière.">${icon('book')} Comprendre une notion ${icon('arrow')}</button><button type="button" data-prompt="Aide-moi à réviser les points clés de la surveillance des traitements.">${icon('quiz')} Préparer ma révision ${icon('arrow')}</button><button type="button" data-prompt="Comment relier les effets d’un médicament à sa surveillance infirmière ?">${icon('pulse')} Faire le lien avec les soins ${icon('arrow')}</button></div><div id="oi-answer" class="oi-conversation" aria-live="polite"></div><p class="oi-question-balance"><strong data-balance="IA">${Number(account.balances.IA)}</strong> question(s) IA disponible(s) · 1 crédit par réponse réussie</p><form id="oi-chat" class="oi-chat-composer"><label for="oi-question">Qu’est-ce que tu veux éclaircir ?</label><textarea id="oi-question" required maxlength="2000" rows="3" placeholder="Explique-moi cette notion simplement…"></textarea><div><span>Un assistant pour apprendre, à ton rythme.</span><button type="submit" ${!account.ai_available||Number(account.balances.IA)===0?'disabled':''}>Poser ma question ${icon('send')}</button></div></form><p id="oi-status" role="status"></p><p class="oi-ai-privacy">${icon('shield')} Assistant automatisé dédié aux révisions. Aucune donnée de patient. Les réponses sont à vérifier avec tes cours et les protocoles ; elles ne remplacent pas un avis professionnel.</p></section>`;
    document.querySelector('#oi-chat').addEventListener('submit', async e=>{
      e.preventDefault();
      const button=e.target.querySelector('button[type=submit]');
      const question=document.querySelector('#oi-question').value.trim();
      if (!question) return;
      const sequence=routeSequence;
      button.disabled=true;
      status('Ton assistant cherche dans tes fiches…');
      try {
        aiRequestId ||= crypto.randomUUID();
        const answer=await api('ai',{question,fiche_id:context?.id || 0,request_id:aiRequestId});
        aiRequestId=null;account=await api('account');document.querySelectorAll('[data-balance=IA]').forEach(el=>el.textContent=Number(account.balances.IA));
        if (sequence!==routeSequence) return;
        status();
        const output=document.querySelector('#oi-answer');
        output.insertAdjacentHTML('beforeend',`<div class="oi-message oi-message-user"><span>TOI</span><p>${esc(question)}</p></div><div class="oi-message oi-message-assistant"><span>${icon('spark')} TON ASSISTANT</span><p class="oi-answer-text">${esc(answer.text)}</p>${answer.sources?.length?`<div class="oi-answer-sources"><strong>Pour aller plus loin</strong>${answer.sources.map(s=>`<button type="button" data-fiche="${Number(s.id)}">${icon('book')}${esc(s.title)}${icon('arrow')}</button>`).join('')}</div>`:''}</div>`);
        document.querySelector('#oi-question').value='';
        output.lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
      } catch(e) {if(e.status)aiRequestId=null;if(sequence===routeSequence) error(e);} finally {button.disabled=!account.ai_available||Number(account.balances.IA)===0;}
    });
    window.scrollTo({top:0,behavior:'smooth'});
  }
  for(const event of ['copy','cut','contextmenu','dragstart'])document.addEventListener(event,e=>{if(e.target.closest?.('[data-protected]')||window.getSelection()?.anchorNode?.parentElement?.closest('[data-protected]'))e.preventDefault();});
  document.addEventListener('click', async e=>{
    const b=e.target.closest('button');
    if (!b || !b.closest('.oi-app')) return;
    try {
      if (b.dataset.tab) {if(b.dataset.tab==='ai') await chat(false);else if(b.dataset.tab==='premium')await premium();else if(b.dataset.tab==='progress')await progression();else if(b.dataset.tab==='imports')await imports();else if(b.dataset.tab==='qcm-imports')await qcmImports();else await dashboard(b.dataset.tab);}
      if(b.hasAttribute('data-refresh-account'))await dashboard();
      if(b.hasAttribute('data-resend')){b.disabled=true;try{await api('verify/resend',{});status('Un nouveau lien a été envoyé. Vérifie ta boîte mail et les indésirables.');}finally{b.disabled=false;}}
      if(b.dataset.unlock){
        if(!await loadAccount())return;
        b.disabled=true;
        try{const result=await api('unlock',{id:Number(b.dataset.unlock),kind:b.dataset.kind});account=result.account;await fiche(Number(b.dataset.unlock),b.dataset.kind==='QCM');}catch(e){if(e.code==='oi_no_credit'){await premium();status('Tes crédits sont épuisés. Tes contenus déjà débloqués restent accessibles.');}else throw e;}finally{b.disabled=false;}
      }
      if (b.hasAttribute('data-home')) await dashboard();
      if (b.dataset.return) await dashboard(b.dataset.return,false);
      if(b.hasAttribute('data-folder')) {
        const selected=Number(b.dataset.folder);
        if(unit===null){unit=selected;folderNames.unit=b.dataset.folderName;theme=null;}
        else{theme=selected;folderNames.theme=b.dataset.folderName;}
        page=1;await search();document.querySelector('#oi-folder-trail button:last-child')?.focus();
      }
      if(b.hasAttribute('data-library-level')) {
        if(b.dataset.libraryLevel==='root'){unit=null;theme=null;}else theme=null;
        document.querySelector('#oi-query').value='';page=1;await search();
      }
      if (b.dataset.fiche) await fiche(Number(b.dataset.fiche));
      if (b.dataset.quizFiche) await fiche(Number(b.dataset.quizFiche),true);
      if (b.dataset.page) {page+=Number(b.dataset.page);await search();document.querySelector('#oi-library-title')?.scrollIntoView({behavior:'smooth'});}
      if (b.hasAttribute('data-favorites')) await dashboard('favorites');
      if (b.hasAttribute('data-ai')) await chat();
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
  async function paymentReturn() {
    const state=new URLSearchParams(location.search).get('oi_payment');
    if(!['received','cancelled'].includes(state))return;
    const banner=document.createElement('p');banner.className='oi-payment-notice';banner.setAttribute('role','status');view.prepend(banner);
    if(state==='cancelled'){banner.textContent='Paiement interrompu. Ton accès actuel et tes crédits sont conservés.';return;}
    banner.textContent='Nous attendons la confirmation sécurisée de ton paiement. Ton accès sera activé dès sa réception.';
    for(let attempt=0;attempt<10;attempt++){
      try{
        const current=await api('account');
        if(current.premium){await dashboard();banner.textContent='Ton accès Premium est actif. Tes fiches et QCM sont disponibles ; retrouve ton solde IA dans ton espace.';return;}
      }catch{banner.textContent='La confirmation est en cours. Actualise ton espace dans quelques instants.';return;}
      await new Promise(resolve=>setTimeout(resolve,3000));
    }
    banner.textContent='La confirmation du paiement prend un peu de temps. Actualise ton espace dans quelques instants avant de tenter un nouvel achat.';
  }
  if (view && OI.loggedIn) (new URLSearchParams(location.search).get('view')==='premium'?premium():new URLSearchParams(location.search).get('view')==='imports'&&OI.admin?imports():new URLSearchParams(location.search).get('view')==='qcm-imports'&&OI.admin?qcmImports():new URLSearchParams(location.search).get('view')==='qcm'?dashboard('qcm'):dashboard()).then(paymentReturn).catch(error);
  else if(publicCatalog) showCatalog(publicCatalog).catch(error);
})();
