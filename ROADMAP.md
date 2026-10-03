# Roadmap

Lots réalisés progressivement, chacun avec validation avant commit.

1. Socle : plugin, rôle, fiches, taxonomies, packs, administration et permissions.
2. Étudiant : connexion, dashboard, navigation, recherche, lecteur responsive et 10 fiches démo.
3. Stripe TEST : Checkout, signature webhook, idempotence, comptes et historique. Validation distante conditionnée aux secrets et au webhook publiquement joignable après installation autorisée.
4. IA : Responses, File Search, synchronisation, citations, contexte, quota et erreurs. Validation distante conditionnée aux secrets.
5. Révision : favoris, consultations, progression, quiz et watermark.
6. Audit : tests permissions/parcours, responsive, documentation, sauvegarde et déploiement OVH.

## Blocages externes initiaux
Pas d'installation WordPress existante ni d'accès à la production OVH dans l'environnement. Aucun secret Stripe/OpenAI fourni. Ne pas déclarer le parcours final validé avant paiements TEST et réponses sourcées réels.

## État du prototype local

- Lot 1 : implémenté et validé localement (16 assertions).
- Lot 2 : implémenté et validé localement (8 assertions + parcours navigateur).
- Lot 3 : implémenté en TEST ; 15 assertions avec Stripe/email simulés. Achat réel et email délivré en attente.
- Lot 4 : implémenté ; 16 assertions avec OpenAI simulé. Indexation réelle des 10 fiches vérifiée ; Responses bloqué par 401 invalid_api_key via le binding/proxy, à résoudre avant validation de la réponse.
- Lot 5 : implémenté et validé localement (13 assertions + navigateur).
- Lot 6 : contrôles syntaxe/permissions/nonces/cache/responsive, packaging et documentation faits. Audit de charge, conformité RGPD, média premium, Safari réel et compatibilité production restant à réaliser.

Pas d'environnement de staging distant : production cible `https://app-dev.objectif-infirmiere.fr/`. Aucune action de déploiement effectuée. Le critère final n'est pas encore validé ; attendre les bindings API et préparer l'inventaire OVH et la sauvegarde.
