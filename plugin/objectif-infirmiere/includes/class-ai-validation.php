<?php
defined('ABSPATH') || exit;

/** Administrator-only preparation and trials; never opens the student service. */
final class OI_AI_Validation {
    public static function register(): void {
        foreach (['check','prepare','index','ask'] as $action) {
            register_rest_route('oi/v1','/ai-validation/'.$action,[
                'methods'=>'POST', 'permission_callback'=>fn()=>current_user_can('manage_options'),
                'callback'=>[self::class,$action],
            ]);
        }
    }
    public static function check(): array|WP_Error {
        $model=get_option('oi_ai_model','gpt-4.1-mini');
        $result=OI_AI::request('GET','models/'.rawurlencode($model));
        if(is_wp_error($result))return $result;
        return ['connected'=>true,'model'=>$model,'enabled'=>(bool)get_option('oi_ai_enabled',false),'store_configured'=>(bool)get_option('oi_vector_store',''),'provider_limits'=>get_transient('oi_ai_provider_limits')?:null,'last_provider_error'=>get_transient('oi_ai_last_provider_error')?:null,'last_rejection'=>get_transient('oi_ai_last_rejection')?:null];
    }
    public static function prepare(WP_REST_Request $r): array|WP_Error {
        $raw=$r['fiche_ids'];
        if(!is_array($raw)||!$raw||count($raw)>15)return new WP_Error('oi_selection','Choisis de 1 à 15 fiches pour cet essai.',['status'=>400]);
        $ids=array_values(array_unique(array_map('absint',$raw)));
        foreach($ids as $id)if(get_post_type($id)!=='oi_fiche'||get_post_status($id)!=='publish')return new WP_Error('oi_selection','Fiche publiée introuvable.',['status'=>400]);
        if(!add_option('oi_ai_validation_lock',time(),'','no'))return new WP_Error('oi_busy','Une préparation est déjà en cours.',['status'=>409]);
        try {
            $store=get_option('oi_vector_store','');
            if(!$store){
                $result=OI_AI::request('POST','vector_stores',['name'=>'Objectif Infirmière — recette pédagogique','expires_after'=>['anchor'=>'last_active_at','days'=>7]]);
                if(is_wp_error($result))return $result;
                if(empty($result['id']))return new WP_Error('oi_store','Création des sources impossible.',['status'=>502]);
                $store=$result['id'];update_option('oi_vector_store',$store,false);
            }
            update_option('oi_ai_validation_fiches',$ids,false);
            return ['prepared'=>true,'fiche_ids'=>$ids,'enabled'=>(bool)get_option('oi_ai_enabled',false)];
        } finally {delete_option('oi_ai_validation_lock');}
    }
    public static function index(WP_REST_Request $r): array|WP_Error {
        $id=absint($r['fiche_id']);
        if(!in_array($id,get_option('oi_ai_validation_fiches',[]),true))return new WP_Error('oi_selection','Fiche absente de la sélection de test.',['status'=>400]);
        OI_AI::sync($id);
        return ['id'=>$id,'title'=>get_the_title($id),'status'=>get_post_meta($id,'oi_ai_status',true)];
    }
    public static function ask(WP_REST_Request $r): array|WP_Error {
        // Defence in depth: the preview cannot be enabled by a public request parameter.
        if(!current_user_can('manage_options'))return new WP_Error('oi_forbidden','Accès administrateur requis.',['status'=>403]);
        if($r['model']!==null && !in_array($r['model'],['gpt-4.1-mini','gpt-4.1'],true))return new WP_Error('oi_model','Modèle de test non autorisé.',['status'=>400]);
        return OI_AI::ask($r,true);
    }
    public static function panel(): void {
        if(!current_user_can('manage_options'))return;
        echo '<section id="oi-ai-trial" style="max-width:900px;background:#fff;border:1px solid #c3c4c7;padding:20px;margin:24px 0"><h2>Essayer l’assistant — administrateurs</h2><p>Les essais utilisent la vraie API OpenAI et les crédits IA de ton compte (1 crédit par réponse réussie). Ils ne rendent pas l’assistant accessible aux étudiants. Les sources utilisées sont les fiches indexées auxquelles ton compte a accès ; les références externes sont limitées aux sites de santé sélectionnés lorsque tu actives cette option.</p><button type="button" class="button" id="oi-ai-check">Vérifier la connexion OpenAI</button><p id="oi-ai-check-result" role="status"></p><form id="oi-ai-trial-form"><label for="oi-ai-trial-question"><strong>Ta question de test</strong></label><p><textarea id="oi-ai-trial-question" rows="4" maxlength="2000" required class="large-text" placeholder="Explique la différence entre asepsie et antisepsie à partir de nos fiches."></textarea></p><p><label><input type="checkbox" id="oi-ai-trial-research"> Consulter aussi les recommandations officielles</label></p><button class="button button-primary">Tester cette question</button></form><p id="oi-ai-trial-status" role="status"></p><div id="oi-ai-trial-answer" style="white-space:pre-wrap"></div><ul id="oi-ai-trial-sources"></ul></section>';
        wp_enqueue_script('oi-ai-validation',OI_URL.'assets/ai-validation.js',[],OI_VERSION,true);
        wp_localize_script('oi-ai-validation','OI_AI_TRIAL',['url'=>rest_url('oi/v1/ai-validation/'),'nonce'=>wp_create_nonce('wp_rest'),'fiche_url'=>OI_App::url('member').'?view=fiches&fiche=']);
    }
}
