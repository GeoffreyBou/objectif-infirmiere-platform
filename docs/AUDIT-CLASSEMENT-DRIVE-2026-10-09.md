# Audit du classement Drive / programme — 9 octobre 2026

Audit de l’arborescence contenant les Word et du référentiel de classement du site ; ne constitue pas une validation pédagogique des contenus.

## Bilan

453 Word détectés avant correction : 425 disposent d’un dossier UE/thème identifiable, 19 sont hors dossier de thème et 9 sont archivés. Après exclusion des archives, 444 Word sont proposés, dont 425 classables automatiquement. Aucun document n’a été déplacé ou supprimé dans Drive.

## Causes et corrections

- Le classement dépendait initialement de textes identiques. La correction précédente par numéro ne couvrait pas tous les séparateurs (notamment `B3 01`) et les anciennes préparations conservaient leurs champs vides.
- Chaque thème du programme porte désormais un code explicite (`B3-01`, `B3-12`, etc.), indépendant du libellé et de l’ordre dans le fichier de configuration. Les formats avec espaces, tirets et underscores sont reconnus. Les codes contradictoires ou absents ne sont pas devinés.
- Les anciennes préparations vides reçoivent une proposition de classement lors du chargement, enregistrée à la publication. Les choix manuels existants sont préservés.
- B4 était incomplet : un thème générique sur le site face à cinq dossiers Drive. B4-01 conserve son identifiant et reçoit son intitulé précis ; B4-02 à B4-05 sont ajoutés. Aucun reclassement automatique des fiches publiées.
- Les dossiers `ARCHIVE…` sont exclus des prochaines analyses. Les préparations déjà créées ne sont pas supprimées automatiquement.
- Les versions annotées (`v0.3 - A RELIRE`, `v0.3 - STANDARD VALIDE`) sont reconnues comme versions, afin de sélectionner correctement la plus récente.
- L’analyse affiche le classement proposé et les totaux des Word classés, à compléter et archivés ignorés.

## Convention durable

Arborescence recommandée : `B3 - Pratiques et interventions infirmières / 01 - Formations et santé mentale initiale / v0.3 / fichier.docx`.

Le code UE et le numéro de thème sont stables ; les textes peuvent évoluer. Une nouvelle UE ou un nouveau numéro de thème doit être ajouté au référentiel du site. Le titre interne du Word reste indépendant de ce classement.

## Rangement restant dans Drive

Le compte de service du site dispose d’un accès en lecture seule : aucun déplacement ni renommage n’a été réalisé.

- Renommer le dossier parent `B3 - Medicaments, dispositifs et examens` en `B3 - Pratiques et interventions infirmières` pour éviter la confusion entre UE et thème 12. Ce renommage n’est pas nécessaire au fonctionnement du code B3.
- 16 Word sont directement sous ce parent B3, sans sous-dossier de thème. Ils concernent les médicaments, dispositifs, examens et la transfusion : destination proposée B3 / 12, à contrôler avant déplacement pour éviter d’introduire des doublons avec les versions existantes.
- À la racine : `B3-URG-001_AFGSU2_v0.1.docx` et `B2-COG-001_Memoire-apprentissage_PILOTE_v0.1.docx` doivent être rangés dans leur UE et thème. Leur nom seul n’est pas utilisé pour deviner le thème.
- `Charte_Fiches_Objectif-Infirmiere_v0.1.docx` est un document éditorial à conserver hors du dossier des fiches à importer.
- Les 9 Word archivés de C1 ne sont plus proposés. Les 6 entrées en conflit observées appartenaient à ces archives ; elles ne doivent pas être prises pour des fiches courantes à publier.

## Correspondance de tous les chemins observés

