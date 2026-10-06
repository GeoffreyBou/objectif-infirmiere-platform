# Inventaire OVH — application de revue déployée

Site de production : https://app-dev.objectif-infirmiere.fr/.
Compte : `dev-agent`, rôle administrator et capacité manage_options confirmés via REST. Authentification HTTP Basic valide avec le secret réseau OI_WP_APPLICATION_PASSWORD. Aucun secret stocké dans le dépôt.

## Installation vérifiée en lecture seule

| Élément | Résultat |
| --- | --- |
| WordPress | 7.1.2 confirmé par la méthode XML-RPC de lecture wp.getOptions (software_version) |
| PHP | 8.3 annoncé par le serveur ; patch non exposé |
| Thème actif | Twenty Twenty-Five 1.5, sans thème enfant |
| Autres thèmes | Twenty Twenty-Four 1.5 et Twenty Twenty-Three 1.6, inactifs |
| Extensions | Objectif Infirmière 0.2.2 et UpdraftPlus 1.26.8 actifs ; Akismet et Hello Dolly inactifs |
| REST | /wp-json/ et /index.php/wp-json/ répondent ; lecture authentifiée de users/me, plugins, themes et settings réussie |
| Adresse WordPress (siteurl) | https://app-dev.objectif-infirmiere.fr |
| Adresse du site (home) | https://app-dev.objectif-infirmiere.fr |
| Fuseau horaire | Chaîne vide, décalage UTC 0 |

L’adresse WordPress a été corrigée en HTTPS après sauvegarde. La page d’accueil présente désormais l’offre ; les pages inscription, connexion et espace membre sont publiées. Dix fiches de démonstration sont proposées aux nouveaux comptes dans le pack gratuit explicitement autorisé. Voir [Revue produit](REVUE-PRODUIT.md) pour le parcours et les limites de cette livraison.

## Authentification résolue

Le mot de passe d'application initial avait été créé sur un autre compte. Après correction par le propriétaire pour dev-agent, le même mécanisme HTTP Basic fonctionne. L'hypothèse précédente d'un blocage du proxy n'est donc pas établie et la méthode d'en-tête complet n'est pas nécessaire. Ne pas demander de remplir OI_WP_AUTHORIZATION ni d'utiliser le fichier de préparation HTML pour poursuivre.

Reproduire la lecture avec `python3 scripts/inventory-ovh.py`. Le script privilégie OI_WP_APPLICATION_PASSWORD ; l'en-tête complet n'est qu'un fallback historique. Redirections refusées, TLS vérifié, sorties limitées aux informations d'inventaire. Pas d'email, mot de passe ni en-tête d'authentification affiché.

## Livraison de revue

L’accès REST administrateur a permis de créer un compte technique temporaire, puis une session d’administration classique pour installer les ZIP. UpdraftPlus a sauvegardé la base et les cinq catégories de contenus avant l’installation de l’application ; copies téléchargées en stockage privé et archives vérifiées. Le compte technique et l’extension temporaire de préparation sont supprimés après validation.

La connexion SSH directe a été refusée depuis l’environnement cloud. Le secret réseau SSH n’est pas une injection de mot de passe exploitable par SSH ; aucune connexion SSH authentifiée n’a été réalisée.

## Restant avant commercialisation

- Validation pédagogique des contenus ; les dix fiches sont des exemples de revue.
- Configuration des secrets sur OVH, email réellement délivré, achat Stripe TEST complet, réponse OpenAI sourcée réelle.
- Restauration isolée complète, configuration serveur, limites PHP, cron et cache à qualifier.
- La sauvegarde UpdraftPlus utilisée couvre la base et wp-content, pas le cœur WordPress ni toute la configuration d’hébergement.
