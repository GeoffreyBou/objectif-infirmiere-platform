<?php
defined('ABSPATH') || exit;
/** Retrieval is scoped before generation; only short verified excerpts reach the model. */
final class OI_AI_Sources {
    public const DOMAINS=['has-sante.fr','ansm.sante.fr','santepubliquefrance.fr','sante.gouv.fr','sante.fr','legifrance.gouv.fr','sf2h.net','preventioninfection.fr','who.int','inserm.fr','ameli.fr','base-donnees-publique.medicaments.gouv.fr'];
    public static function retrieve(string $store,array $keys,array $files,string $question): array|WP_Error {
        if(!$keys || !$files)return [];
        $result=OI_AI::request('POST','vector_stores/'.rawurlencode($store).'/search',[
            'query'=>$question,'max_num_results'=>12,'rewrite_query'=>true,
            'filters'=>['type'=>'in','key'=>'fiche_key','value'=>array_values($keys)],
        ]);
        if(is_wp_error($result))return $result;
        $evidence=[];$chars=0;
        foreach($result['data']??[] as $hit){
            $file=$hit['file_id']??'';$id=$files[$file]??0;
            if(!$id || !OI_Model::can_read(get_current_user_id(),$id) || get_post_meta($id,'oi_ai_hash',true)!==OI_AI::hash($id))continue;
            if(count($evidence)>=6 && !isset($evidence[$id]))continue;
            if(isset($evidence[$id]))continue; // Keep the best matching chunk per document.
            $text='';foreach($hit['content']??[] as $part)if(($part['type']??'')==='text' && is_string($part['text']??null))$text.=$part['text']."\n";
            $text=mb_substr(trim($text),0,min(2200,12000-$chars));if(!$text)continue;
            $chars+=mb_strlen($text);$evidence[$id]=['id'=>$id,'title'=>get_the_title($id),'excerpt'=>$text];
            if($chars>=12000)break;
        }
        return array_values($evidence);
    }
    public static function web_url(mixed $url): string {
        if(!is_string($url)||strlen($url)>3000)return '';
        $p=wp_parse_url($url);
        if(!is_array($p)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||(isset($p['port'])&&$p['port']!==443))return '';
        $host=strtolower($p['host']??'');$allowed=false;
        foreach(self::DOMAINS as $domain)if($host===$domain||str_ends_with($host,'.'.$domain)){$allowed=true;break;}
        if(!$allowed)return '';
        $query=[];parse_str($p['query']??'',$query);foreach(array_keys($query)as$key)if(str_starts_with(strtolower($key),'utm_')||in_array(strtolower($key),['trk','fbclid','gclid','mc_cid','mc_eid'],true))unset($query[$key]);
        return 'https://'.$host.($p['path']??'/').($query?'?'.http_build_query($query):'');
    }
    public static function reference_text(string $text,array $sources): string|WP_Error {
        $refs=[];foreach($sources as $i=>$source)$refs[$source['url']]=$i+1;
        $invalid=false;
        $replace=static function(string $raw)use($refs,&$invalid):string{
            $url=self::web_url(html_entity_decode($raw,ENT_QUOTES|ENT_HTML5,'UTF-8'));
            if(!$url||!isset($refs[$url])){$invalid=true;return '';}
            return '[R'.$refs[$url].']';
        };
        $text=preg_replace_callback('~\[[^\]\r\n]*\]\((https?://[^\s)]+)\)~u',fn($m)=>$replace($m[1]),$text);
        $text=preg_replace_callback('~https?://[^\s<>\]\)]+~u',fn($m)=>$replace(rtrim($m[0],'.;,!')),$text);
        if($invalid)return new WP_Error('oi_ai_reference','Référence externe non vérifiée.');
        $missing=[];foreach($refs as $number)if(!str_contains($text,'[R'.$number.']'))$missing[]='[R'.$number.']';
        if($missing)$text.="\nRéférences : ".implode(' ',$missing);
        return $text;
    }
    public static function web_evidence(array $response): array {
        $found=[];$completed=false;
        foreach($response['output']??[] as $output){
            if(($output['type']??'')==='web_search_call' && ($output['status']??'')==='completed'){
                $completed=true;
                foreach($output['action']['sources']??[] as $source){$url=self::web_url($source['url']??'');if($url)$found[$url]=['url'=>$url,'title'=>is_string($source['title']??null)?sanitize_text_field($source['title']):wp_parse_url($url,PHP_URL_HOST)];}
            }
        }
        // Native provider annotations are search evidence too; plain URLs inside model text are not.
        if(!$completed)return [];
        foreach($response['output']??[] as $output)foreach($output['content']??[] as $content)foreach($content['annotations']??[] as $citation){
            if(($citation['type']??'')!=='url_citation')continue;
            $url=self::web_url($citation['url']??'');if($url)$found[$url]=['url'=>$url,'title'=>!empty($citation['title'])?sanitize_text_field($citation['title']):wp_parse_url($url,PHP_URL_HOST)];
        }
        return $found;
    }
}
