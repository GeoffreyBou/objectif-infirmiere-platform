/* global OI */
(() => {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const view = document.querySelector('#oi-view');
  const publicCatalog = document.querySelector('#oi-catalog');
  const paths = {
    users: '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v3"/>',
    crown: '<path d="m3 6 4 4 5-7 5 7 4-4-2 13H5Z"/><path d="M5 16h14"/>',
    print: '<path d="M7 8V3h10v5M7 17H4V8h16v9h-3"/><path d="M7 14h10v7H7Z"/><path d="M16 11h1"/>',
    target: '<circle cx="11" cy="13" r="8"/><circle cx="11" cy="13" r="4"/><path d="m11 13 9-10m-1 0h3v3"/>',
    bolt: '<path d="m13 2-9 12h7l-1 8 10-13h-7z"/>',
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
  let aiDraft = null;
  let page = 1;
  let favoritesOnly = false;
  let ownedOnly = false;
  const canFilterOwned = () => currentTab==='fiches' && account && !account.premium && !account.administrator;
  let currentTab = 'fiches';
  let activeFiche = null;
  let query = '';
  let unit = null;
  let theme = null;
  const folderNames = {unit:'',theme:''};
  let routeSequence = 0;
  let searchSequence = 0;
  let tocObserver = null;
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
    if (target) { target.classList.remove('oi-loading'); target.textContent = e.message; target.setAttribute('role','alert'); }
  }
  function status(message='') {
    const target = document.querySelector('#oi-status');
    if (target) { target.setAttribute('role','status'); target.textContent = message; }
  }
  function notifyEmptyCredits(kind) {
    document.querySelector('.oi-credit-notice')?.remove();
    const notice=document.createElement('aside');
    notice.className='oi-credit-notice';
    const quiz=kind==='QCM';
    notice.innerHTML=`<span class="oi-credit-notice-icon">${icon('lock')}</span><div role="status" aria-live="polite"><strong>Tes crédits ${quiz?'QCM':'Fiche'} sont épuisés.</strong><p>Tu as été redirigé vers l’offre Premium pour débloquer ${quiz?'cette série et accéder à tous les QCM':'cette fiche et accéder à toutes les fiches'}.</p></div><button type="button" aria-label="Fermer la notification">×</button>`;
    notice.querySelector('button').addEventListener('click',()=>notice.remove());
    view.append(notice);
  }
  function navButton(tab, label, glyph) {
    return `<button type="button" data-tab="${tab}" ${currentTab===tab?'aria-current="page"':''}>${icon(glyph)}<span>${label}</span>${tab==='ai'?'<span class="oi-nav-tag">IA</span>':''}</button>`;
  }
  function shell() {
    if (document.querySelector('#oi-screen')) return;
    const loading = view.querySelector('#oi-status');
    view.removeAttribute('aria-live');
    view.innerHTML = `<div class="oi-workspace"><aside class="oi-sidebar"><nav class="oi-main-nav" aria-label="Mon espace membre">${navButton('fiches','Mes fiches','book')}${navButton('qcm','QCM & partiels','quiz')}${navButton('ai','Mon assistant','spark')}${navButton('favorites','Mes favoris','heart')}${navButton('progress','Ma progression','pulse')}${OI.admin?navButton('imports','Mise à jour Fiches','folder')+navButton('qcm-imports','Gérer les QCM','quiz')+navButton('users','Gérer les utilisateurs','users'):''}</nav><section class="oi-credit-wallet" aria-label="Mes crédits" aria-live="polite" aria-atomic="true" hidden></section><button type="button" class="oi-sidebar-unlock" data-tab="premium" hidden><span class="oi-premium-card-title">${icon('crown')}<strong>Passer Premium</strong></span><span class="oi-premium-card-copy">Débloquez toutes les fiches, QCMs et contenus exclusifs</span><span class="oi-premium-card-cta">Découvrir l’offre ${icon('arrow')}</span></button><div class="oi-sidebar-signature"><p>Réviser aujourd’hui,<br>soigner demain !</p></div></aside><div id="oi-screen" class="oi-screen"></div></div>`;
    if (loading) screen().append(loading);
  }
  function setTab(tab, ficheId=null) {
    document.querySelector('.oi-credit-notice')?.remove();
    const url=new URL(location.href);
    const sameReader=ficheId!==null&&url.searchParams.get('view')===tab&&url.searchParams.get('fiche')===String(ficheId);
    url.searchParams.set('view',tab);
    if(tab==='fiches'&&ownedOnly)url.searchParams.set('access','unlocked');else url.searchParams.delete('access');
    if(ficheId!==null)url.searchParams.set('fiche',String(ficheId));else url.searchParams.delete('fiche');
    if(!sameReader)url.hash='';
    history.replaceState(history.state,'',url);
    tocObserver?.disconnect();
    tocObserver = null;
    currentTab = tab;
    document.querySelectorAll('.oi-sidebar [data-tab]').forEach(button=>{
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
  function artFamily(item) {
    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/œ/g,'oe');
    const themes = normalize(item.terms?.theme?.map(term=>term.name).join(' '));
    const units = normalize(item.terms?.enseignement?.map(term=>term.name).join(' '));
    // Theme labels survive Word updates and taxonomy IDs differ between environments.
    // New themes inherit a family; uncategorised subjects retain the general visual.
    if (/(?:^|\s)c\.?2\b/.test(units+' '+themes)) return 'environment';
    const family = text => {
      if (/nerveux|neuro|cerebr|encephal|cognition|cogniti/.test(text)) return 'neuro';
      if (/cardio|vasculaire|coeur|cardiaque|circulation sanguine|hemodynam/.test(text)) return 'cardio';
      if (/pharmaco|medicament|transfusion|therapeutique|antibio|furos[eé]mide|paracetamol|heparine|insuline|morphine/.test(text)) return 'pharma';
      if (/environnement|ecologi|climat|pollution|one health|zoonose/.test(text)) return 'environment';
      if (/soin|hygiene|securite|clinique|urgence|secours|prevention|sante mentale|relation|communication/.test(text)) return 'care';
      if (/recherche|methodologie|statistique|epistemologie|donnees probantes/.test(text)) return 'research';
      return '';
    };
    return family(themes) || family(normalize(item.title)) || family(units) || 'research';
  }
  function followReaderSections(headings) {
    const toc = document.querySelector('#oi-toc');
    if (!toc || !headings.length) return;
    const links = [...toc.querySelectorAll('a')];
    const activate = id => links.forEach(link=>{
      if (link.getAttribute('href')==='#'+id) link.setAttribute('aria-current','location');
      else link.removeAttribute('aria-current');
    });
    activate(headings[0].id);
    // Native anchors keep their keyboard and browser navigation behaviour.
    toc.addEventListener('click', event=>{
      const link = event.target.closest('a');
      if (link && toc.contains(link)) activate(link.getAttribute('href').slice(1));
    });
    if (!('IntersectionObserver' in window)) return;
    tocObserver = new IntersectionObserver(()=>{
      if (!toc.isConnected) return;
      const passed = headings.filter(heading=>heading.getBoundingClientRect().top<=160);
      activate((passed[passed.length-1] || headings[0]).id);
    }, {rootMargin:'-110px 0px -45% 0px',threshold:0});
    headings.forEach(heading=>tocObserver.observe(heading));
  }
  function card(item, quizMode=false) {
    const topic = subject(item), kind=quizMode?'QCM':'FICHE';
    const accessible=!!item.access?.[kind], available=Number(account?.balances?.[kind]||0);
    const action=accessible?`data-${quizMode?'quiz-fiche':'fiche'}="${Number(item.id)}"`:available>0?`data-unlock="${Number(item.id)}" data-kind="${kind}"`:'data-tab="premium"';
    const label=accessible?(quizMode?'Commencer le QCM':item.state?.revised?'Révisée':'Ouvrir la fiche'):(available>0?`Débloquer ${quizMode?'l’entraînement':'la fiche'}`:'Passer Premium');
    return `<button type="button" class="oi-card oi-fiche-card oi-tone-${topic.color} ${accessible?'is-unlocked':'is-locked'}" data-art="${artFamily(item)}" ${action}><span class="oi-card-top">${item.cover?`<img class="oi-card-cover" src="${esc(item.thumbnail||item.cover)}" alt="" loading="lazy" width="88" height="88">`:`<span class="oi-subject-icon">${icon(quizMode?'quiz':topic.icon)}</span>`}<span class="oi-card-badge">${accessible?(account?.administrator?'Accès administrateur':account?.premium?'Inclus dans mon accès':'Débloquée'):icon('lock')+' Verrouillée'}</span></span><span class="oi-eyebrow">${esc(topic.name)}</span><h3>${esc(item.title)}</h3><span class="oi-card-description">${quizMode?`${Number(item.quiz_count)} question(s) · Correction expliquée`:'Les repères pour comprendre et retenir.'}</span><span class="oi-card-bottom"><span>${label}</span><span class="oi-card-action-end">${!accessible&&available>0?'<span class="oi-credit-cost">1 crédit</span>':''}${icon('arrow')}</span></span>${item.state?.favorite?'<span class="oi-card-favorite" aria-label="En favori">'+icon('heart')+'</span>':''}</button>`;
  }
  function verification() {
    screen().innerHTML=`<section class="oi-verification"><span class="oi-eyebrow">UNE DERNIÈRE ÉTAPE</span><h1>Vérifie ton adresse e-mail.</h1><p>${account.verification_email_sent?'Ouvre le message Objectif Infirmière reçu dans ta boîte mail, puis confirme ton adresse. Pense aussi aux indésirables.':'L’e-mail de confirmation n’a pas pu être envoyé. Tu peux demander un nouvel envoi ci-dessous.'}</p><p>Tu recevras ensuite 5 crédits Fiche, 5 crédits QCM et 5 crédits IA, une seule fois.</p><button type="button" data-resend>Renvoyer le lien de vérification</button><button type="button" data-refresh-account>J’ai confirmé mon adresse</button><p id="oi-status" role="status"></p></section>`;
  }
  function canRecharge() { return !!account?.premium && Number(account?.balances?.IA)===0; }
  function syncAccountNavigation() {
    const wallet=document.querySelector('.oi-credit-wallet');
    if(wallet&&account){
      const unlimited=!!(account.premium||account.administrator);
      const markup=`<span class="oi-wallet-title">Mes crédits</span><dl>${[['FICHE','Fiches'],['QCM','QCM'],['IA','IA']].map(([kind,label])=>{
        const infinite=unlimited&&kind!=='IA', value=Math.max(0,Number(account.balances?.[kind])||0);
        return `<div><dt>${label}</dt><dd${infinite?' class="is-unlimited" title="Illimité" aria-label="Illimité"':` aria-label="${value} crédit${value>1?'s':''} restant${value>1?'s':''}"`}>${infinite?'∞':value}</dd></div>`;
      }).join('')}</dl>`;
      if(wallet.innerHTML!==markup)wallet.innerHTML=markup;
      wallet.hidden=false;
    }
    const button=document.querySelector('.oi-sidebar-unlock');
    if(button){
      const recharge=canRecharge();button.hidden=!!account.premium&&!recharge;button.dataset.tab=recharge?'recharge':'premium';
      button.innerHTML=recharge?`<span class="oi-premium-card-title">${icon('spark')}<strong>Recharger mon assistant</strong></span><span class="oi-premium-card-copy">100 nouvelles questions pour continuer tes révisions</span><span class="oi-premium-card-cta">Découvrir la recharge ${icon('arrow')}</span>`:`<span class="oi-premium-card-title">${icon('crown')}<strong>Passer Premium</strong></span><span class="oi-premium-card-copy">Débloquez toutes les fiches, QCMs et contenus exclusifs</span><span class="oi-premium-card-cta">Découvrir l’offre ${icon('arrow')}</span>`;
      if(button.dataset.tab===currentTab)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');
    }
    document.querySelectorAll('[data-recharge-shortcut]').forEach(button=>button.hidden=!canRecharge());
  }
  async function loadAccount() {
    account=await api('account');
    syncAccountNavigation();
    if(account.verification_required){verification();return false;}return true;
  }
  async function premium() {
    const sequence=++routeSequence;shell();setTab('premium');if(!await loadAccount()||sequence!==routeSequence)return;
    if(account.premium)return dashboard();
    const offer=account.offer;
    if(!account.premium)api('events',{event:'premium_view'}).catch(()=>{});
    const price=new Intl.NumberFormat('fr-FR',{style:'currency',currency:offer.currency||'EUR',maximumFractionDigits:0}).format(Number(offer.amount)/100);
    screen().innerHTML=`<section class="oi-premium-page oi-unlock-page"><header class="oi-unlock-hero"><span class="oi-unlock-kicker">${icon(account.premium?'check':'lock')}${account.premium?'Ton espace est débloqué':'Débloque toute la puissance Objectif Infirmière'}</span><h1>${account.premium?'Tout est prêt pour <span>tes révisions.</span>':'Accède à tout <span>en illimité</span> pour tes révisions.'}</h1><p>Des notions plus claires. Des entraînements qui font progresser.<br>Ton espace pour avancer des cours IFSI aux partiels, à ton rythme.</p></header><div class="oi-unlock-layout"><div class="oi-unlock-content"><div class="oi-unlock-features"><article><span class="oi-unlock-feature-icon">${icon('book')}</span><h3>Accès aux 700 Fiches</h3><p>${Number(offer.fiches)} fiches du catalogue, à consulter et revoir autant que tu en as besoin.</p></article><article><span class="oi-unlock-feature-icon">${icon('quiz')}</span><h3>Des QCM à volonté</h3><p>${Number(offer.qcm)} séries, des corrigés expliqués et des entraînements par fiche, thème ou UE.</p></article><article><span class="oi-unlock-feature-icon">${icon('pulse')}</span><h3>Personnalise ton parcours</h3><p>Tes objectifs, ton planning, tes favoris et ta progression restent réunis dans ton espace.</p></article><article><span class="oi-unlock-feature-icon">${icon('spark')}</span><h3>Ton assistant soignant</h3><p>100 crédits IA supplémentaires inclus, utilisables à ton rythme et non renouvelés automatiquement.</p></article></div></div><aside class="oi-unlock-offer" aria-label="Débloquer mon accès"><div class="oi-unlock-offer-top"><span>${icon('shield')}TON ACCÈS COMPLET</span><span class="oi-unlock-pill">${account.premium?'Déjà activé':'Sans abonnement'}</span></div><h2>${account.premium?'Bienvenue de l’autre côté.':'Tout commence ici.'}</h2>${account.premium?'<p class="oi-unlock-active">Tes fiches et QCM sont accessibles sans dépenser de crédits.</p>':`<div class="oi-unlock-price"><strong>${esc(price)}</strong><span>une seule fois</span></div><p class="oi-unlock-payment-note">Un paiement unique pour débloquer les fiches et QCM du catalogue.</p>`}<div class="oi-unlock-benefits"><ul>${['Fiches et QCM en accès illimité','Aucun crédit à dépenser pour les consulter','100 crédits IA supplémentaires','Tes favoris et ta progression conservés'].map(text=>`<li>${icon('check')}<span>${text}</span></li>`).join('')}</ul><img class="oi-unlock-mascot" src="${esc(OI.brandUrl)}mascot.png" alt="" width="500" height="500"></div>${account.premium?'<button type="button" data-tab="fiches">Reprendre mes révisions '+icon('arrow')+'</button>':`<button type="button" data-buy="${Number(offer.pack_id)}" ${!offer.available||!account.verified?'disabled':''}>Débloquer tout le contenu ${icon('arrow')}</button><p class="oi-unlock-checkout-note">${offer.available?icon('lock')+' Paiement via Stripe':'Le paiement n’est pas encore disponible.'}${offer.payment_mode==='test'?'<br>Paiement en mode test.':''}</p>`}</aside></div><p id="oi-status" role="status"></p></section>`;
  }
  async function recharge() {
    const sequence=++routeSequence;shell();setTab('recharge');if(!await loadAccount()||sequence!==routeSequence)return;
    const preview=OI.admin&&new URLSearchParams(location.search).get('preview')==='1';
    if(!preview&&!account.premium)return premium();
    if(!preview&&!canRecharge())return chat(false);
    const offer=account.recharge,price=new Intl.NumberFormat('fr-FR',{style:'currency',currency:offer.currency,maximumFractionDigits:0}).format(offer.amount/100);
    const cards=[['spark','100 réponses supplémentaires','Un crédit par réponse réussie, pour éclaircir les notions qui te posent question.'],['book','Tes sources de révision','Des réponses appuyées sur les fiches accessibles dans ton espace.'],['pulse','À ton rythme','Tes crédits restent disponibles jusqu’à leur utilisation, sans renouvellement automatique.'],['shield','Ton Premium reste actif','Tes fiches, tes QCM, tes favoris et ta progression restent accessibles.']];
    screen().innerHTML=`<section class="oi-premium-page oi-unlock-page oi-recharge-page"><header class="oi-unlock-hero"><span class="oi-unlock-kicker">${icon('spark')}Ton assistant personnel soignant</span><h1>Encore des questions ?<br><span>Continue à progresser.</span></h1><p>Recharge ton assistant et reprends tes révisions là où tu en étais.</p></header><div class="oi-unlock-layout"><div class="oi-unlock-content"><div class="oi-unlock-features">${cards.map(([glyph,title,copy])=>`<article><span class="oi-unlock-feature-icon">${icon(glyph)}</span><h3>${title}</h3><p>${copy}</p></article>`).join('')}</div></div><aside class="oi-unlock-offer" aria-label="Recharger mon assistant"><div class="oi-unlock-offer-top"><span>${icon('spark')}TA RECHARGE IA</span><span class="oi-unlock-pill">Sans abonnement</span></div><h2>100 nouvelles questions.</h2><div class="oi-unlock-price"><strong>${esc(price)}</strong><span>une seule fois</span></div><p class="oi-unlock-payment-note">Un achat ponctuel pour recharger ton assistant.</p><div class="oi-unlock-benefits"><ul>${['100 crédits IA ajoutés après confirmation du paiement','1 crédit par réponse réussie','Crédit restitué en cas d’échec technique','Ton accès Premium reste inchangé'].map(t=>`<li>${icon('check')}<span>${t}</span></li>`).join('')}</ul><img class="oi-unlock-mascot" src="${esc(OI.brandUrl)}mascot.png" alt="" width="500" height="500"></div><button type="button" data-buy-recharge ${preview||!offer.available||!account.verified||!canRecharge()?'disabled':''}>Recharger mon assistant ${icon('arrow')}</button><p class="oi-unlock-checkout-note">${preview?'Aperçu administrateur — achat désactivé.':offer.available?icon('lock')+' Paiement via Stripe':'La recharge sera disponible à l’ouverture de l’assistant.'}${offer.payment_mode==='test'?'<br>Paiement en mode test.':''}</p></aside></div><p id="oi-status" role="status"></p></section>`;
  }
  async function users(){if(!OI.admin)return;const sequence=++routeSequence;shell();setTab('users');const host=document.createElement('div');screen().replaceChildren(host);await window.OIUserAdmin.render(host);if(sequence!==routeSequence)host.remove();}
  async function qcmImports(){if(!OI.admin)return;const sequence=++routeSequence;shell();setTab('qcm-imports');const host=document.createElement('div');screen().replaceChildren(host);await window.OIQCMImports.render(host);if(sequence!==routeSequence)host.remove();}
  async function imports(){if(!OI.admin)return;const sequence=++routeSequence;shell();setTab('imports');screen().innerHTML='<p role="status">Chargement de ton atelier…</p>';const host=document.createElement('div');screen().replaceChildren(host);await window.OIImports.render(host);if(sequence!==routeSequence)host.remove();}
  async function progression() {
    const sequence=++routeSequence;shell();setTab('progress');if(!await loadAccount()||sequence!==routeSequence)return;
    activeFiche=null;const host=document.createElement('div');screen().replaceChildren(host);
    await window.OIGoals.render(host,{openFiche:id=>fiche(id),openAssistant:goal=>chat(false,goal)});
    if(sequence!==routeSequence)host.remove();
  }

  function progress(p) {
    const total = Number(p?.total || 0);
    const revised = Number(p?.revised || 0);
    const percentage = total ? Math.round(revised/total*100) : 0;
    return `<section class="oi-progress-panel" aria-label="Ma progression"><div class="oi-progress-title"><span>${icon('pulse')} Ma progression</span><strong>${percentage}%</strong></div><progress value="${revised}" max="${Math.max(1,total)}" aria-label="Fiches révisées"></progress><p><strong>${revised} / ${total}</strong> fiches révisées<span>${Number(p?.quizzes || 0)} QCM réalisé${Number(p?.quizzes || 0)>1?'s':''}</span></p></section>`;
  }
  function aiBanner(quiz=false) {
    const eyebrow=quiz?'UNE RÉVISION QUI TE RESSEMBLE':'UN COUP DE POUCE QUAND ÇA BLOQUE';
    const title=quiz?'Et si on adaptait tes révisions à tes besoins ?':'Et si on te l’expliquait autrement ?';
    const copy=quiz?'Demande à ton assistant des questions ciblées, des explications sur tes erreurs ou une méthode adaptée à ton prochain partiel.':'Ton assistant personnel t’aide à approfondir des notions et faire les liens entre elles.';
    return `<section class="oi-ai-banner"><img class="oi-ai-banner-mascot" src="${esc(OI.brandUrl)}mascot.png" alt="" width="96" height="96" loading="lazy"><div><span class="oi-eyebrow">${eyebrow}</span><h2>${title}</h2><p>${copy}</p></div><button type="button" ${quiz?'data-ai-training':'data-ai'}>${quiz?'Personnaliser mes révisions':'Ouvrir mon assistant'} ${icon('arrow')}</button></section>`;
  }
  function recentSection(items) {
    return `<section class="oi-recent" aria-labelledby="oi-recent-title"><div class="oi-section-title"><div><span class="oi-eyebrow">REPRENDS LE FIL</span><h2 id="oi-recent-title">Dernières fiches consultées</h2><p>Les plus récentes en premier, pour reprendre là où tu en étais.</p></div></div>${items.length?`<div class="oi-grid">${items.map(item=>card(item)).join('')}</div>`:'<p class="oi-recent-empty">Tes dernières lectures apparaîtront ici dès que tu ouvriras une fiche.</p>'}</section>`;
  }
  function folderFilters() {
    if(currentTab==='fiches'&&ownedOnly)return '&unlocked=1';
    return ['fiches','qcm'].includes(currentTab)&&!query?(unit!==null?'&enseignement='+unit:'')+(theme!==null?'&theme='+theme:''):'';
  }
  function folderTrail() {
    if(!['fiches','qcm'].includes(currentTab)||(currentTab==='qcm'&&unit===null))return '';
    return `<nav class="oi-folder-trail" aria-label="${currentTab==='qcm'?'Parcours des QCM':'Parcours des fiches'}"><button type="button" data-library-level="root" ${unit===null&&!query&&!ownedOnly?'aria-current="page"':''}>${icon('folder')} ${currentTab==='qcm'?'Tous les QCM':'Toutes les unités'}</button>${canFilterOwned()?`<button type="button" data-library-access="unlocked" aria-pressed="${ownedOnly}">${icon('book')} Mes Fiches</button>`:''}${query?'<span>Résultats de recherche</span>':unit!==null?`${icon('arrow')}<button type="button" data-library-level="unit" ${theme===null?'aria-current="page"':''}>${esc(folderNames.unit)}</button>${theme!==null?icon('arrow')+'<span aria-current="page">'+esc(folderNames.theme)+'</span>':''}`:''}</nav>`;
  }
  async function libraryResults(data,quizMode) {
    if(currentTab!=='fiches'||ownedOnly||query||theme!==null)return resultCards(data,quizMode);
    const folders=await api(`library${unit!==null?'?enseignement='+unit:''}`);
    return folders.folders.map(folder=>`<button type="button" class="oi-folder-card" data-folder="${Number(folder.id)}" data-folder-name="${esc(folder.name)}"><span class="oi-folder-symbol">${icon('folder')}</span><span class="oi-folder-copy"><span class="oi-eyebrow">${unit===null?'UNITÉ D’ENSEIGNEMENT':'THÈME'}</span><h3>${esc(folder.name)}</h3><span>${folder.count?Number(folder.count)+' fiche'+(folder.count>1?'s':''):'À venir'}</span></span>${icon('arrow')}</button>`).join('') || '<div class="oi-empty"><h3>Aucune fiche dans ce dossier pour le moment.</h3><p>Choisis une autre unité.</p></div>';
  }
  function searchForm(data, quizMode) {
    return `<form id="oi-search" class="oi-search"><div class="oi-search-field">${icon('search')}<label class="oi-sr-only" for="oi-query">Rechercher dans mes fiches</label><input id="oi-query" type="search" value="${esc(query)}" placeholder="${quizMode?'Quel sujet veux-tu travailler ?':'Une notion, un médicament, une UE…'}" maxlength="150"><button type="submit" class="oi-search-submit">Rechercher</button></div></form>`;
  }
  function resultCards(data, quizMode) {
    const items = quizMode ? data.items.filter(item=>Number(item.quiz_count)>0) : data.items;
    if(currentTab==='fiches'&&ownedOnly&&!items.length&&!query)return `<div class="oi-empty">${icon('book')}<h3>Tes fiches débloquées, au même endroit.</h3><p>Choisis une fiche dans « Toutes les unités » et débloque-la avec tes crédits pour la retrouver ici.</p><button type="button" data-library-level="root">Explorer les fiches</button></div>`;
    return items.map(item=>card(item,quizMode)).join('') || `<div class="oi-empty">${icon(quizMode?'quiz':favoritesOnly?'heart':'book')}<h3>${favoritesOnly?'Tes essentiels, au même endroit.':quizMode?'Aucun QCM pour cette sélection.':'Aucune fiche pour cette sélection.'}</h3><p>${favoritesOnly?'Ajoute une fiche aux favoris depuis sa page pour la retrouver ici.':data.total?'Essaie un autre mot-clé.':query?'Essaie un autre mot-clé.':'Les fiches de ce thème arrivent prochainement.'}</p>${query?'<button type="button" data-clear-search>Effacer les filtres</button>':''}</div>`;
  }
  function pagination(data) {
    const target = document.querySelector('#oi-pagination');
    if (!target) return;
    if(currentTab==='fiches'&&!ownedOnly&&!query&&theme===null){target.innerHTML='';return;}
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
    if (reset) { page=1; query=''; unit=null; theme=null; ownedOnly=false; }
    if(!canFilterOwned())ownedOnly=false;
    setTab(tab);
    screen().setAttribute('aria-busy','true');
    let data;
    try { data=await api(`fiches?page=${page}&q=${encodeURIComponent(query)}${favoritesOnly?'&favorites=1':''}${currentTab==='qcm'?'&kind=QCM':''}${folderFilters()}`); }
    finally { screen().removeAttribute('aria-busy'); }
    if (sequence!==routeSequence) return;
    account=data.account;syncAccountNavigation();
    const quizMode = tab==='qcm';
    const intro = favoritesOnly ? `<div class="oi-page-intro"><span class="oi-eyebrow">TA SÉLECTION PERSONNELLE</span><h1 id="oi-favorites-title">À garder sous la main.</h1></div>` : quizMode ? `<div class="oi-page-intro"><span class="oi-eyebrow">PLACE À LA PRATIQUE</span><h1>Tu sais. Maintenant, <em>prouve-le-toi.</em></h1><p>Teste tes connaissances, comprends tes erreurs et avance vers tes partiels.</p></div>` : `<div class="oi-dashboard-intro"><div><p class="oi-library-welcome">Tes notions de soins infirmiers, un peu plus claires chaque jour.</p><h1>Bonjour ${esc(OI.name)}.</h1></div></div>`;
    const [contents,recent] = await Promise.all([libraryResults(data,quizMode), tab==='fiches'?api('recent'):Promise.resolve([])]);
    if(sequence!==routeSequence)return;
    screen().innerHTML = `${intro}${quizMode?'<div id="oi-training"></div>':''}<section class="oi-library" aria-labelledby="${favoritesOnly?'oi-favorites-title':'oi-library-title'}">${favoritesOnly?'':`<div class="oi-section-title"><div><span class="oi-eyebrow">${quizMode?'APPRENDRE EN S’ENTRAÎNANT':favoritesOnly?'LE MEILLEUR DE TES RÉVISIONS':'TA BIBLIOTHÈQUE'}</span><h2 id="oi-library-title">${quizMode?'Choisis ton prochain défi.':favoritesOnly?'Mes favoris':'Qu’est-ce qu’on révise ?'}</h2></div>${!favoritesOnly&&!quizMode?'<button type="button" class="oi-text-button" data-tab="favorites">'+icon('heart')+' Mes favoris</button>':''}</div>`}${searchForm(data,quizMode)}<p id="oi-status" role="status"></p><div id="oi-folder-trail">${folderTrail()}</div><div id="oi-results" class="oi-grid">${contents}</div><div id="oi-pagination" class="oi-pagination"></div></section>${quizMode?aiBanner(true):!favoritesOnly?recentSection(recent)+aiBanner():''}<footer class="oi-member-footer"><span>Objectif Infirmière</span><span>Un pas de plus vers la blouse.</span></footer>`;
    if(quizMode)await window.OITraining.render(document.querySelector('#oi-training'),id=>fiche(id));
    if(sequence!==routeSequence)return;
    pagination(data);
    document.querySelector('#oi-search').addEventListener('submit', e=>{e.preventDefault();page=1;search().catch(error);});
    if (document.querySelector('#oi-catalog')) showCatalog(document.querySelector('#oi-catalog')).catch(error);
  }
  async function search() {
    query = document.querySelector('#oi-query')?.value || '';
    const sequence = ++searchSequence;
    const route = routeSequence;
    const results = document.querySelector('#oi-results');
    results?.setAttribute('aria-busy','true');
    results?.querySelectorAll('button').forEach(button=>button.disabled=true);
    status('Recherche en cours…');
    try {
      const data = await api(`fiches?page=${page}&q=${encodeURIComponent(query)}${favoritesOnly?'&favorites=1':''}${currentTab==='qcm'?'&kind=QCM':''}${folderFilters()}`);
      if (sequence!==searchSequence || route!==routeSequence) return;
      account=data.account;syncAccountNavigation();if(!canFilterOwned())ownedOnly=false;setTab(currentTab);
      const contents=await libraryResults(data,currentTab==='qcm');
      if(sequence!==searchSequence || route!==routeSequence)return;
      results.innerHTML = contents;
      document.querySelector('#oi-folder-trail').innerHTML=folderTrail();
      pagination(data);
      status(data.total || !query ? '' : 'Aucun résultat pour cette recherche.');
    } finally { if(sequence===searchSequence){results?.removeAttribute('aria-busy');results?.querySelectorAll('button').forEach(button=>button.disabled=false);} }
  }
  function readerPath(data) {
    const units=data.terms?.enseignement||[], themes=data.terms?.theme||[];
    return `<nav class="oi-reader-path" aria-label="Classement de la fiche">${units.map(t=>`<button type="button" data-reader-unit="${Number(t.id)}" title="${esc(t.name)}">UE ${esc(t.name.match(/^[A-E]\.?[1-9]/i)?.[0]||t.name)}</button>`).join('')}${themes.map(t=>`<button type="button" data-reader-theme="${Number(t.id)}">${esc(t.name)}</button>`).join('')}</nav>`;
  }
  const readerHeadingLabels = headings => window.OIReaderHeadings.enhance(headings);
  function revisionButton(data) {
    const goal=data.goal, revised=goal?goal.done:!!data.state?.revised;
    const label=goal?(revised?'Révisée pour mon objectif':'Valider pour mon objectif'):(revised?'Révisée':'Marquer comme révisée');
    return `<button type="button" ${goal?'data-reader-goal':'data-state="revised"'} aria-pressed="${revised}">${icon('check')}${label}</button>`;
  }
  function associatedFiche(data) {
    const accessible=!!data.access?.FICHE, credits=Number(account?.balances?.FICHE||0);
    const action=accessible?`data-fiche="${Number(data.id)}"`:credits?`data-unlock="${Number(data.id)}" data-kind="FICHE"`:'data-tab="premium"';
    const label=accessible?'Ouvrir la fiche':credits?'Débloquer la fiche — 1 crédit Fiche':'Découvrir le Premium';
    return `<section class="oi-qcm-associated" aria-labelledby="oi-qcm-associated-title"><div class="oi-qcm-associated-art" aria-hidden="true">${data.cover?`<img src="${esc(data.thumbnail||data.cover)}" alt="" width="100" height="100" loading="lazy">`:icon('book')}</div><div class="oi-qcm-associated-copy"><p class="oi-eyebrow">Fiche associée au QCM</p><h2 id="oi-qcm-associated-title">${esc(data.title)}</h2><p>Retrouve les notions clés et les explications du cours.</p></div><button type="button" ${action}>${label}${icon(accessible?'arrow':'lock')}</button></section>`;
  }
  async function fiche(id, quizOnly=false) {
    const sequence = ++routeSequence;
    status('Ouverture de la fiche…');
    const data = await api(`${quizOnly?'qcm':'fiches'}/${id}`);
    if (sequence!==routeSequence) return;
    activeFiche = data;
    setTab(quizOnly?'qcm':'fiches',Number(id));
    const topic = subject(data);
    screen().innerHTML = `<nav class="oi-breadcrumb" aria-label="Fil d’Ariane"><button type="button" data-return="${quizOnly?'qcm':'fiches'}">${icon('back')} ${quizOnly?'Tous les QCM':'Mes révisions'}</button></nav><article class="oi-reader"><div class="oi-reader-hero">${data.cover?`<img class="oi-theme-art oi-fiche-cover" src="${esc(data.cover)}" alt="" width="320" height="320" fetchpriority="high">`:`<div class="oi-theme-art oi-theme-art--${artFamily(data)}" aria-hidden="true"></div>`}<header class="oi-reader-heading">${readerPath(data)}<h1>${esc(data.title)}</h1><p class="oi-muted" ${quizOnly?'data-quiz-intro':''}>${quizOnly?'Préparation de tes questions…':`Mise à jour le ${esc(new Date(data.updated+'Z').toLocaleDateString('fr-FR'))}`}</p></header>${!quizOnly?`<div class="oi-actions"><button type="button" data-state="favorite" aria-pressed="${!!data.state?.favorite}">${icon('heart')}${data.state?.favorite?'Favori':'Ajouter aux favoris'}</button>${revisionButton(data)}${data.goal?'<button type="button" data-tab="progress">Mon objectif</button>':''}<button type="button" class="oi-ask-button" data-ai>${icon('spark')}Poser une question sur cette fiche</button><button type="button" data-print-fiche>${icon('print')}Imprimer</button></div>`:''}</div><p id="oi-status" role="status"></p>${!quizOnly?'<div class="oi-reading-layout"><nav id="oi-toc" aria-label="Sommaire"></nav><div class="oi-content oi-protected" data-protected>'+data.content+'<div class="oi-reader-complete">'+revisionButton(data)+'</div></div></div><div id="oi-watermark"></div>':''}<section id="oi-quiz" class="oi-quiz-section"></section>${quizOnly?associatedFiche(data)+aiBanner(true):''}${!quizOnly?`${aiBanner()}<section class="oi-related"><span class="oi-eyebrow">GARDE TON ÉLAN</span><h2>On continue ?</h2><div class="oi-grid">${data.related.map(item=>card(item)).join('')}</div></section>`:''}</article>`;
    if (!quizOnly) {
      const headings = [...screen().querySelectorAll('.oi-content h2, .oi-content h3')];
      const labels=readerHeadingLabels(headings);
      document.querySelector('#oi-toc').innerHTML = labels.length ? `<span class="oi-eyebrow">Dans cette fiche</span>${labels.map(h=>`<a href="#${h.id}" ${h.sub?'class="oi-toc-subsection"':''}><span>${esc(h.number)}</span><span class="oi-toc-title">${esc(h.title)}</span></a>`).join('')}` : '';
      const content=screen().querySelector('.oi-content');
      const firstParagraph=content.querySelector(':scope > p');
      if(firstParagraph?.textContent.trim()===data.title.trim())firstParagraph.classList.add('oi-word-title');
      content.querySelectorAll(':scope > p').forEach(paragraph=>{
        if(/^UE\s+[A-E][. ]?\d/i.test(paragraph.textContent.trim()))paragraph.classList.add('oi-word-meta');
        if(/^Objectifs?\s*:/i.test(paragraph.textContent.trim())){paragraph.classList.add('oi-word-objectives');paragraph.insertAdjacentHTML('afterbegin',icon('target','oi-callout-icon'));}
      });
      window.OIReaderDiagrams.enhance(content);
      content.querySelectorAll(':scope > table').forEach(table=>{
        if(table.rows.length===1&&table.rows[0].cells.length===1){
          table.classList.add('oi-word-callout');const essential=/essentiel.{0,20}30\s+secondes/i.test(table.textContent);
          if(essential){table.classList.add('oi-word-essential');table.rows[0].cells[0].insertAdjacentHTML('afterbegin',icon('bolt','oi-callout-icon'));}
          else table.classList.add('oi-word-callout--plain');
        }
      });
      followReaderSections(headings);
      if (data.watermark) {document.querySelector('#oi-watermark').textContent = data.watermark;document.querySelector('.oi-content').setAttribute('data-watermark',data.watermark);}
    }
    if (data.quiz?.length) await window.OITraining.start(document.querySelector('#oi-quiz'),{scope:'fiche',id:Number(id),count:20},id=>fiche(id),{onReady:total=>{if(sequence!==routeSequence)return;const intro=screen().querySelector('[data-quiz-intro]');if(intro)intro.textContent=`${total} question${total>1?'s':''} tirée${total>1?'s':''} au hasard. Prends le temps de réfléchir. À toi de jouer !`;}});
    else if (!quizOnly && data.quiz_count>0) document.querySelector('#oi-quiz').innerHTML='<h2>Et si on s’entraîner sur le sujet ?</h2><p>Une série de 20 questions pour tester ton niveau</p><button type="button" data-unlock="'+Number(id)+'" data-kind="QCM">Débloquer cette série QCM - 1 Crédit</button>';
    else if (quizOnly) document.querySelector('#oi-quiz').innerHTML='<div class="oi-empty"><h2>Ce QCM arrive bientôt.</h2><p>Tu peux déjà réviser le cours associé.</p><button type="button" data-fiche="'+Number(id)+'">Ouvrir la fiche</button></div>';
    const anchor=/^#oi-section-\d+$/.test(location.hash)?document.getElementById(location.hash.slice(1)):null;
    if(anchor)anchor.scrollIntoView({behavior:'instant',block:'start'});else window.scrollTo({top:0,behavior:'smooth'});
  }
  async function showCatalog(target) {
    if (!target) return;
    const packs = (await api('catalog')).filter(p=>p.id===account?.offer?.pack_id);
    if (!target.isConnected) return;
    target.innerHTML = packs.length ? `<div class="oi-pack-grid">${packs.map(p=>`<section class="oi-pack-card"><span class="oi-eyebrow">PACK DE RÉVISION</span><h3>${esc(p.title)}</h3><p>${esc(p.description)}</p>${p.available?`<button type="button" data-buy="${Number(p.id)}">Acheter en mode TEST ${icon('arrow')}</button>`:`<span class="oi-muted">${p.free_demo?'Inclus dans la découverte gratuite':'Ce pack sera proposé prochainement'}</span>`}</section>`).join('')}</div>` : '';
  }
  function renderQuiz(quiz) {
    document.querySelector('#oi-quiz').innerHTML = `<div class="oi-quiz-heading"><span class="oi-subject-icon">${icon('quiz')}</span><div><span class="oi-eyebrow">À TOI DE JOUER</span><h2>Vérifier mes connaissances</h2><p>${quiz.length} question${quiz.length>1?'s':''} pour faire le point.</p></div></div><form id="oi-quiz-form">${quiz.map((q,i)=>`<fieldset><legend><span class="oi-question-number">${String(i+1).padStart(2,'0')}</span>${esc(window.OIQCMDisplay.question(q.question))}</legend><p class="oi-question-help">${q.multiple?'Plusieurs réponses possibles.':'Une seule réponse attendue.'}</p>${q.options.map((o,j)=>`<label class="oi-quiz-option"><input type="${q.multiple?'checkbox':'radio'}" name="q${i}" value="${j}" ${!q.multiple&&j===0?'required':''}><span class="oi-option-letter">${String.fromCharCode(65+j)}</span><span>${esc(o)}</span></label>`).join('')}</fieldset>`).join('')}<div class="oi-quiz-submit"><span>Comprendre compte autant que répondre juste.</span><button type="submit">Voir ma correction ${icon('arrow')}</button></div></form><div id="oi-quiz-result" role="status"></div>`;
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
  function showEmptyAICredits() {
    if(document.querySelector('.oi-ai-credit-dialog'))return;
    const premiumMember=!!account?.premium;
    const origin=document.activeElement;
    const dialog=document.createElement('dialog');dialog.className='oi-ai-credit-dialog';
    dialog.setAttribute('aria-labelledby','oi-ai-credit-title');dialog.setAttribute('aria-describedby','oi-ai-credit-description');
    dialog.innerHTML=`<button type="button" class="oi-ai-credit-close" aria-label="Fermer">×</button><img src="${esc(OI.brandUrl)}mascot.png" alt="" width="80" height="80"><span class="oi-eyebrow">ON CONTINUE ENSEMBLE ?</span><h2 id="oi-ai-credit-title">Tu n’as plus de crédits IA.</h2><p id="oi-ai-credit-description">${premiumMember?'Recharge ton assistant pour continuer à poser tes questions. Tes fiches et tes QCM restent accessibles.':'Tes questions gratuites sont épuisées. Le Premium inclut 100 crédits IA supplémentaires et l’accès aux fiches et aux QCM en illimité.'}</p><div class="oi-ai-credit-actions"><button type="button" data-credit-continue>${premiumMember?'Recharger mon assistant':'Découvrir le Premium'} ${icon('arrow')}</button><button type="button" class="oi-ai-credit-later">Plus tard</button></div><small>Tu peux fermer cette fenêtre sans perdre ta question.</small>`;
    dialog.addEventListener('close',()=>{dialog.remove();if(origin?.isConnected)origin.focus();},{once:true});
    for(const button of dialog.querySelectorAll('.oi-ai-credit-close,.oi-ai-credit-later'))button.addEventListener('click',()=>dialog.close());
    dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const rect=dialog.getBoundingClientRect();if(e.clientX<rect.left||e.clientX>rect.right||e.clientY<rect.top||e.clientY>rect.bottom)dialog.close();});
    dialog.querySelector('[data-credit-continue]').addEventListener('click',async()=>{
      aiDraft={question:document.querySelector('#oi-question')?.value||'',research:!!document.querySelector('#oi-research')?.checked};
      dialog.close();try{if(premiumMember)await recharge();else await premium();}catch(e){error(e);}
    });
    view.append(dialog);dialog.showModal();
  }
  function aiAnswerText(answer) {
    return esc(answer.text).replace(/\[R(\d+)\]/g,(marker,n)=>{const source=answer.web_sources?.[Number(n)-1];return source?.url.startsWith('https://')?`<a href="${esc(source.url)}" target="_blank" rel="noopener noreferrer">${marker}</a>`:marker;});
  }
  function aiWebReferences(answer) {
    return answer.web_sources?.length?`<div class="oi-answer-sources"><strong>Références de santé consultées</strong>${answer.web_sources.map((source,i)=>`<a class="oi-ai-web-reference" href="${esc(source.url)}" target="_blank" rel="noopener noreferrer">[R${i+1}] ${esc(source.title)}<small>Consulté le ${esc(source.consulted_at)}</small></a>`).join('')}</div>`:'';
  }
  async function chat(withContext=true,goalContext=null,draftPrompt='') {
    const openingSequence=++routeSequence;
    shell();
    const context = withContext?activeFiche:null;
    activeFiche = null;
    setTab('ai');
    if(!await loadAccount())return;
    if(openingSequence!==routeSequence)return;
    aiRequestId=null;
    screen().innerHTML = `<nav class="oi-breadcrumb"><button type="button" data-home>${icon('back')} Mes révisions</button><span>Ton espace pour comprendre</span></nav><section class="oi-chat-page"><header class="oi-chat-header"><div class="oi-chat-identity"><img class="oi-chat-mascot" src="${esc(OI.brandUrl)}mascot.png" alt="" width="112" height="112"><span class="oi-eyebrow">TON ASSISTANT PERSONNEL SOIGNANT</span></div><h1>Une question.<br><em>Un nouveau déclic.</em></h1></header>${!account.ai_available?'<p class="oi-chat-availability">L’assistant est en préparation. Découvre son espace ; les réponses seront disponibles prochainement.</p>':''}${Number(account.balances.IA)===0?`<p class="oi-credit-empty">${account.premium?'Tes crédits IA sont épuisés. Tes fiches et QCM restent accessibles.':'Tes questions gratuites sont épuisées. Le Premium inclut 100 questions supplémentaires.'}</p>`:''}<p class="oi-chat-intro">Reformuler une notion, relier deux idées, préparer une révision.<br>Tu n’as plus à rester bloqué devant ton cours.</p>${goalContext?`<div class="oi-chat-context">${icon('pulse')} Mon objectif : <strong>${esc(goalContext.title)}</strong></div>`:''}${context?`<div class="oi-chat-context">${icon('book')} On parle de : <strong>${esc(context.title)}</strong></div>`:''}<div class="oi-chat-suggestions"><button type="button" data-prompt="Aide-moi à approfondir le rôle de la surveillance infirmière et les liens entre observations, risques et actions.">${icon('book')} Approfondir une notion ${icon('arrow')}</button><button type="button" data-prompt="Aide-moi à réviser les points clés de la surveillance des traitements.">${icon('quiz')} Préparer ma révision ${icon('arrow')}</button><button type="button" data-prompt="Comment relier les effets d’un médicament à sa surveillance infirmière ?">${icon('pulse')} Faire le lien avec les soins ${icon('arrow')}</button></div><div id="oi-answer" class="oi-conversation" aria-live="polite"></div><p class="oi-question-balance"><strong data-balance="IA">${Number(account.balances.IA)}</strong> question(s) IA disponible(s) · 1 crédit par réponse réussie</p><button type="button" class="oi-recharge-entry" data-recharge-shortcut data-tab="recharge" ${canRecharge()?'':'hidden'}>${icon('spark')}Recharger mon assistant ${icon('arrow')}</button><form id="oi-chat" class="oi-chat-composer"><label for="oi-question">Qu’est-ce que tu veux éclaircir ?</label><textarea id="oi-question" required maxlength="2000" rows="3" placeholder="Explique-moi cette notion simplement…"></textarea>${account.ai_research_available?'<label class="oi-ai-research"><input type="checkbox" id="oi-research"> Consulter aussi les recommandations officielles</label>':''}<div><span>Un assistant pour apprendre, à ton rythme.</span><button type="submit" ${!account.ai_available?'disabled':''}>Poser ma question ${icon('send')}</button></div></form><p id="oi-status" role="status"></p><p class="oi-ai-privacy">${icon('shield')} Assistant IA automatisé dédié aux révisions et aux soins. N’indiquer aucune donnée de patient. Les réponses sont produites à partir de nos fiches et de recherches approfondies mais restent à vérifier avec tes cours et les protocoles ; elles ne remplacent en aucun cas l’avis d’un professionnel de santé.</p></section>`;
    if(!goalContext&&!draftPrompt&&aiDraft){draftPrompt=aiDraft.question;if(document.querySelector('#oi-research'))document.querySelector('#oi-research').checked=aiDraft.research;aiDraft=null;}
    if(goalContext||draftPrompt){document.querySelector('#oi-question').value=goalContext?goalContext.assistant_prompt:draftPrompt;document.querySelector('#oi-question').focus();}
    document.querySelector('#oi-chat button[type=submit]').addEventListener('click',e=>{if(Number(account.balances.IA)===0&&!e.currentTarget.disabled){e.preventDefault();showEmptyAICredits();}});
    document.querySelector('#oi-chat').addEventListener('submit', async e=>{
      e.preventDefault();
      const button=e.target.querySelector('button[type=submit]');
      const question=document.querySelector('#oi-question').value.trim();
      if(button.disabled)return;
      if(Number(account.balances.IA)===0){showEmptyAICredits();return;}
      if(!question)return;
      const sequence=routeSequence;
      const research=!!document.querySelector('#oi-research')?.checked;
      const buttonLabel=button.innerHTML;
      button.disabled=true;button.textContent='Réponse en cours…';e.target.setAttribute('aria-busy','true');status();
      const pending=document.createElement('div');pending.className='oi-ai-pending';pending.setAttribute('role','status');pending.setAttribute('aria-atomic','true');
      pending.innerHTML=`<div class="oi-ai-pending-avatar"><img src="${esc(OI.brandUrl)}mascot.png" alt="" width="64" height="64"></div><div class="oi-ai-pending-copy"><strong>Ton assistant prépare ta réponse<span class="oi-ai-pending-dots" aria-hidden="true"><i></i><i></i><i></i></span></strong><p>${research?'Il croise tes fiches avec les références de santé pour t’aider à comprendre.':'Il explore tes fiches pour t’aider à comprendre et faire les liens.'}</p><span class="oi-ai-pending-track" aria-hidden="true"></span></div>`;
      document.querySelector('#oi-answer').append(pending);
      pending.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});
      try {
        aiRequestId ||= crypto.randomUUID();
        const answer=await api('ai',{question,fiche_id:context?.id || 0,request_id:aiRequestId,research});
        aiRequestId=null;account=await api('account');syncAccountNavigation();document.querySelectorAll('[data-balance=IA]').forEach(el=>el.textContent=Number(account.balances.IA));
        if (sequence!==routeSequence) return;
        status();pending.remove();
        const output=document.querySelector('#oi-answer');
        output.insertAdjacentHTML('beforeend',`<div class="oi-message oi-message-user"><span>TOI</span><p>${esc(question)}</p></div><div class="oi-message oi-message-assistant"><span class="oi-assistant-signature"><img src="${esc(OI.brandUrl)}mascot.png" alt="" width="44" height="44"> TON ASSISTANT</span><p class="oi-answer-text">${aiAnswerText(answer)}</p>${aiWebReferences(answer)}${answer.sources?.length?`<div class="oi-answer-sources"><strong>Pour aller plus loin</strong>${answer.sources.map(s=>`<button type="button" data-fiche="${Number(s.id)}">${icon('book')}${esc(s.title)}${icon('arrow')}</button>`).join('')}</div>`:''}</div>`);
        document.querySelector('#oi-question').value='';aiDraft=null;
        output.lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
      } catch(e) {
        if(e.status)aiRequestId=null;
        if(sequence===routeSequence){
          if(e.code==='oi_no_credit'){
            account.balances.IA=0;syncAccountNavigation();document.querySelectorAll('[data-balance=IA]').forEach(el=>el.textContent='0');status();showEmptyAICredits();
          }else error(e);
        }
      } finally {pending.remove();e.target.removeAttribute('aria-busy');button.innerHTML=buttonLabel;button.disabled=!account.ai_available;}
    });
    window.scrollTo({top:0,behavior:'smooth'});
  }
  for(const event of ['copy','cut','contextmenu','dragstart'])document.addEventListener(event,e=>{if(e.target.closest?.('[data-protected]')||window.getSelection()?.anchorNode?.parentElement?.closest('[data-protected]'))e.preventDefault();});
  document.addEventListener('click', async e=>{
    const b=e.target.closest('button');
    if (!b || !b.closest('.oi-app')) return;
    try {
      if (b.dataset.tab) {if(b.dataset.tab==='ai') await chat(false);else if(b.dataset.tab==='premium')await premium();else if(b.dataset.tab==='recharge')await recharge();else if(b.dataset.tab==='progress')await progression();else if(b.dataset.tab==='imports')await imports();else if(b.dataset.tab==='users')await users();else if(b.dataset.tab==='qcm-imports')await qcmImports();else await dashboard(b.dataset.tab);}
      if(b.hasAttribute('data-refresh-account'))await dashboard();
      if(b.hasAttribute('data-resend')){b.disabled=true;try{await api('verify/resend',{});status('Un nouveau lien a été envoyé. Vérifie ta boîte mail et les indésirables.');}finally{b.disabled=false;}}
      if(b.dataset.unlock){
        if(!await loadAccount())return;
        b.disabled=true;
        try{const result=await api('unlock',{id:Number(b.dataset.unlock),kind:b.dataset.kind});account=result.account;syncAccountNavigation();await fiche(Number(b.dataset.unlock),b.dataset.kind==='QCM');}catch(e){if(e.code==='oi_no_credit'){await premium();if(currentTab==='premium'&&!account.premium){notifyEmptyCredits(b.dataset.kind);status('Tes contenus déjà débloqués restent accessibles.');}}else throw e;}finally{b.disabled=false;}
      }
      if(b.hasAttribute('data-print-fiche')&&activeFiche&&document.querySelector('.oi-reader .oi-content'))window.print();
      if((b.hasAttribute('data-reader-unit')||b.hasAttribute('data-reader-theme'))&&activeFiche){
        const destination=currentTab==='qcm'?'qcm':'fiches';
        const opened=activeFiche, chosenTheme=(opened.terms?.theme||[]).find(t=>t.id===Number(b.dataset.readerTheme));
        const chosenUnit=(opened.terms?.enseignement||[]).find(t=>t.id===Number(b.dataset.readerUnit))||(opened.terms?.enseignement||[])[0];
        if(chosenUnit){ownedOnly=false;unit=Number(chosenUnit.id);theme=chosenTheme?Number(chosenTheme.id):null;folderNames.unit=chosenUnit.name;folderNames.theme=chosenTheme?.name||'';query='';page=1;await dashboard(destination,false);document.querySelector('#oi-library-title')?.scrollIntoView({behavior:'smooth'});}
      }
      if (b.hasAttribute('data-home')) await dashboard();
      if (b.dataset.return) await dashboard(b.dataset.return,false);
      if(b.hasAttribute('data-folder')) {
        const selected=Number(b.dataset.folder);
        if(unit===null){unit=selected;folderNames.unit=b.dataset.folderName;theme=null;}
        else{theme=selected;folderNames.theme=b.dataset.folderName;}
        page=1;await search();document.querySelector('#oi-folder-trail button:last-child')?.focus();
      }
      if(b.dataset.libraryAccess==='unlocked'&&canFilterOwned()){ownedOnly=true;unit=null;theme=null;document.querySelector('#oi-query').value='';page=1;await search();document.querySelector('[data-library-access]')?.focus();}
      if(b.hasAttribute('data-library-level')) {
        ownedOnly=false;
        if(b.dataset.libraryLevel==='root'){unit=null;theme=null;}else theme=null;
        document.querySelector('#oi-query').value='';page=1;await search();
      }
      if (b.dataset.fiche) await fiche(Number(b.dataset.fiche));
      if (b.dataset.quizFiche) await fiche(Number(b.dataset.quizFiche),true);
      if (b.dataset.page) {page+=Number(b.dataset.page);await search();document.querySelector('#oi-library-title')?.scrollIntoView({behavior:'smooth'});}
      if (b.hasAttribute('data-favorites')) await dashboard('favorites');
      if (b.hasAttribute('data-ai-training')) {
        const selection=document.querySelector('[data-training-launch] select[name=id]');
        const topic=activeFiche?.title||(selection?.value?selection.selectedOptions[0]?.textContent:'');
        const prompt=`Je souhaite préparer mes partiels${topic?' sur « '+topic+' »':''}. Aide-moi à construire une révision personnalisée : commence par me demander mon niveau, mes difficultés et le type de partiel. Propose ensuite une méthode adaptée et des questions ciblées, avec des explications après mes réponses.`;
        await chat(true,null,prompt);
      }
      if (b.hasAttribute('data-ai')) await chat();
      if (b.dataset.prompt) {document.querySelector('#oi-question').value=b.dataset.prompt;document.querySelector('#oi-question').focus();}
      if (b.hasAttribute('data-clear-search')) {document.querySelector('#oi-query').value='';page=1;await search();}
      if(b.hasAttribute('data-reader-goal')&&activeFiche?.goal){
        const controls=[...screen().querySelectorAll('[data-reader-goal]')];controls.forEach(control=>control.disabled=true);const opened=activeFiche;try{const r=await api(`goals/${opened.goal.id}/complete`,{fiche:opened.id,done:!opened.goal.done,version:opened.goal.version});if(activeFiche===opened){const g=r.goals.find(g=>g.id===opened.goal.id);opened.goal={...opened.goal,done:!opened.goal.done,version:g.version};controls.forEach(control=>{control.setAttribute('aria-pressed',String(opened.goal.done));control.innerHTML=icon('check')+(opened.goal.done?'Révisée pour mon objectif':'Valider pour mon objectif');});status('Ton objectif est mis à jour.');}}finally{controls.forEach(control=>control.disabled=false);}
      }
      if (b.dataset.state && activeFiche) {
        const field=b.dataset.state;
        const controls=[...screen().querySelectorAll(`[data-state="${field}"]`)];
        controls.forEach(control=>control.disabled=true);
        const ficheId=activeFiche.id;
        const value=!activeFiche.state?.[field];
        try {
          await api(`fiches/${ficheId}/state`,{field,value});
          if(activeFiche?.id===ficheId) {
            activeFiche.state={...activeFiche.state,[field]:value};
            controls.forEach(control=>{control.setAttribute('aria-pressed',String(value));control.innerHTML=icon(field==='favorite'?'heart':'check')+(field==='favorite'?(value?'Favori':'Ajouter aux favoris'):(value?'Révisée':'Marquer comme révisée'));});
            status(field==='favorite'?(value?'Fiche ajoutée à tes favoris.':'Fiche retirée de tes favoris.'):(value?'Ta progression est enregistrée.':'Fiche marquée comme à réviser.'));
          }
        } finally { controls.forEach(control=>control.disabled=false); }
      }
      if(b.hasAttribute('data-buy-recharge')) {
        b.disabled=true;
        try {const checkout=await api('ai-recharge/checkout',{});const url=new URL(checkout.url);if(url.protocol!=='https:'||url.hostname!=='checkout.stripe.com')throw new Error('Adresse de paiement invalide.');window.location.assign(url.href);}
        finally {b.disabled=false;}
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
    if(!['received','cancelled','recharged'].includes(state))return;
    const banner=document.createElement('p');banner.className='oi-payment-notice';banner.setAttribute('role','status');view.prepend(banner);
    if(state==='cancelled'){banner.textContent='Paiement interrompu. Ton accès actuel et tes crédits sont conservés.';return;}
    if(state==='recharged'){
      const session=new URLSearchParams(location.search).get('session_id')||'';
      banner.textContent='Nous attendons la confirmation sécurisée de ta recharge.';
      for(let attempt=0;attempt<10;attempt++){
        try{
          const result=await api('ai-recharge/status?session_id='+encodeURIComponent(session));
          if(result.status==='paid'){await chat(false);banner.textContent='Recharge confirmée : 100 crédits IA ont été ajoutés à ton compte.';return;}
          if(['refunded','expired'].includes(result.status)){banner.textContent='Cette recharge n’est pas active. Tes autres crédits restent conservés.';return;}
        }catch{banner.textContent='La recharge ne peut pas encore être confirmée. Actualise ton espace dans quelques instants.';return;}
        await new Promise(resolve=>setTimeout(resolve,3000));
      }
      banner.textContent='La confirmation de la recharge prend un peu de temps. Actualise ton espace avant de tenter un nouvel achat.';return;
    }
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
  async function initialView() {
    const params=new URLSearchParams(location.search),tab=params.get('view'),rawId=params.get('fiche')||'';
    ownedOnly=tab==='fiches'&&params.get('access')==='unlocked';
    const id=/^[1-9]\d*$/.test(rawId)?Number(rawId):0;
    if(['fiches','qcm'].includes(tab)&&Number.isSafeInteger(id)&&id>0){
      shell();if(!await loadAccount())return;
      return fiche(id,tab==='qcm');
    }
    if(tab==='recharge')return recharge();
    if(tab==='ai')return chat(false);
    if(tab==='premium')return premium();
    if(tab==='imports'&&OI.admin)return imports();
    if(tab==='qcm-imports'&&OI.admin)return qcmImports();
    if(tab==='users'&&OI.admin)return users();
    if(tab==='progress')return progression();
    return dashboard(['qcm','favorites'].includes(tab)?tab:'fiches',!ownedOnly);
  }
  if (view && OI.loggedIn) initialView().then(paymentReturn).catch(error);
  else if(publicCatalog) showCatalog(publicCatalog).catch(error);
})();
