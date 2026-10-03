# Inventaire OVH — accès administratif validé

Site de production : https://app-dev.objectif-infirmiere.fr/.
Compte : `dev-agent`, rôle administrator et capacité manage_options confirmés via REST. Authentification HTTP Basic valide avec le secret réseau OI_WP_APPLICATION_PASSWORD. Aucun secret stocké dans le dépôt.

## Installation vérifiée en lecture seule

| Élément | Résultat |
| --- | --- |
| WordPress | 7.1.2 confirmé par la méthode XML-RPC de lecture wp.getOptions (software_version) |
| PHP | 8.3 annoncé par le serveur ; patch non exposé |
| Thème actif | Twenty Twenty-Five 1.5, sans thème enfant |
| Autres thèmes | Twenty Twenty-Four 1.5 et Twenty Twenty-Three 1.6, inactifs |
| Extensions | Akismet 5.7 et Hello Dolly 1.7.2, tous deux inactifs |
| REST | /wp-json/ et /index.php/wp-json/ répondent ; lecture authentifiée de users/me, plugins, themes et settings réussie |
| Adresse WordPress (siteurl) | http://app-dev.objectif-infirmiere.fr |
| Adresse du site (home) | https://app-dev.objectif-infirmiere.fr |
| Fuseau horaire | Chaîne vide, décalage UTC 0 |

Le test natif Santé du site `https-status` répond recommended, « Votre site n’utilise pas HTTPS ». Le transport public est bien HTTPS, mais siteurl reste configuré en HTTP. Avant installation et paiements, prévoir la correction de l'adresse WordPress vers HTTPS après sauvegarde ; home est déjà HTTPS. Rien n'a été changé à distance durant l'inventaire.

## Authentification résolue

Le mot de passe d'application initial avait été créé sur un autre compte. Après correction par le propriétaire pour dev-agent, le même mécanisme HTTP Basic fonctionne. L'hypothèse précédente d'un blocage du proxy n'est donc pas établie et la méthode d'en-tête complet n'est pas nécessaire. Ne pas demander de remplir OI_WP_AUTHORIZATION ni d'utiliser le fichier de préparation HTML pour poursuivre.

Reproduire la lecture avec `python3 scripts/inventory-ovh.py`. Le script privilégie OI_WP_APPLICATION_PASSWORD ; l'en-tête complet n'est qu'un fallback historique. Redirections refusées, TLS vérifié, sorties limitées aux informations d'inventaire. Pas d'email, mot de passe ni en-tête d'authentification affiché.

## À préparer avant intervention de production

- Sauvegarde de la base et des fichiers, avec restauration vérifiable.
- Correction HTTPS de siteurl et vérification du login, des médias et des redirections.
- Compatibilité locale avec la version WordPress 7.1.2 : les tests du prototype ont été exécutés sur 6.8.3, pas encore sur cette version exacte.
- Limites PHP, base, cron, emails/SMTP et cache : pas entièrement accessibles par l'inventaire REST natif.
- Méthode d'installation de l'archive propriétaire : l'accès API ne fournit pas d'accès au système de fichiers ni une sauvegarde intégrale. Installation ZIP depuis l'administration ou accès serveur sécurisé à organiser.

Le plugin Objectif Infirmière n'est pas installé sur OVH. Aucun plugin activé, aucune configuration, aucun contenu ni donnée distante modifiés.
