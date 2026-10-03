<?php
defined('ABSPATH') || exit;
final class OI_App {
    public static function render(): string {
        wp_enqueue_style('oi-app', OI_URL . 'assets/app.css', [], '0.1.0');
        wp_enqueue_script('oi-app', OI_URL . 'assets/app.js', [], '0.1.0', true);
        wp_localize_script('oi-app', 'OI', ['api' => esc_url_raw(rest_url('oi/v1/')), 'nonce' => wp_create_nonce('wp_rest'), 'loggedIn' => is_user_logged_in(), 'name' => is_user_logged_in() ? wp_get_current_user()->display_name : '']);
        if (is_user_logged_in()) {
            nocache_headers();
            return '<main id="oi-app" class="oi-app"><header class="oi-top"><a href="#" class="oi-brand">Objectif <strong>Infirmière</strong></a><a href="' . esc_url(wp_logout_url(get_permalink())) . '">Déconnexion</a></header><div id="oi-view" aria-live="polite"><p>Chargement de vos révisions…</p></div></main>';
        }
        ob_start();
        echo '<main class="oi-app"><header class="oi-top"><span class="oi-brand">Objectif <strong>Infirmière</strong></span></header><section class="oi-welcome"><span class="oi-eyebrow">VOTRE ESPACE DE RÉVISION</span><h1>Un peu chaque jour.<br>Plus confiante demain.</h1><p>Retrouvez vos fiches, vos repères et votre progression.</p></section><section class="oi-login"><h2>Se connecter</h2>';
        wp_login_form(['redirect' => get_permalink(), 'label_username' => 'Identifiant ou adresse email', 'label_password' => 'Mot de passe', 'label_log_in' => 'Entrer dans mon espace']);
        echo '<a href="' . esc_url(wp_lostpassword_url(get_permalink())) . '">Mot de passe oublié ?</a></section><section><h2>Les packs de révision</h2><div id="oi-catalog"></div></section></main>';
        return ob_get_clean();
    }
}
