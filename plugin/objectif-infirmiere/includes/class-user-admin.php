<?php
/** Administrative member support. Purchases remain owned by Stripe. */
defined('ABSPATH') || exit;
final class OI_User_Admin {
    public static function install(): void {
        if ((int)get_option('oi_user_admin_schema') === 1) return;
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_user_actions (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint unsigned NOT NULL,
            actor_id bigint unsigned NOT NULL,
            request_key varchar(36) NOT NULL,
            payload_hash varchar(64) NOT NULL,
            action varchar(32) NOT NULL,
            reason varchar(191) NOT NULL,
            result longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request (user_id,request_key),
            KEY member_history (user_id,id)
        ) ENGINE=InnoDB {$wpdb->get_charset_collate()};");
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->prefix.'oi_user_actions'));
        if(strtolower((string)$engine)!=='innodb')throw new RuntimeException('Journal utilisateurs indisponible.');
        update_option('oi_user_admin_schema',1);
    }
    public static function auth(): bool {return current_user_can('manage_options') && current_user_can('list_users');}
    public static function routes(): void {
        register_rest_route('oi/v1','/admin/users',['methods'=>'GET','permission_callback'=>[self::class,'auth'],'callback'=>[self::class,'listing']]);
        register_rest_route('oi/v1','/admin/users/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>[self::class,'auth'],'callback'=>fn($r)=>self::detail((int)$r['id'])]);
        register_rest_route('oi/v1','/admin/user-actions',['methods'=>'POST','permission_callback'=>[self::class,'auth'],'callback'=>[self::class,'apply']]);
    }
    private static function fail(string $message,int $status=400): WP_Error {return new WP_Error('oi_user_admin',$message,['status'=>$status]);}
    private static function summary(WP_User $u): array {
        $pack=OI_Offer::pack();$manual=OI_Model::ids(get_user_meta($u->ID,'oi_packs',true));$paid=OI_Storage::paid_packs($u->ID);
        $admin=user_can($u,'manage_options');
        return ['id'=>(int)$u->ID,'name'=>$u->display_name,'email'=>$u->user_email,'login'=>$u->user_login,'registered'=>$u->user_registered,'administrator'=>$admin,'editable'=>!$admin&&current_user_can('edit_user',$u->ID),'verified'=>OI_Verification::verified($u->ID),'verification_pending'=>(bool)get_user_meta($u->ID,'oi_email_pending',true),'premium'=>!!$pack&&(in_array($pack,$manual,true)||in_array($pack,$paid,true)),'manual'=>!!$pack&&in_array($pack,$manual,true),'paid'=>!!$pack&&in_array($pack,$paid,true)];
    }
    public static function listing(WP_REST_Request $r): array|WP_Error {
        global $wpdb;
        $search=$r->get_param('search')??'';$filter=$r->get_param('filter')??'';$page=max(1,(int)$r->get_param('page'));
        if(!is_string($search)||!is_string($filter)||!in_array($filter,['','free','premium','paid','manual','unverified'],true))return self::fail('Recherche invalide.');
        $where='1=1';$pack=OI_Offer::pack();
        $paid=$wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->prefix}oi_payments p WHERE p.user_id=u.ID AND p.pack_id=%d AND p.pack_id>0 AND p.status='paid' AND p.purchase_kind='pack')",$pack);
        $manual=$pack?$wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->usermeta} m WHERE m.user_id=u.ID AND m.meta_key='oi_packs' AND m.meta_value REGEXP %s)",'^a:[0-9]+:[{](i:[0-9]+;i:[0-9]+;)*i:[0-9]+;i:'.$pack.';'):'0=1';
        if($search!==''){$like='%'.$wpdb->esc_like(mb_substr($search,0,150)).'%';$where.=$wpdb->prepare(' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)',$like,$like,$like);}
        if($filter==='premium')$where.=" AND ($paid OR $manual)";
        if($filter==='paid')$where.=" AND ($paid)";
        if($filter==='manual')$where.=" AND ($manual)";
        if($filter==='free')$where.=" AND NOT ($paid OR $manual)";
        if($filter==='unverified')$where.=" AND NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} v WHERE v.user_id=u.ID AND v.meta_key='oi_email_verified' AND v.meta_value<>'')";
        // Administrators have unrestricted access and are not free/unverified students.
        if(in_array($filter,['free','unverified'],true))$where.=$wpdb->prepare(" AND NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} c WHERE c.user_id=u.ID AND c.meta_key=%s AND (c.meta_value LIKE %s OR c.meta_value LIKE %s))",$wpdb->prefix.'capabilities','%"administrator";b:1%','%"manage_options";b:1%');
        $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} u WHERE $where");$pages=max(1,(int)ceil($total/25));$page=min($page,$pages);
        $ids=$wpdb->get_col($wpdb->prepare("SELECT u.ID FROM {$wpdb->users} u WHERE $where ORDER BY u.user_registered DESC,u.ID DESC LIMIT 25 OFFSET %d",($page-1)*25));
        return ['items'=>array_map(fn($id)=>self::summary(get_userdata($id)),$ids),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public static function detail(int $id): array|WP_Error {
        global $wpdb;$u=get_userdata($id);if(!$u)return self::fail('Compte introuvable.',404);
        $actions=$wpdb->get_results($wpdb->prepare("SELECT actor_id,action,reason,result,created_at FROM {$wpdb->prefix}oi_user_actions WHERE user_id=%d ORDER BY id DESC LIMIT 50",$id),ARRAY_A);
        foreach($actions as &$a){$a['actor']=get_userdata((int)$a['actor_id'])->display_name??'Administrateur supprimé';$a['result']=json_decode($a['result'],true);unset($a['actor_id']);}unset($a);
        return self::summary($u)+['balances'=>OI_Credits::balances($id),'bonus_given'=>(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oi_credit_grants WHERE user_id=%d AND kind='IA' AND source_key='admin_premium_bonus'",$id)),'credits'=>OI_Credits::history($id,50),'payments'=>$wpdb->get_results($wpdb->prepare("SELECT amount,currency,status,refund_status,purchase_kind,created_at FROM {$wpdb->prefix}oi_payments WHERE user_id=%d ORDER BY id DESC LIMIT 50",$id),ARRAY_A),'actions'=>$actions,'pack_available'=>OI_Offer::pack()>0];
    }
    public static function apply(WP_REST_Request $r): array|WP_Error {
        if(!self::auth())return self::fail('Accès refusé.',403);
        $p=$r->get_json_params();if(!is_array($p))return self::fail('Demande invalide.');
        $action=$p['action']??'';$reason=$p['reason']??'';$key=$p['request_key']??'';$ids=$p['users']??[];
        if(!is_string($action)||!in_array($action,['grant_premium','revoke_premium','credits','reset_password','verify_email'],true)||!is_string($reason)||!trim($reason)||mb_strlen($reason)>191||!is_string($key)||!wp_is_uuid($key)||!is_array($ids)||!$ids||count($ids)>50)return self::fail('Choisis de 1 à 50 comptes, une action et un motif (191 caractères maximum).');
        $reason=sanitize_text_field($reason);if(!$reason)return self::fail('Un motif est nécessaire.');
        foreach($ids as $id)if(!is_int($id)||$id<1)return self::fail('Identifiant de compte invalide.');
        $ids=array_values(array_unique($ids));sort($ids);
        if(in_array($action,['reset_password','verify_email'],true)&&count($ids)!==1)return self::fail('Les e-mails d’assistance sont envoyés individuellement.');
        foreach($ids as $id){if(!get_userdata($id))return self::fail('Un compte sélectionné n’existe plus.',404);if(user_can($id,'manage_options')||!current_user_can('edit_user',$id))return self::fail('Les comptes administrateurs et les comptes hors de tes droits sont protégés.',403);}
        $kind=$p['kind']??'IA';$amount=$p['amount']??0;$bonus=$p['bonus']??false;
        if(!is_bool($bonus))return self::fail('Option de bonus invalide.');
        if($action==='credits'&&(!is_string($kind)||!in_array($kind,OI_Credits::TYPES,true)||!is_int($amount)||$amount<1||$amount>10000))return self::fail('Choisis un type et un nombre entier de 1 à 10 000 crédits.');
        $pack=OI_Offer::pack();if(in_array($action,['grant_premium','revoke_premium'],true)&&!$pack)return self::fail('Le pack Premium doit être configuré.',409);
        $actor=get_current_user_id();$hash=hash('sha256',wp_json_encode([$actor,$action,$reason,$ids,$action==='credits'?[$kind,$amount]:null,$action==='grant_premium'?$bonus:null,$pack]));$results=[];
        foreach($ids as $id){
            $out=OI_Credits::transaction($id,function()use($id,$actor,$action,$reason,$key,$hash,$kind,$amount,$bonus,$pack){
                global $wpdb;
                $existing=$wpdb->get_row($wpdb->prepare("SELECT actor_id,payload_hash,result FROM {$wpdb->prefix}oi_user_actions WHERE user_id=%d AND request_key=%s",$id,$key));
                if($existing){if((int)$existing->actor_id!==$actor||!hash_equals($existing->payload_hash,$hash))return self::fail('Cette demande a déjà été utilisée avec d’autres paramètres.',409);return json_decode($existing->result,true)+['replayed'=>true];}
                wp_cache_delete($id,'user_meta');$manual=OI_Model::ids(get_user_meta($id,'oi_packs',true));$paid=in_array($pack,OI_Storage::paid_packs($id),true);$has=in_array($pack,$manual,true);$result=['ok'=>true,'message'=>''];
                if($action==='grant_premium'){
                    if($paid||$has){$result['message']=$paid?'Premium déjà acheté : aucun changement.':'Premium déjà offert : aucun changement.';}
                    else {if(!update_user_meta($id,'oi_packs',array_values(array_unique([...$manual,$pack]))))throw new RuntimeException('Accès non enregistré.');$credited=$bonus&&OI_Credits::grant_locked($id,'IA',100,'admin_premium_bonus',mb_substr('Bonus Premium offert : '.$reason,0,191));$result['message']='Premium offert.'.($credited?' 100 crédits IA ajoutés.':($bonus?' Bonus déjà attribué précédemment.':''));}
                }elseif($action==='revoke_premium'){
                    if($has){if(!update_user_meta($id,'oi_packs',array_values(array_diff($manual,[$pack]))))throw new RuntimeException('Accès non enregistré.');}
                    $result['message']=($has?'Accès offert retiré.':'Aucun accès offert à retirer.').($paid?' Le Premium acheté reste actif.':'').' Les crédits existants sont conservés.';
                }elseif($action==='credits'){
                    OI_Credits::grant_locked($id,$kind,$amount,'admin:'.$key,$reason);$result['message']=$amount.' crédits '.$kind.' ajoutés.';
                }else{
                    $recent=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}oi_user_actions WHERE user_id=%d AND action=%s AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)",$id,$action));
                    if($recent>=3)return self::fail('Trois demandes ont déjà été traitées pour ce compte. Réessaie dans une heure.',429);
                    if($action==='verify_email'&&OI_Verification::verified($id)){$result['message']='Adresse déjà confirmée : aucun e-mail envoyé.';}
                    else {$sent=$action==='verify_email'?OI_Verification::issue($id):retrieve_password(get_userdata($id)->user_login);$result=['ok'=>!is_wp_error($sent)&&$sent!==false,'message'=>!is_wp_error($sent)&&$sent!==false?'E-mail confié au service d’envoi.':'L’envoi n’a pas abouti. Tu peux réessayer.'];}
                }
                $written=$wpdb->insert($wpdb->prefix.'oi_user_actions',['user_id'=>$id,'actor_id'=>$actor,'request_key'=>$key,'payload_hash'=>$hash,'action'=>$action,'reason'=>$reason,'result'=>wp_json_encode($result),'created_at'=>gmdate('Y-m-d H:i:s')]);
                if(!$written)throw new RuntimeException('Journal indisponible.');return $result;
            });
            wp_cache_delete($id,'user_meta');$results[]=['id'=>$id]+(is_wp_error($out)?['ok'=>false,'message'=>$out->get_error_message()]:$out);
        }
        return ['results'=>$results];
    }
}
