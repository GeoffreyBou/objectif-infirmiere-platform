# Refonte Freemium — plan et décisions

Demande : `CAHIER-FREEMIUM.md`. Travail uniquement local sur `feature/freemium-premium`. Référence conservée : `reference/pre-freemium-2026-10-08` (0.2.2, b41b5c5). Consigne initiale : aucun changement de production. **Consigne ultérieure du propriétaire : « sisi envoi en PROD que je review » (8 octobre 2026)** ; le déploiement de revue est donc autorisé.

## État initial

Fonctionnels : comptes WordPress, connexion, packs privés, fiches, favoris/progression, QCM liés aux fiches, vitrine bleue, Stripe Checkout TEST et webhook idempotent, indexation et client Responses/File Search. Dix contenus locaux de démonstration, non validés cliniquement. Réponse OpenAI réelle et livraison SMTP non validées ; aucun encaissement réel activé. Pas encore de vérification e-mail, portefeuille, catalogue verrouillé, Premium unique ni suivi marketing complet.

## Lots et validations

1. Portefeuille serveur transactionnel : trois types, lots de crédits, expiration, journal, opérations idempotentes et droits permanents. Tests de double attribution/dépense, relecture, expiration et restitution.
2. Vérification e-mail à jeton haché expirant, attribution 5/5/5 une seule fois, état du compte et dashboard. Tests de rejeu, comptes non vérifiés, reconnexion.
3. Catalogue publié avec métadonnées publiques aux membres, déblocages explicites et droits FICHE/QCM séparés. Tests d’absence de corps/correction et de contrôle des accès.
4. Réservation IA transactionnelle, une requête simultanée par compte, restitution sur échec, journal de tokens/coûts, conservation des textes désactivée par défaut. Tests de succès, panne, zéro crédit et concurrence.
5. Premium à 59 EUR TEST, utilisateur déjà connecté et vérifié, signature et relecture Stripe, crédit +100 unique par compte, remboursement. Tests de rejeu, montant, propriétaire, paiement incomplet.
6. Réemploi du design bleu : vitrine Freemium/Premium, parcours gratuit, compteurs, catalogue verrouillé et suppression des upsells pour Premium. Tests navigateur sur ordinateur et mobile.
7. Administration des crédits/campagnes, statistiques agrégées et export, sécurité et documentation. Validation globale et séparation explicite des tests simulés / services réels.

## Décisions V1

- Un QCM est la série attachée à une fiche, avec un droit distinct. Acheter/débloquer la fiche ne dévoile pas automatiquement les questions ni les corrections du QCM.
- Les droits des packs existants restent reconnus ; les nouveaux inscrits n’obtiennent plus automatiquement le pack démo entier.
- Un pack Premium configuré côté administration définit explicitement les fiches IDE couvertes. Aucun autre contenu payant n’est accordé implicitement.
- Favoris et progression restent disponibles sur les contenus gratuits déverrouillés, pour conserver les acquis lors du passage Premium.
- Crédits promotionnels consommés avant expiration la plus proche ; crédits de bienvenue et bonus Premium sans expiration. Aucune reconduction mensuelle.
- Remboursement total confirmé : retrait du droit issu du paiement et des crédits bonus non dépensés, sans solde négatif, sans effacer favoris/progression/déblocages individuels. Remboursement partiel : examen administrateur, pas de retrait automatique. Le bonus +100 reste attribuable une seule fois sur toute la vie du compte, même après rachat.
- Les mesures internes sont agrégées, sans cookie public ni identifiant visiteur persistant, sans texte IA ni donnée de santé. Aucun outil tiers de marketing installé. Les compteurs de visites ne représentent pas des visiteurs uniques.
- Prix affiché 59 €, TVA et conditions légales à finaliser avant commercialisation. TEST seulement. L’indisponibilité IA est affichée et ne consomme aucun crédit.

## Résultats

À compléter après chaque lot. Les intégrations non validées réellement ne seront pas présentées comme acquises.

### Point de validation intermédiaire

