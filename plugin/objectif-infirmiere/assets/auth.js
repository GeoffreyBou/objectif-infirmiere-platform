(() => {
    'use strict';
    document.querySelectorAll('[data-oi-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.oiPasswordToggle);
        if (!input) return;
        button.hidden = false;
        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
})();
