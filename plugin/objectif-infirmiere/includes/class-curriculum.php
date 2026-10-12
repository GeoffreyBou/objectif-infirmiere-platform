<?php
defined('ABSPATH') || exit;
final class OI_Curriculum {
    public const VERSION = 4;
    public static function install(): void {
        $installedVersion=(int)get_option('oi_curriculum_version');if($installedVersion>=self::VERSION)return;
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
                if(self::uses_drive($code)){
                    // Retire the bullet-point themes without reusing their identifiers.
                    foreach(get_terms(['taxonomy'=>'oi_theme','hide_empty'=>false,'meta_key'=>'oi_programme_unit','meta_value'=>$id]) as $existing)
                        if(!get_term_meta($existing->term_id,'oi_drive_theme',true))update_term_meta($existing->term_id,'oi_programme_legacy',1);
                    foreach($unit['themes'] as $definition)self::drive_theme($code,$definition['code'].' - '.$definition['name']);
                    continue;
                }
                foreach($unit['themes'] as $definition){
                    $number=$definition['code'];$title=$definition['name'];
                    $slug='programme-2026-'.strtolower($code).'-'.$number;$name=$code.' - '.$number.' '.$title;
                    $old=get_term_by('slug',$slug,'oi_theme');
                    if($old&&sanitize_title($old->name)!==sanitize_title($name)){
                        $renamed=wp_update_term($old->term_id,'oi_theme',['slug'=>$slug.'-ancien-'.$old->term_id]);if(is_wp_error($renamed))throw new RuntimeException('Migration de thème impossible');
                        update_term_meta($old->term_id,'oi_programme_legacy',1);
                    }
                    $theme=self::term('oi_theme',$slug,$name);delete_term_meta($theme,'oi_programme_legacy');
                    update_term_meta($theme,'oi_programme_notions',$definition['notions']);update_term_meta($theme,'oi_programme_source_line',$definition['source_line']);
                    update_term_meta($theme,'oi_programme_unit',$id);update_term_meta($theme,'oi_programme_code',$code.'-'.$number);$index[$code]['themes'][(int)$number]=$theme;
                }
            }
            // Initial catalogue only. Never overwrite an existing UE classification.
            $mapping=[
                'Hypokaliémie'=>['B1',1], 'Hyperkaliémie'=>['B1',1], 'Ionogramme sanguin'=>['B1',1],
                'Furosémide'=>['B1',2], 'Insuffisance cardiaque'=>['B1',2], 'Respiration : repères'=>['B1',2],
                'Hygiène des mains'=>['B3',2], 'Douleur : évaluation'=>['B3',3], 'Transmissions ciblées'=>['B3',3], 'Calculs de doses : méthode'=>['B3',12],
            ];
            if($installedVersion===0)foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','numberposts'=>-1]) as $post){
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
    public static function uses_drive(string $code): bool {
        return in_array($code,['B4','C2','D2','E2'],true);
    }
    public static function drive_theme(string $code,string $label): int {
        if(!self::uses_drive($code)||!preg_match('/^(\d{1,2})[\s_\-–—]+(.+)$/u',trim($label),$m))return 0;
        $number=str_pad($m[1],2,'0',STR_PAD_LEFT);$title=sanitize_text_field($m[2]);
        $unit=get_term_by('slug','programme-2026-'.strtolower($code),'oi_enseignement');if(!$unit)return 0;
        $slug='drive-'.strtolower($code).'-'.substr(hash('sha256',sanitize_title($label)),0,16);
        $name=$code.' - '.$number.' '.$title;$same=get_term_by('name',$name,'oi_theme');
        $id=$same?(int)$same->term_id:self::term('oi_theme',$slug,$name);
        if($same&&$same->name!==$name){$updated=wp_update_term($id,'oi_theme',['name'=>$name]);if(is_wp_error($updated))throw new RuntimeException('Mise à jour du thème impossible.');}
        update_term_meta($id,'oi_programme_unit',(int)$unit->term_id);update_term_meta($id,'oi_programme_code',$code.'-'.$number);
        update_term_meta($id,'oi_drive_theme',1);delete_term_meta($id,'oi_programme_legacy');return $id;
    }
    public static function sync_drive_folders(array $paths): void {
        foreach($paths as $path){
            if(OI_Drive::archived($path))continue;
            $count=count($path);if($count<2)continue;
            // Only immediate children of a UE folder, including empty folders.
            if(preg_match('/^([A-E])\.?([1-9])(?=$|[\s_\-–—])/iu',trim($path[$count-2]),$m))
                self::drive_theme(strtoupper($m[1].$m[2]),$path[$count-1]);
        }
    }
    public static function migrate_drive_fiche(int $id,int $unit,int $theme): bool {
        if(!$unit||!$theme||!get_term_meta($theme,'oi_drive_theme',true))return false;
        $units=wp_get_object_terms($id,'oi_enseignement',['fields'=>'ids']);$old=wp_get_object_terms($id,'oi_theme',['fields'=>'ids']);
        if(is_wp_error($units)||is_wp_error($old)||$units!==[$unit]||!$old)return false;
        foreach($old as $term)if(!get_term_meta($term,'oi_programme_legacy',true)||(int)get_term_meta($term,'oi_programme_unit',true)!==$unit)return false;
        add_post_meta($id,'oi_before_drive_themes',['themes'=>$old,'date'=>gmdate('c')],true);
        $result=wp_set_object_terms($id,[$theme],'oi_theme');if(is_wp_error($result))throw new RuntimeException('Rattachement de fiche impossible.');return true;
    }
    private static function term(string $tax,string $slug,string $name): int {
        $term=get_term_by('slug',$slug,$tax);
        if($term)return (int)$term->term_id;
        $created=wp_insert_term($name,$tax,['slug'=>$slug]);
        if(is_wp_error($created))throw new RuntimeException('Création de dossier impossible');
        return (int)$created['term_id'];
    }
}
