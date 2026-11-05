<?php
defined('ABSPATH') || exit;
final class OI_API {
    public static function register(): void {
        register_rest_route('oi/v1', '/catalog', ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [self::class, 'catalog']]);
        foreach (['/fiches' => 'listing', '/fiches/(?P<id>\d+)' => 'fiche'] as $route => $callback) {
            register_rest_route('oi/v1', $route, ['methods' => 'GET', 'permission_callback' => [self::class, 'auth'], 'callback' => [self::class, $callback]]);
        }
        add_filter('rest_post_dispatch', [self::class, 'private_response'], 10, 3);
    }
    public static function auth(): bool|WP_Error {
        if (!is_user_logged_in()) { return new WP_Error('oi_auth', 'Connectez-vous pour accéder à vos révisions.', ['status' => 401]); }
        if(get_user_meta(get_current_user_id(),'oi_email_pending',true))return new WP_Error('oi_verify','Valide ton adresse e-mail pour commencer.',['status'=>403]);
        return OI_Limit::take('student', 120, 60) ? true : new WP_Error('oi_rate', 'Veuillez patienter avant de poursuivre.', ['status' => 429]);
    }
    public static function private_response($response, $server, $request) {
        if (str_starts_with($request->get_route(), '/oi/v1/')) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
            $response->header('Vary', 'Cookie');
        }
        return $response;
    }
    public static function catalog(): array {
        return array_map(fn($p) => ['id' => $p->ID, 'title' => $p->post_title, 'description' => wp_strip_all_tags($p->post_content), 'available' => (bool)get_post_meta($p->ID, 'oi_price', true), 'free_demo' => get_post_meta($p->ID, 'oi_free_demo', true)==='1' && !get_post_meta($p->ID, 'oi_price', true)], get_posts(['post_type' => 'oi_pack', 'post_status' => 'publish', 'numberposts' => 100]));
    }
    public static function summary(WP_Post $p): array {
        $terms = [];
        foreach (OI_Model::TAX as $tax) {
            $found = wp_get_post_terms($p->ID, 'oi_' . $tax);
            $terms[$tax] = is_wp_error($found) ? [] : array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name], $found);
        }
        return ['id' => $p->ID, 'title' => $p->post_title, 'terms' => $terms, 'updated' => $p->post_modified_gmt, 'cover'=>OI_Fiche_Art::url($p->ID), 'thumbnail'=>OI_Fiche_Art::url($p->ID,true), 'quiz_count'=>count(OI_Revision::quiz($p->ID,true,200)), 'access'=>['FICHE'=>OI_Offer::can_access(get_current_user_id(),'FICHE',$p->ID),'QCM'=>OI_Offer::can_access(get_current_user_id(),'QCM',$p->ID)], 'state'=>OI_Revision::state(get_current_user_id(),$p->ID)];
    }
    public static function listing(WP_REST_Request $r): array {
        $allowed = OI_Model::ids(array_merge(OI_Offer::catalogue(), OI_Model::allowed(get_current_user_id()), OI_Credits::unlocked(get_current_user_id(),'QCM')));
        if(rest_sanitize_boolean($r['unlocked']??false))$allowed=array_values(array_intersect($allowed,OI_Model::allowed(get_current_user_id())));
        if($r['kind']==='QCM')$allowed=array_values(array_filter($allowed,fn($id)=>(bool)OI_Revision::quiz($id)));
        $args = ['post_type' => 'oi_fiche', 'post_status' => 'publish', 'post__in' => $allowed ?: [0], 'posts_per_page' => 20, 'paged' => max(1, absint($r['page'])), 'orderby' => 'title', 'order' => 'ASC', 'search_columns'=>['post_title']];
        if ($r['favorites']) { $args['post__in'] = array_values(array_filter($allowed, fn($id) => !empty(OI_Revision::state(get_current_user_id(), $id)['favorite']))) ?: [0]; }
        if ($r['q']) {
            $text=substr(sanitize_text_field($r['q']),0,150);
            $matches=get_posts(array_merge($args,['s'=>$text,'posts_per_page'=>-1,'paged'=>1,'fields'=>'ids']));
            foreach(['oi_enseignement','oi_theme'] as $taxonomy){
                $terms=get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>true,'search'=>$text,'fields'=>'ids']);
                if(!is_wp_error($terms)&&$terms){$objects=get_objects_in_term($terms,$taxonomy);if(!is_wp_error($objects))$matches=array_merge($matches,$objects);}
            }
            $args['post__in']=array_values(array_intersect($args['post__in'],OI_Model::ids($matches)))?:[0];
        }
        if ($r['semestre']) { $args['tax_query'] = [['taxonomy' => 'oi_semestre', 'field' => 'term_id', 'terms' => absint($r['semestre'])]]; }
        foreach (['enseignement','theme'] as $tax) {
            if ($r->has_param($tax)) $args['tax_query'][] = OI_Library::tax_filter('oi_'.$tax, absint($r[$tax]));
        }
        $query = new WP_Query($args);
        $semesters = get_terms(['taxonomy' => 'oi_semestre', 'object_ids' => $allowed ?: [0], 'hide_empty' => true]);
        return ['items' => array_map([self::class, 'summary'], $query->posts), 'total' => $query->found_posts, 'pages' => $query->max_num_pages, 'account' => OI_Offer::account(), 'progress' => OI_Revision::progress(get_current_user_id()), 'packs' => array_values(array_map(fn($id) => ['id' => $id, 'title' => get_the_title($id)], array_filter(OI_Model::packs(get_current_user_id()), fn($id) => get_post_status($id) === 'publish'))), 'semesters' => is_wp_error($semesters) ? [] : array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name], $semesters)];
    }
    /** Continue within the current theme, independently of personal goals. Metadata only. */
    public static function related(int $id): array {
        $user=get_current_user_id();
        $state=OI_Revision::all($user);
        $posts=get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','post__in'=>OI_Library::catalogue()?:[0],'numberposts'=>-1]);
        // Cache ordering keys once. Word's permanent codes survive title changes.
        $keys=[];
        foreach($posts as $post)$keys[$post->ID]=(string)(get_post_meta($post->ID,'oi_source_code',true)?:remove_accents($post->post_title));
        usort($posts,fn($a,$b)=>strnatcasecmp($keys[$a->ID],$keys[$b->ID])?:$a->ID<=>$b->ID);
        $ids=array_column($posts,'ID');$position=array_search($id,$ids,true);$count=count($posts);
        $terms=static function(int $post,string $taxonomy): array {$found=get_the_terms($post,$taxonomy);return is_array($found)?array_map('intval',array_column($found,'term_id')):[];};
        $themes=$terms($id,'oi_theme');$units=$terms($id,'oi_enseignement');
        $ranked=[];
        foreach($posts as $index=>$post){
            $fid=$post->ID;if($fid===$id)continue;
            $sameUnit=(bool)array_intersect($units,$terms($fid,'oi_enseignement'));
            $sameTheme=(bool)array_intersect($themes,$terms($fid,'oi_theme'))&&(!$units||$sameUnit);
            if($themes?!$sameTheme:!$sameUnit)continue;
            $distance=$position===false?$index:($index-$position+$count)%$count;
            $rank=[!empty($state[$fid]['revised'])?1:0,$distance];
            $ranked[]=['post'=>$post,'rank'=>$rank];
        }
        usort($ranked,fn($a,$b)=>$a['rank']<=>$b['rank']);
        return array_column(array_slice($ranked,0,4),'post');
    }
    public static function fiche(WP_REST_Request $r): array|WP_Error {
        $id = absint($r['id']);
        if (!OI_Model::can_read(get_current_user_id(), $id)) { return new WP_Error('oi_forbidden', 'Cette fiche ne fait pas partie de vos accès.', ['status' => 403]); }
        $post = get_post($id);
        OI_Revision::viewed(get_current_user_id(), $id);
        $related = self::related($id);
        // Native blocks are rendered; no arbitrary shortcodes from protected content are executed.
        return self::summary($post) + ['state' => OI_Revision::state(get_current_user_id(), $id), 'quiz' => OI_Offer::can_access(get_current_user_id(),'QCM',$id)?OI_Revision::quiz($id):[], 'goal'=>OI_Goals::for_fiche(get_current_user_id(),$id), 'watermark' => OI_Revision::watermark(get_current_user_id()), 'content' => OI_Docx::content(do_blocks($post->post_content), $id), 'related' => array_map([self::class, 'summary'], $related)];
    }
}
