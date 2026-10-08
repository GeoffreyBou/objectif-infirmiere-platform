# Bibliothèque de fiches — 0.3.0-rc.3

## Parcours de revue

« Mes fiches » commence par « Tes notions de soins infirmiers, un peu plus claires chaque jour. », puis le bonjour et la bibliothèque. Le cartouche de présentation, la progression à cet emplacement, le titre « Ton campus, partout », l’onglet Crédits et le bloc des packs sont retirés.

Navigation : **UE → thème → fiche**. Un fil d’Ariane permet de remonter et le retour depuis une fiche conserve le dossier ou la recherche. La recherche couvre les titres de fiches, d’UE et de thèmes. La pagination reste disponible dans les thèmes de plus de 20 fiches. Une rubrique vide affiche « À venir » ; elle ne représente pas un contenu déjà publié.

Les six dernières fiches consultées sont propres au compte, ordonnées de la plus récente à la plus ancienne. Réouvrir une fiche la remonte sans doublon. L’historique persiste entre connexions, ne montre ni contenu retiré ni contenu devenu inaccessible, et ne consomme aucun crédit supplémentaire.

L’encart « Et si on te l’expliquait autrement ? » reste dans la bibliothèque et apparaît dans chaque lecteur de fiche. Son bouton transmet le contexte de la fiche à l’assistant. Le service IA demeure désactivé tant que sa configuration réelle n’est pas validée.

Captures locales avec compte de démonstration : [ordinateur](review-freemium/bibliotheque-desktop.png), [mobile](review-freemium/bibliotheque-mobile.png). La barre mobile fixe apparaît au milieu de la capture pleine page, mais reste en bas de l’écran pendant la navigation.

## Programme et gestion éditoriale

Source de classement fournie par le propriétaire : [programme Google Docs](https://docs.google.com/document/d/1xhT_lRc4P-LjR0RppDUEFUtZQ1hUUtANigqe4mWoWco/edit), consulté le 8 octobre 2026. Les 15 UE et 65 rubriques sont enregistrées dans `plugin/objectif-infirmiere/data/programme-2026.json`. Il s’agit de la structure éditoriale du document fourni, pas d’une certification indépendante du référentiel. Les UE sans sous-titres utilisent une rubrique générale ; E2 utilise « Anglais professionnel et scientifique ».

Les taxonomies WordPress existantes `oi_enseignement` et `oi_theme` servent au classement. L’administration permet d’y rattacher les prochaines fiches. Codes A1…E3 pour les unités ; thèmes numérotés dans l’ordre du document, par exemple **B1 - 01 Fondements biologiques et développement humain**. La vignette affiche ce code de thème à la place de l’ancienne catégorie générique.

Classement initial des dix fiches existantes :

| Thème | Fiches |
| --- | --- |
| B1 - 01 Fondements biologiques et développement humain | Hypokaliémie, Hyperkaliémie, Ionogramme sanguin |
| B1 - 02 Système cardiovasculaire, respiratoire et sang | Furosémide, Insuffisance cardiaque, Respiration : repères |
| B3 - 02 Hygiène et prévention des infections | Hygiène des mains |
| B3 - 03 Douleur et démarche clinique appliquée | Douleur : évaluation, Transmissions ciblées |
| B3 - 12 Médicaments, dispositifs et examens | Calculs de doses : méthode |

Les crédits, contenus, packs et droits existants ne sont pas réécrits. L’installation idempotente crée les dossiers et classe les titres connus uniquement lorsqu’ils n’ont pas déjà d’UE. Les anciennes associations sont conservées dans la métadonnée `oi_before_curriculum` de chaque fiche concernée. Les fiches sans classement restent accessibles dans « Fiches à classer ». Les dossiers vides du programme restent navigables.

## Vérifications

- 15 assertions serveur : dossiers et compteurs, pagination au-delà de 20 fiches, recherche UE, absence de déblocage implicite, ordre/dédoublonnage des consultations, isolation entre comptes, retraits et accès anonyme refusé.
- Parcours ordinateur et iPhone : trois niveaux, recherche, lecteur, assistant contextualisé, favoris, QCM, historique après rechargement, suppression des anciens blocs, affichage jusqu’à 320 px.
- Freemium : attribution et débit uniques conservés. Premium : confirmation du webhook et progression conservées.
- L’ancien test attendait un titre de fiche unique dans toute la page ; il cible désormais le titre H1 du lecteur, car la même fiche peut aussi figurer dans l’historique.

Retour arrière : réinstaller l’archive rc.2. Les taxonomies peuvent rester sans incidence sur les droits. Pour annuler aussi le classement, restaurer les associations `oi_before_curriculum` après comparaison avec les éventuels changements éditoriaux ultérieurs, sans restauration aveugle de la base.
