<?php
$GLOBALS["count"] = 0;
function oi_assert($condition, $message) {
    global $count;
    if (!$condition) { throw new RuntimeException($message); }
    $count++;
}
$suffix = wp_generate_password(10, false);
$student = wp_insert_user(['user_login' => 'test_' . $suffix, 'user_pass' => wp_generate_password(), 'role' => 'oi_etudiant']);
$other = wp_insert_user(['user_login' => 'other_' . $suffix, 'user_pass' => wp_generate_password(), 'role' => 'oi_etudiant']);
$fiche = wp_insert_post(['post_type' => 'oi_fiche', 'post_status' => 'publish', 'post_title' => 'TEST PRIVATE', 'post_content' => 'SECRET_TEST_CONTENT']);
$draft = wp_insert_post(['post_type' => 'oi_fiche', 'post_status' => 'draft', 'post_title' => 'TEST DRAFT']);
$pack = wp_insert_post(['post_type' => 'oi_pack', 'post_status' => 'publish', 'post_title' => 'TEST PACK']);
try {
    oi_assert(!is_wp_error($student), 'Création étudiant');
    oi_assert(!user_can($student, 'edit_posts'), 'Étudiant ne peut pas éditer');
    oi_assert(!user_can($student, 'manage_options'), 'Étudiant ne peut pas administrer');
    oi_assert(!OI_Model::can_read(0, $fiche), 'Anonyme interdit');
    oi_assert(!OI_Model::can_read($student, $fiche), 'Sans pack interdit');
    update_post_meta($pack, 'oi_fiches', [$fiche, $draft]);
    update_user_meta($student, 'oi_packs', [$pack]);
    oi_assert(OI_Model::can_read($student, $fiche), 'Pack accorde fiche publiée');
    oi_assert(!OI_Model::can_read($student, $draft), 'Brouillon interdit');
    oi_assert(!OI_Model::can_read($other, $fiche), 'Autre étudiant interdit');
    wp_update_post(['ID' => $pack, 'post_status' => 'draft']);
    oi_assert(!OI_Model::can_read($student, $fiche), 'Pack dépublié révoque');
    oi_assert(!get_post_type_object('oi_fiche')->show_in_rest, 'REST natif fermé');
    oi_assert(!get_post_type_object('oi_fiche')->publicly_queryable, 'URL publique fermée');
    foreach (OI_Model::TAX as $tax) { oi_assert(taxonomy_exists('oi_' . $tax), 'Taxonomie ' . $tax); }
    echo "Socle : ".$GLOBALS["count"]." assertions réussies\n";
} finally {
    foreach ([$pack, $fiche, $draft] as $id) { wp_delete_post($id, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($student); wp_delete_user($other);
}
