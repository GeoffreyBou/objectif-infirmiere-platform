# Changelog

## 0.1.0 — Prototype local

- Plugin propriétaire, rôle étudiant, CPT privés et taxonomies administrables.
- Packs, droits manuels et paiements, routes REST avec contrôle serveur.
- Interface autonome mobile-first, recherche, lecteur, sommaire et dix fiches de démonstration.
- Stripe Checkout TEST, vérification cryptographique, relecture de session/prix, verrous et idempotence, email de compte et historique.
- OpenAI Responses/File Search, synchronisation versionnée, reprise sans duplication, citations autorisées, quotas et statistiques.
- Favoris, consultations, progression, quiz corrigés côté serveur et watermark minimal.
- Tests serveur et navigateur, configuration locale Docker, archive plugin et guide OVH/Stripe/OpenAI.
- Accès administratif OVH validé en lecture seule ; environnement local aligné sur WordPress 7.1.2 et Twenty Twenty-Five 1.5, tests réussis.

Les tests automatisés utilisent des API simulées. Validation réelle supplémentaire : Stripe authentifié TEST, prix de 39 EUR configuré et Checkout réel créé ; dix documents indexés dans OpenAI. Responses est refusé avec 401 invalid_api_key sur le binding réseau, réponse réelle non validée. Aucune installation OVH ni activation des paiements production.
