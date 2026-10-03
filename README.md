# Objectif Infirmière

Prototype WordPress propriétaire : fiches privées par pack, navigation, recherche, lecteur mobile, favoris, progression, quiz, Stripe Checkout TEST et Conseiller IA avec Responses/File Search.

Développement en local. Production cible : https://app-dev.objectif-infirmiere.fr/. Aucun déploiement distant effectué. Clés réseau injectées : Stripe confirme TEST, OpenAI authentifie Models/Files/Vector Stores et les dix fiches démo sont indexées. Aucun paiement complet ni réponse Responses réelle validé : pas de prix Stripe TEST ni secret webhook, et Responses refuse la clé via le proxy (401 invalid_api_key).

## Démarrage local

Prérequis : Docker avec Compose, Python 3, Node 24/npm pour les tests navigateur et Chromium. Docker ne sert qu'au développement ; OVH exécute le plugin directement dans WordPress.

```sh
scripts/setup.sh
scripts/dc.sh wp eval-file /oi-scripts/demo.php
npm --cache /workspace/.npm-cache ci --ignore-scripts --no-audit --no-fund
scripts/test.sh
```

WordPress écoute sur le port local 8080, limité à la boucle locale. Les identifiants locaux générés sont conservés dans `.runtime/local.env`, jamais dans Git. Les processus devront redémarrer après restauration de l'environnement. Les 10 fiches démo ne sont installées que par la commande explicite et ne sont pas des contenus pédagogiques validés. Le script ne doit jamais être exécuté sur la production.

## Installation OVH

Le dossier `plugin/objectif-infirmiere` est un plugin autonome, sans dépendance Composer/npm en production. PHP 8.3+, WordPress 6.8+, MySQL/MariaDB avec droits de création de tables. Téléverser l'archive issue de `scripts/package.sh` depuis Extensions → Ajouter une extension. Ne pas importer la base locale ni ses comptes sur OVH.

Créer une page dont le contenu est uniquement `[objectif_infirmiere]` : le plugin fournit alors une interface autonome du thème. Si le shortcode est inséré dans une page comportant d'autres contenus, le thème est conservé. Les comptes étudiants n'ont pas de back-office.

Voir [guide pas à pas](docs/DEMARRAGE.md), [configuration et exploitation](docs/EXPLOITATION.md), [architecture](ARCHITECTURE.md), [roadmap](ROADMAP.md) et [résultats de validation](docs/VALIDATION.md).

## Secrets serveur

- `OI_STRIPE_SECRET_KEY` : clé restreinte TEST, Checkout Sessions écriture, Prices lecture.
- `OI_STRIPE_WEBHOOK_SECRET` : signature du endpoint Stripe, clé réelle utilisée localement pour le HMAC.
- `OI_OPENAI_API_KEY` : projet OpenAI avec facturation activée, permissions Responses, Files, Vector Stores.

Variables d'environnement ou constantes dans `wp-config.php`, jamais dans le plugin, JavaScript, Git ou les réglages publics. Les secrets cloud via proxy doivent être utilisés sur leur destination HTTPS autorisée ; un placeholder proxy n'est pas un secret HMAC utilisable pour vérifier une signature locale.

## Limites actuelles

Stripe TEST seulement, achats ponctuels. Remboursements, abonnements et mode paiement production restent à ajouter et tester. IA limitée à 100 fiches indexées autorisées par utilisateur. Emails locaux simulés ; livraison SMTP à valider. Aucun média premium à stocker dans des URLs publiques sans dispositif OVH complémentaire. Une sauvegarde et l'inventaire administratif OVH sont nécessaires avant toute installation autorisée.
