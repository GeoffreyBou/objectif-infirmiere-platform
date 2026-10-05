<?php
/** Local WordPress integration: registration cannot grant paid access or elevated roles. */
if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Tests d’inscription réservés à WordPress local.');
}
$auth_checks = 0;
$auth_users = [];
$auth_posts = [];
$auth_default_role = get_option('default_role');
$auth_demo_option = get_option('oi_registration_demo_pack', false);
$auth_server = $_SERVER;
$auth_post = $_POST;
$auth_current_user = get_current_user_id();
$auth_suffix = strtolower(wp_generate_password(12, false, false));
$auth_fields = [
    'first_name' => 'Camille',
    'email' => 'auth_' . $auth_suffix . '@example.invalid',
    'password' => 'Une phrase locale de test 2026!',
    'consent' => '1',
    'interest' => 'hygiene',
    'website' => '',
];
$auth_assert = static function (bool $condition, string $message) use (&$auth_checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $auth_checks++;
};
$auth_create = static function (array $fields) use (&$auth_users) {
    $result = OI_Auth::create_student($fields);
    if (!is_wp_error($result)) {
        $auth_users[] = $result;
    }
    return $result;
};
try {
    foreach ([
        ['first_name' => '', 'code' => 'name'],
        ['first_name' => ['array'], 'code' => 'name'],
        ['email' => 'not-an-email', 'code' => 'email'],
        ['password' => 'short', 'code' => 'password'],
        ['password' => 'éééééé', 'code' => 'password'],
        ['consent' => '', 'code' => 'consent'],
        ['website' => 'https://bot.example', 'code' => 'account'],
    ] as $invalid) {
        $code = $invalid['code'];
        unset($invalid['code']);
        $result = $auth_create(array_replace($auth_fields, $invalid));
        $auth_assert(is_wp_error($result) && $result->get_error_code() === $code, 'Formulaire invalide refusé : ' . $code);
    }
    $auth_assert(!email_exists($auth_fields['email']), 'Aucun compte créé par les soumissions invalides');

    delete_option('oi_registration_demo_pack');
    update_option('default_role', 'administrator');
    $student = $auth_create(array_merge($auth_fields, [
        'role' => 'administrator',
        'oi_packs' => [999],
        'meta_input' => ['oi_packs' => [999]],
    ]));
    $auth_assert(is_int($student), 'Compte réel créé');
    $account = get_userdata($student);
    $auth_assert($account->roles === ['oi_etudiant'], 'Le rôle étudiant reste obligatoire, même avec un rôle par défaut administrateur');
    $auth_assert(!user_can($student, 'manage_options') && !user_can($student, 'edit_posts'), 'Aucun privilège d’administration ou d’édition');
    $auth_assert(wp_check_password($auth_fields['password'], $account->user_pass, $student), 'Mot de passe chiffré compatible avec la connexion native');
    $auth_assert($account->first_name === 'Camille', 'Prénom conservé');
    $auth_assert(get_user_meta($student, 'oi_interest', true) === 'hygiene', 'Sujet d’intérêt enregistré');
    $auth_assert(OI_Model::packs($student) === [], 'Aucun pack accordé implicitement ou via un champ forgé');
    $duplicate = $auth_create($auth_fields);
    $auth_assert(is_wp_error($duplicate) && $duplicate->get_error_code() === 'account', 'Doublon renvoie une erreur générique');

    $paid_pack = wp_insert_post(['post_type' => 'oi_pack', 'post_status' => 'publish', 'post_title' => 'Auth test paid ' . $auth_suffix]);
    $auth_posts[] = $paid_pack;
    update_option('oi_registration_demo_pack', $paid_pack);
    $blocked = $auth_create(array_replace($auth_fields, ['email' => 'auth_paid_' . $auth_suffix . '@example.invalid', 'interest' => 'administrator']));
    $auth_assert(is_int($blocked) && OI_Model::packs($blocked) === [], 'Pack non marqué gratuit refusé même si son ID est configuré');
    $auth_assert(get_user_meta($blocked, 'oi_interest', true) === '', 'Sujet inconnu ignoré');

    $demo_fiche = wp_insert_post(['post_type' => 'oi_fiche', 'post_status' => 'publish', 'post_title' => 'Auth test demo ' . $auth_suffix]);
    $paid_fiche = wp_insert_post(['post_type' => 'oi_fiche', 'post_status' => 'publish', 'post_title' => 'Auth test private ' . $auth_suffix]);
    $demo_pack = wp_insert_post(['post_type' => 'oi_pack', 'post_status' => 'publish', 'post_title' => 'Auth test free ' . $auth_suffix]);
    array_push($auth_posts, $demo_fiche, $paid_fiche, $demo_pack);
    update_post_meta($paid_pack, 'oi_fiches', [$paid_fiche]);
    update_post_meta($demo_pack, 'oi_fiches', [$demo_fiche]);
    update_post_meta($demo_pack, 'oi_free_demo', '1');
    update_option('oi_registration_demo_pack', $demo_pack);
    $demo_student = $auth_create(array_replace($auth_fields, ['email' => 'auth_demo_' . $auth_suffix . '@example.invalid']));
    $auth_assert(is_int($demo_student) && OI_Model::packs($demo_student) === [$demo_pack], 'Seul le pack démo explicitement marqué est accordé');
    $auth_assert(OI_Model::can_read($demo_student, $demo_fiche), 'La fiche de démonstration est effectivement accessible');
    $auth_assert(!OI_Model::can_read($demo_student, $paid_fiche), 'Une fiche d’un autre pack reste interdite');
    update_post_meta($demo_pack, 'oi_price', 'price_authConvertedDemo');
    $converted_student = $auth_create(array_replace($auth_fields, ['email' => 'auth_converted_' . $auth_suffix . '@example.invalid']));
    $auth_assert(is_int($converted_student) && OI_Model::packs($converted_student) === [], 'Un ancien pack démo devenu payant n’est pas accordé malgré son marqueur gratuit');
    $auth_assert(!OI_Model::can_read($converted_student, $demo_fiche), 'La fiche du pack démo devenu payant reste inaccessible au nouveau compte');
    delete_post_meta($demo_pack, 'oi_price');
    wp_update_post(['ID' => $demo_pack, 'post_status' => 'draft']);
    $draft_student = $auth_create(array_replace($auth_fields, ['email' => 'auth_draft_' . $auth_suffix . '@example.invalid']));
    $auth_assert(is_int($draft_student) && OI_Model::packs($draft_student) === [], 'Pack de démonstration dépublié non accordé');

    $auth_assert(OI_Auth::same_origin('https://app.example/inscription/', 'https://app.example/'), 'Origine attendue acceptée');
    $auth_assert(OI_Auth::same_origin('https://app.example:443', 'https://app.example/'), 'Port HTTPS explicite reconnu');
    $auth_assert(!OI_Auth::same_origin('https://evil.example', 'https://app.example/'), 'Origine tierce refusée');
    $auth_assert(!OI_Auth::same_origin('https://app.example.evil.example', 'https://app.example/'), 'Suffixe trompeur refusé');
    $auth_assert(!OI_Auth::same_origin('http://app.example', 'https://app.example/'), 'Repli HTTP refusé');
    $auth_assert(!OI_Auth::same_origin('https://app.example:8443', 'https://app.example/'), 'Port tiers refusé');
    $auth_assert(!OI_Auth::same_origin('null', 'https://app.example/'), 'Origine opaque refusée');

    wp_set_current_user(0);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = 'auth-test-' . $auth_suffix;
    $_SERVER['HTTP_ORIGIN'] = home_url('/');
    $_POST = ['_oi_nonce' => wp_create_nonce('oi_register')];
    $request_check = new ReflectionMethod(OI_Auth::class, 'check_request');
    $auth_assert($request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS) === '', 'Formulaire de même origine avec nonce accepté');
    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
    $auth_assert($request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS) === 'expired', 'Nonce public réutilisé depuis une origine externe refusé');
    $_SERVER['HTTP_ORIGIN'] = home_url('/');
    $_POST['_oi_nonce'] = 'forged';
    $auth_assert($request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS) === 'expired', 'Nonce forgé refusé');
    $_POST['_oi_nonce'] = wp_create_nonce('oi_register');
    $request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS);
    $request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS);
    $request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS);
    $auth_assert($request_check->invoke(null, 'oi_register', 6, HOUR_IN_SECONDS) === 'slow_down', 'Créations répétées limitées au niveau de la requête');
    echo 'Inscription et connexion : ' . $auth_checks . " assertions réussies\n";
} finally {
    $_SERVER = $auth_server;
    $_POST = $auth_post;
    wp_set_current_user($auth_current_user);
    update_option('default_role', $auth_default_role);
    if ($auth_demo_option === false) {
        delete_option('oi_registration_demo_pack');
    } else {
        update_option('oi_registration_demo_pack', $auth_demo_option);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($auth_users as $id) {
        wp_delete_user($id);
    }
    foreach ($auth_posts as $id) {
        wp_delete_post($id, true);
    }
}
