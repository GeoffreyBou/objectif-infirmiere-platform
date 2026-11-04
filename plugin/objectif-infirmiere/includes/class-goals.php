<?php
defined('ABSPATH') || exit;
final class OI_Goals {
    public static function routes(): void {
        foreach(['/goals'=>[['GET','listing'],['POST','save']],'/goals/catalogue'=>[['GET','catalogue']],'/goals/(?P<id>[a-f0-9-]{36})/activate'=>[['POST','activate']],'/goals/(?P<id>[a-f0-9-]{36})/complete'=>[['POST','complete']]] as $path=>$endpoints){$args=[];foreach($endpoints as [$method,$callback])$args[]=['methods'=>$method,'permission_callback'=>[OI_API::class,'auth'],'callback'=>[self::class,$callback]];register_rest_route('oi/v1',$path,$args);}
    }
    private static function store(int $user): array {$data=get_user_meta($user,'oi_goals',true);return is_array($data)&&isset($data['active'],$data['goals'])&&is_array($data['goals'])?$data:['active'=>'','goals'=>[]];}
    private static function write(callable $callback): array|WP_Error {
        global $wpdb;$user=get_current_user_id();$lock='oi_goals_'.md5(DB_NAME.$wpdb->prefix.$user);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)',$lock))!==1)return new WP_Error('oi_busy','Enregistrement en cours. Réessaie.',['status'=>409]);
        try{wp_cache_delete($user,'user_meta');$store=self::store($user);$next=$callback($store);if(is_wp_error($next))return $next;if($next!==$store&&!update_user_meta($user,'oi_goals',$next))throw new RuntimeException('Enregistrement impossible. Réessaie.');return self::listing();}
        catch(Throwable $e){return new WP_Error('oi_goal',$e->getMessage(),['status'=>400]);}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function catalogue(): array {
        $units=[];$themes=[];
        foreach(get_terms(['taxonomy'=>'oi_enseignement','hide_empty'=>false]) as $t)$units[$t->term_id]=['id'=>$t->term_id,'name'=>$t->name,'themes'=>[]];
        foreach(get_terms(['taxonomy'=>'oi_theme','hide_empty'=>false]) as $t){if(get_term_meta($t->term_id,'oi_programme_legacy',true))continue;$parent=(int)get_term_meta($t->term_id,'oi_programme_unit',true);if(isset($units[$parent])){$themes[$t->term_id]=$parent;$units[$parent]['themes'][$t->term_id]=['id'=>$t->term_id,'name'=>$t->name,'fiches'=>[]];}}
        $posts=get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','post__in'=>OI_Library::catalogue()?:[0],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        foreach($posts as $p){$ues=wp_get_object_terms($p->ID,'oi_enseignement',['fields'=>'ids']);$ts=wp_get_object_terms($p->ID,'oi_theme',['fields'=>'ids']);$u=$ues[0]??0;$t=0;foreach($ts as $candidate)if(($themes[$candidate]??-1)===$u){$t=$candidate;break;}
            if(!isset($units[$u]))$units[$u]=['id'=>$u,'name'=>'Autres unités','themes'=>[]];if(!isset($units[$u]['themes'][$t]))$units[$u]['themes'][$t]=['id'=>$t,'name'=>'Autres notions','fiches'=>[]];
            $units[$u]['themes'][$t]['fiches'][]=['id'=>$p->ID,'title'=>$p->post_title,'accessible'=>OI_Offer::can_access(get_current_user_id(),'FICHE',$p->ID)];
        }
        uasort($units,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));foreach($units as &$unit){uasort($unit['themes'],fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));$unit['themes']=array_values($unit['themes']);}unset($unit);return ['units'=>array_values($units)];
    }
    private static function day(string $day,DateTimeZone $tz): DateTimeImmutable {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$day,$tz);if(!$date||$date->format('Y-m-d')!==$day)throw new RuntimeException('Choisis une date valide.');return $date;
    }
    private static function schedule_input(array $input): array {
        $zone=$input['timezone']??'Europe/Paris';if(!is_string($zone)||!in_array($zone,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true))throw new RuntimeException('Fuseau horaire invalide.');$tz=new DateTimeZone($zone);
        $mode=$input['mode']??'none';if(!in_array($mode,['none','deadline','duration'],true))throw new RuntimeException('Mode de planning invalide.');if($mode==='none')return ['mode'=>'none','timezone'=>$zone];
        $start=self::day((string)($input['start']??''),$tz);$today=new DateTimeImmutable('today',$tz);
        if($start<$today->modify('-365 days')||$start>$today->modify('+365 days'))throw new RuntimeException('La date de début doit rester dans l’année autour d’aujourd’hui.');
        $duration=$input['duration_days']??28;if(!is_int($duration)||$duration<1||$duration>365)throw new RuntimeException('Choisis une durée entre 1 et 365 jours.');
        $end=$mode==='duration'?$start->modify('+'.($duration-1).' days'):self::day((string)($input['deadline']??''),$tz);
        if($end<$start||$end>$start->modify('+365 days'))throw new RuntimeException('L’échéance doit suivre le début, dans un délai maximal d’un an.');
        $days=$input['weekdays']??[];if(!is_array($days)||!$days||count($days)>7)throw new RuntimeException('Choisis au moins un jour de révision.');foreach($days as $d)if(!is_int($d)||$d<1||$d>7)throw new RuntimeException('Jour de révision invalide.');$days=array_values(array_unique($days));sort($days);
        $minutes=$input['minutes']??45;$estimate=$input['estimate']??20;if(!is_int($minutes)||$minutes<10||$minutes>240||!is_int($estimate)||$estimate<5||$estimate>180)throw new RuntimeException('Temps de séance : 10 à 240 min ; temps estimé par fiche : 5 à 180 min.');
        return ['mode'=>$mode,'timezone'=>$zone,'start'=>$start->format('Y-m-d'),'deadline'=>$end->format('Y-m-d'),'duration_days'=>$duration,'weekdays'=>$days,'minutes'=>$minutes,'estimate'=>$estimate];
    }
    public static function save(WP_REST_Request $r): array|WP_Error {
        return self::write(function($store)use($r){
            $id=(string)($r['id']??'');$old=$id?($store['goals'][$id]??null):null;if($id&&!$old)return new WP_Error('oi_missing','Objectif introuvable.',['status'=>404]);
            if($old&&(int)$r['version']!==$old['version'])return new WP_Error('oi_conflict','Cet objectif a été modifié ailleurs. Recharge la page avant de l’enregistrer.',['status'=>409]);
            if(!$old&&count($store['goals'])>=50)throw new RuntimeException('Tu as atteint la limite de 50 objectifs enregistrés. Modifie un objectif existant.');
            $title=sanitize_text_field($r['title']);if(!$title||mb_strlen($title)>100)throw new RuntimeException('Donne un nom à ton objectif (100 caractères maximum).');
            $ids=$r['selected'];if(!is_array($ids)||!$ids||count($ids)>1000)throw new RuntimeException('Sélectionne entre 1 et 1 000 fiches.');foreach($ids as $fid)if(!is_int($fid)||$fid<1)throw new RuntimeException('Sélection de fiches invalide.');$ids=array_values(array_unique($ids));
            $allowed=array_values(array_filter(OI_Library::catalogue(),fn($fid)=>get_post_type($fid)==='oi_fiche'&&get_post_status($fid)==='publish'));foreach($ids as $fid)if(!in_array($fid,$allowed,true)&&!in_array($fid,$old['selected']??[],true))throw new RuntimeException('Une fiche sélectionnée ne figure plus dans ta bibliothèque.');
            $exam=$r['exam']??'mixte';if(!in_array($exam,['mixte','qcm','qroc','cas','oral','pratique'],true))throw new RuntimeException('Type de partiel invalide.');$input=$r['schedule']??[];if(!is_array($input))throw new RuntimeException('Planning invalide.');$schedule=self::schedule_input($input);
            $titles=[];foreach($ids as $fid)$titles[$fid]=get_post_status($fid)==='publish'?get_the_title($fid):($old['titles'][$fid]??'Fiche indisponible');
            $id=$id?:wp_generate_uuid4();$done=array_intersect_key($old['done']??[],array_flip($ids));$store['goals'][$id]=['id'=>$id,'title'=>$title,'selected'=>$ids,'titles'=>$titles,'done'=>$done,'exam'=>$exam,'schedule'=>$schedule,'version'=>($old['version']??0)+1,'created'=>$old['created']??time(),'updated'=>time()];$store['active']=$id;return $store;
        });
    }
    public static function activate(WP_REST_Request $r): array|WP_Error {return self::write(function($s)use($r){$id=(string)$r['id'];if(!isset($s['goals'][$id]))return new WP_Error('oi_missing','Objectif introuvable.',['status'=>404]);$s['active']=$id;return $s;});}
    public static function complete(WP_REST_Request $r): array|WP_Error {
        return self::write(function($s)use($r){$id=(string)$r['id'];if(!isset($s['goals'][$id]))return new WP_Error('oi_missing','Objectif introuvable.',['status'=>404]);$g=&$s['goals'][$id];$fid=absint($r['fiche']);
            if((int)$r['version']!==$g['version'])return new WP_Error('oi_conflict','Ton objectif a changé. Recharge sa progression avant de continuer.',['status'=>409]);
            if(!in_array($fid,$g['selected'],true)||!is_bool($r['done']))throw new RuntimeException('Fiche ou état invalide.');if($r['done'])$g['done'][$fid]=time();else unset($g['done'][$fid]);$g['version']++;$g['updated']=time();return $s;
        });
    }
    private static function summary(array $g): array {$done=count(array_intersect($g['selected'],array_map('intval',array_keys($g['done']))));$total=count($g['selected']);return ['id'=>$g['id'],'title'=>$g['title'],'version'=>$g['version'],'total'=>$total,'completed'=>$done,'percent'=>$total?(int)floor($done/$total*100):0];}
    public static function plan(array $g,?string $today=null): array {
        $s=$g['schedule'];$remaining=array_values(array_diff($g['selected'],array_map('intval',array_keys($g['done']))));
        if($s['mode']==='none')return ['enabled'=>false,'remaining'=>count($remaining)];
        $tz=new DateTimeZone($s['timezone']);$now=self::day($today??(new DateTimeImmutable('today',$tz))->format('Y-m-d'),$tz);$start=self::day($s['start'],$tz);$end=self::day($s['deadline'],$tz);$dates=[];
        for($d=max($now,$start);$d<=$end;$d=$d->modify('+1 day'))if(in_array((int)$d->format('N'),$s['weekdays'],true))$dates[]=$d->format('Y-m-d');
        $slots=count($dates);$capacity=$slots*(int)floor($s['minutes']/$s['estimate']);$placed=min(count($remaining),$capacity);$sessions=[];
        for($i=0;$i<$placed;$i++){$date=$dates[(int)floor($i*$slots/$placed)];$sessions[$date][]=$remaining[$i];}
        $items=[];foreach($sessions as $date=>$ids)$items[]=['date'=>$date,'fiches'=>$ids,'minutes'=>count($ids)*$s['estimate']];
        return ['enabled'=>true,'expired'=>$end<$now,'remaining'=>count($remaining),'available_days'=>$slots,'capacity'=>$capacity,'estimated_minutes'=>count($remaining)*$s['estimate'],'unplanned'=>array_slice($remaining,$placed),'sessions'=>$items,'today'=>$now->format('Y-m-d')];
    }
    public static function for_fiche(int $user,int $fiche): ?array {$s=self::store($user);$g=$s['goals'][$s['active']]??null;return $g&&in_array($fiche,$g['selected'],true)?['id'=>$g['id'],'title'=>$g['title'],'version'=>$g['version'],'done'=>isset($g['done'][$fiche])]:null;}
    public static function listing(): array {
        $s=self::store(get_current_user_id());$goal=$s['goals'][$s['active']]??null;$goals=array_values($s['goals']);usort($goals,fn($a,$b)=>$b['created']<=>$a['created']);
        if($goal){$goal+=self::summary($goal);$goal['plan']=self::plan($goal);$catalogue=OI_Library::catalogue();$goal['fiches']=[];foreach($goal['selected'] as $id){$available=in_array($id,$catalogue,true)&&get_post_status($id)==='publish';$goal['fiches'][]=['id'=>$id,'title'=>$available?get_the_title($id):$goal['titles'][$id],'done'=>isset($goal['done'][$id]),'available'=>$available,'accessible'=>$available&&OI_Offer::can_access(get_current_user_id(),'FICHE',$id)];}
            $scheduling=$goal['schedule'];$prompt='Aide-moi à organiser mes révisions pour mon objectif « '.$goal['title'].' ». Type de partiel : '.$goal['exam'].'. J’ai révisé '.$goal['completed'].' fiches sur '.$goal['total'].'. ';
            if($scheduling['mode']!=='none')$prompt.='Échéance : '.$scheduling['deadline'].'. Disponibilité : '.$scheduling['minutes'].' minutes les jours '.implode(', ',array_map(fn($d)=>['','lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'][$d],$scheduling['weekdays'])).'. ';
            $remaining=array_values(array_filter($goal['fiches'],fn($f)=>!$f['done']));$prompt.=($remaining?'Notions à travailler : ':'Notions à consolider : ').implode(', ',array_column(array_slice($remaining?:$goal['fiches'],0,15),'title')).'. Propose une méthode adaptée au type de partiel, du rappel actif et des révisions espacées. Signale si le temps prévu paraît insuffisant.';$goal['assistant_prompt']=mb_substr($prompt,0,1950);
        }
        return ['active'=>$goal,'goals'=>array_map([self::class,'summary'],$goals)];
    }
}
