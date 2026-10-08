<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Démo locale uniquement.');
$pack=(int)get_option('oi_premium_pack');
if(!$pack||get_post_type($pack)!=='oi_pack'){
 $pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Pack Premium Objectif Infirmière','post_content'=>'Fiches et QCM IDE du pack, 100 crédits IA. Paiement unique de 59 €. Contenus de démonstration à valider avant commercialisation.']);
 update_option('oi_premium_pack',$pack);
 update_post_meta($pack,'oi_fiches',OI_Model::ids(get_post_meta((int)get_option('oi_demo_pack'),'oi_fiches',true)));
}
echo 'Pack Premium local configuré : '.$pack.' (prix Stripe à vérifier séparément).'."\n";
