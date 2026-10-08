<?php
defined('ABSPATH') || exit;
final class OI_Metrics {
    public const EVENTS=['home_view','premium_view','quiz_started','checkout_started'];
    public static function migrate(): void {
        global $wpdb;$c=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_metrics (
            day date NOT NULL,
            event varchar(32) NOT NULL,
            user_id bigint unsigned NOT NULL DEFAULT 0,
            hits bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (day,event,user_id)
        ) ENGINE=InnoDB $c;");
    }
    public static function count(string $event,int $user=0): void {
        if(!in_array($event,self::EVENTS,true)||($_SERVER['HTTP_DNT']??'')==='1'||(defined('WP_CLI')&&WP_CLI))return;
        if($user && user_can($user,'manage_options'))return;
        global $wpdb;
        $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}oi_metrics (day,event,user_id,hits) VALUES (%s,%s,%d,1) ON DUPLICATE KEY UPDATE hits=hits+1",gmdate('Y-m-d'),$event,$user));
    }
    public static function register(): void {
        register_rest_route('oi/v1','/events',['methods'=>'POST','permission_callback'=>[OI_API::class,'auth'],'callback'=>function(WP_REST_Request $r){if($r['event']!=='premium_view')return new WP_Error('oi_event','Événement invalide.',['status'=>400]);self::count('premium_view',get_current_user_id());return ['recorded'=>true];}]);
    }
    public static function report(): array {
        global $wpdb;$prefix=$wpdb->prefix;
        $scalar=fn($sql)=>(float)$wpdb->get_var($sql);
        $students="SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='{$prefix}capabilities' AND meta_value LIKE '%oi_etudiant%'";
        $verifiedStudents="SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='oi_email_verified' AND user_id IN ($students)";
        $verifiedBuyers=$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_payments WHERE pack_id=".OI_Offer::pack()." AND user_id IN ($verifiedStudents)");
        $verified=$scalar("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key='oi_email_verified' AND user_id IN ($students)");
        $buyers=$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_payments WHERE pack_id=".OI_Offer::pack()." AND user_id IN ($students)");
        $spent=$scalar("SELECT COALESCE(SUM(-delta),0) FROM {$prefix}oi_credit_operations WHERE state='committed' AND delta<0 AND (op_key LIKE 'unlock:%' OR op_key LIKE 'ai:%') AND user_id IN ($students)");
        $report=['inscriptions_gratuites'=>$scalar("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key='oi_signup_source' AND meta_value='public_form'"),'adresses_verifiees'=>$verified,'utilisateurs_fiche_debloquee'=>$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_unlocks WHERE kind='FICHE' AND user_id IN ($students)"),'utilisateurs_ia'=>$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_ai_requests WHERE state='success' AND user_id IN ($students)"),'credits_consommes_moyenne'=>$verified?round($spent/$verified,2):0,'achats_premium_confirmes'=>$scalar("SELECT COUNT(*) FROM {$prefix}oi_payments WHERE pack_id=".OI_Offer::pack()." AND user_id IN ($students)"),'acheteurs_premium'=>$buyers,'conversion_gratuit_payant_pct'=>$verified?round(100*$verifiedBuyers/$verified,2):0];
        foreach(self::EVENTS as $event)$report[$event]=$scalar($wpdb->prepare("SELECT COALESCE(SUM(hits),0) FROM {$prefix}oi_metrics WHERE event=%s",$event));
        $report['utilisateurs_qcm_lance']=$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_metrics WHERE event='quiz_started' AND user_id>0");
        foreach(OI_Credits::TYPES as $kind){
            $total=$scalar($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}oi_credit_grants WHERE kind=%s AND source_key='welcome' AND user_id IN ($students)",$kind));
            $empty=$scalar($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}oi_credit_grants w WHERE w.kind=%s AND w.source_key='welcome' AND w.user_id IN ($students) AND NOT EXISTS (SELECT 1 FROM {$prefix}oi_credit_grants g WHERE g.user_id=w.user_id AND g.kind=w.kind AND g.remaining>0 AND (g.expires_at IS NULL OR g.expires_at>UTC_TIMESTAMP()))",$kind));
            $report['solde_epuise_'.strtolower($kind).'_pct']=$total?round(100*$empty/$total,2):0;
        }
        $premiumUsers=$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_ai_requests WHERE premium=1 AND user_id IN ($students)");
        $report['ia_premium_questions_moyenne']=$premiumUsers?round($scalar("SELECT COUNT(*) FROM {$prefix}oi_ai_requests WHERE premium=1 AND state='success' AND user_id IN ($students)")/$premiumUsers,2):0;
        $aiUsers=$scalar("SELECT COUNT(DISTINCT user_id) FROM {$prefix}oi_ai_requests WHERE user_id IN ($students)");
        $unknown=$scalar("SELECT COUNT(*) FROM {$prefix}oi_ai_requests WHERE cost_usd IS NULL AND user_id IN ($students)");
        $report['cout_tokens_usd_moyen_etudiant']=$unknown?null:($aiUsers?round($scalar("SELECT COALESCE(SUM(cost_usd),0) FROM {$prefix}oi_ai_requests WHERE user_id IN ($students)")/$aiUsers,6):0);
        $report['cout_tokens_non_estime_requetes']=$unknown;
        return $report;
    }
}
