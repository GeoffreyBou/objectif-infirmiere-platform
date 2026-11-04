# Console utilisateurs

L’onglet **Gérer les utilisateurs** est réservé aux administrateurs et utilise les comptes WordPress existants. Les routes `/oi/v1/admin/users`, `/admin/users/{id}` et `/admin/user-actions` vérifient les capacités côté serveur. Les mutations exigent également `edit_user` pour chaque cible ; les comptes administrateurs sont protégés.

## Fonctionnement

- Recherche par nom, e-mail ou identifiant, 25 comptes par page. Filtres : gratuit, Premium, acheté, offert, e-mail non confirmé.
- Détail : statut, crédits, achats et 50 dernières entrées de chaque historique. Les conversations IA et les mots de passe ne sont pas exposés.
- Premium offert : attribution du pack manuel existant, avec bonus IA de 100 crédits facultatif. Ce bonus utilise une origine distincte `admin_premium_bonus` et n’est offert qu’une fois par compte. Un accès déjà acheté ou offert ne déclenche aucune attribution supplémentaire.
- Retrait : enlève seulement le pack manuel Premium. Les achats Stripe, autres packs et crédits restent intacts. Les remboursements restent dans Stripe.
- Crédits : de 1 à 10 000 crédits Fiche, QCM ou IA par utilisateur ; sélection de 50 comptes maximum par opération. Aucun accès ne contourne la validation de l’adresse e-mail.
- Assistance : renvoi de validation ou réinitialisation via WordPress, individuellement, maximum trois demandes par compte et par type sur une heure. Le message confirme la remise au service d’envoi, pas la réception effective du courrier.

## Traçabilité et reprise

Chaque opération exige un motif et un récapitulatif avant confirmation. La table additive `oi_user_actions` conserve utilisateur, administrateur, action, motif, date UTC et résultat. Les modifications d’accès, crédits et audit sont effectuées dans la transaction du portefeuille de l’utilisateur.

Une UUID identifie une demande. La même demande rejouée renvoie son résultat sans répéter l’attribution ; un changement d’auteur ou de paramètres est refusé. Les lots sont atomiques par utilisateur, avec résultats individuels. Après une interruption réseau, la demande reste dans `sessionStorage`, rattachée à l’administrateur, et peut être reprise. Les e-mails passent par le service WordPress : une panne de base de données après l’envoi ne peut pas annuler le courrier déjà transmis.

La migration de cette table est indépendante (`oi_user_admin_schema=1`). Un retour à l’archive précédente conserve les comptes, les attributions et le journal ; aucune table n’est supprimée.

## Validation locale

- `scripts/dc.sh wp eval-file /tests/user-admin.php`
- `npx playwright test tests/browser/user-admin.spec.js --project=desktop --project=iPhone`

Les tests utilisent des comptes jetables locaux. Les tests serveur interceptent les e-mails, sans envoyer de messages aux utilisateurs réels. La vérification PROD est en lecture seule.
