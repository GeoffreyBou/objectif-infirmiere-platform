(() => {
  'use strict';
  const panel = document.querySelector('#oi-demo-panel');
  const topics = window.OIDemoTopics;
  if (panel && topics) {
    const signup = document.querySelector('[data-demo-signup]');
    const signupBase = signup.href;
    const states = {};
    let selected = 'hygiene';
    const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fresh = () => ({index:0, choices:[], results:[], done:false});
    const state = () => states[selected] || (states[selected] = fresh());
    const number = value => String(value).padStart(2, '0');
    const correct = (question, choices) => question.correct.length === choices.length && question.correct.every(i => choices.includes(i));
    const destination = () => {
      const url = new URL(signupBase, location.href);
      // Registration currently offers hygiene, calculs and cardio. AVC has no matching preference.
      url.searchParams.delete('interest');
      if (['hygiene','calculs'].includes(selected)) url.searchParams.set('interest', selected);
      return url.href;
    };
    const excerpt = question => `<details class="oi-try-excerpt"><summary>Comprendre avec la fiche <span aria-hidden="true">＋</span></summary><div><span>EXTRAIT DE NOTRE FICHE</span><h4>${escape(topics[selected].ficheTitle)}</h4><p>${escape(question.excerpt)}</p></div></details>`;
    const focus = selector => {
      const target = panel.querySelector(selector);
      if (target) { target.focus({preventScroll:true}); target.scrollIntoView({block:'nearest', behavior:'instant'}); }
    };
    const render = focusOn => {
      const data = topics[selected], current = state();
      document.querySelectorAll('[data-demo-topic]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.demoTopic === selected)));
      signup.href = destination();
      if (current.done) {
        const score = current.results.filter(result => result.correct).length;
        panel.innerHTML = `<div class="oi-try-finish"><div class="oi-try-score" aria-label="${score} bonnes réponses sur 3">${score}<span>/ 3</span></div><div><span class="oi-try-eyebrow">TON PREMIER BILAN</span><h3 tabindex="-1" data-demo-focus>${score === 3 ? 'Trois repères bien en place.' : 'Tu sais quoi retravailler.'}</h3><p>${score} réponse${score > 1 ? 's' : ''} juste${score > 1 ? 's' : ''} · ${3-score} question${3-score > 1 ? 's' : ''} à revoir</p></div></div><div class="oi-try-review">${data.questions.map((q,i) => `<details><summary><span class="oi-try-number">${number(i+1)}</span><span>${escape(q.question)}</span><b class="${current.results[i].correct ? 'is-right' : 'is-wrong'}">${current.results[i].correct ? '✓ Juste' : '× À revoir'}</b></summary><div><p><strong>Ta réponse :</strong> ${current.results[i].choices.map(n => escape(q.options[n])).join(' ; ')}</p><p><strong>Réponse attendue :</strong> ${q.correct.map(n => escape(q.options[n])).join(' ; ')}</p><p>${escape(q.explanation)}</p></div></details>`).join('')}</div><div class="oi-try-invitation"><div><h4>Ce n’est que le début.</h4><p>5 fiches, 5 séries de QCM et 5 questions à ton assistant offertes après confirmation de ton e-mail.</p></div><a class="oi-btn oi-btn-dark" href="${escape(destination())}">Continuer gratuitement <span aria-hidden="true">→</span></a></div><button type="button" class="oi-try-restart" data-demo-restart>Recommencer ce sujet <span aria-hidden="true">↻</span></button>`;
      } else {
        const q = data.questions[current.index], result = current.results[current.index], multiple = q.correct.length > 1;
        panel.innerHTML = `<div class="oi-try-progress"><span>${escape(data.title)}</span><span>Question <strong>${number(current.index+1)}</strong> / 03</span></div><div class="oi-try-track" aria-hidden="true">${data.questions.map((_,i) => `<span class="${current.results[i] ? (current.results[i].correct ? 'is-right' : 'is-wrong') : (i === current.index ? 'is-current' : '')}"></span>`).join('')}</div><fieldset class="oi-try-question"><legend><span class="oi-try-number" aria-hidden="true">${number(current.index+1)}</span><span tabindex="-1" data-demo-focus>${escape(q.question)}</span></legend><p class="oi-try-hint">${multiple ? 'Plusieurs réponses possibles.' : 'Une seule réponse attendue.'}</p><div class="oi-try-answers">${q.options.map((answer,i) => {
          const chosen = current.choices.includes(i), right = q.correct.includes(i);
          const status = result ? (right ? 'is-right' : chosen ? 'is-wrong' : '') : '';
          const label = result ? (right ? (chosen ? 'Bonne réponse · Choisie' : 'Bonne réponse · Non choisie') : chosen ? 'Ta réponse · Incorrecte' : '') : '';
          return `<label class="oi-try-answer ${status}"><input type="${multiple ? 'checkbox' : 'radio'}" name="demo-answer" value="${i}" ${chosen ? 'checked' : ''} ${result ? 'disabled' : ''}><span class="oi-try-letter" aria-hidden="true">${String.fromCharCode(65+i)}</span><span>${escape(answer)}${label ? `<small>${label}</small>` : ''}</span>${result && (right || chosen) ? `<b aria-hidden="true">${right ? '✓' : '×'}</b>` : ''}</label>`;
        }).join('')}</div></fieldset>${result ? `<section class="oi-try-correction" aria-labelledby="oi-demo-correction"><h4 id="oi-demo-correction" tabindex="-1">${result.correct ? '✓ Bien vu !' : 'Un repère à retenir.'}</h4><p>${escape(q.explanation)}</p><details><summary>Le détail des réponses</summary><ul>${q.details.map((line,i) => `<li><strong>${String.fromCharCode(65+i)} · ${q.correct.includes(i) ? 'Vrai' : 'Faux'}</strong> ${escape(line)}</li>`).join('')}</ul></details></section>${excerpt(q)}` : ''}<div class="oi-try-actions"><span>${result ? 'Chaque correction fait avancer.' : 'Prends le temps de réfléchir.'}</span><button type="button" class="oi-btn oi-btn-dark" ${result ? 'data-demo-next' : `data-demo-validate ${current.choices.length ? '' : 'disabled'}`}>${result ? (current.index === 2 ? 'Voir mon bilan' : 'Question suivante') : 'Valider ma réponse'} <span aria-hidden="true">→</span></button></div>`;
      }
      if (focusOn) focus(focusOn);
    };
    panel.addEventListener('change', event => {
      const input = event.target.closest('input[name="demo-answer"]');
      if (!input) return;
      const current = state();
      if (current.done || current.results[current.index]) return;
      current.choices = Array.from(panel.querySelectorAll('input[name="demo-answer"]:checked'), i => Number(i.value));
      panel.querySelector('[data-demo-validate]').disabled = !current.choices.length;
    });
    panel.addEventListener('click', event => {
      const current = state();
      if (event.target.closest('[data-demo-validate]') && !current.done && !current.results[current.index] && current.choices.length) {
        current.results[current.index] = {choices:[...current.choices], correct:correct(topics[selected].questions[current.index], current.choices)};
        render('#oi-demo-correction');
      } else if (event.target.closest('[data-demo-next]') && !current.done && current.results[current.index]) {
        if (current.index === 2) current.done = true;
        else {current.index++; current.choices = [];}
        render('[data-demo-focus]');
      } else if (event.target.closest('[data-demo-restart]')) {
        states[selected] = fresh(); render('[data-demo-focus]');
      }
    });
    document.querySelectorAll('[data-demo-topic]').forEach(button => button.addEventListener('click', () => {
      if (!topics[button.dataset.demoTopic]) return;
      selected = button.dataset.demoTopic;
      render('[data-demo-focus]');
    }));
    render();
  }
  const toggle=document.querySelector('[data-menu-toggle]');const menu=document.querySelector('[data-menu]');
  toggle?.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';toggle.setAttribute('aria-expanded',String(open));menu?.classList.toggle('is-open',open);});
  menu?.addEventListener('click',e=>{if(e.target.closest('a')){toggle?.setAttribute('aria-expanded','false');menu.classList.remove('is-open');}});
})();
