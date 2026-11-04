<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$checks=0;$users=[];$posts=[];
function step_req($route,$data){$r=new WP_REST_Request('POST','/oi/v1/'.$route);$r->set_body_params($data);return rest_do_request($r);}
$check=function($ok,$why)use(&$checks){if(!$ok)throw new RuntimeException($why);$checks++;};
try{
 foreach(['administrator','oi_etudiant'] as $role){$id=wp_insert_user(['user_login'=>'qcm_step_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>$role]);$users[]=$id;}
 [$admin,$other]=$users;wp_set_current_user($admin);$fiche=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_author'=>$admin,'post_title'=>'QCM séquentiel local']);$posts[]=$fiche;
 $bank=[];foreach([[0],[0,2],[1]] as $i=>$correct)$bank[]=['code'=>'B1-TST-991-Q00'.($i+1),'question'=>'Question locale '.($i+1),'options'=>['A','B','C'],'correct'=>$correct,'explanation'=>'Explication locale '.($i+1)];update_post_meta($fiche,'oi_quiz',$bank);
 wp_set_current_user(0);$check(step_req('qcm-sessions/1/answer',['index'=>0,'answers'=>[0]])->get_status()===401,'Authentication required');wp_set_current_user($admin);
 $start=step_req('qcm-sessions',['scope'=>'fiche','id'=>$fiche,'count'=>20,'stepwise'=>true])->get_data();$session=$start['id'];$snapshot=get_post_meta($session,'oi_session',true);$qs=$snapshot['questions'];
 $check(count($start['quiz'])===3&&count(array_unique(array_column($start['quiz'],'code')))===3,'Random selection bounded without duplicates');$check(!str_contains(wp_json_encode($start),'"correct"')&&!str_contains(wp_json_encode($start),'"explanation"'),'No future answer keys at start');
 wp_set_current_user($other);$check(step_req("qcm-sessions/$session/answer",['index'=>0,'answers'=>[0]])->get_status()===404,'Other account cannot answer');wp_set_current_user($admin);
 foreach([['index'=>1,'answers'=>[0]],['index'=>-1,'answers'=>[0]],['index'=>'0','answers'=>[0]],['index'=>0,'answers'=>[]],['index'=>0,'answers'=>['0']],['index'=>0,'answers'=>[99]]] as $bad)$check(step_req("qcm-sessions/$session/answer",$bad)->get_status()===400,'Invalid or out of order answer rejected');
 $check(step_req("qcm-sessions/$session/submit",['answers'=>array_column($qs,'correct')])->get_status()===400,'Legacy batch cannot bypass sequential answers');
 $initial=(int)get_user_meta($admin,'oi_qcm_completed',true);$credits=OI_Credits::balances($admin);$wrong=array_values(array_diff([0,1,2],$qs[0]['correct']))[0];
 $first=step_req("qcm-sessions/$session/answer",['index'=>0,'answers'=>[$wrong]])->get_data();$check(!$first['correction']['correct']&&$first['correction']['selected']===[$wrong]&&$first['result']===null&&$first['answered']===1,'Immediate correction only for answered question');$check($first['correction']['answers']===$qs[0]['correct']&&!isset($first['quiz']),'No future corrections returned');
 $retry=step_req("qcm-sessions/$session/answer",['index'=>0,'answers'=>$qs[0]['correct']])->get_data();$check($retry===$first,'Retry cannot change a revealed answer');$check((int)get_user_meta($admin,'oi_qcm_completed',true)===$initial,'Partial session does not count as completed');
 $changed=$bank;foreach($changed as &$q){$q['correct']=[2];$q['explanation']='MODIFIED';}unset($q);update_post_meta($fiche,'oi_quiz',$changed);
 $second=step_req("qcm-sessions/$session/answer",['index'=>1,'answers'=>$qs[1]['correct']])->get_data();$check($second['correction']['correct']&&$second['correction']['explanation']===$qs[1]['explanation'],'Session keeps original questions and explanation');
 $final=step_req("qcm-sessions/$session/answer",['index'=>2,'answers'=>$qs[2]['correct']])->get_data();$check($final['completed']&&$final['result']['score']===2&&$final['result']['total']===3&&count($final['result']['corrections'])===3,'Final score and complete review returned');$check((int)get_user_meta($admin,'oi_qcm_completed',true)===$initial+1,'Completion counted once');
 $again=step_req("qcm-sessions/$session/answer",['index'=>2,'answers'=>[2]])->get_data();$check($again===$final&&(int)get_user_meta($admin,'oi_qcm_completed',true)===$initial+1,'Final answer retry idempotent');$check(OI_Credits::balances($admin)===$credits,'No extra credit consumption');
 $expired=$snapshot;$expired['created']=time()-DAY_IN_SECONDS-1;update_post_meta($session,'oi_session',$expired);$check(step_req("qcm-sessions/$session/answer",['index'=>0,'answers'=>[0]])->get_status()===400,'Expired session rejected');
 update_post_meta($session,'oi_session',$snapshot);wp_update_post(['ID'=>$fiche,'post_status'=>'draft']);$check(step_req("qcm-sessions/$session/answer",['index'=>0,'answers'=>[0]])->get_status()===400,'Withdrawn fiche revokes access even for retry');
 echo "QCM question par question : $checks assertions réussies\n";
}finally{foreach($users as $user){foreach(get_posts(['post_type'=>['oi_fiche','oi_quiz_session'],'post_status'=>['publish','draft','private'],'author'=>$user,'fields'=>'ids','numberposts'=>-1]) as $id)wp_delete_post($id,true);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);}}
