<?php
defined('ABSPATH') || exit;
final class OI_Docx {
    private ZipArchive $zip;
    private array $images=[],$warnings=[],$relations=[],$styles=[],$numbers=[],$links=[];
    private const W='http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    public static function convert(string $path): array {
        if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))throw new RuntimeException('Les extensions PHP ZIP et DOM sont nécessaires.');
        if(!is_file($path)||filesize($path)>12*1024*1024)throw new RuntimeException('Word trop volumineux (12 Mo maximum).');
        $self=new self();$self->zip=new ZipArchive();
        if($self->zip->open($path)!==true)throw new RuntimeException('Ce fichier n’est pas un document Word DOCX valide.');
        try{
            $size=0;if($self->zip->numFiles>2000)throw new RuntimeException('Archive Word trop complexe.');
            for($i=0;$i<$self->zip->numFiles;$i++){$stat=$self->zip->statIndex($i);$size+=$stat['size'];if($size>80*1024*1024||$stat['size']>20*1024*1024)throw new RuntimeException('Archive Word décompressée trop volumineuse.');}
            if($self->zip->locateName('word/vbaProject.bin')!==false)throw new RuntimeException('Les documents contenant des macros ne sont pas acceptés.');
            $xml=$self->xml('word/document.xml');$xp=new DOMXPath($xml);$xp->registerNamespace('w',self::W);
            if($self->zip->locateName('word/_rels/document.xml.rels')!==false){$rels=$self->xml('word/_rels/document.xml.rels');foreach($rels->getElementsByTagName('Relationship') as $r){if($r->getAttribute('TargetMode')!=='External')$self->relations[$r->getAttribute('Id')]=$r->getAttribute('Target');elseif(str_ends_with($r->getAttribute('Type'),'/hyperlink')&&in_array(wp_parse_url($r->getAttribute('Target'),PHP_URL_SCHEME),['https','http'],true))$self->links[$r->getAttribute('Id')]=$r->getAttribute('Target');}}
            if($self->zip->locateName('word/styles.xml')!==false){$styles=$self->xml('word/styles.xml');foreach($styles->getElementsByTagNameNS(self::W,'style') as $s){$name=$s->getElementsByTagNameNS(self::W,'name')->item(0);$self->styles[$s->getAttributeNS(self::W,'styleId')]=$name?->getAttributeNS(self::W,'val')??'';}}
            if($self->zip->locateName('word/numbering.xml')!==false){$numbering=$self->xml('word/numbering.xml');$formats=[];foreach($numbering->getElementsByTagNameNS(self::W,'abstractNum') as $n){$fmt=$n->getElementsByTagNameNS(self::W,'numFmt')->item(0);$formats[$n->getAttributeNS(self::W,'abstractNumId')]=$fmt?->getAttributeNS(self::W,'val')??'decimal';}foreach($numbering->getElementsByTagNameNS(self::W,'num') as $n){$abstract=$n->getElementsByTagNameNS(self::W,'abstractNumId')->item(0);$self->numbers[$n->getAttributeNS(self::W,'numId')]=$formats[$abstract?->getAttributeNS(self::W,'val')??'']??'decimal';}}
            $body=$xp->query('//w:body')->item(0);if(!$body)throw new RuntimeException('Corps du document introuvable.');
            $html=$self->blocks($body);
            if(!trim(wp_strip_all_tags($html))&&!$self->images)throw new RuntimeException('Le document ne contient aucun contenu importable.');
            if($xp->query('//w:ins|//w:del')->length)$self->warnings[]='Le document contient des modifications suivies : accepter ou refuser ces modifications dans Word avant publication.';
            if($xp->query('//w:footnoteReference|//w:endnoteReference')->length)$self->warnings[]='Notes de bas de page non importées : les reporter dans le corps du document.';
            if($xp->query('//w:object|//w:pict')->length)$self->warnings[]='Certains objets ou anciens dessins Word ne sont pas pris en charge.';
            if($xml->getElementsByTagNameNS('*','oMath')->length||$xml->getElementsByTagNameNS('*','chart')->length)$self->warnings[]='Équations ou graphiques Word non convertis : les remplacer par des images ou du texte avant publication.';
            if($xp->query('//w:vMerge')->length)$self->warnings[]='Vérifier les cellules fusionnées verticalement des tableaux.';
            return ['title'=>$self->title($body),'html'=>wp_kses_post($html),'images'=>$self->images,'warnings'=>array_values(array_unique($self->warnings))];
        }finally{$self->zip->close();}
    }
    private function title(DOMNode $body): string {
        // Only an explicit document title, or a leading Heading 1, is authoritative.
        $first=true;$leading='';
        foreach($body->childNodes as $p){
            if($p->localName!=='p')continue;
            $text='';foreach($p->getElementsByTagNameNS(self::W,'t') as $t)$text.=$t->textContent;
            $text=sanitize_text_field($text);if(!$text)continue;
            $id=$p->getElementsByTagNameNS(self::W,'pStyle')->item(0)?->getAttributeNS(self::W,'val')??'';
            $style=$this->styles[$id]??$id;
            if(!$first&&$leading&&preg_match('/^UE\s+[A-E]\.?[1-9]\b/u',$text))return $leading;
            if($first&&mb_strlen($text)<=200)$leading=$text;
            if(preg_match('/^(title|titre)$/i',$style)||($first&&preg_match('/^(heading|titre)\s*1$/i',$style)))return $text;
            $first=false;
        }
        return '';
    }
    private function xml(string $name): DOMDocument {
        $raw=$this->zip->getFromName($name);if($raw===false||stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('XML Word refusé.');
        $doc=new DOMDocument();$old=libxml_use_internal_errors(true);
        try{if(!$doc->loadXML($raw,LIBXML_NONET)||$doc->doctype)throw new RuntimeException('Structure Word invalide.');}finally{libxml_clear_errors();libxml_use_internal_errors($old);}return $doc;
    }
    private function blocks(DOMNode $node): string {
        $out='';$list='';
        foreach($node->childNodes as $child){
            if($child->localName==='p'){
                $isList=$child->getElementsByTagNameNS(self::W,'numPr')->length>0;
                $num=$child->getElementsByTagNameNS(self::W,'numId')->item(0);$listTag=$isList?(($this->numbers[$num?->getAttributeNS(self::W,'val')??'']??'decimal')==='bullet'?'ul':'ol'):'';
                if($list!==$listTag){if($list)$out.="</$list>";if($listTag)$out.="<$listTag>";$list=$listTag;}
                $level=$child->getElementsByTagNameNS(self::W,'ilvl')->item(0);if($level&&(int)$level->getAttributeNS(self::W,'val')>0)$this->warnings[]='Les sous-listes sont présentées à plat : vérifier leur hiérarchie.';
                $tag=$isList?'li':'p';$style=$child->getElementsByTagNameNS(self::W,'pStyle')->item(0);$id=$style?->getAttributeNS(self::W,'val')??'';
                if(!$isList&&preg_match('/(?:heading|titre)\s*([1-6])/i',($this->styles[$id]??'').' '.$id,$m))$tag='h'.min(6,(int)$m[1]+1);
                $content=$this->inline($child);if(trim($content))$out.="<$tag>$content</$tag>";
            }elseif($child->localName==='tbl'){
                if($list){$out.="</$list>";$list='';}$out.='<table><tbody>';
                foreach($child->childNodes as $row){if($row->localName!=='tr')continue;$out.='<tr>';foreach($row->childNodes as $cell){if($cell->localName!=='tc')continue;$span=$cell->getElementsByTagNameNS(self::W,'gridSpan')->item(0);$n=min(20,max(1,(int)($span?->getAttributeNS(self::W,'val')??1)));$out.='<td'.($n>1?' colspan="'.$n.'"':'').'>'.$this->blocks($cell).'</td>';}$out.='</tr>';}$out.='</tbody></table>';
            }
        }
        return $out.($list?"</$list>":'');
    }
    private function inline(DOMNode $node): string {
        $out='';foreach($node->childNodes as $c){
            if($c->localName==='t')$out.=esc_html($c->textContent);
            elseif(in_array($c->localName,['br','cr'],true))$out.='<br>';
            elseif($c->localName==='tab')$out.=' ';
            elseif($c->localName==='blip'){
                $rid=$c->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','embed');$target=$this->relations[$rid]??'';
                if(!preg_match('~^media/[^/\\\\]+$~',$target)){$this->warnings[]='Une image liée ou externe n’a pas été importée.';continue;}
                $bytes=$this->zip->getFromName('word/'.$target);$info=$bytes!==false?@getimagesizefromstring($bytes):false;
                if(!$info||!in_array($info['mime'],['image/png','image/jpeg','image/gif','image/webp'],true)||strlen($bytes)>3*1024*1024){$this->warnings[]='Une image non prise en charge ou de plus de 3 Mo a été ignorée.';continue;}
                $hash=hash('sha256',$bytes);$this->images[$hash]=['mime'=>$info['mime'],'data'=>base64_encode($bytes)];$out.='<img src="#oi-image-'.$hash.'" alt="Illustration de la fiche">';
            }elseif($c->localName==='hyperlink'){$text=$this->inline($c);$rid=$c->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$out.=isset($this->links[$rid])?'<a href="'.esc_url($this->links[$rid]).'" rel="noreferrer noopener">'.$text.'</a>':$text;
            }elseif($c->localName==='r'){
                $text=$this->inline($c);foreach(['b'=>'strong','i'=>'em','u'=>'u'] as $prop=>$tag){$p=$c->getElementsByTagNameNS(self::W,$prop)->item(0);if($p&&!in_array($p->getAttributeNS(self::W,'val'),['0','false','none'],true))$text="<$tag>$text</$tag>";}$v=$c->getElementsByTagNameNS(self::W,'vertAlign')->item(0)?->getAttributeNS(self::W,'val');if($v==='superscript')$text='<sup>'.$text.'</sup>';elseif($v==='subscript')$text='<sub>'.$text.'</sub>';$out.=$text;
            }elseif(!in_array($c->localName,['pPr','rPr','del','instrText'],true))$out.=$this->inline($c);
        }return $out;
    }
    public static function content(string $html,int $id): string {
        return preg_replace_callback('/#oi-image-([a-f0-9]{64})/',fn($m)=>esc_url(admin_url('admin-post.php?action=oi_fiche_image&fiche='.$id.'&image='.$m[1])),wp_kses_post($html));
    }
    public static function image(): void {
        $id=absint($_GET['fiche']??0);$hash=sanitize_text_field($_GET['image']??'');
        if(!is_user_logged_in()||(!current_user_can('manage_options')&&!OI_Model::can_read(get_current_user_id(),$id))){status_header(403);exit;}
        $images=get_post_meta($id,'oi_docx_images',true);$image=is_array($images)?($images[$hash]??null):null;
        if(!$image||!in_array($image['mime'],['image/png','image/jpeg','image/gif','image/webp'],true)){status_header(404);exit;}
        nocache_headers();header('Content-Type: '.$image['mime']);header('X-Content-Type-Options: nosniff');echo base64_decode($image['data'],true);exit;
    }
}
