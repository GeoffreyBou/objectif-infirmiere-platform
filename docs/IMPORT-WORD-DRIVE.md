# Import Word et connexion Drive — 0.3.0-rc.6

## Parcours administrateur

L’onglet **Mise à jour Fiches** est visible uniquement avec la capacité WordPress `manage_options`. Toutes les opérations serveur vérifient également cette capacité et l’authentification REST par nonce.

1. Importer un ou plusieurs `.docx` (10 par lot, 12 Mo chacun), ou analyser le dossier Drive configuré.
2. Choisir le titre, l’UE, le thème et la fiche cible. « Créer une nouvelle fiche » reste explicite. Une correspondance existante est proposée, jamais publiée sans prévisualisation.
3. Prévisualiser la version actuelle et la version proposée. Vérifier les avertissements de conversion.
4. Publier une fiche ou une sélection prévisualisée (20 par lot). Les succès et erreurs sont détaillés ; aucun lot n’est présenté comme réussi si une fiche échoue.
5. Utiliser les versions précédentes pour restaurer une mise à jour. Une édition ultérieure empêche la restauration automatique d’une version devenue obsolète.

Le contenu HTML et les images sont copiés dans WordPress. Le fichier Word n’est pas conservé dans un répertoire public. Les images sont servies par une route privée vérifiant l’accès à la fiche ; les images de préparation ne sont accessibles qu’aux administrateurs. Les anciens contenus et images sont conservés dans un historique privé. Les préparations et versions stockées consomment de l’espace ; une politique de rétention pourra être ajoutée selon le volume réel.

Une mise à jour conserve l’identifiant WordPress, les favoris, la progression, les déblocages et les QCM existants. Ces derniers ne sont pas régénérés : leur cohérence pédagogique doit être vérifiée si le cours change. Les nouvelles fiches publiées rejoignent le pack Premium configuré.

## Conversion Word

Pris en charge : texte, titres, gras, italique, souligné, indices/exposants, liens HTTP(S), listes simples, tableaux simples et images PNG/JPEG/GIF/WebP intégrées (3 Mo par image). La mise en page Word n’est pas reproduite à l’identique. Les sous-listes, cellules fusionnées, notes, modifications suivies, équations et graphiques complexes nécessitent une revue ; les limites détectées sont signalées. Les PDF et anciens fichiers `.doc` ne sont pas importés.

La conversion ne récupère aucune ressource externe. Les XML avec DTD/entités, macros et archives trop volumineuses sont refusés. L’HTML est assaini ; les aperçus sont isolés dans des iframes sans scripts ni formulaires.

## Google Drive : connexion permanente à effectuer

La connexion WordPress est distincte d’un éventuel connecteur Drive dans le chat. Dossier racine : `131LsjTxr9RswdWDWY_l9-wfx_hmnAs30`.

### Compte de service — méthode retenue

Le propriétaire a créé `app-dev-objectif-infirmiere@dev-airlock-511015-d5.iam.gserviceaccount.com` dans son projet existant.

1. Activer Google Drive API dans ce projet.
2. Partager le dossier racine avec cette adresse, en rôle **Lecteur**.
3. Sur le compte de service : **Clés → Ajouter une clé → Créer une clé → JSON**.
4. Dans WordPress : **Mise à jour Fiches → Configurer la connexion Google → Compte de service Google**, sélectionner ce JSON puis **Vérifier et connecter le Drive**.
5. Le serveur signe une assertion RSA avec le scope `drive.readonly`, vérifie auprès de Google l’accès au dossier précis, puis enregistre la clé chiffrée. Le fichier JSON temporaire est supprimé, aucun exemplaire n’est créé dans les médias.
6. Lancer **Analyser les nouveautés**, puis préparer et prévisualiser un vrai Word avant publication.

Cette méthode n’exige ni écran de consentement OAuth utilisateur, ni mode production OAuth, ni abonnement supplémentaire. L’accès est renouvelé avec la clé du compte de service ; la suppression de la clé ou du partage impose une correction/reconnexion. Aucun rôle d’administration du projet Google ni délégation de domaine n’est nécessaire pour lire les fichiers partagés.

