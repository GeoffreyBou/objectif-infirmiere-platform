<?php
defined('ABSPATH') || exit;
final class OI_Curriculum {
    public const VERSION = 1;
    public static function install(): void {
        if((int)get_option('oi_curriculum_version')>=self::VERSION)return;
        // A short lease avoids duplicate term creation on simultaneous first requests.
        $lease=(int)get_option('oi_curriculum_installing');
        if($lease&&$lease<time()-120)delete_option('oi_curriculum_installing');
        if(!add_option('oi_curriculum_installing',time(),'','no'))return;
        try{
            $units=json_decode(file_get_contents(OI_DIR.'data/programme-2026.json'),true,512,JSON_THROW_ON_ERROR);
            $index=[];
            foreach($units as $unit){
                $code=$unit['code'];$id=self::term('oi_enseignement','programme-2026-'.strtolower($code),$code.' — '.$unit['name']);
                update_term_meta($id,'oi_programme_code',$code);$index[$code]=['id'=>$id,'themes'=>[]];
                foreach($unit['themes'] as $i=>$title){
                    $number=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);
                    $theme=self::term('oi_theme','programme-2026-'.strtolower($code).'-'.$number,$code.' - '.$number.' '.$title);
                    update_term_meta($theme,'oi_programme_unit',$id);$index[$code]['themes'][$i+1]=$theme;
                }
            }
            // Initial catalogue only. Never overwrite an existing UE classification.
            $mapping=[
                'Hypokaliémie'=>['B1',1], 'Hyperkaliémie'=>['B1',1], 'Ionogramme sanguin'=>['B1',1],
                'Furosémide'=>['B1',2], 'Insuffisance cardiaque'=>['B1',2], 'Respiration : repères'=>['B1',2],
                'Hygiène des mains'=>['B3',2], 'Douleur : évaluation'=>['B3',3], 'Transmissions ciblées'=>['B3',3], 'Calculs de doses : méthode'=>['B3',12],
            ];
            foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','numberposts'=>-1]) as $post){
                if(!isset($mapping[$post->post_title]))continue;
                $old=wp_get_object_terms($post->ID,'oi_enseignement',['fields'=>'ids']);
                if(is_wp_error($old)||$old)continue;
                [$code,$number]=$mapping[$post->post_title];
                if(!metadata_exists('post',$post->ID,'oi_before_curriculum'))add_post_meta($post->ID,'oi_before_curriculum',['enseignement'=>$old,'theme'=>wp_get_object_terms($post->ID,'oi_theme',['fields'=>'ids'])],true);
                $ue=wp_set_object_terms($post->ID,[$index[$code]['id']],'oi_enseignement');
                $theme=wp_set_object_terms($post->ID,[$index[$code]['themes'][$number]],'oi_theme');
                if(is_wp_error($ue)||is_wp_error($theme))throw new RuntimeException('Classement impossible');
            }
            update_option('oi_curriculum_version',self::VERSION,false);
        }catch(Throwable $e){OI_Log::event('curriculum_install_error');}
        finally{delete_option('oi_curriculum_installing');}
    }
    private static function term(string $tax,string $slug,string $name): int {
        $term=get_term_by('slug',$slug,$tax);
        if($term)return (int)$term->term_id;
        $created=wp_insert_term($name,$tax,['slug'=>$slug]);
        if(is_wp_error($created))throw new RuntimeException('Création de dossier impossible');
        return (int)$created['term_id'];
    }
}
