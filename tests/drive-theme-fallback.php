<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$n=0;$check=function($v,$m)use(&$n){if(!$v)throw new RuntimeException($m);$n++;};
$c2=(int)get_term_by('slug','programme-2026-c2','oi_enseignement')->term_id;
$path=['C2 - Sante environnementale et transition ecologique','01 - Fondements politiques et acteurs','v0.3'];
[$u,$t]=OI_Drive::classification($path,'Organisation des politiques de santé environnementale en France et dans le monde');
$check($u===$c2&&$t>0&&get_term_meta($t,'oi_drive_theme',true),'Dossier C2 non reconnu');
$old=get_term_by('slug','programme-2026-c2-03','oi_theme');
$created=false;if(!$old){$id=wp_insert_term('Ancien thème C2 fixture','oi_theme');$old=get_term($id['term_id'],'oi_theme');update_term_meta($old->term_id,'oi_programme_unit',$c2);update_term_meta($old->term_id,'oi_programme_legacy',1);$created=true;}
$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Fixture migration Drive']);$draft=wp_insert_post(['post_type'=>'oi_import','post_status'=>'draft','post_title'=>'Fixture migration Drive']);$new=0;
try{
 wp_set_object_terms($id,[$c2],'oi_enseignement');wp_set_object_terms($id,[(int)$old->term_id],'oi_theme');
 $check(OI_Curriculum::migrate_drive_fiche($id,$u,$t),'Migration non réalisée');$check(wp_get_object_terms($id,'oi_theme',['fields'=>'ids'])===[$t],'Mauvais thème publié');$check(get_post_meta($id,'oi_before_drive_themes',true)['themes']===[(int)$old->term_id],'Ancien classement non conservé');
 $check(!OI_Curriculum::migrate_drive_fiche($id,$u,$t),'Migration non idempotente');
 update_post_meta($draft,'oi_source',['path_parts'=>$path]);update_post_meta($draft,'oi_unit',$c2);update_post_meta($draft,'oi_theme',$old->term_id);
 $blocked=false;try{OI_Imports::publish_one($draft,'obsolete-token');}catch(Throwable $e){$blocked=str_contains($e->getMessage(),'classement a changé');}$check($blocked,'Publication de thème retiré non bloquée');
 $rows=array_values(array_filter(OI_Imports::listing()['stages'],fn($s)=>$s['id']===$draft));$check($rows[0]['theme']===$t,'Préparation non rapprochée');
 OI_Curriculum::sync_drive_folders([['E2 - UE','99 - Dossier vide fixture'],['A1 - UE','99 - Interdit fixture'],['E2 - UE','98 - Archives fixture','v0.3']]);
 $new=get_term_by('name','E2 - 99 Dossier vide fixture','oi_theme');$check((bool)$new,'Dossier vide ignoré');$check(!get_term_by('name','A1 - 99 Interdit fixture','oi_theme'),'UE à groupes contaminée');$check(!get_term_by('name','E2 - 98 Archives fixture','oi_theme'),'Chemin imbriqué accepté');
 $check(OI_Drive::classification(['C2 - UE','01 - Libellé inconnu'])===[$c2,0],'Rapprochement au seul numéro');
}finally{wp_delete_post($id,true);wp_delete_post($draft,true);if($new)wp_delete_term($new->term_id,'oi_theme');if($created)wp_delete_term($old->term_id,'oi_theme');}
echo "$n assertions : thèmes Drive, migration et dossiers vides.\n";
