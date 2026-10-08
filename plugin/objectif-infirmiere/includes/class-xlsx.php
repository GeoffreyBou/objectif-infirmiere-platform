<?php
defined('ABSPATH') || exit;
/** Bounded, data-only reader for the supplied QCM workbook format. */
final class OI_Xlsx {
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    public static function read(string $file): array {
        if(!is_file($file)||filesize($file)>5*1024*1024)throw new RuntimeException('Excel trop volumineux (5 Mo maximum).');
        $zip=new ZipArchive();if($zip->open($file)!==true)throw new RuntimeException('Choisis un fichier Excel .xlsx valide.');
        try{
            $size=0;if($zip->numFiles>500)throw new RuntimeException('Classeur trop complexe.');
            for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$size+=$s['size'];if($size>20*1024*1024||$s['size']>8*1024*1024)throw new RuntimeException('Classeur décompressé trop volumineux.');if(str_contains(strtolower($s['name']),'vbaproject'))throw new RuntimeException('Les macros ne sont pas acceptées.');}
            $work=self::xml($zip,'xl/workbook.xml');$sheets=$work->getElementsByTagNameNS(self::NS,'sheet');$sheet=null;
            foreach($sheets as $s)if(mb_strtolower($s->getAttribute('name'))==='questions')$sheet=$s;
            if(!$sheet)throw new RuntimeException('Le classeur doit contenir un onglet Questions. Télécharge le modèle.');
            $rid=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$rels=self::xml($zip,'xl/_rels/workbook.xml.rels');$path='';
            foreach($rels->getElementsByTagName('Relationship') as $r)if($r->getAttribute('Id')===$rid&&$r->getAttribute('TargetMode')!=='External'){$target=ltrim($r->getAttribute('Target'),'/');$path=str_starts_with($target,'xl/')?$target:'xl/'.$target;}
            if(!preg_match('~^xl/worksheets/[A-Za-z0-9_-]+\.xml$~',$path))throw new RuntimeException('Feuille Questions invalide.');
            $strings=[];if($zip->locateName('xl/sharedStrings.xml')!==false)foreach(self::xml($zip,'xl/sharedStrings.xml')->getElementsByTagNameNS(self::NS,'si') as $si)$strings[]=self::text($si);
            $rows=[];foreach(self::xml($zip,$path)->getElementsByTagNameNS(self::NS,'row') as $row){$values=[];
                foreach($row->getElementsByTagNameNS(self::NS,'c') as $c){if($c->getElementsByTagNameNS(self::NS,'f')->length)throw new RuntimeException('Ligne '.$row->getAttribute('r').' : remplace les formules par des valeurs.');
                    if(!preg_match('/^([A-Z]{1,2})\d+$/',$c->getAttribute('r'),$m))throw new RuntimeException('Adresse de cellule invalide.');
                    $col=0;foreach(str_split($m[1]) as $letter)$col=$col*26+ord($letter)-64;if($col>30)throw new RuntimeException('Utilise les colonnes du modèle (30 maximum).');
                    $type=$c->getAttribute('t');$v=$c->getElementsByTagNameNS(self::NS,'v')->item(0)?->textContent??'';
                    if($type==='e')throw new RuntimeException('Ligne '.$row->getAttribute('r').' : une cellule contient une erreur Excel.');
                    $value=$type==='inlineStr'?self::text($c):($type==='s'?($strings[(int)$v]??''):$v);
                    if(mb_strlen($value)>10000)throw new RuntimeException('Cellule trop longue.');$values[$col-1]=trim($value);
                }
                if(array_filter($values,fn($v)=>$v!==''))$rows[]=['line'=>(int)$row->getAttribute('r'),'values'=>$values];
                if(count($rows)>201)throw new RuntimeException('200 questions maximum par fiche et par classeur.');
            }
            if(count($rows)<2)throw new RuntimeException('Le classeur ne contient aucune question.');
            $header=array_shift($rows)['values'];$headers=[];foreach($header as $index=>$name){$name=strtolower(remove_accents(trim($name)));$name=preg_replace('/[ -]+/','_',$name);if(in_array($name,$headers,true))throw new RuntimeException('Colonne répétée : '.$name);$headers[$index]=$name;}
            foreach(['code_question','enonce','a','b','bonnes_reponses','explication'] as $required)if(!in_array($required,$headers,true))throw new RuntimeException('Colonne manquante : '.$required);
            $result=[];foreach($rows as $row){$data=['line'=>$row['line']];foreach($headers as $i=>$name)$data[$name]=$row['values'][$i]??'';$result[]=$data;}return $result;
        }finally{$zip->close();}
    }
    private static function text(DOMElement $el): string {$text='';foreach($el->getElementsByTagNameNS(self::NS,'t') as $t)$text.=$t->textContent;return $text;}
    private static function xml(ZipArchive $zip,string $path): DOMDocument {
        $raw=$zip->getFromName($path);if($raw===false||stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('Structure Excel refusée.');
        $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);try{if(!$doc->loadXML($raw,LIBXML_NONET))throw new RuntimeException('XML Excel invalide.');}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}return $doc;
    }
    public static function questions(array $rows,string $code): array {
        $result=[];$seen=[];$errors=[];
        foreach($rows as $row){$line=$row['line']??'?';try{
            $id=strtoupper(trim($row['code_question']??''));if(!preg_match('/^'.preg_quote($code,'/').'-Q\d{3,}$/',$id))throw new RuntimeException('code_question attendu : '.$code.'-Q001, Q002…');
            if(isset($seen[$id]))throw new RuntimeException('code question répété : '.$id);$seen[$id]=true;
            if(!empty($row['code_fiche'])&&strtoupper(trim($row['code_fiche']))!==$code)throw new RuntimeException('le code fiche ne correspond pas au fichier.');
            $active=strtolower(remove_accents(trim($row['statut']??'')));if(!in_array($active,['','actif','archive'],true))throw new RuntimeException('statut attendu : actif ou archive.');
            $options=[];$map=[];foreach(range('A','H') as $letter){$v=sanitize_text_field($row[strtolower($letter)]??'');if($v!==''){$map[$letter]=count($options);if(mb_strlen($v)>1000)throw new RuntimeException('proposition de plus de 1 000 caractères.');$options[]=$v;}}
            $question=sanitize_textarea_field($row['enonce']??'');$explanation=sanitize_textarea_field($row['explication']??'');
            if(mb_strlen($question)>2000||mb_strlen($explanation)>6000)throw new RuntimeException('énoncé limité à 2 000 caractères et explication à 6 000.');
            if(!$question||count($options)<2||!$explanation)throw new RuntimeException('énoncé, au moins deux réponses et explication requis.');
            $correct=[];foreach(preg_split('/[;,\s]+/',strtoupper(trim($row['bonnes_reponses']??'')),-1,PREG_SPLIT_NO_EMPTY) as $letter){if(!isset($map[$letter]))throw new RuntimeException('bonne réponse absente ou invalide : '.$letter);$correct[]=$map[$letter];}
            $correct=array_values(array_unique($correct));sort($correct);if(!$correct)throw new RuntimeException('indique au moins une bonne réponse.');
            $result[]=['code'=>$id,'question'=>$question,'options'=>$options,'correct'=>$correct,'explanation'=>$explanation,'active'=>$active!=='archive'];
        }catch(Throwable $e){$errors[]='Ligne '.$line.' : '.$e->getMessage();}}
        if($errors)throw new RuntimeException(implode("\n",array_slice($errors,0,20)));return $result;
    }
}
