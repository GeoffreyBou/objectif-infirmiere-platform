# Alignement appliqué — 4 novembre 2026

La décision finale conserve le programme de référence comme source des thèmes. Le basculement général vers une taxonomie pilotée par Drive a été abandonné avant déploiement.

## Drive

- A1 : fusion des anciens dossiers 01 + 02 et 03 + 04.
- A2 : fusion des anciens dossiers 03 + 04.
- D1 : fusion des anciens dossiers 01 + 02, puis renumérotation des suivants.
- Alignement des libellés sur le programme pour les UE à regroupements explicites.
- Conservation des thèmes Drive pour B4, C2, D2 et E2.
- 153 opérations : 60 renommages de dossiers et 93 déplacements. Les anciens dossiers vides sont conservés dans des sous-dossiers « Archives », sans suppression.
- Contrôle avant/après : 2 793 identifiants conservés ; 2 601 empreintes de fichiers identiques ; le document Google sans empreinte conserve sa date de modification. Les noms des 2 602 fichiers sont inchangés. Chaque chemin final a été comparé au plan prévu.

## Production — version 0.3.0-rc.35

- Un dossier correspondant exactement à un thème du programme est prioritaire sur le titre Word.
- Ajout de deux espaces complémentaires : E3/04 « Consolidation locale - optionnel » et « Parcours IFSI / Validation du diplôme ». Le second n’est pas une 16e UE nationale.
- Exclusion de la charte et du programme Word à la racine, ainsi que des documents placés dans « Documents éditoriaux », de l’import étudiant.
- 646 fiches publiées conservées, avec leurs identifiants, titres et contenus vérifiés inchangés.
- Correction de la fiche « Examens complémentaires : savoir ce que l’on cherche » : B3/01 → B3/12.
- 89 préparations mises à jour (chemin et/ou catégorie), dont 29 thèmes corrigés. Les 114 préparations étudiantes restantes ont une UE et un thème valides, dont 10 dans Parcours IFSI.
- La préparation « Charte des fiches de révision » a été mise à la corbeille, sans suppression du Word source.
- Aucun contenu n’a été republié ou reconverti. Les déblocages, favoris, progression et QCM conservent leurs références aux mêmes identifiants.
- L’interface d’import expose 16 espaces et 81 thèmes actifs : les 15 UE, l’espace transversal et les thèmes complémentaires.

## Points éditoriaux distincts du classement

Huit codes ont deux contenus Word différents à la même version :

- B1-FON-001 ;
- E1-ORG-001, E1-ORG-002, E1-ORG-003, E1-ORG-004 ;
- E3-PRO-001, E3-PRO-002, E3-PRO-003.

Ils sont correctement classés, mais la préparation reste bloquée sur ces doublons tant que la version de contenu à retenir n’est pas choisie. Aucun des deux fichiers n’a été supprimé ni choisi arbitrairement.

Douze anciennes fiches B3 utilisent encore les codes B3-EXA-001 à 003, B3-MED-003 à 010 et B3-TRF-001. Le catalogue Drive actuel contient des fiches sous de nouveaux codes B3-MDE, avec un découpage parfois différent. Ces douze anciennes fiches restent disponibles et sont toutes classées dans B3/12. Leurs identités sources n’ont pas été fusionnées automatiquement avec les nouveaux contenus : il s’agit d’une décision éditoriale distincte du rangement.

## Vérifications et sauvegardes

- 470 assertions locales : programme, import Word, classement, archives, bibliothèques et classeurs QCM.
- Inventaire Drive complet relu après les opérations et comparé au plan simulé.
- Instantanés de classement avant/après et journal de chaque modification ; aucune action de reclassement ne reste à appliquer après contrôle.
- Sauvegarde complète de la base téléchargée et archive gzip vérifiée (558 958 152 octets), plus archive de retour au code rc.34.
- Archives et journaux conservés dans `.runtime/alignment-*`, privés et exclus de Git ; options privées `oi_alignment_journal` et `oi_alignment_site_journal` pour le retour ciblé.
- L’outil de maintenance temporaire est retiré après vérification. L’import normal reste en lecture seule (`drive.readonly`). Le propriétaire peut rétablir le rôle Lecteur du compte de service.
