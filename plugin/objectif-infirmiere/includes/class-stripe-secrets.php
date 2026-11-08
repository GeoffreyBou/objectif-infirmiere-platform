<?php
defined('ABSPATH') || exit;

/** Write-only Stripe configuration, encrypted at rest with the server's WordPress salt. */
final class OI_Stripe_Secrets {
    private const FIELDS = ['OI_STRIPE_SECRET_KEY'=>'stripe_api_key', 'OI_STRIPE_WEBHOOK_SECRET'=>'stripe_webhook_secret'];

    public static function get(string $name): string {
        if (!isset(self::FIELDS[$name]) || !function_exists('openssl_decrypt')) { return ''; }
        $stored = get_option('oi_encrypted_' . strtolower($name));
        if (!is_array($stored) || ($stored['version'] ?? 0) !== 1) { return ''; }
        $iv = base64_decode($stored['iv'] ?? '', true);
        $tag = base64_decode($stored['tag'] ?? '', true);
        $cipher = base64_decode($stored['cipher'] ?? '', true);
        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($cipher)) { return ''; }
        $key = hash('sha256', wp_salt('auth') . '|oi-stripe-secrets-v1', true);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $name);
        return is_string($plain) ? $plain : '';
    }

    public static function store(array $values): true|WP_Error {
        if (!current_user_can('manage_options')) { return new WP_Error('oi_forbidden', 'Accès administrateur requis.'); }
        if (!function_exists('openssl_encrypt')) { return new WP_Error('oi_crypto', 'Le chiffrement serveur est indisponible.'); }
        $mode = $values['stripe_mode'] ?? OI_Stripe::mode();
        if (!in_array($mode, ['test','live'], true) || (defined('OI_STRIPE_MODE') && $mode !== OI_Stripe::mode())) {
            return new WP_Error('oi_mode', 'Environnement Stripe invalide ou imposé par le serveur.');
        }
        if ($mode !== OI_Stripe::mode() && (empty($values['stripe_api_key']) || empty($values['stripe_webhook_secret']))) {
            return new WP_Error('oi_mode', 'Un changement d’environnement exige une nouvelle clé et le secret de son webhook.');
        }
        $encrypted = [];
        foreach (self::FIELDS as $name=>$field) {
            $value = $values[$field] ?? '';
            if (!is_string($value)) { return new WP_Error('oi_secret', 'Format de clé invalide.'); }
            $value = trim($value);
            if ($value === '') { continue; }
            $pattern = $name === 'OI_STRIPE_SECRET_KEY' ? '/^(?:sk|rk)_' . $mode . '_[A-Za-z0-9]{10,250}$/' : '/^whsec_[A-Za-z0-9]{10,250}$/';
            if (!preg_match($pattern, $value)) {
                return new WP_Error('oi_secret', $name === 'OI_STRIPE_SECRET_KEY' ? 'La clé Stripe doit correspondre à l’environnement sélectionné : ' . strtoupper($mode) . '.' : 'Le secret de signature doit commencer par whsec_.');
            }
            try {
                $iv = random_bytes(12); $tag = '';
                $key = hash('sha256', wp_salt('auth') . '|oi-stripe-secrets-v1', true);
                $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $name);
            } catch (Throwable $e) { return new WP_Error('oi_crypto', 'Enregistrement sécurisé impossible.'); }
            if (!is_string($cipher) || strlen($tag) !== 16) { return new WP_Error('oi_crypto', 'Enregistrement sécurisé impossible.'); }
            $encrypted[$name] = ['version'=>1, 'iv'=>base64_encode($iv), 'tag'=>base64_encode($tag), 'cipher'=>base64_encode($cipher)];
        }
        foreach ($encrypted as $name=>$stored) {
            $option = 'oi_encrypted_' . strtolower($name);
            if (!update_option($option, $stored, false) && get_option($option) !== $stored) { return new WP_Error('oi_storage', 'La configuration n’a pas pu être enregistrée.'); }
        }
        update_option('oi_stripe_mode', $mode, false);
        return true;
    }

    public static function save(): void {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.', 403); }
        check_admin_referer('oi_stripe_secrets');
        $result = self::store(wp_unslash($_POST));
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Raccordement Stripe', ['response'=>400, 'back_link'=>true]); }
        wp_safe_redirect(add_query_arg(['page'=>'oi-settings','stripe_saved'=>'1'], admin_url('admin.php')) . '#oi-stripe-connection');
        exit;
    }

    public static function form(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<section id="oi-stripe-connection" style="max-width:780px;background:#fff;border:1px solid #c3c4c7;padding:20px;margin:24px 0"><h2>Raccordement Stripe</h2>';
        if (isset($_GET['stripe_saved'])) { echo '<p role="status"><strong>Configuration enregistrée.</strong> Les clés ne sont jamais réaffichées.</p>'; }
        echo '<p>Renseigne la clé dans ce formulaire administrateur HTTPS. Elle sera chiffrée dans la base de données avec une clé dérivée de la configuration WordPress. Un champ laissé vide conserve la valeur existante.</p><p>Les constantes ou variables d’environnement du serveur restent prioritaires. Choisis TEST pour les essais ou LIVE pour encaisser les paiements réels. Les tarifs et la destination webhook doivent appartenir au même environnement.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" autocomplete="off">';
        wp_nonce_field('oi_stripe_secrets');
        echo '<input type="hidden" name="action" value="oi_stripe_secrets">';
        echo '<p><label for="stripe_mode"><strong>Environnement</strong></label><br><select id="stripe_mode" name="stripe_mode"><option value="test" ' . selected(OI_Stripe::mode(), 'test', false) . '>TEST — aucun encaissement</option><option value="live" ' . selected(OI_Stripe::mode(), 'live', false) . '>LIVE — paiements réels</option></select></p>';
        foreach (self::FIELDS as $name=>$field) {
            $label = $name === 'OI_STRIPE_SECRET_KEY' ? 'Clé secrète / limitée Stripe' : 'Secret de signature du webhook';
            $placeholder = $name === 'OI_STRIPE_SECRET_KEY' ? 'rk_test_… / rk_live_…' : 'whsec_…';
            echo '<p><label for="' . esc_attr($field) . '"><strong>' . esc_html($label) . '</strong> — ' . (OI_Stripe::secret($name) !== '' ? 'Configuré' : 'À renseigner') . '</label><br><input type="password" id="' . esc_attr($field) . '" name="' . esc_attr($field) . '" value="" placeholder="' . esc_attr($placeholder) . '" class="large-text" autocomplete="new-password" spellcheck="false"></p>';
        }
        echo '<p>Adresse de la destination webhook : <code>' . esc_html(rest_url('oi/v1/stripe/webhook')) . '</code></p>';
        submit_button('Enregistrer le raccordement Stripe', 'primary', 'submit', false);
        echo '</form></section>';
    }
}
