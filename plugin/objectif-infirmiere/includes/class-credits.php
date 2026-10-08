<?php
defined('ABSPATH') || exit;

/** Wallet rows serialize all credit mutations; grants retain their origin and expiry. */
final class OI_Credits {
    public const TYPES = ['FICHE', 'QCM', 'IA'];
    public static function migrate(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        $tables = [
            'oi_wallets' => 'user_id bigint unsigned NOT NULL, PRIMARY KEY  (user_id)',
            'oi_credit_grants' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint unsigned NOT NULL, kind varchar(10) NOT NULL,
                source_key varchar(140) NOT NULL, reason varchar(191) NOT NULL,
                amount int unsigned NOT NULL, remaining int unsigned NOT NULL,
                expires_at datetime DEFAULT NULL, created_at datetime NOT NULL,
                PRIMARY KEY  (id), UNIQUE KEY source (user_id,kind,source_key), KEY wallet (user_id,kind)",
            'oi_credit_operations' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint unsigned NOT NULL, op_key varchar(191) NOT NULL,
                kind varchar(10) NOT NULL, delta int NOT NULL, reason varchar(191) NOT NULL,
                state varchar(20) NOT NULL, allocations longtext NOT NULL,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY  (id), UNIQUE KEY operation (user_id,op_key), KEY wallet (user_id,kind,state)",
            'oi_unlocks' => "user_id bigint unsigned NOT NULL, kind varchar(10) NOT NULL,
                content_id bigint unsigned NOT NULL, created_at datetime NOT NULL,
                PRIMARY KEY  (user_id,kind,content_id)",
        ];
        foreach ($tables as $suffix => $columns) {
            $columns = str_replace(', ', ",\n", $columns);
            dbDelta("CREATE TABLE {$wpdb->prefix}$suffix (\n$columns\n) ENGINE=InnoDB $c;");
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->prefix . $suffix));
            if (strtolower((string)$engine) !== 'innodb') { throw new RuntimeException('Les portefeuilles nécessitent InnoDB.'); }
        }
    }
    private static function query(string $sql): int {
        global $wpdb;
        $result = $wpdb->query($sql);
        if ($result === false) { throw new RuntimeException('Échec du stockage des crédits.'); }
        return (int)$result;
    }
    public static function transaction(int $user, callable $work): mixed {
        global $wpdb;
        if ($user < 1 || !get_userdata($user)) { return new WP_Error('oi_user', 'Compte introuvable.', ['status'=>400]); }
        try {
            self::query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}oi_wallets (user_id) VALUES (%d)", $user));
            self::query('START TRANSACTION');
            self::query($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}oi_wallets WHERE user_id=%d FOR UPDATE", $user));
            $stale=$wpdb->get_col($wpdb->prepare("SELECT op_key FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d AND state='reserved' AND updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)",$user));
            foreach($stale as $key)self::settle_locked($user,$key,false,'Interruption technique');
            self::expire_locked($user);
            $result = $work();
            if (is_wp_error($result)) { self::query('ROLLBACK'); return $result; }
            self::query('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK'); OI_Log::event('credits_storage_error', ['user_id'=>$user]);
            return new WP_Error('oi_credit_storage', 'Ton solde n’a pas été modifié. Réessaie dans un instant.', ['status'=>503]);
        }
    }
    private static function event(int $user, string $key, string $kind, int $delta, string $reason, string $state='committed', array $allocations=[]): void {
        global $wpdb;
        $ok = $wpdb->insert($wpdb->prefix.'oi_credit_operations', ['user_id'=>$user,'op_key'=>$key,'kind'=>$kind,'delta'=>$delta,'reason'=>$reason,'state'=>$state,'allocations'=>wp_json_encode($allocations),'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
        if (!$ok) { throw new RuntimeException('Journal des crédits indisponible.'); }
    }
    private static function expire_locked(int $user): void {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_credit_grants WHERE user_id=%d AND remaining>0 AND expires_at IS NOT NULL AND expires_at<=UTC_TIMESTAMP()", $user));
        foreach ($rows as $g) {
            self::query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_grants SET remaining=0 WHERE id=%d", $g->id));
            self::event($user, 'expiry:'.$g->id.':'.wp_generate_uuid4(), $g->kind, -(int)$g->remaining, 'Expiration promotionnelle', 'committed', [$g->id=>(int)$g->remaining]);
        }
    }
    /** Internal: caller must hold the user's wallet transaction. */
    public static function grant_locked(int $user, string $kind, int $amount, string $key, string $reason, ?string $expiry=null): bool {
        global $wpdb;
        if (!in_array($kind,self::TYPES,true) || $amount<1 || $amount>100000 || strlen($key)>140 || !$key || !$reason) { throw new InvalidArgumentException('Attribution invalide.'); }
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oi_credit_grants WHERE user_id=%d AND kind=%s AND source_key=%s", $user,$kind,$key))) { return false; }
        $ok=$wpdb->insert($wpdb->prefix.'oi_credit_grants',['user_id'=>$user,'kind'=>$kind,'amount'=>$amount,'remaining'=>$amount,'source_key'=>$key,'reason'=>sanitize_text_field($reason),'expires_at'=>$expiry,'created_at'=>gmdate('Y-m-d H:i:s')]);
        if (!$ok) { throw new RuntimeException('Attribution impossible.'); }
        self::event($user,'grant:'.$kind.':'.$key,$kind,$amount,$reason,'committed',[(int)$wpdb->insert_id=>$amount]);
        return true;
    }
    public static function grant(int $user,string $kind,int $amount,string $key,string $reason,?string $expiry=null): bool|WP_Error {
        if ($expiry && (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$expiry) || strtotime($expiry.' UTC')<=time())) { return new WP_Error('oi_expiry','La date d’expiration doit être future.',['status'=>400]); }
        return self::transaction($user,fn()=>self::grant_locked($user,$kind,$amount,$key,$reason,$expiry));
    }
    public static function welcome(int $user): bool|WP_Error {
        if (!OI_Verification::verified($user)) { return new WP_Error('oi_verify','Valide ton adresse e-mail pour recevoir tes crédits.',['status'=>403]); }
        return self::transaction($user,function()use($user){foreach(self::TYPES as $kind){self::grant_locked($user,$kind,5,'welcome','Crédits de bienvenue');}return true;});
    }
    public static function balances(int $user): array {
        global $wpdb;
        $result=self::transaction($user,function()use($user,$wpdb){
            $balance=array_fill_keys(self::TYPES,0);
            foreach($wpdb->get_results($wpdb->prepare("SELECT kind,SUM(remaining) AS balance FROM {$wpdb->prefix}oi_credit_grants WHERE user_id=%d AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) GROUP BY kind",$user)) as $r){$balance[$r->kind]=(int)$r->balance;}
            return $balance;
        });
        if(is_wp_error($result)) { throw new RuntimeException('Solde temporairement indisponible.'); }
        return $result;
    }
    public static function unlocked(int $user,string $kind): array {
        global $wpdb;
        return array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT content_id FROM {$wpdb->prefix}oi_unlocks WHERE user_id=%d AND kind=%s",$user,$kind)));
    }
    private static function consume_locked(int $user,string $kind,string $key,string $reason,string $state='committed'): array|WP_Error {
        global $wpdb;
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d AND op_key=%s",$user,$key),ARRAY_A);
        if($existing) return ['duplicate'=>true,'state'=>$existing['state'],'key'=>$key];
        $grant=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_credit_grants WHERE user_id=%d AND kind=%s AND remaining>0 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY expires_at IS NULL,expires_at,id LIMIT 1",$user,$kind));
        if(!$grant) return new WP_Error('oi_no_credit','Tu n’as plus de crédits disponibles pour cette action.',['status'=>402,'kind'=>$kind]);
        self::query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_grants SET remaining=remaining-1 WHERE id=%d AND remaining>0",$grant->id));
        self::event($user,$key,$kind,-1,$reason,$state,[$grant->id=>1]);
        return ['duplicate'=>false,'state'=>$state,'key'=>$key,'source'=>$grant->source_key];
    }
    public static function unlock(int $user,string $kind,int $id): array|WP_Error {
        if(!in_array($kind,['FICHE','QCM'],true) || !in_array($id,OI_Offer::catalogue(),true) || ($kind==='QCM'&&!OI_Revision::quiz($id))) return new WP_Error('oi_content','Ce contenu n’est pas disponible.',['status'=>404]);
        if(!OI_Verification::verified($user)) return new WP_Error('oi_verify','Valide ton adresse e-mail.',['status'=>403]);
        return self::transaction($user,function()use($user,$kind,$id){
            global $wpdb;
            if(OI_Offer::can_access($user,$kind,$id)) return ['unlocked'=>true,'consumed'=>false];
            $result=self::consume_locked($user,$kind,'unlock:'.$kind.':'.$id,'Déblocage '.$kind.' #'.$id);
            if(is_wp_error($result))return $result;
            self::query($wpdb->prepare("INSERT INTO {$wpdb->prefix}oi_unlocks (user_id,kind,content_id,created_at) VALUES (%d,%s,%d,UTC_TIMESTAMP())",$user,$kind,$id));
            return ['unlocked'=>true,'consumed'=>true];
        });
    }
    public static function reserve_ai(int $user,string $request): array|WP_Error {
        if(!preg_match('/^[a-f0-9-]{36}$/i',$request)) return new WP_Error('oi_request','Identifiant de question invalide.',['status'=>400]);
        return self::transaction($user,function()use($user,$request){
            global $wpdb;
            $key='ai:'.$request;
            $existing=$wpdb->get_var($wpdb->prepare("SELECT state FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d AND op_key=%s",$user,$key));
            if($existing) return ['duplicate'=>true,'state'=>$existing,'key'=>$key];
            if($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d AND state='reserved' LIMIT 1",$user)))return new WP_Error('oi_busy','Une question est déjà en cours. Attends sa réponse.',['status'=>409]);
            return self::consume_locked($user,'IA',$key,'Question au conseiller IA','reserved');
        });
    }
    private static function settle_locked(int $user,string $key,bool $success,string $reason): bool {
        global $wpdb;
        $op=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d AND op_key=%s",$user,$key));
        if(!$op || $op->state!=='reserved')return false;
        if(!$success){
            foreach(json_decode($op->allocations,true) as $grant=>$amount){self::query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_grants SET remaining=remaining+%d WHERE id=%d AND user_id=%d",$amount,$grant,$user));}
            self::event($user,'refund:'.$key,'IA',1,$reason,'committed',json_decode($op->allocations,true));
        }
        self::query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_operations SET state=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d",$success?'committed':'refunded',$op->id));
        return true;
    }
    public static function settle_ai(int $user,string $key,bool $success): bool|WP_Error {
        return self::transaction($user,fn()=>self::settle_locked($user,$key,$success,$success?'Réponse fournie':'Échec technique — crédit restitué'));
    }
    /** Internal: revoke only unspent Premium credits; an in-flight refund keeps the old expiry. */
    public static function revoke_premium_locked(int $user): void {
        global $wpdb;
        $grant=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_credit_grants WHERE user_id=%d AND kind='IA' AND source_key='premium_bonus'",$user));
        if(!$grant)return;
        self::query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_grants SET remaining=0,expires_at=UTC_TIMESTAMP() WHERE id=%d",$grant->id));
        if((int)$grant->remaining>0)self::event($user,'revoke:premium:'.$grant->id,'IA',-(int)$grant->remaining,'Remboursement intégral du Premium','committed',[$grant->id=>(int)$grant->remaining]);
    }
    public static function history(int $user,int $limit=50): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT kind,delta,reason,state,allocations,created_at FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d ORDER BY id DESC LIMIT %d",$user,min(200,max(1,$limit))),ARRAY_A);
    }
}
