<?php
defined('ABSPATH') || exit;
final class OI_Revision {
    public static function register(): void {
        foreach (['state'=>'state_endpoint','quiz'=>'quiz_endpoint'] as $route=>$callback) {
            register_rest_route('oi/v1','/fiches/(?P<id>\d+)/'.$route,['methods'=>'POST','permission_callback'=>[OI_API::class,'auth'],'callback'=>[self::class,$callback]]);
        }
    }
    public static function all(int $user): array {
        $state=get_user_meta($user,'oi_revision',true);
        return is_array($state)?$state:[];
    }
    public static function state(int $user,int $id): array {
        return self::all($user)[$id]??['favorite'=>false,'revised'=>false];
    }
    private static function write(int $user,int $id,array $values): bool {
        global $wpdb;
        $lock='oi_state_'.md5(DB_NAME.$wpdb->prefix.$user);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)',$lock))!==1) return false;
        try {
            wp_cache_delete($user,'user_meta');
            $state=self::all($user);$state[$id]=array_merge($state[$id]??[],$values);
            update_user_meta($user,'oi_revision',$state);
            return true;
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }
    public static function viewed(int $user,int $id): void { self::write($user,$id,['viewed_at'=>current_time('mysql',true),'viewed_order'=>microtime(true)]); }
    public static function state_endpoint(WP_REST_Request $r): array|WP_Error {
        $id=absint($r['id']);$user=get_current_user_id();
        if(!OI_Model::can_read($user,$id)) return new WP_Error('oi_forbidden','Cette fiche ne fait pas partie de vos accès.',['status'=>403]);
        if(!in_array($r['field'],['favorite','revised'],true) || !is_bool($r['value'])) return new WP_Error('oi_state','État invalide.',['status'=>400]);
        if(!self::write($user,$id,[$r['field']=>$r['value']])) return new WP_Error('oi_busy','Veuillez réessayer.',['status'=>503]);
        return self::state($user,$id);
    }
    public static function quiz(int $id,bool $public=true): array {
        $quiz=get_post_meta($id,'oi_quiz',true);
        if(!is_array($quiz)) return [];
        $result=[];
        foreach(array_slice($quiz,0,20) as $question) {
            if(!is_array($question) || !is_string($question['question']??null) || !is_array($question['options']??null) || !is_array($question['correct']??null)) continue;
            $options=array_values(array_map('sanitize_text_field',array_slice($question['options'],0,8)));
            $correct=array_values(array_unique(array_map('intval',$question['correct'])));sort($correct);
            if(count($options)<2 || !$correct || min($correct)<0 || max($correct)>=count($options)) continue;
            $q=['question'=>sanitize_text_field($question['question']),'options'=>$options,'multiple'=>count($correct)>1];
            if(!$public) {$q['correct']=$correct;$q['explanation']=sanitize_textarea_field($question['explanation']??'');}
            $result[]=$q;
        }
        return $result;
    }
    public static function quiz_endpoint(WP_REST_Request $r): array|WP_Error {
        $id=absint($r['id']);$user=get_current_user_id();
        if(!OI_Offer::can_access($user,'QCM',$id)) return new WP_Error('oi_forbidden','Cette fiche ne fait pas partie de vos accès.',['status'=>403]);
        $quiz=self::quiz($id,false);$answers=$r['answers'];
        if(!$quiz || !is_array($answers) || count($answers)!==count($quiz)) return new WP_Error('oi_quiz','Réponses invalides.',['status'=>400]);
        $score=0;$corrections=[];
        foreach($quiz as $i=>$q) {
            if(!isset($answers[$i]) || !is_array($answers[$i]) || count($answers[$i])>count($q['options'])) return new WP_Error('oi_quiz','Choix invalides.',['status'=>400]);
            foreach($answers[$i] as $a) if(!is_int($a) || $a<0 || $a>=count($q['options'])) return new WP_Error('oi_quiz','Choix invalide.',['status'=>400]);
            $answer=array_values(array_unique($answers[$i]));sort($answer);$correct=$answer===$q['correct'];
            if($correct) $score++;
            $corrections[]=['correct'=>$correct,'answers'=>$q['correct'],'explanation'=>$q['explanation']];
        }
        if(!self::write($user,$id,['quiz_score'=>$score,'quiz_total'=>count($quiz),'quiz_at'=>current_time('mysql',true)])) return new WP_Error('oi_busy','Veuillez réessayer.',['status'=>503]);
        return ['score'=>$score,'total'=>count($quiz),'corrections'=>$corrections];
    }
    public static function progress(int $user): array {
        $fiches=OI_Model::allowed($user);$allowed=OI_Model::ids(array_merge($fiches,OI_Credits::unlocked($user,'QCM')));$state=self::all($user);$revised=0;$viewed=0;$quizzes=0;
        foreach($allowed as $id) {$s=$state[$id]??[];if(in_array($id,$fiches,true)&&!empty($s['revised']))$revised++;if(in_array($id,$fiches,true)&&!empty($s['viewed_at']))$viewed++;if(!empty($s['quiz_at']))$quizzes++;}
        return ['total'=>count($fiches),'revised'=>$revised,'viewed'=>$viewed,'quizzes'=>$quizzes];
    }
    public static function watermark(int $user): string {
        if(!get_option('oi_watermark',true)) return '';
        $u=get_user_by('id',$user);
        if(!$u) return '';
        $name=mb_substr($u->display_name,0,1).'…';
        return 'OBJECTIF INFIRMIÈRE · '.$name.' · Compte #'.$user;
    }
}
