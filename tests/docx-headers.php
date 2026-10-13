<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$checks=0;$files=[];$posts=[];
$check=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException($message);$checks++;};
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY1sAAAAASUVORK5CYII=');
// Distinct valid PNG byte streams distinguish a cover from a course image.
$bodyPng=$png."course-image";$logoHash=hash('sha256',$png);$bodyHash=hash('sha256',$bodyPng);
$image=static fn(string $hash):string=>'<p><img src="#oi-image-'.$hash.'" alt="Illustration de la fiche"></p>';
$drawing=static fn(string $id):string=>'<w:p><w:r><w:drawing><a:blip r:embed="'.$id.'"/></w:drawing></w:r></w:p>';
$paragraph=static fn(string $text,string $style=''):string=>'<w:p>'.($style?'<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>':'').'<w:r><w:t>'.htmlspecialchars($text,ENT_XML1).'</w:t></w:r></w:p>';
$word=static function(string $body,bool $withHeader=false)use(&$files,$png,$bodyPng):string{
    $file=tempnam(sys_get_temp_dir(),'oi-header-');$files[]=$file;$zip=new ZipArchive();$zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);
    $namespace='xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
    $zip->addFromString('word/document.xml','<w:document '.$namespace.'><w:body>'.$body.($withHeader?'<w:sectPr><w:headerReference w:type="default" r:id="header"/></w:sectPr>':'').'</w:body></w:document>');
    $zip->addFromString('word/_rels/document.xml.rels','<Relationships><Relationship Id="logo" Target="media/logo.png"/><Relationship Id="course" Target="media/course.png"/><Relationship Id="header" Target="header1.xml"/></Relationships>');
    $zip->addFromString('word/media/logo.png',$png);$zip->addFromString('word/media/course.png',$bodyPng);
    if($withHeader){
        $zip->addFromString('word/header1.xml','<w:hdr '.$namespace.'><w:p><w:r><w:drawing><a:blip r:embed="headerLogo"/></w:drawing></w:r></w:p></w:hdr>');
        $zip->addFromString('word/_rels/header1.xml.rels','<Relationships><Relationship Id="headerLogo" Target="media/logo.png"/></Relationships>');
    }
    $zip->close();return $file;
};
try{
    $title='Sang : composition et hématopoïèse';
    $course='<p>'.$title.'</p><p>UE B.1 · Sciences biomédicales</p>'."\n".'<p>Comprendre <strong>les cellules</strong> et leurs fonctions.</p><h2>1. Composition</h2>'.$image($bodyHash).'<p>Source : <a href="https://example.org/?a=1&amp;b=2">Référence</a></p>';
    $check(OI_Docx::without_header_images($image($logoHash).$course)===$course,'Logo initial exclu, reste du cours conservé à l’octet');
    $spaced=" \n<p></p>\t".$image($logoHash)."\n".$course;
    $check(OI_Docx::without_header_images($spaced)===" \n<p></p>\t\n".$course,'Espaces et paragraphes vides existants conservés');
    $explicit=$image($logoHash).'<p><strong>Hydratation &amp; équilibre</strong></p><p>Introduction.</p>';
    $check(OI_Docx::without_header_images($explicit,'Hydratation & équilibre')==='<p><strong>Hydratation &amp; équilibre</strong></p><p>Introduction.</p>','Titre explicite reconnu avec entités et emphase');
    $ambiguous=$image($logoHash).'<p>Repère anatomique</p><p>Une illustration utile au cours.</p>';
    $check(OI_Docx::without_header_images($ambiguous)===$ambiguous,'Image initiale conservée sans preuve de titre');
    $table='<table><tbody><tr><td>'.$image($logoHash).'</td></tr></tbody></table>'.$course;
    $check(OI_Docx::without_header_images($table,$title)===$table,'Tableau initial jamais traversé ni supprimé');
    $mixed='<p><img src="#oi-image-'.$logoHash.'">Légende pédagogique</p>'.$course;
    $check(OI_Docx::without_header_images($mixed,$title)===$mixed,'Image accompagnée de texte conservée');
    $check(OI_Docx::without_header_images($course,$title)===$course,'Cours sans image d’entête inchangé');
    $afterTitle='<p>'.$title.'</p>'.$image($bodyHash).'<p>UE B.1 · Sciences biomédicales</p>';
    $check(OI_Docx::without_header_images($afterTitle,$title)===$afterTitle,'Illustration après le titre conservée');
    $heading=$image($logoHash).'<h2>Physiopathologie</h2><p>Schéma du mécanisme.</p>';
    $check(OI_Docx::without_header_images($heading)===$heading,'Section sans titre prouvé conservée');
    $between=$image($logoHash).'<p>'.$title.'</p>'.$image($bodyHash).'<p>UE B.1 · Sciences biomédicales</p>';
    $check(OI_Docx::without_header_images($between)===$between,'Image intermédiaire bloque la déduction titre plus UE');
    $repeat=$image($logoHash).'<p>'.$title.'</p><p>UE B.1 · Sciences biomédicales</p><h2>Exemple</h2>'.$image($logoHash);
    $check(substr_count(OI_Docx::without_header_images($repeat),'#oi-image-'.$logoHash)===1,'Même image conservée quand elle réapparaît dans le cours');

    $body=$paragraph($title).$paragraph('UE B.1 · Sciences biomédicales').$paragraph('Introduction au cours.').$paragraph('1. Composition','Heading1').$drawing('course');
    $converted=OI_Docx::convert($word($drawing('logo').$body));
    $check($converted['title']===$title&&str_starts_with($converted['html'],'<p>'.$title.'</p>'),'Import Word : titre reconnu et logo initial exclu');
    $check(array_keys($converted['images'])===[$bodyHash]&&str_contains($converted['html'],'#oi-image-'.$bodyHash),'Import Word : seule l’illustration pédagogique est stockée');
    $same=OI_Docx::convert($word($drawing('logo').$body.$drawing('logo')));
    $check(isset($same['images'][$logoHash])&&substr_count($same['html'],'#oi-image-'.$logoHash)===1,'Import Word : image réutilisée dans le corps encore disponible');
    $justLogo=OI_Docx::convert($word($drawing('logo').$paragraph('Titre explicite','Title').$paragraph('Le cours est du texte.')));
    $check($justLogo['images']===[]&&!str_contains($justLogo['html'],'<img'),'Import Word : aucun octet de logo stocké quand il n’est plus utilisé');
    $headingTitle=OI_Docx::convert($word($drawing('logo').$paragraph('Titre principal','Heading1').$paragraph('Le cours commence ici.').$drawing('course')));
    $check($headingTitle['title']==='Titre principal'&&str_starts_with($headingTitle['html'],'<h2>Titre principal</h2>')&&array_keys($headingTitle['images'])===[$bodyHash],'Titre Word en Heading1 reconnu, image précédente exclue et image suivante conservée');
    $genuine=OI_Docx::convert($word($body,true));
    $check($genuine['html']===OI_Docx::convert($word($body))['html']&&array_keys($genuine['images'])===[$bodyHash],'Vrai en-tête Word ignoré, illustration du corps intacte');
    $unknown=OI_Docx::convert($word($drawing('logo').$paragraph('Une légende de schéma').$paragraph('Explication de ce schéma.')));
    $check(isset($unknown['images'][$logoHash]),'Import ambigu : aucune illustration supprimée');

    // Existing drafts and published records are corrected at render time only.
    $stored=$image($logoHash).'<p>Titre Word original</p><p>Introduction.</p><h2>Schéma</h2>'.$image($bodyHash);
    $id=wp_insert_post(wp_slash(['post_type'=>'oi_import','post_status'=>'draft','post_title'=>'Titre personnalisé','post_content'=>$stored]),true);
    if(is_wp_error($id))throw new RuntimeException('Fixture impossible');$posts[]=$id;
    update_post_meta($id,'oi_source',['word_title'=>'Titre Word original']);update_post_meta($id,'oi_docx_images',['before'=>'unchanged']);
    $rendered=OI_Docx::content($stored,$id);
    $check(!str_contains($rendered,$logoHash)&&str_contains($rendered,'image='.$bodyHash),'Ancienne préparation corrigée via son titre Word malgré titre personnalisé');
    $check(get_post_field('post_content',$id)===$stored&&get_post_meta($id,'oi_docx_images',true)===['before'=>'unchanged'],'Contenu et métadonnées stockés restent intacts');
    wp_update_post(['ID'=>$id,'post_type'=>'oi_fiche','post_status'=>'publish']);delete_post_meta($id,'oi_source');update_post_meta($id,'oi_word_title','Titre Word original');
    $check(OI_Docx::content($stored,$id)===$rendered,'Ancienne fiche publiée corrigée via son titre Word');
    delete_post_meta($id,'oi_word_title');wp_update_post(['ID'=>$id,'post_title'=>'Titre Word original']);
    $check(OI_Docx::content($stored,$id)===$rendered,'Titre de la fiche utilisé quand le titre Word historique est absent');
    $fallback=OI_Docx::content($image($logoHash).$course,0);
    $check(!str_contains($fallback,$logoHash)&&str_contains($fallback,'image='.$bodyHash),'Ancien contenu sans métadonnées corrigé grâce au couple titre et UE');
    echo "Images d’entête Word : $checks assertions réussies\n";
}finally{
    foreach($posts as $id)wp_delete_post($id,true);
    foreach($files as $file)if(is_file($file))unlink($file);
}
