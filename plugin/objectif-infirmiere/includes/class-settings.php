<?php
defined('ABSPATH') || exit;
final class OI_Settings {
    public static function menu(): void {
        add_submenu_page('objectif-infirmiere','Réglages','Réglages','manage_options','oi-settings',[self::class,'page']);
        add_submenu_page('objectif-infirmiere','Paiements Stripe TEST','Paiements Stripe','manage_options','oi-payments',[self::class,'payments']);
        add_submenu_page('objectif-infirmiere','Conseiller IA','IA et synchronisation','manage_options','oi-ai',[self::class,'ai']);
    }
    public static function register(): void {
        foreach(['oi_ai_model','oi_vector_store','oi_ai_prompt'] as $key) register_setting('oi_settings',$key,['type'=>'string','sanitize_callback'=>$key==='oi_ai_prompt'?'sanitize_textarea_field':'sanitize_text_field']);
        register_setting('oi_settings','oi_ai_quota',['type'=>'integer','sanitize_callback'=>fn($v)=>max(1,min(1000,absint($v)))]);
        register_setting('oi_settings','oi_watermark',['type'=>'boolean','sanitize_callback'=>'rest_sanitize_boolean']);
        foreach(['oi_ai_input_cost','oi_ai_output_cost'] as $key) register_setting('oi_settings',$key,['type'=>'number','sanitize_callback'=>fn($v)=>max(0,(float)$v)]);
    }
    public static function page(): void {
        if(!current_user_can('manage_options'))return;
        echo '<div class="wrap"><h1>Réglages Objectif Infirmière</h1><p>Stripe : mode TEST uniquement. Configurez les Price IDs et Product IDs dans chaque pack.</p><p>Webhook : <code>'.esc_html(rest_url('oi/v1/stripe/webhook')).'</code></p><p>Secrets à définir côté serveur, jamais ici ni dans Git : <code>OI_STRIPE_SECRET_KEY</code>, <code>OI_STRIPE_WEBHOOK_SECRET</code>, <code>OI_OPENAI_API_KEY</code>.</p><ul>';
        foreach(['OI_STRIPE_SECRET_KEY','OI_STRIPE_WEBHOOK_SECRET','OI_OPENAI_API_KEY'] as $name) echo '<li>'.esc_html($name).' : '.(OI_Stripe::secret($name)?'présent':'absent').'</li>';
        echo '</ul><form method="post" action="options.php">';settings_fields('oi_settings');
        foreach(['oi_ai_model'=>['Modèle OpenAI','gpt-4.1-mini'],'oi_vector_store'=>['Vector Store ID',''],'oi_ai_quota'=>['Quota quotidien (UTC), y compris les demandes échouées',20],'oi_ai_input_cost'=>['Prix entrée USD par million de tokens (renseigner tarif actuel)',0],'oi_ai_output_cost'=>['Prix sortie USD par million de tokens (renseigner tarif actuel)',0]] as $name=>[$label,$default]) {
            echo '<p><label>'.esc_html($label).'<br><input class="regular-text" name="'.esc_attr($name).'" value="'.esc_attr(get_option($name,$default)).'"></label></p>';
        }
        echo '<p><label>Prompt pédagogique complémentaire<br><textarea class="large-text" rows="6" name="oi_ai_prompt">'.esc_textarea(get_option('oi_ai_prompt','Explique les notions avec clarté et propose des pistes de révision.')).'</textarea></label></p><input type="hidden" name="oi_watermark" value="0"><p><label><input type="checkbox" name="oi_watermark" value="1" '.checked(get_option('oi_watermark',true),true,false).'> Watermark discret (initiale et ID compte)</label></p>';
        submit_button();echo '</form></div>';
    }
    public static function payments(): void {
        if(!current_user_can('manage_options'))return;
        global $wpdb;$page=max(1,absint($_GET['paged']??1));$offset=($page-1)*50;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments ORDER BY id DESC LIMIT 50 OFFSET %d",$offset));
        echo '<div class="wrap"><h1>Paiements Stripe TEST</h1><table class="widefat"><thead><tr><th>Date UTC</th><th>Compte</th><th>Pack</th><th>Montant</th><th>Session</th><th>État</th><th>Email accepté</th></tr></thead><tbody>';
        foreach($rows as $row) echo '<tr><td>'.esc_html($row->created_at).'</td><td>'.(int)$row->user_id.'</td><td>'.esc_html(get_the_title($row->pack_id)).'</td><td>'.esc_html(number_format($row->amount/100,2).' '.strtoupper($row->currency)).'</td><td>'.esc_html($row->session_id).'</td><td>'.esc_html($row->status).'</td><td>'.($row->notified?'Oui':'Non — vérifier SMTP et rejouer le webhook').'</td></tr>';
        echo '</tbody></table><p>Les emails acceptés par WordPress ne prouvent pas leur livraison. Configurez et testez votre SMTP sur OVH.</p><p><a href="'.esc_url(add_query_arg(['page'=>'oi-payments','paged'=>$page+1],admin_url('admin.php'))).'">Page suivante</a></p></div>';
    }
    public static function ai(): void {
        if(!current_user_can('manage_options'))return;
        global $wpdb;
        $today=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(requests),0) AS requests,COALESCE(SUM(input_tokens),0) AS input_tokens,COALESCE(SUM(output_tokens),0) AS output_tokens FROM {$wpdb->prefix}oi_ai_usage WHERE day=%s",gmdate('Y-m-d')));
        $month=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(requests),0) AS requests,COUNT(DISTINCT user_id) AS users,COALESCE(SUM(input_tokens),0) AS input_tokens,COALESCE(SUM(output_tokens),0) AS output_tokens FROM {$wpdb->prefix}oi_ai_usage WHERE day>=%s",gmdate('Y-m-d',strtotime('-29 days'))));
        $cost=((int)$month->input_tokens*(float)get_option('oi_ai_input_cost',0)+(int)$month->output_tokens*(float)get_option('oi_ai_output_cost',0))/1000000;
        echo '<div class="wrap"><h1>Conseiller IA — Synchronisation et statistiques</h1><p>Aujourd’hui UTC : '.(int)$today->requests.' demandes réservées. Sur 30 jours : '.(int)$month->requests.' demandes, '.(int)$month->users.' utilisateurs, '.((int)$month->input_tokens+(int)$month->output_tokens).' tokens.</p><p>Modèle : '.esc_html(get_option('oi_ai_model','gpt-4.1-mini')).'. Coût tokens estimé : '.esc_html(number_format($cost,4)).' USD. Hors File Search et stockage ; tarifs à renseigner dans les réglages. Zéro avec tarifs absents ne signifie pas gratuit.</p><p>WP-Cron doit être exécuté régulièrement. Limite du prototype : 100 documents indexés par utilisateur.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('oi_sync');echo '<input type="hidden" name="action" value="oi_sync"><button class="button button-primary" name="fiche_id" value="0">Resynchroniser toutes les fiches</button></form><table class="widefat"><thead><tr><th>Fiche</th><th>État</th><th>Action</th></tr></thead><tbody>';
        foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>['publish','draft','trash'],'numberposts'=>100]) as $p) {
            echo '<tr><td>'.esc_html($p->post_title).'</td><td>'.esc_html(get_post_meta($p->ID,'oi_ai_status',true)?:'Non synchronisée').'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('oi_sync');echo '<input type="hidden" name="action" value="oi_sync"><button class="button" name="fiche_id" value="'.(int)$p->ID.'">Synchroniser</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    public static function sync(): void {
        if(!current_user_can('manage_options'))wp_die('Accès refusé.',403);check_admin_referer('oi_sync');
        $id=absint($_POST['fiche_id']??0);
        $ids=$id?[$id]:get_posts(['post_type'=>'oi_fiche','post_status'=>['publish','draft','trash'],'numberposts'=>-1,'fields'=>'ids']);
        foreach($ids as $fiche)OI_AI::queue($fiche);
        wp_safe_redirect(admin_url('admin.php?page=oi-ai'));exit;
    }
    public static function quiz_box(WP_Post $post): void {
        wp_nonce_field('oi_quiz_edit','oi_quiz_nonce');
        echo '<p>JSON : tableau de questions avec <code>question</code>, <code>options</code>, <code>correct</code> (indices à partir de 0) et <code>explanation</code>. Plusieurs indices = QCM ; deux options Vrai/Faux = vrai/faux. Maximum 20 questions, 8 options.</p><textarea class="widefat" rows="10" name="oi_quiz">'.esc_textarea(wp_json_encode(get_post_meta($post->ID,'oi_quiz',true)?:[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</textarea>';
    }
    public static function save_quiz(int $id): void {
        if(wp_is_post_revision($id)||wp_is_post_autosave($id)||!current_user_can('edit_post',$id)||!isset($_POST['oi_quiz_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oi_quiz_nonce'])),'oi_quiz_edit'))return;
        $raw=wp_unslash($_POST['oi_quiz']??'[]');if(strlen($raw)>50000)return;
        $quiz=json_decode($raw,true);if(!is_array($quiz))return;
        update_post_meta($id,'oi_quiz',$quiz);update_post_meta($id,'oi_quiz',OI_Revision::quiz($id,false));
    }
}
