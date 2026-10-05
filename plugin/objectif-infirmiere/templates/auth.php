<?php
defined('ABSPATH') || exit;
$interest = isset($_GET['interest']) && is_string($_GET['interest']) ? sanitize_key(wp_unslash($_GET['interest'])) : 'hygiene';
if (!in_array($interest, ['hygiene','calculs','cardio'], true)) { $interest = 'hygiene'; }
?>
<main class="oi-auth">
    <a class="oi-auth-skip" href="#oi-auth-form">Aller au formulaire</a>
    <header class="oi-auth-header">
        <a class="oi-auth-brand" href="<?php echo esc_url(OI_App::url('home')); ?>" aria-label="Objectif Infirmière, accueil">
            <span class="oi-auth-brandmark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
            <span>Objectif<strong>Infirmière<span aria-hidden="true">.</span></strong></span>
        </a>
        <a class="oi-auth-back" href="<?php echo esc_url(OI_App::url('home')); ?>"><span aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 12H4m7-7-7 7 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span> Retour à l’accueil</a>
    </header>
    <div class="oi-auth-layout">
        <section class="oi-auth-story" aria-labelledby="oi-auth-title">
            <span class="oi-auth-eyebrow"><span aria-hidden="true"></span> TON FUTUR COMMENCE ICI</span>
            <h1 id="oi-auth-title"><?php echo $mode === 'login' ? 'Ta prochaine<br>réussite commence<br><em>aujourd’hui.</em>' : 'De l’envie<br>de soigner à<br><em>la fierté de réussir.</em>'; ?></h1>
            <p class="oi-auth-intro">Un espace pour comprendre tes cours, entraîner tes réflexes et avancer vers ton diplôme. À ton rythme, avec un cap.</p>
            <div class="oi-auth-preview" aria-label="Les outils de ton espace">
                <div class="oi-auth-preview-top"><span class="oi-auth-preview-icon" aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z" fill="currentColor"/></svg></span><span>TOUT POUR AVANCER</span><span class="oi-auth-preview-dot" aria-hidden="true"></span></div>
                <h2>Un peu plus prête.<br>À chaque révision.</h2>
                <ul>
                    <li><span aria-hidden="true">01</span><span><strong>Des fiches qui vont à l’essentiel</strong><small>Organise tes connaissances, UE après UE.</small></span></li>
                    <li><span aria-hidden="true">02</span><span><strong>Des QCM pour te challenger</strong><small>Comprends tes erreurs et progresse.</small></span></li>
                    <li><span aria-hidden="true">03</span><span><strong>Ton assistant de révision</strong><small>Un espace IA pensé pour tes questions.</small></span></li>
                </ul>
                <div class="oi-auth-preview-bottom"><span aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 18 12-12M6 6h12v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span> Objectif diplôme. Un pas après l’autre.</div>
            </div>
            <p class="oi-auth-story-note">Pensé pour les étudiants en soins infirmiers.<br>Et pour toutes les façons d’apprendre.</p>
        </section>
        <section class="oi-auth-panel" aria-labelledby="oi-auth-panel-title">
            <?php if (is_user_logged_in()) : ?>
                <div class="oi-auth-form-wrap" id="oi-auth-form">
                    <span class="oi-auth-step">TON ESPACE EST PRÊT</span>
                    <h2 id="oi-auth-panel-title">Heureux de te retrouver,<br><?php echo esc_html(wp_get_current_user()->first_name ?: wp_get_current_user()->display_name); ?>.</h2>
                    <p class="oi-auth-description">Tes fiches, tes QCM et ta progression t’attendent.</p>
                    <a class="oi-auth-submit" href="<?php echo esc_url(OI_App::url('member')); ?>">Rejoindre mon espace <span aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 18 12-12M6 6h12v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
                    <p class="oi-auth-switch">Ce n’est pas ton compte ? <a href="<?php echo esc_url(wp_logout_url(OI_App::url('login'))); ?>">Se déconnecter</a></p>
                </div>
            <?php else : ?>
                <div class="oi-auth-form-wrap">
                    <span class="oi-auth-step"><?php echo $mode === 'login' ? 'ON REPREND ?' : 'LE PREMIER PAS EST GRATUIT'; ?></span>
                    <h2 id="oi-auth-panel-title"><?php echo $mode === 'login' ? 'Ton objectif,<br>à portée de main.' : 'Ton futur toi<br>te dira merci.'; ?></h2>
                    <p class="oi-auth-description"><?php echo $mode === 'login' ? 'Connecte-toi pour retrouver ton espace de révision.' : 'Crée ton compte et découvre les fiches et QCM de démonstration. Sans carte bancaire.'; ?></p>
                    <?php if ($error !== '') : ?><div class="oi-auth-error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
                    <form id="oi-auth-form" class="oi-auth-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo $mode === 'login' ? 'oi_login' : 'oi_register'; ?>">
                        <?php wp_nonce_field($mode === 'login' ? 'oi_login' : 'oi_register', '_oi_nonce', false); ?>
                        <?php if ($mode === 'register') : ?>
                            <div class="oi-auth-field"><label for="oi-register-first-name">Ton prénom</label><input id="oi-register-first-name" name="first_name" type="text" placeholder="Camille" autocomplete="given-name" maxlength="80" required></div>
                            <div class="oi-auth-field"><label for="oi-register-email">Ton adresse e-mail</label><input id="oi-register-email" name="email" type="email" placeholder="camille@exemple.fr" autocomplete="email" maxlength="100" required></div>
                            <div class="oi-auth-field"><label for="oi-register-password">Crée ton mot de passe</label><div class="oi-auth-password"><input id="oi-register-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="4096" aria-describedby="oi-password-help" required><button type="button" class="oi-auth-password-toggle" data-oi-password-toggle="oi-register-password" aria-controls="oi-register-password" aria-label="Afficher le mot de passe" aria-pressed="false" hidden><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path class="oi-auth-eye-slash" d="m4 4 16 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></div><small id="oi-password-help">12 caractères minimum. Une phrase facile à retenir, c’est encore mieux.</small></div>
                            <fieldset class="oi-auth-topics"><legend>Par quoi tu aimerais commencer ? <span>Au choix</span></legend><div>
                                <label><input name="interest" type="radio" value="hygiene" <?php checked($interest, 'hygiene'); ?>><span>Hygiène</span></label>
                                <label><input name="interest" type="radio" value="calculs" <?php checked($interest, 'calculs'); ?>><span>Calculs de doses</span></label>
                                <label><input name="interest" type="radio" value="cardio" <?php checked($interest, 'cardio'); ?>><span>Cardiologie</span></label>
                            </div></fieldset>
                            <div class="oi-auth-trap" aria-hidden="true"><label for="oi-register-website">Votre site web</label><input id="oi-register-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                            <label class="oi-auth-check"><input type="checkbox" name="consent" value="1" required><span>Je souhaite créer mon compte Objectif Infirmière pour accéder à mon espace de révision.<?php if (get_privacy_policy_url()) : ?> <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">Confidentialité</a><?php endif; ?></span></label>
                            <button class="oi-auth-submit" type="submit">Créer mon compte gratuit <span aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 18 12-12M6 6h12v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button>
                            <p class="oi-auth-footnote">La démo est gratuite. Elle ne débloque pas les packs payants.</p>
                        <?php else : ?>
                            <div class="oi-auth-field"><label for="user_login">Adresse e-mail ou identifiant</label><input id="user_login" name="log" type="text" autocomplete="username" placeholder="camille@exemple.fr" maxlength="254" required></div>
                            <div class="oi-auth-field"><label for="user_pass">Mot de passe</label><div class="oi-auth-password"><input id="user_pass" name="pwd" type="password" autocomplete="current-password" maxlength="4096" required><button type="button" class="oi-auth-password-toggle" data-oi-password-toggle="user_pass" aria-controls="user_pass" aria-label="Afficher le mot de passe" aria-pressed="false" hidden><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path class="oi-auth-eye-slash" d="m4 4 16 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></div></div>
                            <div class="oi-auth-login-options"><label class="oi-auth-check"><input name="rememberme" type="checkbox" value="forever"><span>Rester connecté·e</span></label><a href="<?php echo esc_url(wp_lostpassword_url(OI_App::url('login'))); ?>">Mot de passe oublié ?</a></div>
                            <button id="wp-submit" class="oi-auth-submit" type="submit">Entrer dans mon espace <span aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 18 12-12M6 6h12v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button>
                        <?php endif; ?>
                    </form>
                    <p class="oi-auth-switch"><?php if ($mode === 'login') : ?>Pas encore de compte ? <a href="<?php echo esc_url(OI_App::url('register')); ?>">Découvrir gratuitement</a><?php else : ?>Déjà dans l’aventure ? <a href="<?php echo esc_url(OI_App::url('login')); ?>">Se connecter</a><?php endif; ?></p>
                </div>
            <?php endif; ?>
            <p class="oi-auth-panel-footer"><span aria-hidden="true"><svg class="oi-auth-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></span> Un espace personnel. Une ambition qui t’appartient.</p>
        </section>
    </div>
</main>
