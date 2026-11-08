# Bascule vers objectif-infirmiere.fr

État du 8 novembre 2026 : préparation en cours. **Le domaine principal n’a pas été modifié.**

## Architecture retenue

Conserver le WordPress actuel du domaine principal, son hébergement, ses articles, pages historiques, médias, utilisateurs, Yoast SEO, redirections et permaliens. Ajouter le plugin et importer uniquement le catalogue de la plateforme. Ne pas remplacer la base du site principal par celle d’app-dev et ne pas modifier le DNS pour cette opération : les deux domaines utilisent déjà le même hébergement.

L’inventaire public a retrouvé 119 articles et 40 pages dans les sitemaps, plus les catégories et étiquettes. Les 160 documents contrôlés (accueil inclus) répondent. Leurs URL, titres, canoniques et empreintes sont enregistrés dans un manifeste privé pour comparaison après lancement. `/accueil/` existe déjà : préserver cette page et laisser WordPress attribuer un slug distinct à la nouvelle page avant de la sélectionner comme accueil.

## Accès et prérequis encore nécessaires

- L’application password disponible authentifie `dev-agent` sur app-dev. Le domaine principal répond **401**. Fournir séparément `OI_WP_PROD_APPLICATION_PASSWORD`, pour un compte administrateur `dev-agent` sur le domaine principal, avec autorisation réseau limitée à ce domaine. Ne pas placer de mot de passe dans Git ou la conversation.
- L’accès Stripe disponible est **TEST**. Le code rc102 ajoute le mode LIVE explicite, mais ne l’active pas. Créer/configurer les tarifs LIVE Premium 59 EUR et recharge 10 EUR / 100 crédits, ainsi qu’une destination LIVE pour `https://objectif-infirmiere.fr/wp-json/oi/v1/stripe/webhook`. Saisir la clé réutilisable par OVH et le secret du webhook dans le formulaire administrateur du domaine principal.
- Vérifier PHP 8.3+, WordPress 6.8+, InnoDB, sauvegarde du site principal et procédure de retour arrière avant toute installation.

## Exports préparés

Exports WXR authentifiés et lots JSON privés dans `.runtime/migration-2026-11-08/`, hors Git, permissions restreintes. Ils comprennent 757 fiches publiées, les banques QCM associées (745 fiches avec banque), 804 illustrations privées et le pack Premium source 33. Les 264 fiches à la corbeille, l’ancien pack gratuit, comptes, crédits de test, paiements, sessions, clés API et mappings OpenAI sont exclus des lots.

Les illustrations et images Word sont stockées dans les métadonnées privées ; elles font partie des exports. Les fichiers des lots totalisent environ 393 Mo. Les empreintes SHA-256 sont enregistrées dans le manifeste. `scripts/prepare-catalogue-migration.py` reconstruit ces lots à partir des WXR. Les exports ne sont pas une sauvegarde complète de WordPress : la sauvegarde UpdraftPlus distincte de la base et des fichiers app-dev est téléchargée et vérifiée (décompression Gzip intégrale et contrôle ZIP). Le fichier SQL compressé fait 566 107 117 octets. Le manifeste privé conserve les tailles et empreintes des cinq archives. Cette sauvegarde ne comprend pas le cœur WordPress ni le fichier wp-config.php de l’hébergement.

## Séquence de déploiement

