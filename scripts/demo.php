<?php
// Explicit demo installer, never run automatically on OVH.
if (!in_array(wp_parse_url(get_option('siteurl'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) { throw new RuntimeException('Démonstration autorisée uniquement sur WordPress local.'); }
if (get_option('oi_demo_seeded')) { OI_App::install_pages(); echo "Démonstration déjà installée.\n"; return; }
$topics = [
 ['Furosémide','Médicament','Le furosémide est un diurétique de l’anse. La surveillance porte notamment sur la diurèse, la pression artérielle et les paramètres prescrits.','Pourquoi surveiller la kaliémie sous furosémide ?',['Pour repérer une perte de potassium','Pour mesurer la glycémie'],[0],'La diurèse peut favoriser les pertes de potassium. Interpréter avec la situation clinique et les prescriptions.'],
 ['Hypokaliémie','Biologie','Une diminution du potassium sanguin peut perturber l’activité musculaire et cardiaque. Les normes dépendent du laboratoire.','Le potassium intervient-il dans l’activité cardiaque ?',['Vrai','Faux'],[0],'Les variations de kaliémie peuvent modifier l’activité électrique cardiaque.'],
 ['Hyperkaliémie','Biologie','Une élévation du potassium doit être interprétée avec le contexte, le prélèvement et les examens prescrits.','Une valeur isolée suffit-elle toujours à décider des soins ?',['Oui','Non'],[1],'La prise en charge dépend du contexte et des professionnels responsables.'],
 ['Ionogramme sanguin','Examen','L’ionogramme mesure notamment sodium et potassium. Il contribue à l’évaluation de l’équilibre hydro-électrolytique.','Quels ions retrouve-t-on dans un ionogramme ?',['Sodium','Potassium','Hémoglobine'],[0,1],'Le sodium et le potassium sont des électrolytes ; l’hémoglobine relève de la numération sanguine.'],
 ['Insuffisance cardiaque','Pathologie','L’insuffisance cardiaque est un syndrome clinique. La surveillance infirmière s’appuie sur les symptômes, le poids et les paramètres prescrits.','Un suivi du poids peut-il être utile ?',['Vrai','Faux'],[0],'Les variations de poids peuvent aider au suivi, selon les consignes de prise en charge.'],
 ['Hygiène des mains','Soins','L’hygiène des mains participe à la prévention de la transmission des micro-organismes. Appliquer les protocoles de l’établissement.','L’hygiène des mains contribue-t-elle à prévenir les infections ?',['Vrai','Faux'],[0],'Elle est une mesure fondamentale de prévention de la transmission.'],
 ['Calculs de doses : méthode','Méthode','Identifier la prescription, l’unité et la concentration ; poser le calcul puis vérifier sa cohérence. Ne jamais valider une administration sur ce prototype.','Avant un calcul, que faut-il vérifier ?',['Les unités','La concentration','La couleur de l’emballage'],[0,1],'La cohérence des unités et de la concentration est essentielle.'],
 ['Douleur : évaluation','Soins','L’évaluation de la douleur repose sur l’expression du patient et les outils adaptés à sa situation. Tracer et réévaluer selon le protocole.','La douleur doit-elle être réévaluée ?',['Vrai','Faux'],[0],'La réévaluation permet de suivre son évolution et les effets des interventions.'],
 ['Respiration : repères','Notion','Observer fréquence, rythme, amplitude et signes associés. Toute situation réelle nécessite les procédures adaptées de l’établissement.','Que peut-on observer ?',['Le rythme','L’amplitude','La fréquence'],[0,1,2],'Ces observations contribuent à l’évaluation respiratoire.'],
 ['Transmissions ciblées','Méthode','Les transmissions structurées contribuent à la continuité des soins. Écrire des faits observés, les actions et les résultats sans jugement.','Que privilégier dans une transmission ?',['Les faits observés','Les jugements personnels'],[0],'La traçabilité doit être factuelle, pertinente et professionnelle.'],
];
$formation = wp_insert_term('IDE — Démonstration', 'oi_formation');
$semesters=[];
foreach (['Semestre A — Démo','Semestre B — Démo'] as $name) { $semesters[]=wp_insert_term($name,'oi_semestre')['term_id']; }
$ids=[];
foreach ($topics as $i => [$title,$theme,$text,$question,$options,$correct,$explanation]) {
 $content='<p><strong>Contenu de démonstration, à faire relire avant usage pédagogique. Aucun référentiel officiel certifié.</strong></p><h2>Objectif pédagogique</h2><p>Comprendre les repères essentiels : '.esc_html($title).'.</p><h2>Définition et repères</h2><p>'.esc_html($text).'</p><h2>Vigilance IDE</h2><blockquote>Ce support ne remplace pas les prescriptions ni les protocoles de soins.</blockquote><h2>À retenir</h2><p>'.esc_html($text).'</p><h2>Sources et validation</h2><p>Exemple éditorial fictif. Validation pédagogique et références à ajouter avant publication commerciale.</p>';
 if ($i===3) $content.='<h2>Tableau de lecture</h2><table><thead><tr><th>Élément</th><th>Rôle du repère</th><th>Interprétation</th></tr></thead><tbody><tr><td>Sodium</td><td>Équilibre hydro-électrolytique</td><td>Selon laboratoire et contexte</td></tr><tr><td>Potassium</td><td>Activité neuromusculaire</td><td>Selon laboratoire et contexte</td></tr></tbody></table>';
 if ($i===4) for($j=1;$j<=5;$j++) $content.='<h2>Repère de révision '.$j.'</h2><p>'.esc_html($text).' Reliez chaque observation au contexte et transmettez selon les procédures. Ces paragraphes illustrent un lecteur de fiche longue.</p>';
 if ($i===8) $content.='<h2>Schéma de révision</h2><figure><div role="img" aria-label="Observation puis transmission puis réévaluation">Observer → Transmettre → Réévaluer</div><figcaption>Exemple de démarche d’observation.</figcaption></figure>';
 $id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>$title,'post_content'=>$content]);$ids[]=$id;
 wp_set_object_terms($id,[(int)$formation['term_id']],'oi_formation');wp_set_object_terms($id,[(int)$semesters[$i%2]],'oi_semestre');wp_set_object_terms($id,$theme,'oi_theme');
 update_post_meta($id,'oi_quiz',[['question'=>$question,'options'=>$options,'correct'=>$correct,'explanation'=>$explanation]]);
}
$pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Pack Découverte — Démonstration','post_content'=>'10 fiches pour tester les parcours de révision. Contenus non validés pour un usage clinique.']);update_post_meta($pack,'oi_fiches',$ids);
$page=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Mon espace de révision','post_content'=>'[objectif_infirmiere]']);
update_option('show_on_front','page');update_option('page_on_front',$page);update_option('oi_demo_pack',$pack);update_option('oi_demo_seeded',true);
OI_App::install_pages();
echo "10 fiches et un pack de démonstration créés.\n";