| Chemin Drive | Word | Destination / traitement |
|---|---:|---|
| (racine) | 3 | Hors dossier de thème : classement manuel / rangement Drive |
| B1 - Sciences biomedicales / 01 - Fondements biologiques et developpement humain / v0.3 - A RELIRE | 23 | B1-01 — Fondements biologiques et développement humain |
| B1 - Sciences biomedicales / 02 - Cardiovasculaire respiratoire et sang / v0.3 - STANDARD VALIDE | 17 | B1-02 — Système cardiovasculaire, respiratoire et sang |
| B1 - Sciences biomedicales / 03 - Systeme nerveux / v0.3 - STANDARD VALIDE | 8 | B1-03 — Système nerveux |
| B1 - Sciences biomedicales / 04 - Systeme locomoteur / v0.3 - STANDARD VALIDE | 7 | B1-04 — Système locomoteur |
| B1 - Sciences biomedicales / 05 - Systeme urinaire / v0.3 - STANDARD VALIDE | 7 | B1-05 — Système urinaire |
| B1 - Sciences biomedicales / 06 - Systeme digestif / v0.3 - STANDARD VALIDE | 12 | B1-06 — Système digestif |
| B1 - Sciences biomedicales / 07 - Systeme endocrinien / v0.3 - STANDARD VALIDE | 6 | B1-07 — Système endocrinien |
| B1 - Sciences biomedicales / 08 - Systemes urogenital et reproducteur / v0.3 - STANDARD VALIDE | 16 | B1-08 — Systèmes urogénital et reproducteur |
| B1 - Sciences biomedicales / 09 - Systeme tegumentaire / v0.3 - STANDARD VALIDE | 7 | B1-09 — Système tégumentaire |
| B1 - Sciences biomedicales / 10 - Immunite infections et inflammation / v0.3 - STANDARD VALIDE | 21 | B1-10 — Immunité, infections et inflammation |
| B1 - Sciences biomedicales / 11 - ORL et ophtalmologie / v0.3 - STANDARD VALIDE | 14 | B1-11 — ORL et ophtalmologie |
| B1 - Sciences biomedicales / 12 - Cancerologie et hemopathies / v0.3 - A RELIRE | 16 | B1-12 — Cancérologie et hémopathies |
| B1 - Sciences biomedicales / 13 - Enfant adolescent et pathologies infantiles / v0.3 - A RELIRE | 24 | B1-13 — Développement de l’enfant et de l’adolescent, pathologies infantiles |
| B1 - Sciences biomedicales / 14 - Psychiatrie adulte enfant adolescent / v0.3 - A RELIRE | 20 | B1-14 — Psychiatrie de l’adulte, de l’enfant et de l’adolescent |
| B1 - Sciences biomedicales / 15 - Situations critiques et urgences / v0.3 - A RELIRE | 15 | B1-15 — Situations critiques et urgences |
| B1 - Sciences biomedicales / 16 - Vieillissement et geriatrie / v0.3 - A RELIRE | 16 | B1-16 — Vieillissement et gériatrie |
| B1 - Sciences biomedicales / 17 - Soins palliatifs et medecine sociale / v0.3 - A RELIRE | 16 | B1-17 — Soins palliatifs et médecine sociale |
| B2 - Sciences humaines et sociales / 01 - Fondements et psychologie / v0.3 - A RELIRE | 13 | B2-01 — Fondements et psychologie |
| B2 - Sciences humaines et sociales / 02 - Sociologie / v0.3 - A RELIRE | 12 | B2-02 — Sociologie |
| B2 - Sciences humaines et sociales / 03 - Ethnologie et anthropologie / v0.3 - A RELIRE | 9 | B2-03 — Ethnologie et anthropologie |
| B2 - Sciences humaines et sociales / 04 - Violences, addictions et vulnerabilites / v0.3 - A RELIRE | 9 | B2-04 — Violences, addictions et vulnérabilités |
| B3 - Medicaments, dispositifs et examens | 16 | Hors dossier de thème : classement manuel / rangement Drive |
| B3 - Medicaments, dispositifs et examens / 01 - Formations et sante mentale initiale / v0.3 - A RELIRE | 6 | B3-01 — Formations et repérages initiaux |
| B3 - Medicaments, dispositifs et examens / 02 - Hygiene et prevention des infections / v0.3 - A RELIRE | 12 | B3-02 — Hygiène et prévention des infections |
| B3 - Medicaments, dispositifs et examens / 03 - Douleur et demarche clinique / v0.3 - A RELIRE | 8 | B3-03 — Douleur et démarche clinique appliquée |
| B3 - Medicaments, dispositifs et examens / 04 - Consultation infirmiere / v0.3 - A RELIRE | 7 | B3-04 — Consultation infirmière |
| B3 - Medicaments, dispositifs et examens / 05 - Soins courants / v0.3 - A RELIRE | 6 | B3-05 — Soins courants à tous les âges |
| B3 - Medicaments, dispositifs et examens / 06 - Pediatrie / v0.3 - A RELIRE | 8 | B3-06 — Pédiatrie |
| B3 - Medicaments, dispositifs et examens / 07 - Psychiatrie / v0.3 - A RELIRE | 10 | B3-07 — Psychiatrie |
| B3 - Medicaments, dispositifs et examens / 08 - Soins critiques et crises collectives / v0.3 - A RELIRE | 10 | B3-08 — Soins critiques, urgences et crises collectives |
| B3 - Medicaments, dispositifs et examens / 09 - Geriatrie / v0.3 - A RELIRE | 7 | B3-09 — Gériatrie |
| B3 - Medicaments, dispositifs et examens / 10 - Plaies et pansements / v0.3 - A RELIRE | 10 | B3-10 — Plaies et pansements |
| B3 - Medicaments, dispositifs et examens / 11 - Vaccination addictions et fin de vie / v0.3 - A RELIRE | 6 | B3-11 — Vaccination, addictions et fin de vie |
| B3 - Medicaments, dispositifs et examens / 12 - Medicaments dispositifs transfusion et examens / v0.3 - A RELIRE | 22 | B3-12 — Médicaments, dispositifs et examens |
| B4 - Demarche qualite et gestion des risques / 01 - Fondements et pilotage de la qualite / v0.3 - A RELIRE | 6 | B4-01 — Fondements et pilotage de la qualité |
| B4 - Demarche qualite et gestion des risques / 02 - Experience usager tracabilite et evaluation des pratiques / v0.3 - A RELIRE | 4 | B4-02 — Expérience usager, traçabilité et évaluation des pratiques |
| B4 - Demarche qualite et gestion des risques / 03 - Gestion des risques methodes et vigilances / v0.3 - A RELIRE | 6 | B4-03 — Gestion des risques, méthodes et vigilances |
| B4 - Demarche qualite et gestion des risques / 04 - Facteurs humains et culture de securite / v0.3 - A RELIRE | 7 | B4-04 — Facteurs humains et culture de sécurité |
| B4 - Demarche qualite et gestion des risques / 05 - Ressources et amelioration continue / v0.3 - A RELIRE | 2 | B4-05 — Ressources et amélioration continue |
| C1 - Sante publique promotion prevention ETP / 01 - Concepts et comportements de sante / ARCHIVE - upload partiel - ne pas utiliser | 9 | Archive ignorée |

## Vérification après déploiement

En production : 425 Word classables, 19 hors thème, 9 archivés ignorés, aucun classement publié différent du classement reconnu dans Drive parmi les correspondances identifiables. Les identifiants des thèmes préexistants sont conservés et les cinq thèmes B4 sont présents. Aucun document Word ni classement manuel publié n’a été modifié pendant l’audit.
