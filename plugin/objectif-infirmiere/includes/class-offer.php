<?php
defined('ABSPATH') || exit;
final class OI_Offer {
    public static function pack(): int {
        $id=absint(get_option('oi_premium_pack',0));
        return get_post_type($id)==='oi_pack' && get_post_status($id)==='publish' ? $id : 0;
    }
    public static function catalogue(): array {
        $pack=self::pack();
        if(!$pack)return [];
        return array_values(array_filter(OI_Model::ids(get_post_meta($pack,'oi_fiches',true)),fn($id)=>get_post_type($id)==='oi_fiche'&&get_post_status($id)==='publish'));
    }
    public static function premium(int $user): bool {
        $pack=self::pack();
        return $pack && in_array($pack,OI_Model::packs($user),true);
    }
    public static function can_access(int $user,string $kind,int $id): bool {
        if(!$user || !in_array($kind,['FICHE','QCM'],true) || get_post_type($id)!=='oi_fiche' || get_post_status($id)!=='publish')return false;
        if(get_user_meta($user,'oi_email_pending',true))return false;
        if(user_can($user,'manage_options'))return true;
        if(in_array($id,OI_Model::legacy_allowed($user),true))return true;
        return in_array($id,OI_Credits::unlocked($user,$kind),true);
    }
    public static function account(): array {
        $user=get_current_user_id();$verified=OI_Verification::verified($user);
        if($verified) OI_Credits::welcome($user);
        return ['verified'=>$verified,'verification_email_sent'=>(bool)get_user_meta($user,'oi_email_sent',true),'verification_required'=>(bool)get_user_meta($user,'oi_email_pending',true),'premium'=>self::premium($user),'balances'=>OI_Credits::balances($user),'offer'=>self::public_offer(),'ai_available'=>(bool)(get_option('oi_ai_enabled',false)&&get_option('oi_vector_store')&&OI_Stripe::secret('OI_OPENAI_API_KEY')),'progress'=>OI_Revision::progress($user)];
    }
    public static function public_offer(): array {
        $ids=self::catalogue();$pack=self::pack();
        return ['pack_id'=>$pack,'amount'=>5900,'currency'=>'EUR','payment_mode'=>'test','available'=>(bool)($pack&&get_post_meta($pack,'oi_price',true)&&OI_Stripe::secret('OI_STRIPE_SECRET_KEY')),'fiches'=>count($ids),'qcm'=>count(array_filter($ids,fn($id)=>(bool)OI_Revision::quiz($id))),'demo'=>(bool)($ids&&count(array_filter($ids,fn($id)=>str_contains((string)get_post_field('post_content',$id),'démonstration'))))];
    }
    public static function register(): void {
        register_rest_route('oi/v1','/account',['methods'=>'GET','permission_callback'=>[self::class,'member'],'callback'=>[self::class,'account']]);
        register_rest_route('oi/v1','/offer',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>[self::class,'public_offer']]);
        register_rest_route('oi/v1','/unlock',['methods'=>'POST','permission_callback'=>[OI_API::class,'auth'],'callback'=>function(WP_REST_Request $r){$result=OI_Credits::unlock(get_current_user_id(),is_string($r['kind'])?$r['kind']:'',absint($r['id']));return is_wp_error($result)?$result:$result+['account'=>self::account()];}]);
        register_rest_route('oi/v1','/qcm/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>[OI_API::class,'auth'],'callback'=>function(WP_REST_Request $r){$id=absint($r['id']);if(!self::can_access(get_current_user_id(),'QCM',$id))return new WP_Error('oi_locked','Débloque cette série avec un crédit QCM.',['status'=>403]);OI_Metrics::count('quiz_started',get_current_user_id());return OI_API::summary(get_post($id))+['quiz'=>OI_Revision::quiz($id),'related'=>[]];}]);
    }
    public static function member(): bool|WP_Error {return is_user_logged_in()?true:new WP_Error('oi_auth','Connecte-toi.',['status'=>401]);}
}
