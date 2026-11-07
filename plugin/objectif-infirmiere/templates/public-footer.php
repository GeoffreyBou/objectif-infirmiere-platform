<?php defined('ABSPATH') || exit; ?>
<footer class="oi-footer"><div class="oi-container">
    <div class="oi-footer-top">
        <a class="oi-site-brand" href="<?php echo esc_url(OI_App::url('home')); ?>"><img class="oi-brand-image" src="<?php echo esc_url(OI_URL . 'assets/brand/logo.png'); ?>" width="1300" height="436" alt="Objectif Infirmière — La référence de la révision en ligne"></a>
        <p>Apprendre aujourd’hui.<br><strong>Prendre soin demain.</strong></p>
        <nav aria-label="Navigation de pied de page"><a href="<?php echo esc_url(OI_App::url('home') . '#reviser'); ?>">Les révisions</a><a href="<?php echo esc_url(OI_App::url('home') . '#demo'); ?>">Essayer une notion</a><a href="<?php echo esc_url(OI_App::url('home') . '#faq'); ?>">Questions fréquentes</a><a href="<?php echo esc_url(OI_App::url('login')); ?>">Mon espace</a></nav>
    </div>
    <div class="oi-footer-bottom">
        <span>© <?php echo esc_html(wp_date('Y')); ?> Objectif Infirmière</span>
        <nav class="oi-footer-legal" aria-label="Informations légales"><a href="<?php echo esc_url(OI_App::url('legal')); ?>">Mentions légales</a><a href="<?php echo esc_url(OI_App::url('privacy')); ?>">Politique de confidentialité</a></nav>
        <span>Avec toi, du premier cours au prochain déclic. <i aria-hidden="true">✦</i></span>
    </div>
</div></footer>
