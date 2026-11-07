<?php defined('ABSPATH') || exit; ?>
<div class="oi-landing oi-legal-page">
    <a class="oi-skip" href="#oi-main">Aller au contenu</a>
    <header class="oi-site-header oi-container">
        <a class="oi-site-brand" href="<?php echo esc_url(OI_App::url('home')); ?>" aria-label="Objectif Infirmière, accueil"><img class="oi-brand-image" src="<?php echo esc_url(OI_URL . 'assets/brand/logo.png'); ?>" width="1300" height="436" alt="Objectif Infirmière — La référence de la révision en ligne"></a>
        <nav class="oi-public-nav" aria-label="Navigation principale"><a href="<?php echo esc_url(OI_App::url('home')); ?>">Retour à l’accueil</a><a class="oi-btn oi-btn-dark oi-nav-signup" href="<?php echo esc_url(OI_App::url(is_user_logged_in() ? 'member' : 'register')); ?>"><?php echo is_user_logged_in() ? 'Mon espace' : 'S’inscrire'; ?> <span aria-hidden="true">→</span></a></nav>
    </header>
    <main id="oi-main" class="oi-container">
        <div class="oi-legal-heading"><span class="oi-eyebrow-public">OBJECTIF INFIRMIÈRE</span><h1><?php echo esc_html(get_the_title()); ?></h1></div>
        <div class="oi-legal-layout">
            <?php if ($sections) : ?><nav class="oi-legal-toc" aria-label="Sommaire"><strong>Dans cette page</strong><ol><?php foreach ($sections as $section) : ?><li><a href="#<?php echo esc_attr($section['id']); ?>"><?php echo esc_html($section['title']); ?></a></li><?php endforeach; ?></ol></nav><?php endif; ?>
            <article class="oi-legal-content" aria-label="<?php echo esc_attr(get_the_title()); ?>"><?php echo $content; ?></article>
        </div>
    </main>
    <?php require OI_DIR . 'templates/public-footer.php'; ?>
</div>
