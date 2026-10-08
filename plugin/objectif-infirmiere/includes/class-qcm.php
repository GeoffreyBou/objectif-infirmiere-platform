<?php
defined('ABSPATH') || exit;
final class OI_QCM {
    public static function routes(): void {
        foreach(['/qcm-scopes'=>['GET','scopes'],'/qcm-sessions'=>['POST','start'],'/qcm-sessions/(?P<id>\d+)/submit'=>['POST','submit']] as $path=>[$method,$methodName])register_rest_route('oi/v1',$path,['methods'=>$method,'permission_callback'=>[OI_API::class,'auth'],'callback'=>[self::class,$methodName]]);
    }
    private static function eligible(): array {
        $ids=[];foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','numberposts'=>-1]) as $p)if(OI_Offer::can_access(get_current_user_id(),'QCM',$p->ID)&&OI_Revision::quiz($p->ID))$ids[]=$p->ID;return $ids;
    }
    public static function scopes(): array {
        $ids=self::eligible();$units=[];$themes=[];$fiches=[];
        foreach($ids as $id){$fiches[]=['id'=>$id,'name'=>get_the_title($id),'count'=>count(OI_Revision::quiz($id,false,200))];foreach(['oi_enseignement'=>'units','oi_theme'=>'themes'] as $tax=>$kind)foreach(wp_get_object_terms($id,$tax) as $t){$item=['id'=>$t->term_id,'name'=>$t->name];if($kind==='units')$units[$t->term_id]=$item;else $themes[$t->term_id]=$item;}}
        return ['fiche'=>$fiches,'theme'=>array_values($themes),'ue'=>array_values($units)];
    }
    public static function start(WP_REST_Request $r): array|WP_Error {
        $scope=$r['scope'];$id=absint($r['id']);$count=absint($r['count']??10);
        if(!in_array($scope,['fiche','theme','ue'],true)||!$id||$count<1||$count>40)return new WP_Error('oi_scope','Choisis une fiche, un thème ou une UE et entre 1 et 40 questions.',['status'=>400]);
        $eligible=self::eligible();$ids=$scope==='fiche'?array_values(array_intersect($eligible,[$id])):get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','post__in'=>$eligible?:[0],'numberposts'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>$scope==='theme'?'oi_theme':'oi_enseignement','terms'=>[$id]]]]);
        $pools=[];$available=0;foreach($ids as $fid){$qs=OI_Revision::quiz($fid,false,200);foreach($qs as $i=>&$q){$q['fiche_id']=$fid;$q['fiche_title']=get_the_title($fid);$q['code']=$q['code']??'legacy-'.$fid.'-'.$i;}unset($q);shuffle($qs);if($qs){$available+=count($qs);$pools[]=$qs;}}
        if(!$available)return new WP_Error('oi_empty','Aucune question accessible ici. Débloque une série de cette sélection pour commencer.',['status'=>403]);
        shuffle($pools);$questions=[];while(count($questions)<min($count,$available)){foreach($pools as &$pool){if($pool)$questions[]=array_pop($pool);if(count($questions)>=min($count,$available))break;}unset($pool);}shuffle($questions);
        $user=get_current_user_id();$label=$scope==='fiche'?get_the_title($id):get_term($id,$scope==='theme'?'oi_theme':'oi_enseignement')?->name;
        global $wpdb;$lock='oi_qcm_user_'.md5(DB_NAME.$wpdb->prefix.$user);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)',$lock))!==1)return new WP_Error('oi_busy','Réessaie dans un instant.',['status'=>409]);
        try{
            // Keep a bounded per-account history; old sessions cannot be submitted after 24h.
            $existing=get_posts(['post_type'=>'oi_quiz_session','post_status'=>'private','author'=>$user,'numberposts'=>-1,'orderby'=>'ID','order'=>'DESC']);
            foreach($existing as $i=>$p)if($i>=49)wp_delete_post($p->ID,true);
            $session=wp_insert_post(['post_type'=>'oi_quiz_session','post_status'=>'private','post_author'=>$user,'post_title'=>$label?:'Entraînement'],true);if(is_wp_error($session))throw new RuntimeException('Session impossible.');
            update_post_meta($session,'oi_session',['scope'=>$scope,'scope_id'=>$id,'created'=>time(),'questions'=>$questions]);
            return ['id'=>$session,'title'=>$label,'requested'=>$count,'available'=>$available,'quiz'=>array_map(function($q){unset($q['correct'],$q['explanation']);return $q;},$questions)];
        }catch(Throwable $e){return new WP_Error('oi_session',$e->getMessage(),['status'=>500]);}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function submit(WP_REST_Request $r): array|WP_Error {
        $id=absint($r['id']);$user=get_current_user_id();$p=get_post($id);if(!$p||$p->post_type!=='oi_quiz_session'||(int)$p->post_author!==$user)return new WP_Error('oi_session','Session introuvable.',['status'=>404]);
        global $wpdb;$lock='oi_qcm_user_'.md5(DB_NAME.$wpdb->prefix.$user);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)',$lock))!==1)return new WP_Error('oi_busy','Réessaie.',['status'=>409]);
        try{
            wp_cache_delete($id,'post_meta');$session=get_post_meta($id,'oi_session',true);if(!$session||time()-$session['created']>DAY_IN_SECONDS)throw new RuntimeException('Session expirée. Lance un nouvel entraînement.');
            foreach($session['questions'] as $q)if(!OI_Offer::can_access($user,'QCM',$q['fiche_id']))throw new RuntimeException('Ton accès à cette série a changé. Relance un entraînement.');
            $result=get_post_meta($id,'oi_result',true);if($result)return $result;
            $answers=$r['answers'];if(!is_array($answers)||count($answers)!==count($session['questions']))throw new RuntimeException('Réponds à toutes les questions avant de corriger.');$score=0;$corrections=[];
            foreach($session['questions'] as $i=>$q){$answer=$answers[$i]??null;if(!is_array($answer)||!$answer||count($answer)>count($q['options']))throw new RuntimeException('Réponse manquante ou invalide à la question '.($i+1).'.');foreach($answer as $a)if(!is_int($a)||$a<0||$a>=count($q['options']))throw new RuntimeException('Choix invalide.');$answer=array_values(array_unique($answer));sort($answer);$correct=$answer===$q['correct'];if($correct)$score++;$corrections[]=['correct'=>$correct,'answers'=>$q['correct'],'explanation'=>$q['explanation'],'fiche_id'=>$q['fiche_id'],'fiche_title'=>$q['fiche_title']];}
            $result=['score'=>$score,'total'=>count($corrections),'corrections'=>$corrections];$wpdb->query('START TRANSACTION');update_post_meta($id,'oi_result',$result);wp_cache_delete($user,'user_meta');update_user_meta($user,'oi_qcm_completed',(int)get_user_meta($user,'oi_qcm_completed',true)+1);$wpdb->query('COMMIT');return $result;
        }catch(Throwable $e){$wpdb->query('ROLLBACK');return new WP_Error('oi_answers',$e->getMessage(),['status'=>400]);}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
}
