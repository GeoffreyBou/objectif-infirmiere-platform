# Revue Freemium / Premium — 0.3.0-rc.3

La navigation de la bibliothèque est décrite dans [Bibliothèque 2026](BIBLIOTHEQUE-2026.md). L’onglet « Mes crédits » a été retiré à la demande du propriétaire.

Travail sur `feature/freemium-premium`. **Déployée pour revue sur https://app-dev.objectif-infirmiere.fr/ le 8 octobre 2026, après la nouvelle autorisation explicite du propriétaire.** Le cahier interdisait initialement la production ; cette dernière consigne prévaut. La version 0.2.2 reste la référence sur `reference/pre-freemium-2026-10-08`. Cette version de travail réutilise la palette bleue, le logo, les mascottes et les composants existants.

## Captures à examiner

Captures du WordPress local avec comptes et contenus de démonstration :

- [Accueil ordinateur](review-freemium/accueil-1440.png) et [accueil mobile](review-freemium/accueil-390.png).
- [Espace gratuit ordinateur](review-freemium/espace-gratuit-desktop.png) et [espace gratuit mobile](review-freemium/espace-gratuit-mobile.png).
- [Offre Premium](review-freemium/offre-premium-mobile.png) et [espace après confirmation du webhook simulé](review-freemium/premium-actif-mobile.png).
- [Administration des crédits et campagnes](review-freemium/administration.png).

La barre de navigation fixe peut apparaître au milieu d'une capture mobile de page entière : elle reste au bas de l'écran pendant la navigation.

## Ce qui change pour l'étudiant

1. L'accueil présente le compte gratuit, une démonstration, un comparatif et le Premium à 59 €, sans abonnement.
2. L'inscription ouvre un écran de vérification. Un lien envoyé par e-mail demande une confirmation explicite ; le simple passage d'un scanner d'e-mail ne valide rien.
3. Après confirmation, le compte reçoit 5 crédits Fiche, 5 crédits QCM et 5 crédits IA. Le coût et le solde utiles sont affichés sur les contenus à débloquer ou dans l’assistant. Reconnexion et confirmation répétée n'ajoutent aucun crédit.
4. Le catalogue est visible ; les cartes verrouillées portent un cadenas et « Débloquer — 1 crédit ». Le premier déblocage débite son portefeuille respectif une seule fois. Relire et recommencer restent gratuits.
5. Favoris et progression suivent les contenus débloqués. Le Premium couvre les fiches et QCM explicitement sélectionnés dans le pack.
6. Seul un paiement de 59 € confirmé côté serveur active le Premium et ajoute 100 crédits IA une seule fois par compte. La page de retour attend cette confirmation ; son URL ne donne aucun droit.
7. Le membre Premium ne voit plus les incitations à acheter l'offre qu'il possède.

L'IA est explicitement **en préparation** tant que son service réel n'a pas été validé. Les crédits sont conservés. Lorsqu'elle est activée, une réponse utilisable et sourcée coûte un crédit ; les erreurs le restituent. Une seule requête peut être en cours par compte. Les réservations interrompues depuis plus de cinq minutes sont récupérées lors de la prochaine consultation du portefeuille.

## Administration

`Objectif Infirmière → Crédits` : recherche d'un compte, soldes, historique, achats, attribution manuelle, campagnes et statistiques agrégées exportables. Les campagnes conservent leur sélection, leur motif et leur identifiant ; les lots de 50 peuvent être repris sans nouvelle attribution. La cohorte de prospects exclut les comptes ayant déjà acheté, même remboursés.

`Objectif Infirmière → Réglages` : sélectionner le pack Premium, régler la disponibilité de l'IA et sa conservation facultative des textes (0 à 30 jours). Zéro signifie aucune conservation de question/réponse. Une purge horaire WP-Cron et une purge lors des changements de durée complètent le nettoyage à chaque question. Une exploitation future doit assurer l'exécution régulière de WP-Cron. Les montants de coût sont des estimations de tokens en USD ; les outils, recherches et stockage ne sont pas inclus. Sans tarifs configurés, le coût reste « non estimé ».

Un remboursement total retire le droit payé et le bonus IA inutilisé ; les déblocages individuels, favoris et progression restent conservés. Un remboursement partiel est signalé pour traitement administrateur. Un rachat ne redonne pas le bonus.

Les statistiques n'envoient aucun texte de conversation à un outil marketing. Les visites sont des chargements de pages, pas des visiteurs uniques. La conversion rapporte les acheteurs étudiants vérifiés aux comptes étudiants vérifiés, remboursements compris. Les journaux techniques de crédits et paiements restent conservés pour la traçabilité ; leur politique d'archivage et d'effacement doit être définie avant commercialisation.

## Reproduire en local

Utiliser le checkout existant, sans créer de worktree. Commandes depuis la racine :

```bash
scripts/setup.sh
scripts/dc.sh wp eval-file /oi-scripts/demo.php
scripts/dc.sh wp eval-file /oi-scripts/freemium-demo.php
npm --cache /workspace/.npm-cache ci --ignore-scripts --no-audit --no-fund
scripts/test.sh
python3 scripts/test-credit-races.py
scripts/package.sh
```

Les e-mails locaux sont capturés dans `.runtime/mail`, sans envoi externe. Ils contiennent des liens confidentiels : ne pas les publier. Les tests navigateur lisent uniquement leurs propres messages. Les comptes créés par les scénarios d’inscription sont supprimés ; quatre comptes locaux de navigateur sont conservés pour la revue et remplacés à la prochaine batterie. Le plugin installable exclut tous les scripts, tests, données locales et secrets. L'archive générée est `.runtime/objectif-infirmiere-0.3.0-rc.3.zip` ; elle n'est pas une autorisation de déploiement.

## Services restant à valider

- **Stripe** : la clé confirme le mode TEST, mais la création du produit a reçu un refus 403 `more_permissions_required`. Il faut un produit et un prix ponctuel TEST de 59 € ; l'ancien `price_1UMTv1GTCUb35N377JAmJRo1` vaut 39 € et est refusé pour le Premium. Création par l'agent : Produits et Prix en écriture ; exploitation : Checkout Sessions écriture, Prices/Checkout/Charges lecture pour les vérifications. Autre voie : fournir uniquement l'identifiant public du prix de 59 € créé dans le tableau de bord Stripe.
- **Webhook TEST réel** : secret HMAC serveur réel et relais Stripe CLI local à configurer. Événements : `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `charge.refunded`, `refund.updated`. Les secrets réseau du proxy ne remplacent pas ce secret de signature.
- **OpenAI** : Models répond mais Responses renvoie 401 `invalid_api_key` avec le binding existant. L'activation reste désactivée ; les réponses sourcées réelles ne sont pas validées. Ne pas redemander une clé sans diagnostiquer son acheminement et les droits de l'opération Responses. Recherche limitée actuellement à 100 fiches indexées autorisées ; une extension sera nécessaire si le catalogue couvert dépasse ce seuil.
- **E-mail** : parcours vérifié avec capture locale, livraison réelle SMTP à qualifier.
- **Contenus et fiscalité** : catalogue local de dix démonstrations, validation pédagogique et configuration fiscale définitive avant vente.

Le critère final du cahier — parcours avec réponse IA réelle puis achat Stripe TEST complet — reste ouvert à cause de ces intégrations. Aucun résultat simulé n'est présenté comme un paiement ou une réponse réelle.
