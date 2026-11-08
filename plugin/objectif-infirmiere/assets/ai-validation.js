(() => {
  const byId = id => document.getElementById(id);
  async function call(action, body = {}) {
    const url = new URL(OI_AI_TRIAL.url);
    if(url.searchParams.has('rest_route'))url.searchParams.set('rest_route',url.searchParams.get('rest_route')+action);else url.pathname+=action;
    const res = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':OI_AI_TRIAL.nonce}, body:JSON.stringify(body)});
    const data = await res.json();
    if (!res.ok) throw new Error(data.message + (data.data?.upstream_status ? ` (OpenAI : ${data.data.upstream_status}${data.data.upstream_code ? ', '+data.data.upstream_code : ''})` : ''));
    return data;
  }
  byId('oi-ai-check')?.addEventListener('click', async event => {
    const button=event.currentTarget;button.disabled=true;byId('oi-ai-check-result').textContent='Vérification…';
    try { const d=await call('check');byId('oi-ai-check-result').textContent=`Connexion réussie · ${d.model}. ${d.store_configured?'Sources configurées.':'Sources à préparer.'} Aucun test de réponse effectué par cette vérification.`;
      if(d.last_provider_error?.reason==='billing_or_quota')byId('oi-ai-check-result').textContent+=' Dernier refus de génération : solde ou quota API insuffisant. Vérifie la facturation OpenAI, puis teste une question.'; }
    catch(e){byId('oi-ai-check-result').textContent=e.message;} finally{button.disabled=false;}
  });
  byId('oi-ai-trial-form')?.addEventListener('submit', async event => {
    event.preventDefault();const button=event.currentTarget.querySelector('button');button.disabled=true;
    byId('oi-ai-trial-status').textContent='Recherche dans les fiches et préparation de la réponse…';
    byId('oi-ai-trial-answer').textContent='';byId('oi-ai-trial-sources').replaceChildren();
    try {
      const d=await call('ask',{question:byId('oi-ai-trial-question').value,request_id:crypto.randomUUID()});
      byId('oi-ai-trial-answer').textContent=d.text;
      for(const source of d.sources){const li=document.createElement('li'),a=document.createElement('a');a.textContent=source.title;a.href=OI_AI_TRIAL.fiche_url+encodeURIComponent(source.id);li.append(a);byId('oi-ai-trial-sources').append(li);}
      byId('oi-ai-trial-status').textContent=`Réponse reçue · ${d.balance} crédit(s) IA restant(s). À relire pour évaluer l’exactitude et l’utilité pédagogique.`;
    }catch(e){byId('oi-ai-trial-status').textContent=e.message;}finally{button.disabled=false;}
  });
})();
