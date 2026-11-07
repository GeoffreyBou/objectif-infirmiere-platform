<?php
defined('ABSPATH') || exit;
$oi_signup = esc_url(OI_App::url('register'));
$oi_login = esc_url(OI_App::url('login'));
$oi_member = esc_url(OI_App::url('member'));
$oi_brand = esc_url(OI_URL . 'assets/brand/logo.png');
$oi_founders = esc_url(OI_URL . 'assets/brand/anais-geoffrey.jpg');
$oi_mascot = esc_url(OI_URL . 'assets/brand/mascot.png');
$oi_monitor = esc_url(OI_URL . 'assets/brand/mascot-monitor.png');
$oi_arrow = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$oi_check = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10 3.5 3.5L15 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$oi_refresh = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M6.1 6.1A8 8 0 0 1 20 12M4 12a8 8 0 0 0 13.9 5.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$oi_spark = '<svg class="oi-symbol" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1c1.8 7.1 3.9 9.2 11 11-7.1 1.8-9.2 3.9-11 11C10.2 15.9 8.1 13.8 1 12 8.1 10.2 10.2 8.1 12 1Z" fill="currentColor"/></svg>';
$oi_burst = '<svg class="oi-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
$oi_heart = '<svg class="oi-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.8 4.9a5.5 5.5 0 0 0-7.8 0L12 6l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.3a5.5 5.5 0 0 0 0-7.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';
$oi_diagonal = '<svg class="oi-symbol" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 18 12-12M6 6h12v12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<div class="oi-landing">
    <a class="oi-skip" href="#oi-main">Aller au contenu</a>
    <header class="oi-site-header oi-container">
        <a class="oi-site-brand" href="<?php echo esc_url(OI_App::url('home')); ?>" aria-label="Objectif Infirmière, accueil"><img class="oi-brand-image" src="<?php echo $oi_brand; ?>" width="1300" height="436" alt="Objectif Infirmière — La référence de la révision en ligne"></a>
        <nav aria-label="Navigation principale" class="oi-public-nav">
            <a class="oi-nav-courses" href="#reviser">Réviser les cours IFSI</a>
            <?php if (is_user_logged_in()) : ?>
                <a class="oi-btn oi-btn-dark oi-nav-signup" href="<?php echo $oi_member; ?>">Mon espace <?php echo $oi_arrow; ?></a>
            <?php else : ?>
                <a class="oi-nav-login" href="<?php echo $oi_login; ?>">Se connecter</a>
                <a class="oi-btn oi-btn-dark oi-nav-signup" href="<?php echo $oi_signup; ?>">S’inscrire <?php echo $oi_arrow; ?></a>
            <?php endif; ?>
        </nav>
    </header>

    <main id="oi-main">
        <section class="oi-hero oi-container" aria-labelledby="oi-hero-title">
            <div class="oi-hero-copy">
                <span class="oi-kicker"><span class="oi-status-dot"></span> TES RÉVISIONS EN SOINS INFIRMIERS</span>
                <h1 id="oi-hero-title">Tes cours d’IFSI.<br>Enfin plus simples<br>à <span class="oi-hero-highlight">réviser.</span></h1>
                <p class="oi-hero-description">Révise avec des fiches claires, des QCM corrigés et un conseiller IA pensé pour les étudiants infirmiers.</p>
                <div class="oi-hero-actions"><a class="oi-btn oi-btn-dark" href="<?php echo $oi_signup; ?>">Créer mon compte gratuit <?php echo $oi_arrow; ?></a><a class="oi-link-arrow" href="#reviser">Comment ça marche ? <span aria-hidden="true"><?php echo $oi_diagonal; ?></span></a></div>
                <p class="oi-hero-note"><span>5 fiches + 5 séries de QCM + 5 crédits IA offerts après confirmation de ton e-mail.</span></p>
                <div class="oi-hero-bottom"><a class="oi-link-arrow" href="#demo">Tester une révision sans compte <?php echo $oi_arrow; ?></a></div>
            </div>
            <div class="oi-hero-visual" aria-label="Aperçu de l’espace de révision">
                <span class="oi-visual-label oi-reform-label"><?php echo $oi_check; ?> Mise à jour Réforme 2026 incluse</span><svg class="oi-hero-ecg" viewBox="0 0 540 120" fill="none" aria-hidden="true"><path d="M0 63h100l18-18 20 36 24-69 31 99 26-48h321" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="oi-preview-window">
                    <div class="oi-preview-top"><span class="oi-preview-logo"><?php echo $oi_heart; ?></span><div><strong>Mon carnet de révision</strong><span>Des notions qui prennent tout leur sens.</span></div><span class="oi-preview-avatar">M</span></div>
                    <div class="oi-preview-tabs"><span class="is-active">Mes fiches</span><span>Mes QCM</span><span>Mon assistant <small><?php echo $oi_spark; ?></small></span></div>
                    <div class="oi-preview-greeting"><span>UE 2.2 · LES GRANDES FONCTIONS</span><h2>Comprendre le cœur.<br>Retenir l’essentiel.</h2><span class="oi-greeting-spark" aria-hidden="true"><?php echo $oi_heart; ?></span></div>
                    <div class="oi-preview-search"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.6"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>Une UE, une notion, un déclic…<span aria-hidden="true">/</span></div>
                    <div class="oi-preview-section-title"><strong>Au programme aujourd’hui</strong><span>Voir les fiches <?php echo $oi_diagonal; ?></span></div>
                    <div class="oi-preview-cards"><div class="oi-preview-course"><span class="oi-course-icon" aria-hidden="true"><?php echo $oi_heart; ?></span><small>UE 2.2 · BIOLOGIE</small><h3>Le système<br>cardiovasculaire</h3><div><span>Comprendre l’essentiel</span><b><?php echo $oi_diagonal; ?></b></div></div><div class="oi-preview-course oi-preview-course-purple"><span class="oi-course-icon" aria-hidden="true"><?php echo $oi_burst; ?></span><small>UE 2.10 · HYGIÈNE</small><h3>Les précautions<br>standard</h3><div><span>Les bons réflexes</span><b><?php echo $oi_diagonal; ?></b></div></div></div>
                </div>
                <img class="oi-hero-mascot" src="<?php echo $oi_mascot; ?>" width="500" height="500" alt="La mascotte Objectif Infirmière, en blouse et toque de diplômée" fetchpriority="high"><span class="oi-mascot-badge">Cap sur le diplôme <span aria-hidden="true"><?php echo $oi_diagonal; ?></span></span><div class="oi-floating-quiz"><span class="oi-floating-check"><?php echo $oi_check; ?></span><div><strong>Du cours au déclic.</strong><span>Une fiche. Un QCM. Un repère en plus.</span></div><span class="oi-floating-spark" aria-hidden="true"><?php echo $oi_spark; ?></span></div>
                <span class="oi-visual-caption">Aperçu illustratif de l’espace membre</span>

            </div>
        </section>

        <div class="oi-subject-strip oi-reform-strip" aria-label="Ton programme de révision"><div class="oi-container"><div class="oi-reform-strip-label"><?php echo $oi_refresh; ?><span>Mise à jour<strong>Réforme 2026</strong></span></div><ul class="oi-catalog-highlights"><li><strong>760</strong><span>Fiches</span></li><li><strong>14 000</strong><span>QCM</span></li><li class="oi-highlight-assistant"><span>Ton</span><strong>Assistant personnel</strong></li></ul><a class="oi-btn oi-btn-dark oi-strip-signup" href="<?php echo $oi_signup; ?>">M’inscrire <?php echo $oi_arrow; ?></a></div></div>

        <section id="reviser" class="oi-offer oi-container" aria-labelledby="oi-offer-title">
            <div class="oi-section-heading"><div><span class="oi-eyebrow-public">UN PARCOURS SIMPLE, À TON RYTHME.</span><h2 id="oi-offer-title">Comment ça marche ?</h2></div><p>Lis une fiche, teste tes connaissances, puis approfondis avec ton assistant. Trois façons de réviser, un seul espace.</p></div>
            <div class="oi-offer-grid">
                <article class="oi-feature oi-feature-fiches"><div class="oi-feature-heading"><span class="oi-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 4h11l3 3v13H5V4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 10h6m-6 4h6m-6 3h3M15 4v4h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span><span>01 / Comprendre</span></div><h3>Une fiche claire.<br>L’essentiel à retenir.</h3><p>Choisis ton sujet et retrouve les notions essentielles dans une fiche structurée. Garde tes favoris à portée de main pour y revenir facilement.</p><div class="oi-fiche-illustration" aria-hidden="true"><div class="oi-paper"><span>UE 2.10 · INFECTIOLOGIE & HYGIÈNE</span><strong>Précautions<br>standard.</strong><div class="oi-paper-line oi-line-long"></div><div class="oi-paper-line"></div><div class="oi-paper-highlight">Avant le soin, les bons repères.</div><div class="oi-paper-line oi-line-long"></div></div><span class="oi-paper-bookmark"><?php echo $oi_spark; ?></span><span class="oi-paper-tag"><?php echo $oi_check; ?> Dans mes favoris</span></div></article>
                <article class="oi-feature oi-feature-qcm"><div class="oi-feature-heading"><span class="oi-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.6"/><path d="m8 12 3 3 5-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>02 / S’entraîner</span></div><h3>Teste-toi.<br>Progresse à chaque réponse.</h3><p>Entraîne-toi question après question, découvre la correction et repère les notions à revoir.</p><figure class="oi-qcm-example" aria-label="Exemple de question corrigée">
                    <figcaption><span>Question 01 / 20</span><span>Exemple de correction</span></figcaption>
                    <div class="oi-qcm-example-question"><span aria-hidden="true">01</span><h4>Après avoir retiré ses gants, un soignant a les mains visiblement souillées. Quel geste réaliser ?</h4></div>
                    <ul class="oi-qcm-example-answers">
                        <li class="is-incorrect"><span class="oi-qcm-example-mark" aria-hidden="true">×</span><div><span>Une friction hydroalcoolique.</span><small>Ta réponse · Incorrecte</small></div></li>
                        <li class="is-correct"><span class="oi-qcm-example-mark" aria-hidden="true"><?php echo $oi_check; ?></span><div><span>Un lavage à l’eau et au savon.</span><small>Bonne réponse</small></div></li>
                    </ul>
                    <div class="oi-qcm-example-explanation"><strong>Comprendre la correction</strong><p>La friction hydroalcoolique s’utilise sur des mains sèches et visiblement propres. En présence de salissures visibles, le lavage à l’eau et au savon permet de les éliminer.</p></div>
                </figure></article>
                <article class="oi-feature oi-feature-ai">
                    <div class="oi-assistant-copy"><div class="oi-feature-heading"><span class="oi-feature-icon" aria-hidden="true"><?php echo $oi_spark; ?></span><span>03 / Comprendre mieux avec ton assistant</span></div><h3>Un doute ?<br>Éclaire ce qui te bloque.</h3><p>Pose tes questions à ton assistant personnel pour reformuler une notion, approfondir un sujet et faire les liens entre tes cours.</p></div>
                    <figure class="oi-assistant-exchange" aria-label="Exemple d’échange avec l’assistant">
                        <figcaption>Exemple d’échange</figcaption>
                        <p class="oi-assistant-question">« Explique-moi la différence entre antisepsie et asepsie. »</p>
                        <div class="oi-assistant-reply"><img src="<?php echo $oi_mascot; ?>" width="500" height="500" alt="La mascotte de ton assistant soignant" loading="lazy"><div class="oi-assistant-bubble"><strong>Ton assistant soignant</strong><p>L’asepsie vise à éviter la contamination. L’antisepsie utilise un antiseptique sur les tissus vivants pour réduire ou éliminer les micro-organismes.</p></div></div>
                    </figure>
                </article>
            </div>
            <section class="oi-trial-step" aria-labelledby="oi-free-title">
                <div class="oi-trial-copy">
                    <div class="oi-feature-heading"><span class="oi-feature-icon" aria-hidden="true"><?php echo $oi_arrow; ?></span><span>04 / Démarrer gratuitement</span></div>
                    <h3 id="oi-free-title">On t’offre de quoi commencer.</h3>
                    <p>Choisis les sujets qui t’intéressent et découvre ta façon de réviser. Ton compte gratuit te donne accès à :</p>
                </div>
                <div class="oi-trial-access">
                    <ul class="oi-trial-benefits"><li><strong>5</strong><span>fiches au choix</span></li><li><strong>5</strong><span>séries de QCM</span></li><li><strong>5</strong><span>questions à l’assistant</span></li></ul>
                    <a class="oi-btn oi-btn-dark" href="<?php echo $oi_signup; ?>">Démarrer gratuitement <?php echo $oi_arrow; ?></a>
                    <p class="oi-trial-note">Offerts une seule fois après confirmation de ton e-mail. Sans carte bancaire. Les fiches et séries débloquées restent accessibles.</p>
                    <p class="oi-trial-note">Pour aller plus loin, un accès complet est proposé dans ton espace.</p>
                </div>
            </section>
        </section>

        <section id="demo" class="oi-try-section" aria-labelledby="oi-demo-title">
            <div class="oi-container">
                <header class="oi-try-intro"><span class="oi-eyebrow-public">ESSAIE PAR TOI-MÊME MAINTENANT !</span><h2 id="oi-demo-title">Une question. Une correction.<br><span>Et ça devient plus clair.</span></h2><p>Choisis un sujet et teste-toi sur trois questions de notre catalogue.</p></header>
                <div class="oi-try-workspace">
                    <div class="oi-try-topics" role="group" aria-label="Choisir un sujet de démonstration"><button type="button" data-demo-topic="hygiene" aria-pressed="true"><span aria-hidden="true">01</span> Précautions standard</button><button type="button" data-demo-topic="calculs" aria-pressed="false"><span aria-hidden="true">02</span> Calculs de doses</button><button type="button" data-demo-topic="avc" aria-pressed="false"><span aria-hidden="true">03</span> Pathologie – AVC</button></div>
                    <div id="oi-demo-panel" class="oi-try-panel"><p>Trois questions, leur correction et des extraits de nos fiches pour découvrir ta façon de réviser.</p><noscript><p>Active JavaScript pour essayer la démonstration interactive, ou crée directement ton espace gratuit.</p></noscript></div>
                    <footer class="oi-try-footer"><p>Envie de choisir tes propres sujets ?</p><a href="<?php echo $oi_signup; ?>" data-demo-signup>Créer mon espace gratuit <?php echo $oi_arrow; ?></a></footer>
                </div>
            </div>
        </section>





        <section class="oi-founders oi-container" aria-labelledby="oi-trust-title">
            <figure class="oi-founders-portrait"><img src="<?php echo $oi_founders; ?>" width="960" height="960" alt="Anaïs et Geoffrey, les créateurs d’Objectif Infirmière" loading="lazy"><figcaption><strong>Anaïs &amp; Geoffrey</strong><span>Créateurs d’Objectif Infirmière</span></figcaption></figure>
            <div class="oi-founders-copy">
                <span class="oi-eyebrow-public">À TES CÔTÉS DEPUIS 2019</span>
                <h2 id="oi-trust-title">Derrière tes révisions,<br><span>il y a nous.</span></h2>
                <p>Nous, c’est <strong>Anaïs &amp; Geoffrey</strong>. Depuis 2019, nous accompagnons les étudiants infirmiers avec Objectif Infirmière, pour rendre les révisions plus claires et les notions plus faciles à comprendre.</p>
                <p class="oi-founders-belief">Notre conviction : on apprend mieux quand on comprend ce que l’on révise.</p>
                <ul class="oi-founders-method"><li><?php echo $oi_check; ?><span><strong>Comprendre l’essentiel.</strong> Des fiches structurées pour construire tes repères.</span></li><li><?php echo $oi_check; ?><span><strong>Apprendre de tes erreurs.</strong> Des QCM expliqués pour savoir quoi retravailler.</span></li><li><?php echo $oi_check; ?><span><strong>Avancer à ton rythme.</strong> Un parcours personnel et un assistant pour approfondir.</span></li></ul>
                <div class="oi-founders-social"><span>On se retrouve aussi ici :</span><nav aria-label="Retrouver Anaïs et Geoffrey sur les réseaux sociaux"><a href="https://www.instagram.com/objectif.soignant/" target="_blank" rel="noopener noreferrer" aria-label="Instagram Objectif Soignant (nouvel onglet)"><svg class="oi-social-instagram" viewBox="0 0 24 24" fill="none" aria-hidden="true"><defs><linearGradient id="oi-instagram-gradient" x1="0" y1="24" x2="24" y2="0" gradientUnits="userSpaceOnUse"><stop stop-color="#feda75"/><stop offset=".3" stop-color="#fa7e1e"/><stop offset=".55" stop-color="#d62976"/><stop offset=".8" stop-color="#962fbf"/><stop offset="1" stop-color="#4f5bd5"/></linearGradient></defs><rect width="24" height="24" rx="6" fill="url(#oi-instagram-gradient)"/><rect x="5" y="5" width="14" height="14" rx="4" stroke="#fff" stroke-width="1.7"/><circle cx="12" cy="12" r="3.4" stroke="#fff" stroke-width="1.7"/><circle cx="16.7" cy="7.3" r="1" fill="#fff"/></svg>Instagram <?php echo $oi_diagonal; ?></a><a href="https://www.youtube.com/@ObjectifSoignant" target="_blank" rel="noopener noreferrer" aria-label="YouTube Objectif Soignant (nouvel onglet)"><svg class="oi-social-youtube" viewBox="0 0 24 24" aria-hidden="true"><path fill="#ff0000" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8Z"/><path fill="#fff" d="m9.6 15.6 6.2-3.6-6.2-3.6Z"/></svg>YouTube <?php echo $oi_diagonal; ?></a></nav></div>
            </div>
        </section>


        <section id="faq" class="oi-faq oi-faq-refresh oi-container" aria-labelledby="oi-faq-title">
            <div class="oi-faq-panel">
                <header class="oi-faq-intro"><h2 id="oi-faq-title">Tes questions,<br><span>nos réponses.</span></h2><p>Compte gratuit, révisions, crédits : retrouve ici les réponses utiles.</p></header>
                <div class="oi-faq-groups">
                    <section class="oi-faq-group" aria-labelledby="oi-faq-start"><h3 id="oi-faq-start">Bien démarrer</h3><div class="oi-faq-items"><details><summary>Est-ce que l’inscription est gratuite ?<span aria-hidden="true">+</span></summary><p>Oui, la création de ton compte est gratuite. Après confirmation de ton adresse e-mail, tu reçois une seule fois 5 crédits Fiche, 5 crédits QCM et 5 crédits IA. Un accès complet facultatif est ensuite proposé dans ton espace, sans abonnement.</p></details><details><summary>À qui s’adresse Objectif Infirmière ?<span aria-hidden="true">+</span></summary><p>Aux étudiantes et étudiants en soins infirmiers qui veulent structurer leurs révisions, comprendre les notions de cours et s’entraîner avec des QCM, à leur rythme.</p></details><details><summary>Est-ce adapté au téléphone ?<span aria-hidden="true">+</span></summary><p>Oui. Ton espace est conçu pour fonctionner sur téléphone, tablette et ordinateur, directement dans ton navigateur. Tu peux retrouver tes révisions entre deux cours comme chez toi.</p></details></div></section>
                    <section class="oi-faq-group" aria-labelledby="oi-faq-revise"><h3 id="oi-faq-revise">Réviser au quotidien</h3><div class="oi-faq-items"><details><summary>Qu’est-ce que je retrouve dans mon espace ?<span aria-hidden="true">+</span></summary><p>Un catalogue de fiches et de séries de QCM à choisir avec tes crédits. Une fiche ou une série débloquée reste accessible. Tes favoris et ta progression sont conservés. Le conseiller IA est encore en préparation.</p></details><details><summary>Les crédits se renouvellent-ils chaque mois ?<span aria-hidden="true">+</span></summary><p>Non. Les crédits de bienvenue sont attribués une seule fois. Le Premium ajoute une enveloppe totale de 100 crédits IA, sans renouvellement automatique. Une fiche ou une série de QCM déjà débloquée ne consomme plus de crédit.</p></details><details><summary>Que se passe-t-il si le conseiller IA rencontre une erreur ?<span aria-hidden="true">+</span></summary><p>Une demande qui échoue sans fournir de réponse utilisable ne consomme pas de crédit. Le conseiller reste indiqué en préparation tant que ses réponses sourcées ne sont pas disponibles.</p></details><details><summary>Est-ce que la plateforme remplace mes cours ?<span aria-hidden="true">+</span></summary><p>Elle complète tes enseignements et t’aide à réviser. Tes cours IFSI, les recommandations actualisées et les protocoles de ton terrain de stage restent tes références. Les contenus de démonstration sont identifiés comme tels.</p></details></div></section>
                </div>
            </div>
        </section>
        <section class="oi-join oi-container" aria-labelledby="oi-join-title"><div class="oi-join-inner"><div class="oi-join-copy"><span class="oi-eyebrow-public">TON FUTUR COMMENCE AVEC UN PREMIER PAS.</span><h2 id="oi-join-title">Tes premières révisions<br><span>commencent ici.</span></h2><p>Une blouse, un diplôme, un métier qui a du sens. Commence par les notions qui te feront avancer.</p><a class="oi-btn oi-btn-mint" href="<?php echo $oi_signup; ?>">Créer mon compte gratuit <?php echo $oi_arrow; ?></a><span class="oi-join-note">Sans carte bancaire · Accès complet facultatif</span></div><div class="oi-join-art" aria-hidden="true"><span class="oi-join-orbit"></span><img class="oi-join-mascot" src="<?php echo $oi_monitor; ?>" width="500" height="500" alt="" loading="lazy"><span class="oi-join-label oi-join-label-a">Objectif : IDE.</span><span class="oi-join-label oi-join-label-b">Un déclic à la fois.</span></div></div></section>

    </main>

    <?php require OI_DIR . 'templates/public-footer.php'; ?>
</div>
