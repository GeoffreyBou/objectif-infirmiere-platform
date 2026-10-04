# Validation du prototype 0.1.0

## Vérifié dans l'environnement local

- WordPress 7.1.2 ; PHP 8.3.28 ; MariaDB 11.4 ; thème Twenty Twenty-Five 1.5. Les 69 assertions serveur et 12 tests navigateur ont été réexécutés avec succès après alignement sur la version OVH.
- Plugin activé, désactivation/réactivation sans destruction des données, setup relancé sans réinstallation ni duplication des dix fiches.
- Images officielles épinglées par digest, TLS et checksums conservés. Proxy HTTPS cloud et CA système utilisés par la configuration locale uniquement.
- Syntaxe PHP de tous les fichiers et JavaScript vérifiée.
- 69 assertions serveur exécutées dans WordPress réel : socle 16, expérience 9, Stripe 15, IA 16, révision 13.
- 12 tests Playwright Chromium réussis : parcours étudiant, routes anonymes, nonces/cache/URL ; chacun sur 390×844, 412×915, 820×1180 et 1920×1080. Émulation de taille et tactile, pas une validation matérielle Safari iOS/Android.
- Parcours connexion → recherche → fiche → favori → révisée → quiz → erreur IA maîtrisée → favoris.
- Permissions : pas de contenu sans pack, brouillon interdit, autre étudiant refusé, native REST absent, nonce absent/falsifié refusé, URL directe sans fuite du corps de fiche, cache privé sans stockage.
- Paiement : HMAC falsifié/expiré, non payé, mauvais prix, session live et pack invalide rejetés ; compte créé, second achat sans doublon, rejeu sans seconde transaction. Transport Stripe et email simulés, aucune carte débitée.
- IA : upload/association/indexation, remplacement, reprise après erreur d'association sans duplication, filtre par document autorisé et hash courant, citation étrangère rejetée, quota atomique, erreurs API et indépendance des fiches. Transport OpenAI simulé, pas de réponse médicale réelle validée.
- Authentification Stripe réelle avec la clé réseau fournie : Balance confirme `livemode=false` (TEST) ; prix `price_1UMTv1GTCUb35N377JAmJRo1` vérifié actif, ponctuel, EUR, `livemode=false`, 3 900 centimes ; associé au pack démo avec produit `prod_VNENG1CFfM706H`. Création Checkout réelle réussie via le REST du plugin (HTTP 200, URL HTTPS checkout.stripe.com). Aucun paiement effectué.
- Authentification OpenAI réelle sur Models, Files et Vector Stores : `gpt-4.1-mini` disponible ; vector store local créé avec expiration après 7 jours d’inactivité, 10 fichiers réellement indexés (`completed=10`, `failed=0`).
- Responses réellement essayé avec une question contextualisée et une requête minimale : `401 invalid_api_key`. Même erreur depuis WordPress et Python hors Docker, alors que Models et Vector Stores continuent de fonctionner. Blocage de génération externe, pas diagnostiqué comme un défaut de l’interface ni comme simple droit manquant. Ne pas demander automatiquement une deuxième clé : vérifier l’application du binding/proxy et la validité de la clé sur Responses.

## Corrigé lors des tests

Le REST avec permaliens simples (`?rest_route=`) ne supportait pas la concaténation naïve d'un deuxième `?` pour les paramètres de recherche. La construction utilise désormais URL/URLSearchParams pour les deux configurations WordPress. Les tests HTTP du navigateur exercent cette forme réelle. Le test de panne IA intercepte sa réponse dans le navigateur pour rester déterministe sans appels facturés ; les tests serveur et l’essai réel distinct documentent l’intégration.

## Livraison OVH de revue

WordPress 7.1.2, PHP 8.3, Twenty Twenty-Five 1.5. Application 0.1.0 installée et activée après sauvegarde de la base et des contenus, dix fiches et un pack de démonstration créés, page d’accueil de révision publiée. Siteurl et home sont HTTPS. Voir docs/INVENTAIRE-OVH.md et docs/REVUE-PRODUIT.md.

Le navigateur Chromium a vérifié l’application réellement servie par OVH : dix fiches, recherche, lecture, favoris, progression, quiz et rendu mobile sans débordement. Les requêtes de cette vérification passent par urllib avec TLS vérifié et le proxy cloud, car Chromium ne reconnaît pas directement la chaîne du proxy. Aucun contournement de validation TLS ni simulation des réponses applicatives pour ces contrôles.

## Restant à valider avant commercialisation

- Secret HMAC webhook réel ; autorisation/propagation du binding OpenAI sur Responses.
- Produit et prix TEST configurés, webhook réel joignable/relais CLI, achat TEST de bout en bout.
- Compte et définition de mot de passe depuis un email réellement délivré ; SMTP OVH.
- Réponse Responses/File Search réellement sourcée et consommation associée. Indexation réelle vérifiée, réponse refusée.
- Comportement réel sur OVH : règles de cache, cron, configuration serveur et sauvegarde/restauration. La version WordPress et le thème actif ont été reproduits et testés en local.
- Paiements production, remboursements/annulations et abonnements : non pris en charge dans cette version.
- Validation éditoriale et référentiel IFSI officiel, médias premium, procédures RGPD spécifiques, tests Safari natif et charge/performances production.

Le plugin est un prototype testé localement et une première version de revue est déployée sur OVH. Il ne remplit pas encore le critère final d'achat TEST réel puis réponse IA sourcée réelle. Cette livraison sert à la revue produit et ne constitue pas une ouverture commerciale.
