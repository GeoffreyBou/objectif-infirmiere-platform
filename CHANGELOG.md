# Changelog

## 0.2.2 — Identité bleue et univers infirmier

- Palette bleu profond, bleu vif et bleu glacé sur la vitrine, l’inscription, la connexion et l’espace membre.
- Logo et mascottes originaux de la marque, hébergés dans le plugin avec leur provenance.
- Repères plus concrets autour des UE, de l’hygiène, de la pharmacologie, de la cardio et du quotidien en IFSI.
- Parcours et contenus existants conservés, dont les démonstrations et la mention de l’assistant IA en préparation.
- Validation : 104 assertions serveur et 21 tests navigateur réussis ; 3 répétitions d’inscription volontairement ignorées. Revue visuelle jusqu’à 320 px.

## 0.2.1 — Refonte de la vitrine et de l’espace membre

- Accueil éditorial turquoise : présentation des fiches, QCM, préparation des partiels, assistant en préparation et démo interactive sur trois sujets.
- Inscription gratuite, choix du sujet conservé, connexion dédiée et accès étudiant aux seules démonstrations autorisées.
- Espace membre avec navigation desktop/mobile, bibliothèque, favoris, progression, QCM dédiés et interface du conseiller IA.
- Polices hébergées localement, icônes SVG, contraste et lisibilité mobile améliorés.
- Protection de l’inscription : nonce, origine, limitation, rôle fixe, absence d’attribution de packs payants.
- Tests : 104 assertions serveur ; 21 tests navigateur réussis et 3 répétitions d’inscription volontairement ignorées. Comptes distincts par appareil pour respecter les limites de requêtes.


## Livraison de revue OVH

- Application installée et page d’accueil de révision publiée avec dix fiches de démonstration.
- Sauvegarde base/contenus téléchargée et vérifiée ; adresse WordPress corrigée en HTTPS.
- Parcours réel vérifié dans Chromium sur le site, avec captures ordinateur et mobile.
- Stripe et OpenAI restent à configurer côté serveur avant leurs validations complètes.

## 0.1.0 — Prototype local

- Plugin propriétaire, rôle étudiant, CPT privés et taxonomies administrables.
- Packs, droits manuels et paiements, routes REST avec contrôle serveur.
- Interface autonome mobile-first, recherche, lecteur, sommaire et dix fiches de démonstration.
- Stripe Checkout TEST, vérification cryptographique, relecture de session/prix, verrous et idempotence, email de compte et historique.
- OpenAI Responses/File Search, synchronisation versionnée, reprise sans duplication, citations autorisées, quotas et statistiques.
- Favoris, consultations, progression, quiz corrigés côté serveur et watermark minimal.
- Tests serveur et navigateur, configuration locale Docker, archive plugin et guide OVH/Stripe/OpenAI.
- Accès administratif OVH validé en lecture seule ; environnement local aligné sur WordPress 7.1.2 et Twenty Twenty-Five 1.5, tests réussis.

Les tests automatisés utilisent des API simulées. Validation réelle supplémentaire : Stripe authentifié TEST, prix de 39 EUR configuré et Checkout réel créé ; dix documents indexés dans OpenAI. Responses est refusé avec 401 invalid_api_key sur le binding réseau, réponse réelle non validée. Première version de revue installée sur OVH ; aucun paiement de production activé.
