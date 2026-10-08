<?php
defined('ABSPATH') || exit;
final class OI_Credit_Admin {
    public static function migrate(): void {
        global $wpdb;$c=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_campaigns (
            id varchar(36) NOT NULL,
            kind varchar(10) NOT NULL,
            amount int unsigned NOT NULL,
            reason varchar(191) NOT NULL,
            expires_at datetime DEFAULT NULL,
            user_ids longtext NOT NULL,
            processed_count int unsigned NOT NULL DEFAULT 0,
            created_by bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) ENGINE=InnoDB $c;");
    }
    public static function register(): void {
        add_action('admin_menu',function(){add_submenu_page('objectif-infirmiere','Crédits','Crédits','manage_options','oi-credits',[self::class,'page']);});
        add_action('admin_post_oi_credit_campaign',[self::class,'submit']);
        add_action('admin_post_oi_credit_export',[self::class,'export']);
    }
    public static function campaign(array $fields,int $actor): array|WP_Error {
        global $wpdb;
        if(!user_can($actor,'manage_options'))return new WP_Error('oi_forbidden','Accès refusé.',['status'=>403]);
        $id=sanitize_text_field($fields['campaign']??'');
        if(!preg_match('/^[a-f0-9-]{36}$/i',$id))return new WP_Error('oi_campaign','Identifiant invalide.');
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_campaigns WHERE id=%s",$id));
        if(!$existing){
            $kind=sanitize_key($fields['kind']??'');$kind=strtoupper($kind);$amount=absint($fields['amount']??0);$reason=sanitize_text_field($fields['reason']??'');$expiry=sanitize_text_field($fields['expires_at']??'');
            if(!in_array($kind,OI_Credits::TYPES,true)||$amount<1||$amount>10000||!$reason||strlen($reason)>191)return new WP_Error('oi_campaign','Type, quantité ou motif invalide.');
            $expiry=$expiry?str_replace('T',' ',$expiry).':00':null;
            if($expiry&&(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$expiry)||strtotime($expiry.' UTC')<=time()))return new WP_Error('oi_expiry','Expiration UTC invalide.');
            if(($fields['audience']??'')==='prospects'){
                $ids=get_users(['role'=>'oi_etudiant','fields'=>'ID','number'=>10000,'meta_key'=>'oi_email_verified','meta_compare'=>'EXISTS']);
                $ids=array_values(array_filter(array_map('intval',$ids),fn($u)=>!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oi_payments WHERE user_id=%d LIMIT 1",$u))&&!OI_Offer::premium($u)));
            }else{$ids=array_values(array_unique(array_filter(array_map('absint',preg_split('/[\s,;]+/',(string)($fields['users']??''))))));}
            if(!$ids||count($ids)>10000)return new WP_Error('oi_users','Sélectionnez de 1 à 10 000 comptes.');
            foreach($ids as $u)if(!get_userdata($u))return new WP_Error('oi_users','Un compte sélectionné n’existe pas.');
            $ok=$wpdb->insert($wpdb->prefix.'oi_campaigns',['id'=>$id,'kind'=>$kind,'amount'=>$amount,'reason'=>$reason,'expires_at'=>$expiry,'user_ids'=>wp_json_encode($ids),'created_by'=>$actor,'created_at'=>gmdate('Y-m-d H:i:s')]);
            if(!$ok)return new WP_Error('oi_storage','Campagne non enregistrée.');
            $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_campaigns WHERE id=%s",$id));
        }
        $ids=json_decode($existing->user_ids,true);$cursor=(int)$existing->processed_count;
        if($existing->expires_at&&strtotime($existing->expires_at.' UTC')<=time())return new WP_Error('oi_expiry','Campagne expirée. Aucune nouvelle attribution.');
        // Replays or parallel submissions use the same grant keys, never credit twice.
        foreach(array_slice($ids,$cursor,50) as $u){
            if(get_userdata($u)){$r=OI_Credits::grant((int)$u,$existing->kind,(int)$existing->amount,'campaign:'.$id,$existing->reason,$existing->expires_at);if(is_wp_error($r))return $r;}
            $cursor++;
        }
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_campaigns SET processed_count=GREATEST(processed_count,%d) WHERE id=%s",$cursor,$id));
        return ['campaign'=>$id,'processed'=>$cursor,'total'=>count($ids)];
    }
    public static function submit(): void {
        if(!current_user_can('manage_options'))wp_die('Accès refusé.',403);
        check_admin_referer('oi_credit_campaign');
        $r=self::campaign(wp_unslash($_POST),get_current_user_id());
        if(is_wp_error($r))wp_die(esc_html($r->get_error_message()));
        wp_safe_redirect(add_query_arg(['page'=>'oi-credits','campaign'=>$r['campaign']],admin_url('admin.php')),303);exit;
    }
    public static function export(): void {
        if(!current_user_can('manage_options'))wp_die('Accès refusé.',403);check_admin_referer('oi_credit_export');
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="objectif-infirmiere-statistiques.csv"');
        $out=fopen('php://output','w');fputcsv($out,['indicateur','valeur']);foreach(OI_Metrics::report()as$k=>$v)fputcsv($out,[$k,$v??'non estimé']);fclose($out);exit;
    }
    public static function page(): void {
        if(!current_user_can('manage_options'))return;
        global $wpdb;
        $search=isset($_GET['s'])&&is_string($_GET['s'])?sanitize_text_field(wp_unslash($_GET['s'])):'';$selected=absint($_GET['user']??0);
        echo '<div class="wrap"><h1>Objectif Infirmière → Crédits</h1><form method="get"><input type="hidden" name="page" value="oi-credits"><label>Rechercher un compte <input name="s" value="'.esc_attr($search).'" placeholder="Nom, e-mail ou identifiant"></label> <button class="button">Rechercher</button></form>';
        if($search){$found=get_users(['search'=>'*'.$search.'*','search_columns'=>['user_login','user_email','display_name'],'number'=>30]);echo '<ul>';foreach($found as$u)echo '<li><a href="'.esc_url(add_query_arg(['page'=>'oi-credits','user'=>$u->ID],admin_url('admin.php'))).'">'.esc_html($u->display_name.' — '.$u->user_email).' (#'.(int)$u->ID.')</a></li>';echo '</ul>';}
        if($selected&&get_userdata($selected)){
            echo '<h2>Compte #'.$selected.' — '.esc_html(get_userdata($selected)->display_name).'</h2><p>Adresse '.(OI_Verification::verified($selected)?'confirmée':'non confirmée').' · '.(OI_Offer::premium($selected)?'Premium actif':'Sans Premium actif').'</p><ul>';
            foreach(OI_Credits::balances($selected)as$kind=>$balance)echo '<li>'.esc_html($kind).' : '.(int)$balance.'</li>';echo '</ul><h3>Historique (200 derniers mouvements)</h3><table class="widefat"><tr><th>Date UTC</th><th>Type</th><th>Mouvement</th><th>Motif</th><th>État</th></tr>';
            foreach(OI_Credits::history($selected,200)as$row)echo '<tr><td>'.esc_html($row['created_at']).'</td><td>'.esc_html($row['kind']).'</td><td>'.(int)$row['delta'].'</td><td>'.esc_html($row['reason']).'</td><td>'.esc_html($row['state']).'</td></tr>';echo '</table><h3>Achats</h3><ul>';
            foreach($wpdb->get_results($wpdb->prepare("SELECT amount,currency,status,refund_status,created_at FROM {$wpdb->prefix}oi_payments WHERE user_id=%d ORDER BY id DESC LIMIT 100",$selected)) as$r)echo '<li>'.esc_html($r->created_at.' · '.number_format($r->amount/100,2).' '.$r->currency.' · '.$r->status.' '.$r->refund_status).'</li>';echo '</ul>';
        }
        echo '<h2>Attribuer des crédits — opération manuelle ou campagne</h2><p>Chaque campagne conserve sa sélection et son identifiant pour permettre une reprise sans double attribution. Traitement de 50 comptes par lot.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('oi_credit_campaign');echo '<input type="hidden" name="action" value="oi_credit_campaign"><input type="hidden" name="campaign" value="'.esc_attr(wp_generate_uuid4()).'"><p><label>Audience <select name="audience"><option value="selection">Comptes sélectionnés</option><option value="prospects">Étudiants vérifiés non acheteurs (maximum 10 000)</option></select></label></p><p><label>Identifiants numériques des comptes, séparés par virgules <input class="regular-text" name="users" value="'.($selected?:'').'"></label></p><p><label>Type <select name="kind"><option>FICHE</option><option>QCM</option><option>IA</option></select></label> <label>Nombre <input type="number" name="amount" min="1" max="10000" required></label></p><p><label>Motif <input class="regular-text" name="reason" maxlength="191" required></label></p><p><label>Expiration facultative (UTC) <input type="datetime-local" name="expires_at"></label></p>';submit_button('Attribuer les crédits');echo '</form><h2>Campagnes et reprises</h2><table class="widefat"><tr><th>Motif</th><th>Crédits</th><th>Expiration UTC</th><th>Traitement</th></tr>';
        foreach($wpdb->get_results("SELECT * FROM {$wpdb->prefix}oi_campaigns ORDER BY created_at DESC LIMIT 30")as$c){$total=count(json_decode($c->user_ids,true));echo '<tr><td>'.esc_html($c->reason).'</td><td>'.(int)$c->amount.' '.esc_html($c->kind).'</td><td>'.esc_html($c->expires_at?:'Aucune').'</td><td>'.(int)$c->processed_count.' / '.$total;if((int)$c->processed_count<$total){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('oi_credit_campaign');echo '<input type="hidden" name="action" value="oi_credit_campaign"><input type="hidden" name="campaign" value="'.esc_attr($c->id).'"><button class="button">Traiter le lot suivant</button></form>';}echo '</td></tr>';}echo '</table><h2>Statistiques cumulées</h2><p>Mesure interne sans identifiant visiteur public, sans cookie marketing et sans texte de conversation. Les visites sont des chargements de pages, pas des visiteurs uniques. Les taux portent sur les comptes vérifiés et les achats confirmés, y compris ceux remboursés ; la consommation IA moyenne Premium porte sur ses utilisateurs IA. Coût estimé des tokens en USD, hors outils/recherche/stockage.</p><table class="widefat">';
        foreach(OI_Metrics::report()as$k=>$v)echo '<tr><th>'.esc_html($k).'</th><td>'.esc_html($v===null?'Non estimé — tarifs à renseigner':(string)$v).'</td></tr>';echo '</table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('oi_credit_export');echo '<input type="hidden" name="action" value="oi_credit_export">';submit_button('Exporter les statistiques agrégées');echo '</form></div>';
    }
}
