# Guide de configuration pour le propriétaire

## 1. Stripe — clé de test

1. Connectez-vous à https://dashboard.stripe.com/test/apikeys.
2. Dans le sélecteur d'environnement en haut à gauche, choisissez le mode test ou un bac à sable. L'environnement doit être explicitement indiqué comme test.
3. Créez une clé restreinte. Autorisations nécessaires : Checkout Sessions → Écriture ; Prices → Lecture. Ne donnez pas de droit d'écriture sur les prix, produits ou clients : le plugin ne les crée pas.
4. La clé doit commencer par `rk_test_` ou `sk_test_`. Une clé `rk_live_` ou `sk_live_` n'est pas adaptée. Changer d'environnement ne convertit pas une clé déjà créée.
5. Dans les paramètres sécurisés de l'environnement cloud, fournissez cette clé pour le secret `OI_STRIPE_SECRET_KEY`. Ne partagez pas sa valeur dans le chat.
6. Dans le catalogue Stripe de ce même environnement test, créez un produit et un prix ponctuel. Copiez les identifiants `prod_…` et `price_…` dans le pack WordPress, pas dans le code. Le prix facturé est toujours celui de Stripe.

## 2. OpenAI — clé API

1. Connectez-vous à https://platform.openai.com/ et sélectionnez votre projet.
2. Activez la facturation API. L'abonnement ChatGPT ne finance pas les appels API.
3. Ouvrez https://platform.openai.com/api-keys puis Create new secret key.
4. La clé doit permettre Responses, l'upload/suppression Files et la création/gestion Vector Stores. Si vous utilisez une clé restreinte, ces opérations doivent être autorisées.
5. Enregistrez sa valeur dans le secret cloud `OI_OPENAI_API_KEY`, jamais dans le chat.
6. Créez un Vector Store dans le projet OpenAI, renseignez son ID `vs_…` dans Objectif Infirmière → Réglages. Le modèle par défaut est `gpt-4.1-mini`, modifiable. Vérifier sa disponibilité dans votre projet.
7. Lancez la synchronisation depuis Objectif Infirmière → IA et synchronisation. Attendez l'état `completed` pour les fiches. WP-Cron doit tourner régulièrement.

Les clés créées dans Stripe/OpenAI doivent également être enregistrées dans la configuration sécurisée de l'environnement : leur création n'injecte pas leur valeur automatiquement. Les bindings réseau sont destinés à `api.stripe.com` et `api.openai.com`. Si des valeurs sont remplacées par le proxy, celui-ci doit aussi être accessible depuis le runtime WordPress local ; vérifier cela sans afficher les clés.

## 3. OVH — préparation, sans installation automatique

Le site https://app-dev.objectif-infirmiere.fr/ est la production. Aucun staging distant n'est prévu.

1. Dans le Manager OVHcloud, identifiez l'hébergement et le dossier racine de ce domaine dans Multisite.
2. Identifiez l'accès SFTP/SSH ou FTP TLS fourni par cet hébergement. Ne partagez pas de mot de passe dans le chat ; utiliser un canal sécurisé et un accès limité au projet.
3. Dans WordPress, Outils → Santé du site → Informations donne les versions, le thème, les extensions, les limites PHP et la base. Partager seulement cet inventaire sans identifiants ni valeurs sensibles.
4. Sauvegardez la base et les fichiers du site, vérifiez que la restauration est possible. Préparez une fenêtre de changement.
5. Après validation du déploiement concret, téléversez uniquement l'archive plugin produite localement. Ne transférez ni la base locale, ni `.runtime`, ni les comptes de démonstration.
6. Configurez les secrets serveur hors Git dans `wp-config.php` ou les variables proposées par l'hébergeur. Les secrets enregistrés dans Codex ne sont pas automatiquement installés sur OVH.

## 4. Webhook Stripe

Quand le plugin est installé sur le domaine autorisé, copiez l'adresse affichée dans ses réglages. Adresse prévue avec permaliens compatibles :

`https://app-dev.objectif-infirmiere.fr/wp-json/oi/v1/stripe/webhook`

WordPress peut utiliser `index.php/wp-json/` ou `?rest_route=` : utiliser l'adresse exacte affichée, jamais une adresse locale.

Dans le même environnement TEST Stripe, créez une destination webhook avec l'événement `checkout.session.completed`. Configurez le secret de signature `whsec_…` comme `OI_STRIPE_WEBHOOK_SECRET` côté serveur. Il est distinct de la clé API et doit être une valeur réelle, pas un placeholder d'authentification réseau.

Pour tester entièrement en local, Stripe CLI peut relayer les événements vers le serveur local. Cela nécessite une authentification Stripe CLI dédiée, non fournie ici ; ne pas exposer publiquement le serveur local simplement pour contourner cette étape.

## 5. Validation avant ouverture commerciale

Un achat TEST confirmé doit créer/identifier le compte, attribuer le pack et envoyer un email utilisable. Vérifiez aussi un second achat, un webhook falsifié, un paiement non payé, les droits d'un autre étudiant et une réponse IA citant uniquement les fiches autorisées. Aucun achat de production n'est pris en charge par cette version du plugin.
