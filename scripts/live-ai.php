<?php
// Explicit, billable live API validation, local development only.
if (!in_array(wp_parse_url(get_option('siteurl'),PHP_URL_HOST),['127.0.0.1','localhost'],true)) throw new RuntimeException('Validation réservée au développement local.');
if (!OI_Stripe::secret('OI_OPENAI_API_KEY')) throw new RuntimeException('Clé OpenAI absente.');
$store=get_option('oi_vector_store','');
if (!$store) {
    $created=OI_AI::request('POST','vector_stores',['name'=>'Objectif Infirmière — Démonstration locale','expires_after'=>['anchor'=>'last_active_at','days'=>7]]);
    if (is_wp_error($created) || empty($created['id'])) throw new RuntimeException('Création vector store impossible : '.(is_wp_error($created)?wp_json_encode($created->get_error_data()):'réponse invalide'));
    $store=$created['id'];update_option('oi_vector_store',$store);update_option('oi_local_vector_store_created',true);
    echo "Vector Store local créé, expiration après 7 jours d’inactivité.\n";
}
$pack=(int)get_option('oi_demo_pack');$ids=OI_Model::ids(get_post_meta($pack,'oi_fiches',true));
foreach($ids as $id) { OI_AI::sync($id); echo 'Fiche '.$id.' : '.get_post_meta($id,'oi_ai_status',true)."\n"; }
