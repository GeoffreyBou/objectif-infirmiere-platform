# Recharge assistant — 13 octobre 2026

Prix confirmé par le propriétaire : **10 € pour 100 crédits IA**, achat ponctuel sans renouvellement automatique.

## Règles du parcours

- Un compte gratuit voit « Passer Premium ». Le rôle administrateur donne accès aux fiches mais ne vaut pas achat Premium.
- Un membre Premium avec un solde IA positif ne voit ni promotion Premium ni recharge. L’ancienne URL Premium revient à sa bibliothèque.
- Un membre Premium à zéro crédit voit « Recharger mon assistant » à la place de la carte Premium. Le même accès est disponible dans l’assistant, notamment sur mobile. Le compteur existant est conservé, sans compteur supplémentaire.
- Le passage du dernier crédit de 1 à 0 actualise immédiatement ces accès. Après recharge confirmée, ils disparaissent.
- La page recharge reprend les cartes, le bon de commande bleu et la mascotte de la page Premium. Un accès direct avec des crédits restants revient à l’assistant ; un compte gratuit revient à l’offre Premium.
- Un administrateur peut revoir la page avec `/espace-revision/?view=recharge&preview=1`, sans transformer son compte en acheteur ni permettre un achat depuis cet aperçu.

## Paiement et portefeuille

La route authentifiée `POST /oi/v1/ai-recharge/checkout` exige un compte vérifié, Premium, à zéro crédit et aucune question en cours. Elle vérifie auprès de Stripe le prix ponctuel TEST de 10 EUR, sans utiliser de montant fourni par le navigateur. La recharge reste indisponible tant que le paiement et l’assistant ne sont pas configurés.

Une commande `pending` conserve le propriétaire, le prix Stripe, le montant et la quantité. Deux clics réutilisent la session ouverte. Les recharges sont distinguées des packs dans le registre des paiements et ne donnent aucun nouveau droit de contenu.

Seul un webhook signé, suivi d’une relecture de la session, du produit et du paiement Stripe, attribue les crédits. La session est la clé unique de l’attribution transactionnelle. Un autre achat peut donner 100 crédits supplémentaires après nouvel épuisement ; le bonus Premium reste, lui, unique par compte. Le retour navigateur consulte le statut de cette recharge et ne déduit jamais sa réussite du seul statut Premium ou du solde.

Un remboursement intégral retire seulement les crédits non consommés de cette recharge. Les crédits offerts et le Premium restent intacts. Une réponse IA interrompue ne recrée pas des crédits remboursés. Un paiement déjà remboursé avant réception de son événement de confirmation ne donne aucun crédit. Les remboursements partiels restent à examiner manuellement, comme les remboursements Premium existants.

Schéma 6 : ajout de `purchase_kind`, `stripe_price_id` et `credit_quantity` à `oi_payments`, avec valeurs par défaut compatibles avec les achats précédents. Migration additive et rejouable. Une sauvegarde SQL et l’archive rc.23 précèdent la livraison ; ne pas restaurer une ancienne base par-dessus des achats ou révisions plus récents.

## Configuration et limites vérifiées

`Objectif Infirmière → Réglages` contient le champ Stripe Price ID pour la recharge à 10 €. La clé Stripe de l’environnement a pu lire le compte TEST, mais la création du produit a été refusée par Stripe (`403 more_permissions_required`). Les écritures sur les produits et les prix sont nécessaires pour le créer automatiquement. Aucun produit ni tarif de recharge n’a été créé par cette tentative.

Au contrôle du 13 octobre : paiement du site indisponible, mode TEST uniquement, assistant désactivé, modèle configuré `gpt-4.1-mini`. Le code est livré pour revue ; aucune vente réelle ni réponse OpenAI réelle n’est revendiquée comme validée. L’activation effective du conseiller, de ses sources et du paiement reste une étape distincte.

## Validation

34 assertions de recharge avec Stripe simulé : permissions, solde nul, disponibilité, montant, produit, bénéficiaire, réutilisation de session, idempotence, deuxième achat, statut privé et remboursements, y compris événements reçus dans le désordre. Suites existantes : Stripe 20, crédits 34, remboursements Premium 13 et IA 21 assertions. Migration répétée réussie.

Six parcours Playwright ordinateur/iPhone : dernier crédit consommé, gratuit à zéro, accès direct, bouton conditionnel, prix, Checkout simulé, attente du webhook et disparition après recharge ; activation du Premium et conservation du bonus de 100 crédits. Les appels Stripe/OpenAI des tests sont simulés.

## Hypothèse de coût

Références consultées : [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini), [outils OpenAI](https://developers.openai.com/api/docs/pricing), [Stripe France](https://stripe.com/fr/pricing).

Avec 2 000 à 10 000 tokens d’entrée, 1 000 de sortie et un appel File Search par réponse, 100 réponses représentent environ 0,49 à 0,81 USD : entrée 0,40 USD/million, sortie 1,60 USD/million, recherche 2,50 USD/1 000 appels. Estimation hors stockage, éventuels appels échoués facturés, fiscalité et change ; consommation réelle à mesurer. Stripe affiche 1,5 % + 0,25 € pour une carte standard de l’EEE, soit 0,40 € sur une vente à 10 € ; d’autres cartes ont d’autres frais.
