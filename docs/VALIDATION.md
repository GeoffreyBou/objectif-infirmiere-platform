# Validation du prototype 0.1.0

## Vérifié dans l'environnement local

- WordPress 6.8.3 ; PHP 8.3.28 ; MariaDB 11.4 ; thème Twenty Twenty-Five 1.3.
- Plugin activé, désactivation/réactivation sans destruction des données, setup relancé sans réinstallation ni duplication des dix fiches.
- Images officielles épinglées par digest, TLS et checksums conservés. Proxy HTTPS cloud et CA système utilisés par la configuration locale uniquement.
- Syntaxe PHP de tous les fichiers et JavaScript vérifiée.
- 68 assertions serveur exécutées dans WordPress réel : socle 16, expérience 8, Stripe 15, IA 16, révision 13.
- 12 tests Playwright Chromium réussis : parcours étudiant, routes anonymes, nonces/cache/URL ; chacun sur 390×844, 412×915, 820×1180 et 1920×1080. Émulation de taille et tactile, pas une validation matérielle Safari iOS/Android.
- Parcours connexion → recherche → fiche → favori → révisée → quiz → erreur IA maîtrisée → favoris.
- Permissions : pas de contenu sans pack, brouillon interdit, autre étudiant refusé, native REST absent, nonce absent/falsifié refusé, URL directe sans fuite du corps de fiche, cache privé sans stockage.
- Paiement : HMAC falsifié/expiré, non payé, mauvais prix, session live et pack invalide rejetés ; compte créé, second achat sans doublon, rejeu sans seconde transaction. Transport Stripe et email simulés, aucune carte débitée.
- IA : upload/association/indexation, remplacement, reprise après erreur d'association sans duplication, filtre par document autorisé et hash courant, citation étrangère rejetée, quota atomique, erreurs API et indépendance des fiches. Transport OpenAI simulé, pas de réponse médicale réelle validée.
- Connectivité HTTPS de WordPress local vers Stripe vérifiée par réponse 401 sans authentification, avec TLS activé. Ne prouve pas l'accès d'une clé.

## Corrigé lors des tests

Le REST avec permaliens simples (`?rest_route=`) ne supportait pas la concaténation naïve d'un deuxième `?` pour les paramètres de recherche. La construction utilise désormais URL/URLSearchParams pour les deux configurations WordPress. Les tests HTTP du navigateur exercent cette forme réelle.

## Observation publique OVH

La production https://app-dev.objectif-infirmiere.fr/ répond en HTTPS, annonce PHP 8.3 et expose le thème Twenty Twenty-Five dans son HTML. Sa balise publique annonce WordPress 7.1.2 ; cela doit être confirmé depuis l'administration. Aucun accès administratif, shell ou DB fourni, aucun fichier distant modifié. L'inventaire des extensions privées et la compatibilité exacte de cette installation ne sont pas encore établis.

## Restant à valider avant commercialisation

- Secrets effectivement injectés dans le runtime, authentification et droits Stripe/OpenAI réels.
- Produit et prix TEST configurés, webhook réel joignable/relais CLI, achat TEST de bout en bout.
- Compte et définition de mot de passe depuis un email réellement délivré ; SMTP OVH.
- Vector Store du projet, indexation réelle, réponse File Search réellement sourcée et coûts.
- Compatibilité avec WordPress/thème/extensions OVH, règles de cache, cron et sauvegarde/restauration.
- Paiements production, remboursements/annulations et abonnements : non pris en charge dans cette version.
- Validation éditoriale et référentiel IFSI officiel, médias premium, procédures RGPD spécifiques, tests Safari natif et charge/performances production.

Le plugin est un prototype local testé. Il ne remplit pas encore le critère final d'achat TEST réel puis réponse IA sourcée réelle. Une archive installable ne constitue pas une autorisation ni une preuve de déploiement en production.
