# Livraison de revue — 8 octobre 2026

Le propriétaire a explicitement demandé : « sisi envoi en PROD que je review ». Cette consigne remplace l'interdiction initiale de déploiement du cahier, pour cette livraison.

- Domaine : https://app-dev.objectif-infirmiere.fr/ (production).
- Plugin actif vérifié par l'API WordPress : **0.3.0-rc.1**.
- Code : branche `feature/freemium-premium` ; référence précédente `reference/pre-freemium-2026-10-08`.
- Archive SHA-256 : `afbd3ac9f9417b26117f0ea871f579d95db6399c80c455f03aaec987230fcee4`.
- Sauvegarde UpdraftPlus du 8 octobre à 12:39:39 UTC : base et quatre archives de fichiers téléchargées en stockage privé, décompression/intégrité vérifiées. Ne pas publier ces sauvegardes. Archive plugin 0.2.2 de retour également vérifiée.
- Pack historique #19 préservé ; nouveau pack Premium #33 lié aux dix fiches existantes et à leurs dix QCM. Aucune nouvelle fiche de démonstration importée et aucune base locale transférée.

## Contrôles sur le site réel

Transport HTTPS vérifié à travers le proxy ; aucune réponse applicative Stripe, OpenAI ou WordPress simulée pendant ces contrôles distants. Le harnais navigateur ouvre explicitement la destination après avoir vérifié les réponses 303 et les cookies de connexion/inscription, car les redirections natives de Chromium à travers le proxy sont imparfaites.

Accueil, identité bleue, logo et mascotte, prix 59 €, affichage mobile sans débordement, accès anonyme aux fiches refusé, connexion, catalogue de dix fiches/QCM, correction d'une série, assistant affiché indisponible avec bouton désactivé, page Premium sans paiement activé et administration Crédits vérifiés. Les fichiers JavaScript et CSS publiés ont un hash identique aux fichiers validés localement.

Une inscription réelle de contrôle crée un compte étudiant en attente de vérification, avec **0/0/0 avant confirmation**. WordPress accepte l'e-mail de vérification ; cela ne démontre pas sa livraison à une boîte réelle. Le parcours complet de confirmation et l'attribution 5/5/5 sont validés localement avec capture de courrier. Les contrôles de consultation distants utilisent un compte technique administrateur temporaire, donc ne constituent pas une preuve du débit Freemium en production ; les débits, droits séparés, remboursements et doubles dépenses sont couverts par les tests locaux.

Le contrôle de l'administration a nécessité d'attendre le document chargé, sans attendre l'arrêt de toutes les requêtes WordPress en arrière-plan. Aucun changement du code applicatif n'a été nécessaire.

Les comptes étudiant de contrôle et technique temporaires sont supprimés après les vérifications. Aucun mot de passe d'un compte existant n'est modifié.

## Limites visibles pour la revue

Le site est consultable pour revue du nouveau modèle, pas ouvert à l'encaissement. Le prix Stripe TEST de 59 € reste à créer/configurer, le webhook réel à relayer et OpenAI Responses à débloquer. Le conseiller IA reste en préparation et aucun crédit IA n'est consommé. L'affichage de l'offre ne prouve pas un achat Stripe TEST complet ni une réponse IA réelle.

Les comptes existants conservent leurs droits ; un administrateur dispose des accès de contrôle. Pour examiner le comportement d'un nouvel étudiant, utiliser une inscription distincte et confirmer l'adresse.

Un éventuel retour arrière doit privilégier la réinstallation du plugin 0.2.2 et les réglages enregistrés, sans restauration aveugle de la base qui pourrait effacer de nouvelles inscriptions. Les nouvelles tables peuvent rester en place, inactives sous l'ancienne version. Toute restauration de données doit tenir compte des écritures intervenues après la sauvegarde.

## Ajustement 0.3.0-rc.2 — 8 octobre 2026

