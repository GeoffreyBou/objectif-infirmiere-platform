<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$GLOBALS['wb_checks']=0;function wb_check($ok,$message){if(!$ok)throw new RuntimeException($message);$GLOBALS['wb_checks']++;}
function wb_file(array $rows): string {
 $file=tempnam(sys_get_temp_dir(),'qcm-wb');$z=new ZipArchive();$z->open($file,ZipArchive::OVERWRITE);
 $z->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Questions" sheetId="1" r:id="r1"/></sheets></workbook>');
 $z->addFromString('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="r1" Target="worksheets/sheet1.xml"/></Relationships>');
 $keys=['code_question','enonce','a','b','bonnes_reponses','explication','statut'];$xml='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
 foreach(array_merge([array_combine($keys,$keys)],$rows) as $i=>$row){$xml.='<row r="'.($i+1).'">';foreach($keys as $j=>$key)$xml.='<c r="'.chr(65+$j).($i+1).'" t="inlineStr"><is><t>'.htmlspecialchars($row[$key]??'',ENT_XML1).'</t></is></c>';$xml.='</row>';}
 $z->addFromString('xl/worksheets/sheet1.xml',$xml.'</sheetData></worksheet>');$z->close();return $file;
}
$admin=wp_insert_user(['user_login'=>'qcm_wb_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'administrator']);wp_set_current_user($admin);$files=[];$fiches=[];
try{
 foreach(['B1-ZZM-981','B1-ZZM-982'] as $code){$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Workbook '.$code,'post_author'=>$admin]);$fiches[]=$id;update_post_meta($id,'oi_source_code',$code);}
 $rows=[];foreach(['B1-ZZM-981','B1-ZZM-982'] as $code)for($i=1;$i<=120;$i++)$rows[]=['code_question'=>$code.'-Q'.str_pad($i,3,'0',STR_PAD_LEFT),'enonce'=>'Question '.$i,'a'=>'Réponse A','b'=>'Réponse B','bonnes_reponses'=>'A','explication'=>'Explication de test','statut'=>'actif'];
 $file=wb_file($rows);$files[]=$file;wb_check(count(OI_Xlsx::read($file))===240,'Theme workbook accepts more than 200 total questions');
 $r=OI_QCM_Imports::stage_workbook($file,'B1-03_Systeme-nerveux_QCM.xlsx');wb_check($r['ok']&&$r['prepared']===2&&count($r['series'])===2,'One preparation per question-code fiche');
 foreach($r['series'] as $i=>$series){wb_check($series['count']===120&&(int)get_post($series['id'])->post_parent===$fiches[$i],'Questions assigned to correct fiche');}
 $again=OI_QCM_Imports::stage_workbook($file,'B1-03_Systeme-nerveux_QCM.xlsx');wb_check(array_column($again['series'],'id')===array_column($r['series'],'id'),'Retry reuses existing drafts');
 foreach($r['series'] as $series){$req=new WP_REST_Request('POST');$req->set_param('id',$series['id']);$preview=OI_QCM_Imports::preview($req);wb_check(!is_wp_error($preview)&&$preview['incoming']===120,'Grouped preparation can be previewed');OI_QCM_Imports::publish_one($series['id'],$preview['token']);}
 $again=OI_QCM_Imports::stage_workbook($file,'B1-03_Renomme_QCM.xlsx');wb_check($again['prepared']===0&&$again['unchanged_count']===2,'Published workbook is idempotent after rename');
 $rows[0]['explication']='Explication améliorée';$changed=wb_file($rows);$files[]=$changed;$r=OI_QCM_Imports::stage_workbook($changed,'B1-03_Renomme_QCM.xlsx');wb_check($r['prepared']===1&&$r['unchanged_count']===1,'Changing one fiche does not restage other fiches');
 $rows[120]['bonnes_reponses']='Z';$invalid=wb_file($rows);$files[]=$invalid;$r=OI_QCM_Imports::stage_workbook($invalid,'B1-03_Renomme_QCM.xlsx');wb_check(!$r['ok']&&$r['prepared']===1&&count($r['errors'])===1&&str_contains($r['errors'][0]['message'],'Ligne 122'),'Partial failures keep valid series and identify bad row');
 $rows[120]['bonnes_reponses']='A';wp_update_post(['ID'=>$fiches[1],'post_status'=>'draft']);$r=OI_QCM_Imports::stage_workbook($file,'B1-03_Renomme_QCM.xlsx');wb_check(!$r['ok']&&$r['unchanged_count']===1&&str_contains($r['errors'][0]['message'],'Publie d’abord'),'Unpublished fiche reports error without wrong association');wp_update_post(['ID'=>$fiches[1],'post_status'=>'publish']);
 $blocked=false;try{OI_QCM_Imports::stage_workbook($file,'B1-ZZM-981_Une-fiche_qcm.xlsx');}catch(Throwable $e){$blocked=str_contains($e->getMessage(),'ne correspond pas');}wb_check($blocked,'Single-fiche filename cannot silently target other fiches');
 $tooMany=[];for($i=1;$i<=201;$i++)$tooMany[]=array_replace($rows[0],['code_question'=>'B1-ZZM-981-Q'.str_pad($i,3,'0',STR_PAD_LEFT)]);$file201=wb_file($tooMany);$files[]=$file201;$r=OI_QCM_Imports::stage_workbook($file201,'B1-03_Limite_QCM.xlsx');wb_check(!$r['ok']&&$r['prepared']===0&&str_contains($r['errors'][0]['message'],'200 questions maximum'),'200 questions per fiche still enforced');
 $tooMany=array_fill(0,2001,$rows[0]);$huge=wb_file($tooMany);$files[]=$huge;$blocked=false;try{OI_Xlsx::read($huge);}catch(Throwable $e){$blocked=str_contains($e->getMessage(),'2 000');}wb_check($blocked,'Workbook limit remains bounded');
 echo 'Classeurs QCM multi-fiches : '.$GLOBALS['wb_checks']." assertions réussies\n";
}finally{foreach(get_posts(['post_type'=>['oi_fiche','oi_qcm_import','oi_qcm_history'],'post_status'=>['publish','draft','private'],'author'=>$admin,'fields'=>'ids','numberposts'=>-1]) as $id)wp_delete_post($id,true);foreach($files as $file)unlink($file);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($admin);}
