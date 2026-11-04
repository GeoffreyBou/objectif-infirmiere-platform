<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$n=0;$check=function($v,$m)use(&$n){if(!$v)throw new RuntimeException($m);$n++;};
OI_Curriculum::install();
$u=(int)get_term_by('slug','programme-2026-a1','oi_enseignement')->term_id;$t=(int)get_term_by('slug','programme-2026-a1-01','oi_theme')->term_id;
$check(OI_Drive::classification(['A1 - UE','01 - Profession et sciences infirmières','v0.3'],'Construction du projet de soins.')===[$u,$t],'Title overrode precise folder');
$check(OI_Drive::classification(['A1 - UE','99 - Inconnu'],'Titre reformulé libre')===[$u,0],'Unknown folder guessed');
[$pu,$pt]=OI_Drive::classification(['PARCOURS IFSI - Validation du diplome','v0.3 - A RELIRE']);$check($pu&&$pt&&get_term($pu)->name==='Parcours IFSI','Transversal catalogue missing');
$check((int)get_term_meta($pt,'oi_programme_unit',true)===$pu,'Transversal theme parent');
[$eu,$et]=OI_Drive::classification(['E3 - Methodes','04 - Consolidation locale - optionnel','v0.3']);$check($eu&&$et&&get_term_meta($et,'oi_editorial_extension',true),'E3 editorial complement absent');
$check(OI_Drive::editorial('Charte_Fiches_Objectif-Infirmiere_v0.1.docx',[]),'Root charter not excluded');
$check(OI_Drive::editorial('Programme_IFSI_2026_Objectif-Infirmiere.docx',[]),'Programme source not excluded');
$check(!OI_Drive::editorial('B1-NEU-001_AVC.docx',['B1 - UE','03 - Système nerveux']),'Student fiche excluded');
$check(OI_Drive::editorial('consignes.docx',['Documents éditoriaux']),'Editorial folder not excluded');
$files=[['id'=>'charte','name'=>'Charte_Fiches_v0.1.docx','path'=>[]],['id'=>'fiche','name'=>'B1-NEU-001_AVC.docx','path'=>['B1 - UE','03 - Système nerveux']]];$c=OI_Drive::candidates($files);$check(count($c)===1&&$c[0]['id']==='fiche','Editorial Word present in candidates');
$before=array_column(OI_Imports::listing()['themes'],'id');OI_Curriculum::install();$check(array_column(OI_Imports::listing()['themes'],'id')===$before,'Extension installation not idempotent');
echo "$n assertions : programme folders, title fallback, complementary spaces and editorial filtering.\n";