- Lot 1 : 25 assertions initiales réussies (portefeuille, rollback, expiration, restitution). Migrations répétées vérifiées après correction du format dbDelta.
- Lots 2–3 serveur : 31 assertions crédits/accès et 35 assertions auth réussies. Catalogue sans corps privé, série QCM autonome et droits séparés.
- Lot 4 serveur : 19 assertions IA réussies avec transport OpenAI simulé, dont restitution sur citation invalide et panne.
- Lot 5 serveur : 20 assertions Stripe réussies avec transport simulé, propriétaire existant, +100 unique et refus d’un deuxième Checkout actif. Remboursements à compléter dans la batterie dédiée.
- Services réels le 8 octobre : Stripe Balance confirme TEST ; création du produit refusée 403 `more_permissions_required`. Demande de droits Produits/Prix écriture ou Price ID de 59 € adressée au propriétaire. OpenAI Responses minimal refuse 401 `invalid_api_key` avec le binding existant : activation IA maintenue désactivée, aucun crédit facturé à un étudiant pour ce test.

### Validation de reprise du 8 octobre

Serveur : **179 assertions** réussies au total sur les versions finales des suites (socle 16, expérience 9, Stripe 20, IA 21, révision 13, authentification 35, crédits 34, remboursements 13, administration/confidentialité 18). Les suites affectées par les dernières corrections ont été relancées séparément. Les erreurs injectées de stockage, livraison et réseau figurent volontairement dans les logs de test.

Concurrence réelle MySQL, six connexions PHP indépendantes : une seule consommation pour six ouvertures de la même fiche ; cinq séries accessibles avec cinq crédits, sixième refusée ; une réservation IA, cinq refus de concurrence ; soldes exacts. Les tests d'interruption vérifient ensuite la restitution d'une réservation ancienne et le refus d'un règlement tardif.

Remboursements : refus d'un montant de 39 € et d'un propriétaire incohérent, maintien des droits sur remboursement partiel, retrait du bonus disponible sur remboursement complet, aucune recréation du bonus par une requête IA interrompue, conservation des acquis et aucun nouveau bonus au rachat.

Confidentialité et administration : changement d'adresse nécessitant une nouvelle confirmation sans redonner les crédits ; anciens liens et liens expirés refusés ; livraison en échec sans crédit ; campagnes idempotentes et reprenables ; expiration ; conservation des textes désactivée par défaut ; purge programmée et coût inconnu explicitement non estimé.

Services : nouvelle lecture complète des prix Stripe TEST actifs, aucun prix ponctuel de 59 € trouvé. Le refus de création reste donc bloquant. Aucun changement Stripe LIVE, ni de production WordPress.

Navigateur : **25 scénarios distincts validés**, sur iPhone, Android, tablette et ordinateur, avec 7 répétitions volontairement ignorées (inscription une fois, parcours Freemium et activation Premium sur ordinateur et iPhone). Résultats réunis de la batterie initiale et des reprises ciblées après corrections, pas d'une exécution unique intégralement verte. Le lien de démonstration masqué sur tablette par une ancienne règle CSS a été corrigé puis retesté. Les longs parcours avec captures ont un budget de 90 secondes ; les reprises Freemium réussies ont duré 18 à 20 secondes. Aucun contrôle fonctionnel n'a été retiré.

Le test de retour de paiement attend réellement une mutation serveur : une simple URL `oi_payment=received` ne donne aucun accès. Le harnais local traite ensuite un webhook signé avec transport Stripe simulé ; le navigateur observe les droits Premium, 105 crédits IA, disparition des upsells et progression identique. Cette simulation reste distincte d'un achat via le véritable Checkout Stripe.

Le parcours d'inscription vérifie aussi qu'un GET sur le lien d'e-mail ne confirme pas le compte ; seule la confirmation explicite débloque les crédits. Captures pour revue dans `docs/review-freemium/`.

### Déploiement de revue autorisé ensuite

À la demande explicite ultérieure du propriétaire, version 0.3.0-rc.1 installée sur OVH le 8 octobre. Sauvegarde complète base/plugins/thèmes/uploads/autres téléchargée et intégrité des cinq archives vérifiée avant remplacement. Nouveau pack Premium lié aux dix fiches déjà en place ; aucun import de base locale et aucun retrait des anciens packs. Paiement non activé, IA désactivée. Voir `DEPLOIEMENT-FREEMIUM.md` pour les preuves et limites du contrôle distant.

Redémarrage local `scripts/setup.sh`, reprise des démos et tests de concurrence exécutés à nouveau avec succès avant la livraison.
