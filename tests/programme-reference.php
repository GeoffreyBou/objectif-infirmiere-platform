<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$n=0;$check=function($ok,$message)use(&$n){if(!$ok)throw new RuntimeException($message);$n++;};
function sum_theme_count($data){return array_sum(array_map(fn($u)=>count($u['themes']),$data));}
$data=json_decode(file_get_contents(OI_DIR.'data/programme-2026.json'),true);$listing=OI_Imports::listing();$check(count($data)===15&&count($listing['themes'])===sum_theme_count($data),'Référentiel incomplet');
$counts=['A1'=>2,'A2'=>3,'B1'=>17,'B2'=>4,'B3'=>12,'B4'=>5,'C1'=>6,'C2'=>6,'D1'=>3,'D2'=>3,'D3'=>3,'D4'=>4,'E1'=>4,'E2'=>0,'E3'=>3];
foreach($data as $u){$unit=(int)get_term_by('slug','programme-2026-'.strtolower($u['code']),'oi_enseignement')->term_id;$check(count($u['themes'])===$counts[$u['code']],'Nombre thèmes incorrect');foreach($u['themes'] as $theme){$term=get_term_by('name',$u['code'].' - '.$theme['code'].' '.$theme['name'],'oi_theme');$check($term&&$term->name===$u['code'].' - '.$theme['code'].' '.$theme['name'],'Thème non conforme : '.$u['code'].' '.$theme['name'].' obtenu '.($term?$term->name:'absent'));$check(!get_term_meta($term->term_id,'oi_programme_legacy',true),'Thème de référence marqué ancien');$check(OI_Drive::classification([$u['code'].' - UE',$theme['code'].' - '.$theme['name']])===[$unit,(int)$term->term_id],'Classement exact incorrect');}}
$before=array_column(OI_Imports::listing()['themes'],'id');update_option('oi_curriculum_version',2);OI_Curriculum::install();$check(array_column(OI_Imports::listing()['themes'],'id')===$before,'Migration non idempotente');
echo "$n assertions, 15 UE, thèmes : programme ou dossiers Drive.\n";
