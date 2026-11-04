<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$args=['setup'];ob_start();include __DIR__.'/browser-qcm.php';$c=json_decode(ob_get_clean(),true);
$questions=[];for($i=1;$i<=20;$i++)$questions[]=['code'=>'B1-TST-981-Q'.str_pad($i,3,'0',STR_PAD_LEFT),'question'=>'[Facile] Quel repère correspond à la situation '.$i.' ?','options'=>['Observer la situation','Reprendre le repère du cours','Relier les informations','Ignorer les informations'],'correct'=>$i%2?[0,2]:[1],'explanation'=>'Explication du repère '.$i.' : la correction permet de relier les observations aux notions du cours.'];
update_post_meta($c['fiches'][0],'oi_quiz',$questions);echo wp_json_encode($c);