1. Inventorier en lecture seule le WordPress principal : extensions actives, PHP, pages, réglages de lecture, permaliens, robots, cache, emails et droits d’accès. Faire une sauvegarde complète vérifiée et indépendante, incluant la configuration serveur nécessaire à la restauration des secrets chiffrés.
2. Installer d’abord l’extension temporaire `tools/migration/oi-migration.php` sur le domaine cible. Son activation conserve les réglages d’accueil et marque la migration en préparation. Le filtre `oi_activate_home_on_install` empêche ensuite le plugin principal de remplacer l’accueil à son activation. Les nouvelles pages de plateforme répondent 503 aux visiteurs tant que la préparation est active ; les articles existants restent servis par leur thème. Les pages légales existantes ne sont pas reprises automatiquement pendant cette phase.
3. Installer le plugin rc102. Vérifier les tables InnoDB avant l’import. L’endpoint temporaire `/wp-json/oi-migration/v1/batch` exige `manage_options` et `install_plugins`, un origin source fixé et un lot de dix objets maximum / 16 Mo maximum. Les lots préparés contiennent un seul objet chacun. L’import refuse les articles ordinaires, secrets et anciens mappings IA, préserve les structures des QCM, reconstruit les thèmes Drive si nécessaire et enregistre le mapping source → cible. Il est transactionnel et reprenable : une source déjà importée à l’identique n’est pas dupliquée ; une version différente exige une revue.
4. Conserver le mapping retourné et comparer tous les objets au manifeste. Relier le pack Premium importé aux nouveaux IDs des 757 fiches via son formulaire administrateur. Sélectionner ce pack dans les réglages. Vérifier le classement UE/thèmes et le chargement des couvertures/images.
5. Configurer Stripe LIVE, prix et webhook. Le mode de la clé, du prix, des sessions et des événements doit correspondre. Les sessions LIVE doivent également porter le rattachement au site d’origine ; une session app-dev ne peut pas activer de droits sur le principal. L’activation du Premium reste exclusivement serveur après webhook vérifié. Un achat réel de contrôle et sa réception doivent encore être vérifiés ; les tests automatisés LIVE utilisent des réponses Stripe simulées et ne prouvent pas un encaissement.
6. Renseigner OpenAI sur le serveur principal. Créer un vector store distinct, sans expiration, et réindexer les fiches avec leurs **nouveaux IDs** ; ne pas réutiliser directement les mappings d’app-dev. Répliquer les réglages validés (GPT-4.1, recherche officielle, quota), connecter le Drive si nécessaire, puis vérifier une réponse réelle, les citations et le débit/restitution de crédit. Les clés chiffrées d’app-dev dépendent de ses salts et ne peuvent pas être copiées telles quelles.
7. Tester inscription, confirmation d’email, connexion, lecture gratuite, QCM, Premium, recharge IA, popup sans crédits, paiement et assistant sur le domaine cible. Le contrôle d’envoi SMTP doit distinguer acceptation serveur et réception effective. Vérifier les cookies, liens et retours Stripe du domaine principal.
8. Reprendre l’accueil et les pages légales de la plateforme avec des liens du domaine principal. Préserver les URL légales et les métadonnées pertinentes, sauvegarder leur contenu précédent avant changement. Vérifier les intégrations du thème/OptimizePress/Yoast et les scripts tiers sur les pages de la plateforme.
9. Une fois les contrôles satisfaits, retirer `oi_migration_pending` puis sélectionner la nouvelle page d’accueil. Conserver l’indexation du domaine principal. Exclure les pages privées des caches et de l’indexation ; mettre app-dev hors indexation. Purger les caches sans supprimer les médias historiques.
10. Comparer les 119 articles et 40 pages au manifeste : statut HTTP, URL finale, canonique, titres, sitemap et absence de noindex involontaire. Examiner séparément les pages volontairement remplacées. Garder les articles accessibles directement et dans les sitemaps, même sans les afficher dans la navigation principale. Ne pas promettre l’absence de toute fluctuation SEO.
11. Désactiver/supprimer l’extension temporaire de migration et les comptes techniques. Conserver les sauvegardes et mappings dans un stockage privé. Révoquer les mots de passe d’application temporaires si demandé par le propriétaire.

## Retour arrière

Tant que la migration est en préparation, l’accueil historique reste actif. Après ouverture, restaurer les réglages d’accueil consignés et la version précédente du plugin si nécessaire. **Ne pas réimporter une ancienne base** après de nouvelles inscriptions ou transactions : préserver les comptes et journaux, puis rapprocher les paiements Stripe. Les contenus importés peuvent rester privés/inactifs sans effacer les articles historiques.

## Vérifications techniques préparatoires

- Stripe TEST et LIVE simulés : achat, signature, rejet de mauvais environnement et de mauvais site, idempotence et bonus ; recharge et remboursement ; configuration chiffrée et changement explicite d’environnement.
- Import local : accès administrateur, préservation de l’accueil, article et métadonnées SEO inchangés, QCM conservés, reprise sans doublon, refus des objets sérialisés et des anciennes références OpenAI, rollback d’un lot partiellement invalide.
- Un échantillon réel de fiche et d’illustration a également été importé puis supprimé en local : HTML, QCM et métadonnées/images relus à l’identique.
- rc102 est déployée sur app-dev ; Stripe y reste en TEST, avec 757 fiches et 745 séries dans l’offre publique. Aucune clé LIVE ni aucun tarif réel n’a été activé.
- Le principal reste bloqué par l’absence d’accès administrateur ; aucun de ces résultats n’est présenté comme une migration déjà réalisée.
