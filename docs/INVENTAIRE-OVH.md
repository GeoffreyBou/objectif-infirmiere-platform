# Inventaire OVH — accès administratif en attente

Site de production : https://app-dev.objectif-infirmiere.fr/.
Compte indiqué par le propriétaire : `dev-agent`. Secret réseau `OI_WP_APPLICATION_PASSWORD` enregistré et présent dans le runtime. Aucun secret enregistré dans ce document.

## Observations en lecture seule

- HTTPS répond.
- En-tête public : PHP/8.3 (version patch non exposée).
- Balise generator : WordPress 7.1.2, à confirmer administrativement.
- Ressources publiques du thème `twentytwentyfive` ; version non vérifiée.
- Index REST joignable via `/index.php/wp-json/` ; `/wp-json/wp/v2/users/me` répond 404 sur cette installation.
- Index REST annonce la disponibilité des mots de passe d'application et des routes Plugins/Themes.

## Blocage d'authentification

GET `/index.php/wp-json/wp/v2/users/me?context=edit` avec HTTP Basic et le secret fourni : HTTP 401 `rest_not_logged_in`. Même résultat par `?rest_route=/wp/v2/users/me`, sans redirection. Une requête de diagnostic avec un mot de passe volontairement invalide de 24 caractères donne aussi `rest_not_logged_in`.

L'authentification semble ignorée, plutôt qu'explicitement rejetée comme mauvais mot de passe. Cela suggère un problème de transmission/traitement de l'en-tête Authorization (hébergement/proxy/configuration), sans le prouver. Ne pas recréer automatiquement le secret ni demander sa valeur dans le chat.

Première correction simple à tenter par le propriétaire : WordPress → Réglages → Permaliens → Enregistrer les modifications, sans changer les choix. Cela peut régénérer les règles WordPress de transmission Authorization si `.htaccess` est inscriptible. Retester ensuite la même lecture authentifiée. Si aucun effet, inspecter les règles Apache/FastCGI/OVH et la substitution de l'authentification Basic par le proxy avec un accès serveur sécurisé.

## Non vérifié

Extensions installées/actives et versions, version administrative WordPress, thème actif et parent, limites PHP, base de données, cron, configuration emails, cache, sauvegarde et restaurabilité. Ne pas considérer l'inventaire terminé sur la seule base des informations publiques.

Aucun plugin installé/activé, aucune configuration du site, aucun contenu et aucune donnée distante modifiés durant cet inventaire.


## Après réenregistrement des permaliens

La route `/wp-json/wp/v2/users/me` répond maintenant en JSON WordPress, sans 404. L'authentification retourne toujours HTTP 401 `rest_not_logged_in`, comme avec `/index.php/wp-json/`. Les permaliens ont donc rétabli la route standard sans résoudre l'authentification.

Prochaine vérification : transmettre l'en-tête HTTP Basic complet dans le secret réseau `OI_WP_AUTHORIZATION`, plutôt qu'encoder localement un mot de passe pouvant être un placeholder du proxy. Il s'agit d'une hypothèse à tester, pas d'une cause confirmée. Le fichier local `docs/outils/preparer-acces-wordpress.html` prépare cet en-tête sans réseau ni stockage. Le résultat est un identifiant sensible à renseigner uniquement dans les secrets sécurisés, jamais dans le chat.

Le script `python3 scripts/inventory-ovh.py` utilise directement ce nouveau binding, ou l'ancien en fallback. Il ne fait que des GET, refuse les redirections et conserve la vérification TLS. Il filtre les réponses pour ne pas afficher les emails, mots de passe ou en-têtes d'authentification. Aucune configuration distante n'est modifiée par ce script.
