# Raccordement Stripe et validation réelle de l’assistant

État vérifié le 7 novembre 2026, avant bascule du domaine principal. Cette vérification n’a activé ni paiement réel ni assistant sur app-dev.

## Constats réels

- Stripe : lecture des tarifs TEST réussie (HTTP 200). Seul tarif actif retourné : 39 EUR ; il ne correspond pas au Premium prévu à 59 EUR.
- Création d’un produit de validation refusée : HTTP 403 `more_permissions_required`, permission `product_write` manquante. Aucun produit créé.
- OpenAI : requête réelle refusée : HTTP 401 `expired_secret_key`. Aucune réponse IA réelle obtenue.
- Sur app-dev, les constantes/variables serveur `OI_STRIPE_SECRET_KEY`, `OI_STRIPE_WEBHOOK_SECRET` et `OI_OPENAI_API_KEY` sont absentes. Le vector store et le tarif de recharge ne sont pas configurés ; l’assistant est désactivé.
- Le catalogue déclaré contient 757 fiches et 745 séries QCM.

Les secrets accessibles à l’environnement de travail ne sont pas automatiquement installés sur OVH. Ne pas copier de secrets dans les documents, les journaux, Git ou la conversation. Les renseigner dans la configuration sécurisée du serveur concerné.

## Accès nécessaires

- Renouveler la clé OpenAI du projet destiné à la plateforme ; accès au modèle choisi, à Responses, Files et Vector Stores, avec facturation disponible.
- Stripe TEST : lecture/écriture Produits, Tarifs, Checkout Sessions et Webhook Endpoints ; lecture Payment Intents et Charges. Une clé restreinte doit disposer de ces autorisations.
- Configurer côté serveur les clés de l’environnement de test et le secret de signature du webhook correspondant.
- Endpoint prévu : `https://app-dev.objectif-infirmiere.fr/wp-json/oi/v1/stripe/webhook`.

## Recette IA réelle

1. Créer un espace d’indexation isolé pour la validation et indexer un petit ensemble de fiches publiées : précautions standard, calculs de doses, AVC.
2. Vérifier le statut d’indexation avant toute question.
3. Utiliser un compte de validation distinct ; vérifier une réponse pédagogique, des citations réellement issues des fiches autorisées et les liens vers ces fiches.
4. Tester une reformulation, un QCM personnalisé et une question dont les sources disponibles ne permettent pas la réponse.
5. Vérifier le débit d’un seul crédit pour une réponse réussie, l’absence de double débit en cas de répétition et la restitution du crédit en cas d’erreur.
6. Vérifier l’isolation entre fiches accessibles et fiches verrouillées.
7. Avant l’indexation générale : adapter et tester la limite actuelle de 100 fiches indexées par utilisateur dans `OI_AI::ask()`. Elle ne convient pas aux comptes Premium ayant accès au catalogue complet.
8. N’activer globalement l’assistant qu’après validation réelle du parcours et de ses sources.

## Recette Stripe réelle en mode TEST

1. Créer les tarifs ponctuels correspondant aux offres : Premium 59 EUR ; recharge 10 EUR pour 100 crédits IA.
2. Configurer le pack, le tarif de recharge et le webhook de test, sans réutiliser le tarif 39 EUR existant.
3. Acheter avec un compte vérifié de validation et une carte de test Stripe, via le Checkout de l’application.
4. Vérifier la signature du webhook, le montant et la devise, le passage Premium, l’accès Fiches/QCM et le bonus IA de 100 crédits.
5. Rejouer la notification et vérifier l’absence de second paiement ou de bonus doublonné.
6. Vérifier annulation, refus, remboursement et recharge IA selon l’éligibilité du compte.
7. Le code actuel impose Stripe TEST : le passage LIVE nécessite une adaptation explicite et des contrôles de séparation des environnements. Changer seulement la clé ne suffit pas. Exclure les achats et comptes de test lors de la préparation de la bascule.

## Contrôles internes déjà réussis

Exécutés localement avec services externes simulés : Stripe 20 assertions, IA 21, recharges IA 34, crédits 34, remboursements Premium 13, soit **122 assertions**. Ces résultats ne prouvent ni un paiement réel auprès de Stripe ni une réponse réelle auprès d’OpenAI.