Déployé sur le même domaine pour la revue demandée. Le portefeuille est déplacé dans l’onglet « Mes crédits », accessible aussi dans la navigation mobile. Il n’est plus répété dans les fiches, QCM, favoris, progression, assistant et Premium. Le solde IA reste affiché près du formulaire.

Les contenus à débloquer portent un cadenas et un coût explicite de 1 crédit. Les administrateurs voient « Accès administrateur » : leurs droits de consultation ne consomment pas de crédits. Les règles de débit et les droits existants restent inchangés.

Validation : quatre parcours navigateur Freemium/Premium passent sur ordinateur et iPhone ; le parcours iPhone est repassé après correction du badge mobile. Premier déblocage 5 → 4, relecture sans nouveau débit. Sur le site réel, absence du bandeau dans les six onglets, page Crédits et navigation sans débordement à 390 et 320 px vérifiées avec un administrateur temporaire. Les fichiers JS/CSS distants correspondent exactement aux fichiers locaux. Les comptes étudiants locaux de test sont nettoyés séparément.

Archive SHA-256 : `2701b27d04f5646480bc7f2bbe73529c6a8f10e24f6108873bc9cad1c0d3341a`. Retour arrière disponible avec l’archive 0.3.0-rc.1 ; aucune restauration de base requise pour cet ajustement.

## Bibliothèque 0.3.0-rc.3 — 8 octobre 2026

Déployée pour la revue du propriétaire : retrait de l’onglet Crédits et des cartouches de « Mes fiches », navigation UE → thème → fiche, historique personnel des six dernières lectures et encart assistant dans chaque lecteur. [Détails, source du programme et classement](BIBLIOTHEQUE-2026.md).

Programme créé : 15 UE et 65 thèmes, sans duplication lors d’une nouvelle initialisation. Les dix fiches existantes sont classées ; aucune nouvelle fiche, aucun paiement et aucun contenu clinique ne sont ajoutés. Les anciennes associations de taxonomies sont conservées dans `oi_before_curriculum` pour les fiches migrées.

Vérification réelle après téléversement : quinze dossiers racines, B1 puis thème 01 puis Hypokaliémie, libellé de vignette, encart assistant contextualisé, ordre de l’historique après une deuxième lecture et rechargement, absence des éléments supprimés, affichage à 390/320 px. Les contrôles distants utilisent un compte administrateur temporaire. Les scénarios locaux couvrent les droits étudiants, les crédits et la confirmation Premium. JS/CSS publiés strictement identiques aux fichiers validés.

Validation locale : 15 assertions serveur bibliothèque ; 13 scénarios navigateur distincts passés sur ordinateur/iPhone, 1 scénario d’inscription mobile volontairement ignoré. Deux échecs initiaux provenaient d’un sélecteur de test devenu ambigu entre bibliothèque et historique ; il cible maintenant le H1 du lecteur, et les parcours concernés ont repassé. Retour de dossier et captures revérifiés après l’ajustement des clics pendant le chargement.

Archive SHA-256 : `690b3bd8e4097f8f1cc62ef809116a34e90934f03233e13d07ffea0086b7d6de`. Archive rc.2 conservée pour retour arrière du plugin. Aucune restauration complète de base nécessaire pour revenir à l’interface précédente.

## Atelier Word/Drive et correctif REST — 0.3.0-rc.6

L’atelier administrateur, la copie des Word dans WordPress, la publication sélective et les versions précédentes sont déployés. Les protections de lecture sont dissuasives (sélection/copie/clic droit, impression et filigrane). Les trois lignes demandées dans Favoris sont retirées. [Fonctionnement et connexion](IMPORT-WORD-DRIVE.md).

Le propriétaire a configuré son compte de service depuis le formulaire sécurisé et partagé le dossier racine. Une vraie analyse serveur a retrouvé **441 Word**. Le correctif rc.6 charge le helper WordPress de fichiers avant `wp_tempnam()` et vérifie l’écriture temporaire. Les deux préparations réelles #44 et #45 (Santé sexuelle et Puberté) ont réussi, sans publication. L’ancienne préparation synthétique du contrôle d’interface a été retirée.

