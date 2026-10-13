# Proposition visuelle du 13 octobre 2026

Version `0.3.0-rc.20`. Blanc, bleus clairs et bleu profond ; en-têtes de fiches illustrés, panneaux de lecture blancs, sommaire actif. Bibliothèque, navigation, accueil et formulaires publics harmonisés. Aucun libellé ou contenu pédagogique modifié.

## Illustrations

Six illustrations décoratives générées pour cette proposition, regroupées dans `assets/illustrations/medical-blue-atlas.webp` (1536 × 1024, 162 098 octets) : neurologie, cardiovasculaire, médicaments, soins, environnement et recherche/connaissances. Elles servent de repères visuels ; elles ne remplacent pas les schémas pédagogiques des Word.

Les visuels sont livrés avec le plugin et servis par WordPress. Le navigateur charge une seule image réutilisable. Aucun service de génération, clé supplémentaire ou appel payant n’est nécessaire à la consultation ou à l’import. Aucun contenu des fiches n’est envoyé à un générateur d’images.

Le choix utilise le code UE C2 pour l’environnement, puis les libellés des thèmes, le titre de la fiche et l’UE. Les accents sont normalisés ; les identifiants de BDD ne sont pas utilisés. Une nouvelle fiche hérite de la famille reconnue. Un thème inconnu affiche le visuel général recherche/connaissances. Cette proposition offre un visuel par **famille**, pas une illustration unique pour chaque fiche. Des familles supplémentaires ou des illustrations précises pourront être ajoutées après validation de la direction artistique.

## Présentation du Word

Le contenu source reste inchangé. Le titre, le repère UE, les paragraphes « Objectifs » et les tableaux à cellule unique reçoivent seulement des classes de présentation. Les liens, illustrations du Word, tableaux, protections de copie et filigranes sont conservés. Le sommaire utilise les titres existants et indique la section courante. Sur petit écran, sa hauteur est limitée pour ne pas repousser tout le contenu.

## Vérification et retour arrière

Vérifications ordinateur/mobile : navigation bibliothèque et historique, pages publiques, absence de débordement à 390, 820, 1280 et 1600 px, illustration chargée, sommaire et comparaison exacte des textes visibles avant/après sur la fiche AVC. Les images pédagogiques privées restent servies par leur route protégée habituelle.

La version précédente complète du plugin, `0.3.0-rc.19` (commit `5bb6ddd`), est archivée. Ses cinq assets de présentation ont été comparés à la production avant le déploiement. Une sauvegarde SQL indépendante du 13 octobre est conservée dans l’espace privé, vérifiée après décompression.

Pour annuler cette proposition, réinstaller l’archive rc.19 via l’outil de déploiement habituel et vérifier les assets. **Ne pas restaurer la base pour un simple retour visuel** : cette livraison ne change ni schéma ni contenu ; réinstaller le plugin précédent suffit et préserve le travail effectué depuis. Les chemins et empreintes privés sont consignés dans `.runtime/design-rollback-2026-10-13/manifest.json` et `.runtime/design-blue-backup.json` (hors Git). L’archive peut également être reconstruite avec `git archive --format=zip --output=objectif-infirmiere-rc19.zip 5bb6ddd:plugin`.
