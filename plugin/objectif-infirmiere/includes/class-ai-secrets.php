<?php
defined('ABSPATH') || exit;

/** Write-only OpenAI configuration, encrypted at rest with the server's WordPress salt. */
final class OI_AI_Secrets {
    private const FIELDS = ['OI_OPENAI_API_KEY'=>'openai_api_key'];

    public static function get(string $name): string {
        if (!isset(self::FIELDS[$name]) || !function_exists('openssl_decrypt')) { return ''; }
        $stored = get_option('oi_encrypted_' . strtolower($name));
        if (!is_array($stored) || ($stored['version'] ?? 0) !== 1) { return ''; }
        $iv = base64_decode($stored['iv'] ?? '', true);
        $tag = base64_decode($stored['tag'] ?? '', true);
        $cipher = base64_decode($stored['cipher'] ?? '', true);
        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($cipher)) { return ''; }
        $key = hash('sha256', wp_salt('auth') . '|oi-ai-secrets-v1', true);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $name);
        return is_string($plain) ? $plain : '';
    }

    public static function store(array $values): true|WP_Error {
        if (!current_user_can('manage_options')) { return new WP_Error('oi_forbidden', 'Accès administrateur requis.'); }
        if (!function_exists('openssl_encrypt')) { return new WP_Error('oi_crypto', 'Le chiffrement serveur est indisponible.'); }
        $encrypted = [];
        foreach (self::FIELDS as $name=>$field) {
            $value = $values[$field] ?? '';
            if (!is_string($value)) { return new WP_Error('oi_secret', 'Format de clé invalide.'); }
            $value = trim($value);
            if ($value === '') { continue; }
            if (!preg_match('/^sk-[A-Za-z0-9_-]{20,1024}$/D', $value)) {
                return new WP_Error('oi_secret', 'Utilise une clé API OpenAI commençant par sk- ou sk-proj-.');
            }
            try {
                $iv = random_bytes(12); $tag = '';
                $key = hash('sha256', wp_salt('auth') . '|oi-ai-secrets-v1', true);
                $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $name);
            } catch (Throwable $e) { return new WP_Error('oi_crypto', 'Enregistrement sécurisé impossible.'); }
            if (!is_string($cipher) || strlen($tag) !== 16) { return new WP_Error('oi_crypto', 'Enregistrement sécurisé impossible.'); }
            $encrypted[$name] = ['version'=>1, 'iv'=>base64_encode($iv), 'tag'=>base64_encode($tag), 'cipher'=>base64_encode($cipher)];
        }
        foreach ($encrypted as $name=>$stored) {
            $option = 'oi_encrypted_' . strtolower($name);
            if (!update_option($option, $stored, false) && get_option($option) !== $stored) { return new WP_Error('oi_storage', 'La configuration n’a pas pu être enregistrée.'); }
        }
        return true;
    }

    public static function save(): void {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.', 403); }
        check_admin_referer('oi_ai_secrets');
        $result = self::store(wp_unslash($_POST));
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Raccordement OpenAI', ['response'=>400, 'back_link'=>true]); }
        wp_safe_redirect(add_query_arg(['page'=>'oi-settings','ai_saved'=>'1'], admin_url('admin.php')) . '#oi-ai-connection');
        exit;
    }

    public static function form(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<section id="oi-ai-connection" style="max-width:780px;background:#fff;border:1px solid #c3c4c7;padding:20px;margin:24px 0"><h2>Raccordement de l’assistant IA — OpenAI</h2>';
        if (isset($_GET['ai_saved'])) { echo '<p role="status"><strong>Configuration enregistrée.</strong> La clé n’est jamais réaffichée.</p>'; }
        echo '<p>Colle ici ta clé API OpenAI. Elle sera chiffrée dans la base de données avec une clé dérivée de la configuration WordPress. Un champ laissé vide conserve la clé existante.</p><p>Enregistrer la clé n’active pas l’assistant. Les constantes ou variables d’environnement du serveur restent prioritaires.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" autocomplete="off">';
        wp_nonce_field('oi_ai_secrets');
        echo '<input type="hidden" name="action" value="oi_ai_secrets">';
        echo '<p><label for="openai_api_key"><strong>Clé API OpenAI</strong> — ' . (OI_Stripe::secret('OI_OPENAI_API_KEY') !== '' ? 'Configurée' : 'À renseigner') . '</label><br><input type="password" id="openai_api_key" name="openai_api_key" value="" placeholder="sk-proj-…" class="large-text" autocomplete="new-password" spellcheck="false"></p>';
        submit_button('Enregistrer le raccordement OpenAI', 'primary', 'submit', false);
        echo '</form></section>';
    }
}