Les contrôles locaux comprennent 31 assertions Word/Drive (dont signature RSA et renouvellement du compte de service) et les parcours navigateur de publication/mise à jour/restauration, images privées et protections. Les essais Google simulés sont distingués de la lecture Drive réelle ci-dessus. Le premier contrôle distant de l’atelier avait échoué sur une attente de titre avec espaces alors que le nom de fichier était transformé en tirets ; les titres importés conservent désormais les espaces.

Archive rc.6 SHA-256 : `d88a0c80f41943f3e3213c90ea2aab09efa6e06e683dbff1350afcb7fa717509`. Les archives précédentes restent disponibles pour réinstallation ; ne pas restaurer aveuglément la base ni effacer la connexion Google configurée par le propriétaire.


## Correctif identité et titres — 0.3.0-rc.7

Déployé le 8 octobre 2026. Archive SHA-256 `166aa3231150372af69c96c89612d4f297b96047a1d2c5e35b9775cb2f1dd91e`. Code stable et titre Word, personnalisation conservée, 44 assertions serveur et parcours navigateur desktop réussis. Vérification REST réelle : connexion Google conservée, 419 candidats regroupés, Santé sexuelle et Puberté v0.3 préparées (50/51), titres corrects et aucune publication de contenu. Compte de déploiement temporaire supprimé. Archive rc.6 conservée pour retour arrière.


## Sources cliquables — 0.3.0-rc.8

Déployé le 8 octobre 2026. SHA-256 `2d616a88fcc0b2b4a5681c301a16696707f81fc85c7dfd52ca208b0d845e222d`. 50 assertions serveur et parcours navigateur desktop réussis, ouverture de source dans un onglet isolé vérifiée. URL des préparations existantes 50/51 vérifiées en production sans réimportation ni publication de contenu. Compte technique supprimé.


## Atelier QCM et entraînements — 0.3.0-rc.9

Déployé le 8 octobre 2026. Archive SHA-256 `1c285ff88a03116127f6ab5116d12a5eabd3dfa95c34c06a6238ea289191d876`. Modèle XLSX disponible dans Gérer les QCM. Imports, revue, mises à jour et restauration ; banque par fiche utilisée par les séances fiche/thème/UE. Nouveaux types privés et métadonnées ; aucune migration destructive. Sessions/corrigés figés côté serveur ; droits QCM existants conservés sans débit supplémentaire pour les séances.

Validation locale : 39 assertions QCM, 50 imports, 13 révision, 18 administration ; deux parcours QCM ordinateur/iPhone et quatre parcours existants ordinateur. Vérification réelle : plugin/assets rc.9, APIs administrateur et périmètres, modèle XLSX téléchargé, connexion Google et analyse de tout le Drive (aucun `_qcm.xlsx` encore présent). Aucun contenu technique publié en production. Compte temporaire supprimé. Archive rc.8 disponible pour retour arrière ; les métadonnées QCM antérieures sont conservées dans l’historique d’import.


## Objectifs et planning — 0.3.0-rc.10

Déployé le 8 octobre 2026. Archive SHA-256 `8b3a2e3b405f3c05447a95617adb38b135768122e5ee882ef42c4a088ac07950`. Ma progression suit désormais des objectifs personnels enregistrés dans une métadonnée utilisateur privée : sélection par programme, cycles indépendants, planning indicatif, validation depuis la fiche et passage vers l’IA avec demande préremplie. Aucun envoi IA automatique ni modification des droits/crédits.

