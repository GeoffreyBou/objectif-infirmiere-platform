# Revue produit — première livraison en ligne

Adresse : https://app-dev.objectif-infirmiere.fr/

Se connecter avec son compte administrateur WordPress habituel. Les administrateurs disposent de la lecture des dix fiches de démonstration. L’administration des fiches et packs se trouve dans le menu Objectif Infirmière.

## Parcours à revoir

1. Ouvrir l’espace de révision et parcourir les dix fiches.
2. Rechercher « Furosémide » et ouvrir la fiche.
3. Consulter le sommaire, ajouter un favori et marquer la fiche comme révisée.
4. Répondre au quiz et afficher la correction.
5. Revenir aux fiches et ouvrir les favoris ; vérifier la progression.
6. Refaire la navigation sur téléphone.

Les retours attendus portent sur la navigation, la lisibilité, le classement et le déroulement des révisions. Les textes sont des exemples non validés pédagogiquement, explicitement signalés dans les fiches.

## Périmètre de cette livraison

Application propriétaire installée et activée, page d’accueil remplacée par l’espace de révision, dix exemples et un pack de démonstration. L’adresse WordPress a été corrigée en HTTPS. Les pages existantes sont conservées.

Stripe et OpenAI ne sont pas configurés sur le serveur OVH : les secrets cloud ne sont pas des secrets serveur. Aucun encaissement réel n’est activé. Le Conseiller IA affiche son indisponibilité de configuration ; les fiches restent utilisables.

## Sauvegarde avant installation

Sauvegarde UpdraftPlus de la base, extensions, thèmes, téléversements et autres contenus créée avant installation de l’application. Les cinq archives ont été téléchargées en stockage local privé et vérifiées (ZIP, décompression SQL). Elles sont également accessibles dans WordPress, Réglages → Sauvegardes UpdraftPlus. Ce contrôle d’intégrité n’est pas une restauration complète en environnement isolé ; le cœur WordPress et la configuration d’hébergement ne sont pas inclus dans cette sauvegarde de contenus.

## Retour arrière

Désactiver Objectif Infirmière et rétablir la page d’accueil précédente dans Réglages → Lecture. Les valeurs précédentes ont été conservées dans l’option oi_before_review_front. Aucune donnée existante n’a été supprimée ; les créations de revue sont distinctes. La restauration de la base complète nécessite d’abord de sauvegarder l’état courant pour préserver toute modification intervenue depuis.