### Alternative OAuth personnelle

Configuration alternative par le propriétaire du compte Google :

1. Dans Google Cloud, créer ou sélectionner un projet Objectif Infirmière.
2. Activer **Google Drive API**.
3. Configurer Google Auth Platform : identité de l’application, adresse de contact, audience. Pour un compte personnel, audience externe. Pour l’usage permanent, passer l’application **en production** : le mode test avec l’accès Drive fait expirer les jetons de renouvellement après sept jours. Respecter les éventuelles exigences de validation affichées par Google pour le projet.
4. Créer un client OAuth 2.0 de type **Application Web**, avec l’URL de redirection affichée dans l’onglet administrateur. Sur la production actuelle : `https://app-dev.objectif-infirmiere.fr/wp-admin/admin-post.php?action=oi_drive_callback`.
5. Renseigner l’identifiant et le secret du client directement dans **Mise à jour Fiches → Configurer la connexion Google**. Ne pas transmettre le secret dans le chat, Git ou des captures.
6. Cliquer **Connecter Google Drive**, choisir le compte autorisé à lire le dossier et accorder la lecture. Puis lancer une analyse réelle et prévisualiser un vrai Word.

Le scope demandé est `drive.readonly`. L’application ne crée, ne modifie et ne supprime aucun fichier dans Drive. Les secrets et jetons sont chiffrés en base avec les sels WordPress ; le jeton d’accès est renouvelé côté serveur. Une révocation Google, la suppression du client ou un changement des sels WordPress impose une reconnexion. « Déconnecter » retire la connexion de WordPress ; les autorisations Google peuvent aussi être révoquées depuis le compte Google.

L’analyse est paginée et reprend par petites étapes (3 000 éléments, profondeur 12 maximum). Les dossiers de version `v0.3`, `v0.10` ou `version 0.3` sont comparés numériquement. Seule la version la plus récente par chemin logique est proposée. Les doublons d’une même version sont bloqués. Les Word inchangés sont signalés, les contenus publiés non retrouvés sont listés sans suppression automatique. L’identifiant Drive conserve la correspondance lors d’un déplacement ; les nouvelles copies dans les dossiers de version utilisent le chemin logique hors version. Les correspondances incertaines restent à vérifier dans l’aperçu et le choix de fiche cible.

## Protections de lecture

Dans le lecteur : sélection, événements copier/couper, clic droit et glisser sont bloqués ; l’impression navigateur masque l’espace de fiche. Le filigrane personnalisé reste affiché avant et après le contenu lorsque le réglage de filigrane est actif. Ces protections sont dissuasives : elles ne peuvent empêcher captures, OCR, outils développeur ou extraction par un lecteur autorisé. Aucun DRM ou blocage absolu n’est revendiqué.

## Validation

Tests serveur : conversion/assainissement, images, refus XML, accès administrateur, aperçu obligatoire, double publication, mise à jour concurrente, conservation de l’identifiant/progression/QCM, sauvegarde/restauration, comparaison de versions et doublons, scan/téléchargement Drive avec transport Google simulé. Les secrets n’apparaissent pas dans l’API de statut.

Tests navigateur ordinateur/iPhone : import réel de fichier multipart, aperçu avant/après et image privée, publication, mise à jour, restauration, accès étudiants/anonymes refusés, protection de copie et impression. Les parcours existants de bibliothèque et d’authentification sont également contrôlés.

**Validation réelle du 8 octobre 2026 :** connexion par compte de service configurée par le propriétaire, analyse de 441 Word et préparation réussie de `B1-UGR-002_Sante-sexuelle_v0.1.docx` et `B1-UGR-001_Puberte_v0.1.docx` dans WordPress. Les deux préparations restent disponibles pour revue, sans publication. La correction rc.6 charge explicitement `wp-admin/includes/file.php` dans le parcours REST : WP-CLI chargeait déjà cette dépendance et masquait l’absence de `wp_tempnam()` en production. Les premiers vrais Word doivent être relus pour qualifier leurs mises en page spécifiques.
