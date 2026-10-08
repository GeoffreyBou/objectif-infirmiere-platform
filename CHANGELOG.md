## 0.3.0-rc.8
- URL bibliographiques en texte brut rendues cliquables, y compris dans les préparations déjà enregistrées.
- Liens des sources distingués visuellement et ouverts dans un nouvel onglet isolé depuis l’aperçu et le lecteur.
- Vérification serveur des URL, de leur assainissement et du rendu répété ; test navigateur d’ouverture de source.

## 0.3.0-rc.7
- Import : identité stable par code de fiche, indépendante du titre et du dossier de version ; conflits explicites.
- Titre extrait du Word, corrections manuelles conservées, métadonnées de titre restaurables.
- Reprise des anciennes préparations sans doublon et affichage du code permanent.
- Validation : 44 assertions serveur et parcours navigateur desktop.

# Changelog

## 0.3.0-rc.6 — Préparation Word depuis Drive

- Chargement explicite des fonctions de fichiers WordPress dans le parcours REST : correction de `Call to undefined function wp_tempnam()`.
- Vérification de la création et de l’écriture du fichier temporaire avant conversion.

## 0.3.0-rc.5 — Compte de service Drive

- Import sécurisé de la clé JSON dans WordPress, validation de la signature et de l’accès au dossier avant sauvegarde chiffrée.
- Renouvellement serveur en lecture seule, sans consentement OAuth personnel ; aucun accès réel revendiqué avant dépôt de la clé et partage du dossier.
- Conservation des espaces dans les titres des Word importés.

## 0.3.0-rc.4 — Atelier Word et préparation de la connexion Drive

- Onglet administrateur Mise à jour Fiches : import DOCX, classement, aperçu avant/après, sélection et publication avec historique/restauration.
- Connecteur Google Drive en lecture seule, OAuth chiffré avec renouvellement, scan paginé, versions et doublons ; connexion Google réelle à configurer par le propriétaire.
- Images Word privées, contrôles de concurrence et maintien des identifiants/déblocages/progression.
- Protections dissuasives de copie et d’impression ; filigrane personnalisé.
- Favoris : retrait du sous-texte et du double titre, recherche et liste conservées.

## 0.3.0-rc.3 — Bibliothèque par UE et dernières consultations

- Retrait de l’onglet Crédits, du titre du menu, du cartouche et de la progression de « Mes fiches », ainsi que du bloc des packs.
- Navigation UE → thèmes → fiches selon le programme fourni : 15 UE et 65 rubriques, classement des dix fiches existantes et recherche par intitulés.
- Dernières fiches consultées persistantes, ordonnées et sans doublons ; accès vérifiés côté serveur.
- Encart de l’assistant conservé dans la bibliothèque et ajouté à chaque lecteur avec son contexte.
- Validation de la pagination, des accès, des déblocages et des parcours ordinateur/mobile.

## 0.3.0-rc.2 — Lisibilité des droits et navigation

- Cadenas et mention « Verrouillée » sur les fiches et QCM à débloquer ; coût d'un crédit explicite.
- Badge « Accès administrateur » pour éviter de confondre les droits de contrôle avec un déblocage gratuit.
- Retrait du panneau de crédits en tête des onglets. Soldes regroupés dans « Mes crédits », accessible aussi dans la navigation mobile ; solde IA près du formulaire.
- Droits existants conservés ; aucune modification des règles de débit.


## 0.3.0-rc.1 — 2026-10-08 — revue déployée sur OVH après autorisation explicite

- Vérification e-mail et attribution unique de 5 crédits Fiche, 5 QCM, 5 IA.
- Portefeuilles transactionnels, déblocages permanents distincts, promotions avec expiration et historique.
- Premium unique à 59 € en TEST : confirmation serveur, bonus IA +100 unique, remboursements sans perte pédagogique.
- Accueil, espace membre, progression et comparaison Freemium/Premium dans l'identité bleue existante.
- Administration des crédits et campagnes reprenables, statistiques agrégées et export CSV.
- IA : réservation, restitution sur erreur/interruption, journal de coûts et purge des conversations facultatives.
- Les tests simulés ne valident pas les services externes : Responses, prix Stripe 59 €, webhook réel et livraison SMTP restent à qualifier. Voir docs/REVUE-FREEMIUM.md.


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