34 assertions objectifs, 13 révision, deux parcours objectifs ordinateur/iPhone et quatre parcours membre ordinateur réussis. Vérification REST réelle : création, planning, sauvegarde, édition, progression, nouveau cycle et reprise ; compte technique et objectifs de contrôle supprimés. Aucune modification d’un compte étudiant ou contenu pédagogique. Archive rc.9 conservée ; revenir à cette archive masque la nouvelle interface sans supprimer les objectifs enregistrés.

## Déblocage et sélection Drive — 0.3.0-rc.11

Déployé le 8 octobre 2026. Archive SHA-256 `e48c853a7a8aaba6f36ccf4d39fe023ccf26cd1471058fde630689754ec19fb6`. Le bloc de bas de menu devient un accès bleu avec cadenas « Débloquer tout le contenu ». La page Premium présente le déblocage dans une nouvelle composition bleue avec mascotte ; tarif, conditions d’éligibilité, mode Stripe TEST et quota IA restent inchangés.

La sélection globale des Word exclut les fichiers inchangés et conflictuels. Préparation séquentielle par lots de 10, publication des aperçus validés par lots de 20, compteur et conservation des résultats en cas d’interruption. La liste affiche 100 préparations et le total en attente.

Validation : 50 assertions serveur Word/Drive et quatre parcours navigateur ordinateur/iPhone (sélection, exclusion, état intermédiaire, lots 10/10/1 sans publication automatique, rendu et retour de paiement). Vérification en production : version rc.11, trois assets identiques au paquet local, connexion Drive et liste de préparations accessibles, tarif inchangé. Aucun contenu pédagogique préparé ou publié par ces vérifications. Archive rc.10 conservée pour retour arrière.

## Prévisualisation en masse — 0.3.0-rc.12

8 octobre 2026 : prévisualisation robuste aux erreurs individuelles, compteur, reprise des seules fiches sans aperçu valide et comparaisons repliées avec cadres montés à la demande. Évite l’arrêt au premier classement incomplet et le chargement simultané de 200 iframes pour 100 fiches. Les contrôles de publication restent inchangés.

Test navigateur local avec 100 vraies préparations et REST réel : 99 réussites malgré une UE invalide, puis 100 après correction avec une seule requête supplémentaire ; ouverture/fermeture de la comparaison, aucun iframe conservé lorsque replié. Parcours Word de publication, mise à jour, restauration et protections également réussi. Archive SHA-256 `b9e91561b699ed6fd7effae2a6366875922418e1f88763acefab154ee94f58fe` ; rc.11 conservée pour retour arrière.

## Publication directe — 0.3.0-rc.13

9 octobre 2026 : prévisualisation facultative sur demande du propriétaire produit. Publication de toute la sélection par lots de 20 sans ouvrir ni générer d’aperçu côté interface. Validation serveur de chaque fiche avant publication ; les erreurs individuelles restent en préparation et sont identifiées par leur titre. Les contrôles d’identité, de classement, de concurrence et d’autorisation sont conservés.

Archive SHA-256 `ea24e0a845083fe000d39cc9cc995e289e7e102f51d62c08e92ab146b123d4c8`. Retour arrière disponible vers rc.12.

Validation locale : 50 assertions serveur et deux parcours navigateur avec 100 préparations réelles, publication après prévisualisation et publication directe sans aucun appel d’aperçu. Dans ce dernier parcours, une UE invalide laisse 99 publications réussies ; la dernière est ensuite publiée individuellement après correction. Fixtures locales nettoyées.

## Classement Drive B3 / 12 — 0.3.0-rc.14

9 octobre 2026 : classement des dossiers par UE et numéro du thème, en complément des correspondances textuelles existantes. Le libellé Drive incluant « transfusion » rejoint le thème B3 / 12 du programme. Les correspondances ambiguës ou sans UE restent à classer manuellement. Aucune modification des fiches déjà classées.

Sept cas de classement validés et 50 assertions d’import réussies. Archive SHA-256 `4ddb4cff6a285fe86a7d0edff10962a0b4286c4332ca21a1a9a9767b8218d618` ; rc.13 conservée.
