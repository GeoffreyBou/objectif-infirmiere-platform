<?php
defined('ABSPATH') || exit;

/** Public account flows. Credentials never enter URLs or application logs. */
final class OI_Auth {
    public static function register(): void {
        foreach (['admin_post_nopriv_oi_register', 'admin_post_oi_register'] as $hook) {
            add_action($hook, [self::class, 'submit_registration']);
        }
        foreach (['admin_post_nopriv_oi_login', 'admin_post_oi_login'] as $hook) {
            add_action($hook, [self::class, 'submit_login']);
        }
    }

    public static function render(string $mode = 'register'): string {
        $mode = $mode === 'login' ? 'login' : 'register';
        nocache_headers();
        wp_enqueue_style('oi-base', OI_URL . 'assets/base.css', [], OI_VERSION);
        wp_enqueue_style('oi-auth', OI_URL . 'assets/auth.css', ['oi-base'], OI_VERSION);
        wp_enqueue_script('oi-auth', OI_URL . 'assets/auth.js', [], OI_VERSION, true);
        $notice = isset($_GET['notice']) && is_string($_GET['notice']) ? sanitize_key(wp_unslash($_GET['notice'])) : '';
        $messages = [
            'expired' => 'La page a expiré. Réessaie avec le formulaire ci-dessous.',
            'slow_down' => 'Plusieurs essais ont été effectués. Patiente un peu avant de réessayer.',
            'name' => 'Indique ton prénom pour personnaliser ton espace.',
            'email' => 'Vérifie le format de ton adresse e-mail.',
            'password' => 'Choisis un mot de passe contenant au moins 12 caractères.',
            'consent' => 'Confirme la création de ton compte pour continuer.',
            'account' => 'La création du compte n’a pas abouti. Réessaie, ou connecte-toi si tu as déjà un compte.',
            'login' => 'Connexion impossible avec ces informations. Vérifie-les ou réinitialise ton mot de passe.',
        ];
        $error = $messages[$notice] ?? '';
        ob_start();
        require OI_DIR . 'templates/auth.php';
        return (string) ob_get_clean();
    }

    /** @return int|WP_Error Validated insertion; request protection belongs to the handler. */
    public static function create_student(array $fields) {
        $name = self::field($fields, 'first_name');
        $name = sanitize_text_field(trim($name));
        if ($name === '' || strlen($name) > 240) {
            return new WP_Error('name');
        }
        $email = trim(self::field($fields, 'email'));
        if (!is_email($email) || strlen($email) > 100) {
            return new WP_Error('email');
        }
        $password = self::field($fields, 'password');
        $length = preg_match_all('/./us', $password);
        if ($length === false || $length < 12 || strlen($password) > 4096) {
            return new WP_Error('password');
        }
        if (self::field($fields, 'consent') !== '1') {
            return new WP_Error('consent');
        }
        if (self::field($fields, 'website') !== '' || email_exists($email)) {
            return new WP_Error('account');
        }
        $user = wp_insert_user([
            'user_login' => 'etudiant_' . strtolower(wp_generate_password(20, false, false)),
            'user_email' => sanitize_email($email),
            'user_pass' => $password,
            'first_name' => $name,
            'display_name' => $name,
            'role' => 'oi_etudiant',
            'show_admin_bar_front' => 'false',
        ]);
        if (is_wp_error($user)) {
            return new WP_Error('account');
        }
        $interest = self::field($fields, 'interest');
        if (in_array($interest, ['hygiene', 'calculs', 'cardio'], true)) {
            update_user_meta($user, 'oi_interest', $interest);
        }
        update_user_meta($user, 'oi_signup_source', 'public_form');
        $pack = absint(get_option('oi_registration_demo_pack', 0));
        if ($pack && get_post_type($pack) === 'oi_pack' && get_post_status($pack) === 'publish'
            && get_post_meta($pack, 'oi_free_demo', true) === '1'
            && !get_post_meta($pack, 'oi_price', true)) {
            update_user_meta($user, 'oi_packs', [$pack]);
        }
        return (int) $user;
    }

    public static function submit_registration(): void {
        self::already_connected();
        $error = self::check_request('oi_register', 6, HOUR_IN_SECONDS);
        if ($error !== '') {
            self::redirect('register', $error);
        }
        $user = self::create_student(wp_unslash($_POST));
        if (is_wp_error($user)) {
            self::redirect('register', $user->get_error_code());
        }
        wp_set_current_user($user);
        wp_set_auth_cookie($user, false, is_ssl());
        do_action('wp_login', get_userdata($user)->user_login, get_userdata($user));
        wp_safe_redirect(OI_App::url('member'), 303);
        exit;
    }

    public static function submit_login(): void {
        self::already_connected();
        $error = self::check_request('oi_login', 20, 15 * MINUTE_IN_SECONDS);
        if ($error !== '') {
            self::redirect('login', $error);
        }
        $user = wp_signon([
            'user_login' => trim(self::field(wp_unslash($_POST), 'log')),
            'user_password' => self::field(wp_unslash($_POST), 'pwd'),
            'remember' => self::field($_POST, 'rememberme') === 'forever',
        ], is_ssl());
        if (is_wp_error($user)) {
            self::redirect('login', 'login');
        }
        wp_set_current_user($user->ID);
        wp_safe_redirect(OI_App::url('member'), 303);
        exit;
    }

    /** Also excludes cross-origin login CSRF, including a reused public nonce. */
    private static function check_request(string $action, int $limit, int $seconds): string {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return 'expired';
        }
        if (!OI_Limit::take($action, $limit, $seconds)) {
            return 'slow_down';
        }
        $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        if (!is_string($source) || !self::same_origin($source, home_url('/'))) {
            return 'expired';
        }
        $nonce = self::field($_POST, '_oi_nonce');
        if (!wp_verify_nonce($nonce, $action)) {
            return 'expired';
        }
        return '';
    }

    public static function same_origin(string $source, string $destination): bool {
        $left = wp_parse_url($source);
        $right = wp_parse_url($destination);
        if (!is_array($left) || !is_array($right) || empty($left['scheme']) || empty($left['host'])
            || empty($right['scheme']) || empty($right['host'])) {
            return false;
        }
        return strtolower($left['scheme']) === strtolower($right['scheme'])
            && strtolower($left['host']) === strtolower($right['host'])
            && ($left['port'] ?? ($left['scheme'] === 'https' ? 443 : 80)) === ($right['port'] ?? ($right['scheme'] === 'https' ? 443 : 80));
    }

    private static function field(array $fields, string $key): string {
        return isset($fields[$key]) && is_string($fields[$key]) ? $fields[$key] : '';
    }

    private static function already_connected(): void {
        if (is_user_logged_in()) {
            wp_safe_redirect(OI_App::url('member'), 303);
            exit;
        }
    }

    private static function redirect(string $mode, string $code): void {
        $destination = OI_App::url($mode === 'login' ? 'login' : 'register');
        wp_safe_redirect(add_query_arg('notice', sanitize_key($code), $destination), 303);
        exit;
    }
}
