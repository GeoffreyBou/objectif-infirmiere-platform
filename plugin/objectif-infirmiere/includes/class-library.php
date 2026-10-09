<?php
defined('ABSPATH') || exit;
/** Member library: browse metadata only; reading and unlocking retain their own access checks. */
final class OI_Library {
    public static function register(): void {
        register_rest_route('oi/v1', '/library', ['methods'=>'GET', 'permission_callback'=>[OI_API::class,'auth'], 'callback'=>[self::class,'browse']]);
        register_rest_route('oi/v1', '/recent', ['methods'=>'GET', 'permission_callback'=>[OI_API::class,'auth'], 'callback'=>[self::class,'recent']]);
    }
    public static function catalogue(): array {
        return OI_Model::ids(array_merge(OI_Offer::catalogue(), OI_Model::allowed(get_current_user_id()), OI_Credits::unlocked(get_current_user_id(),'QCM')));
    }
    public static function tax_filter(string $taxonomy, int $id): array {
        return $id ? ['taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>$id,'include_children'=>false] : ['taxonomy'=>$taxonomy,'operator'=>'NOT EXISTS'];
    }
    public static function browse(WP_REST_Request $r): array {
        $args=['post_type'=>'oi_fiche','post_status'=>'publish','post__in'=>self::catalogue()?:[0],'posts_per_page'=>-1,'fields'=>'ids','orderby'=>'title','order'=>'ASC'];
        $filters=[];
        if($r['semestre'])$filters[]=self::tax_filter('oi_semestre',absint($r['semestre']));
        $has_unit=$r->has_param('enseignement');
        if($has_unit)$filters[]=self::tax_filter('oi_enseignement',absint($r['enseignement']));
        if($filters)$args['tax_query']=$filters;
        $ids=get_posts($args);
        $taxonomy=$has_unit?'oi_theme':'oi_enseignement';
        $folders=[];
        $programme=get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'meta_query'=>[$has_unit?['key'=>'oi_programme_unit','value'=>absint($r['enseignement'])]:['key'=>'oi_programme_code','compare'=>'EXISTS']]]);
        if(!is_wp_error($programme))foreach($programme as $term)if(!get_term_meta($term->term_id,'oi_programme_legacy',true))$folders[$term->term_id]=['id'=>(int)$term->term_id,'name'=>$term->name,'count'=>0];
        foreach($ids as $id){
            $terms=wp_get_post_terms($id,$taxonomy);
            if(is_wp_error($terms))continue;
            if(!$terms)$terms=[(object)['term_id'=>0,'name'=>$has_unit?'Autres thèmes':'Fiches à classer']];
            foreach($terms as $term){
                $key=(int)$term->term_id;
                if(!isset($folders[$key]))$folders[$key]=['id'=>$key,'name'=>$term->name,'count'=>0];
                $folders[$key]['count']++;
            }
        }
        uasort($folders,fn($a,$b)=>($a['id']===0)-($b['id']===0) ?: strnatcasecmp(remove_accents($a['name']),remove_accents($b['name'])));
        return ['folders'=>array_values($folders),'total'=>count($ids),'level'=>$has_unit?'theme':'enseignement'];
    }
    public static function recent(): array {
        $user=get_current_user_id();$state=OI_Revision::all($user);
        $allowed=OI_Model::allowed($user);
        $seen=array_filter($state,fn($s,$id)=>!empty($s['viewed_at'])&&in_array((int)$id,$allowed,true),ARRAY_FILTER_USE_BOTH);
        uasort($seen,fn($a,$b)=>($b['viewed_order']??strtotime($b['viewed_at'].' UTC'))<=>($a['viewed_order']??strtotime($a['viewed_at'].' UTC')));
        $result=[];
        foreach(array_slice(array_keys($seen),0,6) as $id){
            $post=get_post($id);
            if($post&&$post->post_type==='oi_fiche'&&$post->post_status==='publish')$result[]=OI_API::summary($post);
        }
        return $result;
    }
}
