(() => {
  'use strict';
  const topics = {
    hygiene:{tag:'SOINS INFIRMIERS',title:'Les bons réflexes commencent par les mains.',text:'L’hygiène des mains participe à la prévention de la transmission des micro-organismes. Un geste essentiel, avant comme après le soin.',key:'Les gants ne remplacent pas l’hygiène des mains.',question:'Le port de gants remplace-t-il l’hygiène des mains ?',answers:['Oui, les gants suffisent.','Non, les deux ont leur place.'],correct:1,explanation:'Exactement. Les gants ne dispensent pas de l’hygiène des mains : les protocoles précisent les gestes adaptés à chaque situation.'},
    calculs:{tag:'MÉTHODE & CALCULS',title:'Les conversions, sans se mélanger.',text:'Avant de calculer, commence par aligner les unités. Un litre contient 1 000 millilitres : convertir devient plus simple quand le repère est clair.',key:'1 L = 1 000 mL. Pour passer de L à mL, on multiplie par 1 000.',question:'0,5 litre correspond à…',answers:['50 mL','500 mL','5 000 mL'],correct:1,explanation:'0,5 × 1 000 = 500 mL. Ce repère illustre une conversion ; un calcul de soins exige aussi les vérifications prévues par les protocoles.'},
    cardio:{tag:'ANATOMIE & PHYSIOLOGIE',title:'Le cœur d’une notion, en quelques lignes.',text:'Le cœur met le sang en circulation. Les artères transportent le sang en s’éloignant du cœur, tandis que les veines le ramènent vers le cœur.',key:'Artères : au départ du cœur. Veines : retour vers le cœur.',question:'Quel est le rôle des artères ?',answers:['Transporter le sang du cœur vers les organes.','Ramener le sang vers le cœur.','Produire de l’oxygène.'],correct:0,explanation:'Le repère est le sens de circulation par rapport au cœur. Il permet de distinguer artères et veines sans les confondre avec la teneur en oxygène.'},
  };
  const panel=document.querySelector('#oi-demo-panel');
  let selected='hygiene';
  const render=topic=>{
    if(!panel||!topics[topic])return;
    selected=topic;const data=topics[topic];
    document.querySelectorAll('[data-demo-topic]').forEach(b=>{const active=b.dataset.demoTopic===topic;b.setAttribute('aria-pressed',String(active));b.classList.toggle('is-active',active);});
    panel.innerHTML=`<div class="oi-demo-live-head"><span>${data.tag}</span><span>EXTRAIT DÉCOUVERTE</span></div><h3>${data.title}</h3><p class="oi-demo-concept">${data.text}</p><div class="oi-demo-key"><span aria-hidden="true">✦</span><div><strong>Le repère à retenir</strong><p>${data.key}</p></div></div><div class="oi-demo-quiz"><span class="oi-demo-kicker">À TOI DE JOUER</span><h4>${data.question}</h4><div class="oi-demo-options">${data.answers.map((answer,index)=>`<button type="button" class="oi-demo-option" data-demo-answer="${index}"><span>${String.fromCharCode(65+index)}</span>${answer}</button>`).join('')}</div><p class="oi-demo-feedback" role="status"></p></div>`;
    document.querySelectorAll('[data-demo-signup]').forEach(a=>{const u=new URL(a.href,location.href);u.searchParams.set('interest',topic);a.href=u.href;});
  };
  document.querySelectorAll('[data-demo-topic]').forEach(b=>b.addEventListener('click',()=>render(b.dataset.demoTopic)));
  panel?.addEventListener('click',event=>{
    const button=event.target.closest('[data-demo-answer]');if(!button)return;
    const data=topics[selected];const correct=Number(button.dataset.demoAnswer)===data.correct;
    panel.querySelectorAll('[data-demo-answer]').forEach(b=>{b.classList.remove('is-correct','is-incorrect');b.setAttribute('aria-pressed',String(b===button));});
    button.classList.add(correct?'is-correct':'is-incorrect');
    const feedback=panel.querySelector('.oi-demo-feedback');feedback.textContent=correct?data.explanation:'Pas tout à fait. Relis le repère à retenir et essaie à nouveau.';feedback.classList.toggle('is-correct',correct);
  });
  if(panel)render(selected);
  const toggle=document.querySelector('[data-menu-toggle]');const menu=document.querySelector('[data-menu]');
  toggle?.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';toggle.setAttribute('aria-expanded',String(open));menu?.classList.toggle('is-open',open);});
  menu?.addEventListener('click',e=>{if(e.target.closest('a')){toggle?.setAttribute('aria-expanded','false');menu.classList.remove('is-open');}});
})();
