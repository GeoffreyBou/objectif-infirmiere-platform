<?php
defined('ABSPATH') || exit;
final class OI_AI_Journal {
    public static function migrate(): void {
        global $wpdb;$c=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_ai_requests (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint unsigned NOT NULL,
            request_key varchar(36) NOT NULL,
            model varchar(100) NOT NULL,
            state varchar(20) NOT NULL,
            source_key varchar(140) NOT NULL,
            premium tinyint NOT NULL DEFAULT 0,
            input_tokens bigint unsigned NOT NULL DEFAULT 0,
            output_tokens bigint unsigned NOT NULL DEFAULT 0,
            cost_usd decimal(18,8) DEFAULT NULL,
            question text NOT NULL,
            answer longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request (user_id,request_key),
            KEY usage_date (created_at,user_id)
        ) ENGINE=InnoDB $c;");
    }
    public static function register(): void {
        add_action('oi_purge_ai_texts',[self::class,'purge']);
        add_action('init',function(){if(!wp_next_scheduled('oi_purge_ai_texts'))wp_schedule_event(time()+HOUR_IN_SECONDS,'hourly','oi_purge_ai_texts');});
        add_action('update_option_oi_ai_retention_days',[self::class,'purge']);
    }
    public static function purge(): void {
        global $wpdb;
        $days=max(0,min(30,(int)get_option('oi_ai_retention_days',0)));
        if(!$days){$wpdb->query("UPDATE {$wpdb->prefix}oi_ai_requests SET question='',answer='' WHERE question<>'' OR answer<>''");return;}
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_ai_requests SET question='',answer='' WHERE created_at<%s",gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS)));
    }
    public static function record(int $user,string $request,string $model,string $source,string $state,array $response,string $question,string $answer): void {
        global $wpdb;
        $in=absint($response['usage']['input_tokens']??0);$out=absint($response['usage']['output_tokens']??0);
        $input=get_option('oi_ai_input_cost',null);$output=get_option('oi_ai_output_cost',null);
        $cost=$input!==null&&$output!==null&&((float)$input>0||(float)$output>0)?($in*(float)$input+$out*(float)$output)/1000000:null;
        $days=max(0,min(30,(int)get_option('oi_ai_retention_days',0)));
        self::purge();
        $ok=$wpdb->insert($wpdb->prefix.'oi_ai_requests',['user_id'=>$user,'request_key'=>$request,'model'=>$model,'state'=>$state,'source_key'=>$source,'premium'=>OI_Offer::premium($user)?1:0,'input_tokens'=>$in,'output_tokens'=>$out,'cost_usd'=>$cost,'question'=>$days?$question:'','answer'=>$days?$answer:'','created_at'=>gmdate('Y-m-d H:i:s')]);
        if(!$ok)OI_Log::event('ai_usage_storage_error',['user_id'=>$user]);
    }
}
