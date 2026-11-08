# Raccordement Stripe et préparation de l’assistant

État vérifié le **8 novembre 2026**, sur app-dev, version **0.3.0-rc.75**. L’utilisateur a reporté les essais IA. Stripe est raccordé en mode **TEST uniquement** ; aucun argent réel n’a été débité.

## Configuration Stripe

- Produit TEST : `prod_Vadt2gF1pxdLXU`.
- Tarif ponctuel 59 EUR : `price_1UZSeKGTCUb35N375xUx5wCr`.
- Pack WordPress 33 associé au produit et au tarif ; liste des fiches conservée exactement. Ancienne configuration sauvegardée hors Git.
- Destination TEST : `we_1UZSgUGTCUb35N37rq8Gh4JF`, API 2024-06-20.
- URL : `https://app-dev.objectif-infirmiere.fr/wp-json/oi/v1/stripe/webhook`.
- Événements : `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `charge.refunded`, `refund.updated`.
- Clé serveur renseignée par le propriétaire via le formulaire sécurisé. Secret du webhook également enregistré côté serveur.

Les clés accessibles au contexte de travail ne sont pas automatiquement installées sur OVH. Le formulaire de raccordement est accessible aux administrateurs dans **Objectif Infirmière → Réglages**, ancre `#oi-stripe-connection`.

## Validation réelle réussie

1. Création Checkout depuis le serveur OVH ; relecture de la session auprès de Stripe : `livemode=false`, 5900 centimes, EUR.
2. Paiement via l’interface Stripe Checkout avec une carte de test ; session `complete` / `paid` et retour du navigateur vers app-dev.
3. Réception du webhook Stripe : un seul paiement enregistré, Premium activé, crédits IA passés de 5 à 105 ; crédits Fiche/QCM restés à 5/5.
4. Répétition contrôlée d’une notification signée sur cette session déjà payée : doublon reconnu et aucun second bonus.
5. Remboursement TEST intégral : Stripe a demandé une approbation humaine, accordée par le propriétaire. L’approbation est passée à `succeeded`, le paiement est remboursé à hauteur de 5900 centimes.
6. Notification de remboursement reçue : Premium retiré, bonus IA retiré, 5 crédits initiaux conservés ; paiement `refunded` / remboursement `full` côté application.
7. Signature invalide refusée sur l’endpoint public ; événement de contrôle correctement signé accepté puis ignoré, sans attribution de droits.

Le compte de contrôle était un administrateur technique isolé : cette recette réelle valide paiement, notifications, écritures des droits et crédits. L’isolation entre accès gratuits et Premium reste couverte par les tests automatisés. Le compte technique est supprimé après vérification ; le paiement TEST remboursé reste dans le journal. La session de simple contrôle de connexion encore ouverte a été expirée.

L’interface Checkout proposait une conversion locale depuis l’environnement de test situé hors zone euro. EUR a été sélectionné pour conserver 59 EUR. Le transport navigateur a utilisé le relais HTTPS vérifié du workspace ; les réponses Stripe et WordPress n’ont pas été simulées. Seule la notification répétée a été construite pour vérifier l’idempotence.

## Stockage des secrets Stripe

Le formulaire est réservé aux administrateurs, protégé par nonce, et refuse les clés LIVE. Les champs sont en écriture seule ; une valeur vide conserve la configuration existante. Les secrets sont chiffrés en AES-256-GCM dans des options non chargées automatiquement, avec une clé dérivée de `wp_salt('auth')` et un contexte authentifié propre à chaque secret. Les constantes et variables d’environnement du serveur restent prioritaires.

Une modification des salts WordPress impose de renseigner à nouveau ces secrets. Sauvegarder la base et les salts serveur ensemble, hors Git. Aucun secret ne doit être réaffiché ou journalisé. Les identifiants de produit, tarif et destination ci-dessus ne sont pas des secrets.

L’offre publique et la création Checkout exigent la présence de la clé et du secret de webhook. Un retour navigateur ne suffit jamais à attribuer le Premium. Le contrôle serveur vérifie signature, session payée, prix, montant, devise et rattachement au compte.

Validation automatisée : stockage et configuration 22 assertions, Stripe 20, recharges 34, crédits 34, remboursements 13. Ces suites utilisent des services externes simulés et complètent la recette réelle décrite ci-dessus.

## Suite avant lancement commercial

- Le code impose Stripe TEST : changer uniquement la clé ne suffit pas pour passer LIVE. Prévoir une configuration explicite, les tarifs/destination LIVE et les contrôles de séparation des environnements au moment de la bascule.
- Exclure les comptes et achats de test lors de la migration du domaine principal.
- La recharge IA n’a pas fait l’objet d’un achat Stripe réel pendant cette recette ; elle reste désactivée tant que l’assistant n’est pas activé.
- Les essais OpenAI sont reportés. La vérification du 7 novembre indiquait une clé expirée ; aucun vector store n’est configuré sur app-dev.
- Avant ouverture de l’IA, tester l’indexation, les citations autorisées, les débits/restaurations de crédits et adapter la limite actuelle de 100 fiches indexées par utilisateur au catalogue Premium.

## Retour arrière

Archive rc.74 conservée hors Git. La réinstallation de rc.74 laisse les options chiffrées en base mais ne les lit plus ; le raccordement devient indisponible si les constantes serveur ne sont pas renseignées. Les paiements et remboursements doivent rester dans le journal ; ne pas restaurer aveuglément une ancienne base.
