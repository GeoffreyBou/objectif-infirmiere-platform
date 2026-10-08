# Livraison de revue — 8 octobre 2026

Le propriétaire a explicitement demandé : « sisi envoi en PROD que je review ». Cette consigne remplace l'interdiction initiale de déploiement du cahier, pour cette livraison.

- Domaine : https://app-dev.objectif-infirmiere.fr/ (production).
- Plugin actif vérifié par l'API WordPress : **0.3.0-rc.1**.
- Code : branche `feature/freemium-premium` ; référence précédente `reference/pre-freemium-2026-10-08`.
- Archive SHA-256 : `afbd3ac9f9417b26117f0ea871f579d95db6399c80c455f03aaec987230fcee4`.
- Sauvegarde UpdraftPlus du 8 octobre à 12:39:39 UTC : base et quatre archives de fichiers téléchargées en stockage privé, décompression/intégrité vérifiées. Ne pas publier ces sauvegardes. Archive plugin 0.2.2 de retour également vérifiée.
- Pack historique #19 préservé ; nouveau pack Premium #33 lié aux dix fiches existantes et à leurs dix QCM. Aucune nouvelle fiche de démonstration importée et aucune base locale transférée.

## Contrôles sur le site réel

Transport HTTPS vérifié à travers le proxy ; aucune réponse applicative Stripe, OpenAI ou WordPress simulée pendant ces contrôles distants. Le harnais navigateur ouvre explicitement la destination après avoir vérifié les réponses 303 et les cookies de connexion/inscription, car les redirections natives de Chromium à travers le proxy sont imparfaites.

Accueil, identité bleue, logo et mascotte, prix 59 €, affichage mobile sans débordement, accès anonyme aux fiches refusé, connexion, catalogue de dix fiches/QCM, correction d'une série, assistant affiché indisponible avec bouton désactivé, page Premium sans paiement activé et administration Crédits vérifiés. Les fichiers JavaScript et CSS publiés ont un hash identique aux fichiers validés localement.

Une inscription réelle de contrôle crée un compte étudiant en attente de vérification, avec **0/0/0 avant confirmation**. WordPress accepte l'e-mail de vérification ; cela ne démontre pas sa livraison à une boîte réelle. Le parcours complet de confirmation et l'attribution 5/5/5 sont validés localement avec capture de courrier. Les contrôles de consultation distants utilisent un compte technique administrateur temporaire, donc ne constituent pas une preuve du débit Freemium en production ; les débits, droits séparés, remboursements et doubles dépenses sont couverts par les tests locaux.

Le contrôle de l'administration a nécessité d'attendre le document chargé, sans attendre l'arrêt de toutes les requêtes WordPress en arrière-plan. Aucun changement du code applicatif n'a été nécessaire.

Les comptes étudiant de contrôle et technique temporaires sont supprimés après les vérifications. Aucun mot de passe d'un compte existant n'est modifié.

## Limites visibles pour la revue

Le site est consultable pour revue du nouveau modèle, pas ouvert à l'encaissement. Le prix Stripe TEST de 59 € reste à créer/configurer, le webhook réel à relayer et OpenAI Responses à débloquer. Le conseiller IA reste en préparation et aucun crédit IA n'est consommé. L'affichage de l'offre ne prouve pas un achat Stripe TEST complet ni une réponse IA réelle.

Les comptes existants conservent leurs droits ; un administrateur dispose des accès de contrôle. Pour examiner le comportement d'un nouvel étudiant, utiliser une inscription distincte et confirmer l'adresse.

Un éventuel retour arrière doit privilégier la réinstallation du plugin 0.2.2 et les réglages enregistrés, sans restauration aveugle de la base qui pourrait effacer de nouvelles inscriptions. Les nouvelles tables peuvent rester en place, inactives sous l'ancienne version. Toute restauration de données doit tenir compte des écritures intervenues après la sauvegarde.
