<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$unit=(int)get_term_by('slug','programme-2026-b3','oi_enseignement')->term_id;
$theme=(int)get_term_by('slug','programme-2026-b3-12','oi_theme')->term_id;
if(!$unit||!$theme)throw new RuntimeException('Programme absent');
$cases=[
 [['B3 - Soins','12 - Medicaments dispositifs transfusion et examens','v0.3'],[$unit,0]],
 [['B3 - Soins','B3 - 12 - Medicaments dispositifs et examens'],[$unit,$theme]],
 [['B3 - Soins','12 – Médicaments, dispositifs et examens'],[$unit,$theme]],
 [['B3 - Soins','Medicaments dispositifs transfusion et examens'],[$unit,0]],
 [['12 - Medicaments dispositifs transfusion et examens'],[0,0]],
 [['B3 - Soins','A1 - 12 - Autre thème'],[0,0]],
 [['B3 - Soins','99 - Inconnu'],[$unit,0]],
];
foreach($cases as [$path,$expected])if(OI_Drive::classification($path)!==$expected)throw new RuntimeException('Classement incorrect : '.implode('/',$path));
echo count($cases).' classements Drive vérifiés.\n';
