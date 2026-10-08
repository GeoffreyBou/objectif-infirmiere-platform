# CODEX — Refonte du modèle économique et de l'UX Objectif Infirmière

## MISSION

Faire évoluer la plateforme Objectif Infirmière vers un modèle **Freemium + Pack Premium à paiement unique de 59 €**.

La plateforme existe déjà et possède une identité graphique que nous souhaitons conserver.

**Ne pas repartir de zéro.** Analyser d'abord le code existant et réutiliser les composants, services et fonctionnalités déjà opérationnels.

L'objectif est de construire un tunnel cohérent :

**Site public → Inscription gratuite → Découverte des fiches/QCM/IA → Achat du pack Premium → Accès complet.**

La plateforme est hébergée chez OVH et utilise WordPress. Le paiement doit passer exclusivement par Stripe.

---

## 1. MODÈLE ÉCONOMIQUE VALIDÉ

### Compte gratuit

Lorsqu'un utilisateur crée un compte, il reçoit automatiquement :

- 5 crédits Fiche ;
- 5 crédits QCM ;
- 5 crédits IA.

Un crédit Fiche permet de sélectionner et déverrouiller une fiche du catalogue.

Une fois déverrouillée, cette fiche reste consultable par cet utilisateur, sans consommer de nouveaux crédits.

Un crédit QCM permet de déverrouiller une série de QCM du catalogue.

Cette série reste ensuite accessible et peut être recommencée sans consommer de nouveaux crédits.

Un crédit IA correspond à une question envoyée au Conseiller IA et traitée avec succès.

Les crédits gratuits ne sont attribués qu'une fois par compte.

### Pack Premium

Prix : **59 € TTC**, sous réserve de la configuration fiscale définitive.

Paiement unique via Stripe Checkout.

Le pack doit débloquer :

- toutes les fiches du catalogue IDE couvertes par l'offre ;
- toutes les séries de QCM correspondantes ;
- les favoris et la progression ;
- 100 crédits IA supplémentaires.

L'achat n'entraîne aucun abonnement et aucun prélèvement récurrent.

Les 100 crédits IA constituent une enveloppe totale, non renouvelable automatiquement.

L'accès aux contenus achetés n'a pas d'expiration automatique en V1.

Ne pas créer d'abonnement IA+ pour le moment.

---

## 2. SYSTÈME DE CRÉDITS

Créer un véritable système de crédits réutilisable pour les futures opérations marketing.

Prévoir trois types de crédits :

- FICHE ;
- QCM ;
- IA.

Chaque compte possède son propre solde.

Les crédits doivent être gérés côté serveur.

### Opérations nécessaires

- Attribution des crédits de bienvenue ;
- Consommation ;
- Consultation du solde ;
- Attribution manuelle par un administrateur ;
- Attribution promotionnelle ;
- Historique des mouvements ;
- Date d'expiration facultative pour les crédits promotionnels ;
- Restauration des crédits en cas d'échec technique.

Utiliser des opérations atomiques afin d'empêcher une double consommation.

Prévoir un journal permettant de connaître la raison de chaque mouvement.

### Règles importantes

Ne jamais débiter un crédit Fiche pour consulter une fiche déjà débloquée.

Ne jamais débiter un crédit QCM pour recommencer une série déjà débloquée.

Ne pas consommer de crédit IA si le serveur OpenAI renvoie une erreur et qu'aucune réponse utilisable n'est fournie.

Empêcher l'attribution multiple des crédits de bienvenue.

Empêcher l'achat multiple du même pack de générer automatiquement des crédits IA supplémentaires, sauf décision explicite ultérieure.

---

## 3. PARCOURS D'INSCRIPTION

Modifier la page publique pour inciter à la création d'un compte gratuit.

CTA principal :

**Créer mon compte gratuit**

Accroche associée :

« 5 fiches, 5 séries de QCM et 5 questions à ton conseiller IA pour commencer tes révisions. »

Après inscription et vérification de l'email :

- créer le profil étudiant ;
- attribuer les crédits de bienvenue ;
- rediriger vers le tableau de bord ;
- présenter les trois crédits disponibles.

Ne pas obliger l'étudiant à renseigner son semestre pour commencer. Ce paramètre doit rester facultatif.

---

## 4. DASHBOARD FREEMIUM

Le tableau de bord doit présenter clairement les crédits restants.

Exemple :

**Bienvenue dans ton espace de révision !**

Tes crédits de bienvenue :

- 5 fiches disponibles ;
- 5 QCM à débloquer ;
- 5 questions IA.

Ajouter les entrées suivantes :

- Explorer les fiches ;
- Explorer les QCM ;
- Interroger mon conseiller IA ;
- Mes favoris ;
- Ma progression ;
- Découvrir le Premium.

Lorsque l'utilisateur possède le Premium, ne pas continuer à afficher les incitations commerciales destinées aux utilisateurs gratuits.

---

## 5. CATALOGUE DES FICHES

Permettre aux utilisateurs de parcourir le catalogue.

Distinguer :

- fiche déjà débloquée ;
- fiche non débloquée ;
- fiche accessible via Premium.

### Utilisateur gratuit

Sur une fiche non débloquée, afficher :

**Débloquer cette fiche — 1 crédit**

Afficher également le solde restant.

Si l'utilisateur ne possède plus de crédits :

**Tu as utilisé tes 5 fiches gratuites.**

Proposer alors :

**Débloquer toutes les fiches — 59 €**

Le contenu complet ne doit pas être transmis au navigateur avant vérification des droits.

### Utilisateur Premium

Toutes les fiches couvertes par le pack sont accessibles directement.

Aucune consommation de crédit.

---

## 6. CATALOGUE DES QCM

Appliquer une logique similaire aux fiches.

Un crédit QCM déverrouille une série complète.

Ne pas confondre une série de QCM avec une question individuelle.

L'utilisateur peut recommencer une série déjà déverrouillée sans payer à nouveau.

Afficher une proposition Premium lorsque les crédits sont épuisés.

---

## 7. CONSEILLER IA

### Utilisateur gratuit

Solde initial :

**5 questions IA.**

Afficher le nombre de questions restantes dans l'interface.

Exemple :

« Il te reste 3 questions gratuites. »

### Utilisateur Premium

Ajouter 100 crédits IA au compte après validation du paiement.

Les crédits IA gratuits restants sont conservés.

Exemple : si l'utilisateur possède encore 2 crédits gratuits lors de l'achat, son solde devient 102.

### Après épuisement

Afficher un message adapté.

Pour l'utilisateur gratuit :

« Tu as utilisé tes questions gratuites. Passe au Premium pour accéder aux fiches complètes et obtenir 100 questions supplémentaires. »

Pour l'utilisateur Premium :

« Tu as utilisé tes 100 questions IA incluses. Tes fiches et QCM restent accessibles. »

Ne pas promettre de recharge payante automatique en V1.

### Suivi de consommation

Enregistrer :

- identifiant utilisateur ;
- date de la question ;
- modèle utilisé ;
- nombre de tokens entrants ;
- nombre de tokens sortants ;
- coût estimé ;
- succès ou échec ;
- source des crédits consommés lorsque pertinent.

La conservation du texte des conversations doit être configurable, minimisée et conforme à la politique de confidentialité.

---

## 8. PAIEMENT STRIPE

Créer un produit Stripe correspondant au :

**Pack Premium Objectif Infirmière — 59 €**

Utiliser Stripe Checkout en mode paiement unique.

Après confirmation serveur du paiement :

1. Vérifier la signature du webhook Stripe.
2. Vérifier que l'événement correspond à un paiement effectivement validé.
3. Identifier l'utilisateur WordPress.
4. Attribuer le Premium.
5. Créditer les 100 questions IA.
6. Enregistrer la transaction.
7. Envoyer une confirmation.
8. Rediriger l'utilisateur vers son tableau de bord.

Les opérations doivent être idempotentes.

Un webhook envoyé plusieurs fois ne doit jamais générer plusieurs attributions.

Prévoir les événements de remboursement et la politique associée, sans supprimer aveuglément les données pédagogiques de l'étudiant.

Aucun secret Stripe ne doit apparaître côté navigateur.

Utiliser uniquement Stripe TEST jusqu'à validation explicite du passage en production.

---

## 9. NOUVELLE PAGE D'ACCUEIL

Conserver :

- la palette actuelle ;
- la mascotte ;
- les illustrations existantes pertinentes ;
- les composants graphiques ;
- les grandes cartes pédagogiques ;
- la qualité des espaces et de la typographie.

Modifier le message commercial et la structure pour présenter clairement le modèle Freemium.

### Hero

Titre suggéré :

**Tes cours d'IFSI. Enfin plus simples à réviser.**

Sous-titre :

« Révise avec des fiches claires, des QCM corrigés et un conseiller IA pensé pour les étudiants infirmiers. »

CTA principal :

**Créer mon compte gratuit**

CTA secondaire :

**Découvrir le Premium**

Sous les boutons :

« 5 fiches + 5 séries de QCM + 5 questions IA offertes à l'inscription. »

Ne pas afficher le conseiller IA comme opérationnel tant que ses fonctionnalités ne sont pas disponibles.

### Nouvelle structure

1. Hero et proposition de valeur.
2. Présentation des trois fonctionnalités : Fiches / QCM / Conseiller IA.
3. Démonstration concrète du produit.
4. Présentation du compte gratuit.
5. Comparatif Gratuit / Premium.
6. Présentation du Pack Premium à 59 €.
7. Expertise pédagogique et éléments de confiance réels.
8. FAQ.
9. CTA final.

Réduire les slogans répétitifs et les sections décoratives qui n'apportent aucune nouvelle information.

---

## 10. BLOC COMMERCIAL PREMIUM

Créer un bloc visible sur la page d'accueil :

**Toutes tes révisions. Un seul paiement.**

Pack Premium Objectif Infirmière

**59 € — Paiement unique**

Inclut :

- Accès à l'ensemble des fiches IDE du pack ;
- Accès à toutes les séries de QCM du pack ;
- Favoris et progression ;
- 100 questions IA incluses ;
- Aucun abonnement obligatoire.

Bouton :

**Débloquer mon accès Premium**

Ne pas afficher de quantité de fiches inventée.

Le nombre réel doit être récupéré depuis le catalogue publié.

Préciser clairement les éventuelles limites de disponibilité de contenus en cours de rédaction.

Ne pas promettre des mises à jour illimitées ou un accès perpétuel au conseiller IA.

---

## 11. BACK-OFFICE ADMINISTRATEUR

Créer une section administrative :

**Objectif Infirmière → Crédits**

Fonctions attendues :

- rechercher un utilisateur ;
- consulter ses soldes ;
- ajouter des crédits ;
- consulter les opérations ;
- vérifier les achats ;
- exporter des statistiques agrégées ;
- identifier les crédits promotionnels.

Permettre d'attribuer des crédits à une sélection d'utilisateurs ou à une cohorte de prospects inscrits non acheteurs.

Prévoir dès la V1 les fondations d'un système de campagnes, sans développer un outil d'emailing complet.

---

## 12. STATISTIQUES MARKETING

Ajouter le suivi des indicateurs :

- visites de la page d'accueil ;
- inscriptions gratuites ;
- utilisateurs ayant débloqué au moins une fiche ;
- utilisateurs ayant lancé un QCM ;
- utilisateurs ayant utilisé l'IA ;
- nombre moyen de crédits consommés ;
- pourcentage d'utilisateurs ayant épuisé leurs crédits ;
- consultations du Premium ;
- démarrages de Checkout ;
- achats Premium confirmés ;
- taux de conversion gratuit vers payant ;
- consommation IA moyenne des utilisateurs Premium ;
- coût IA estimé par étudiant.

Distinguer les événements de consultation, les déblocages et les consommations.

Ne pas envoyer de données de santé ou le contenu des échanges IA aux outils marketing.

Respecter les obligations relatives au consentement lorsque des outils de mesure non exemptés sont utilisés.

---

## 13. SÉCURITÉ ET ABUS

Prévoir :

- vérification des adresses email ;
- protection raisonnable contre la création massive de comptes ;
- limitation du débit des requêtes IA ;
- contrôle des permissions côté serveur ;
- protection contre les doubles dépenses de crédits ;
- prévention du contournement des contenus premium ;
- journaux d'erreur sans secrets ;
- politique de limitation des requêtes concurrentes.

Les contrôles de quotas et d'accès ne doivent jamais reposer uniquement sur JavaScript.

---

## 14. TESTS D'ACCEPTATION

Créer des tests couvrant au minimum :

### Inscription
- Nouvel utilisateur : 5/5/5 crédits.
- Reconnexion : aucun nouveau crédit.
- Validation d'email répétée : aucun doublon.

### Fiches
- Déblocage : -1 crédit.
- Relecture : aucun nouveau débit.
- Solde épuisé : contenu protégé.
- Utilisateur Premium : accès immédiat.

### QCM
- Déblocage : -1 crédit.
- Nouvelle tentative : aucun nouveau débit.

### IA
- Réponse réussie : -1 crédit.
- Erreur serveur : crédit préservé ou restitué.
- Solde nul : requête refusée avant l'appel OpenAI.
- Requêtes simultanées : pas de double dépense ou dépassement de quota.

### Stripe
- Paiement TEST confirmé : Premium activé.
- Paiement non confirmé : aucun accès.
- Webhook en doublon : aucune attribution supplémentaire.
- Achat Premium : +100 crédits IA une seule fois.
- Utilisateur existant : pas de nouveau compte.

### Parcours complet
Tester :

Site public → inscription → crédits gratuits → déblocage d'une fiche → QCM → question IA → achat Stripe TEST → accès Premium.

---

## 15. PLAN DE TRAVAIL

Commencer par inspecter le dépôt existant.

Identifier les fonctionnalités déjà opérationnelles et celles qui ne sont encore que des éléments de démonstration.

Créer un plan de modifications dans le dépôt.

Puis implémenter progressivement :

**Lot 1 :** système de crédits et gestion des droits.

**Lot 2 :** parcours gratuit et dashboard.

**Lot 3 :** catalogues fiches/QCM et déblocages.

**Lot 4 :** intégration des quotas IA.

**Lot 5 :** Stripe Premium à 59 €.

**Lot 6 :** refonte de la page d'accueil et du tunnel de conversion.

**Lot 7 :** statistiques, sécurité, tests et documentation.

Ne pas modifier le site en production.

Conserver une branche de référence avant la refonte.

Ne pas casser le fonctionnement existant.

Après chaque lot, exécuter les tests disponibles et documenter les résultats.

## CRITÈRE FINAL

Le projet est validé lorsqu'un étudiant peut :

1. Découvrir la plateforme.
2. S'inscrire gratuitement.
3. Choisir cinq fiches.
4. Débloquer cinq séries de QCM.
5. Poser cinq questions à l'IA.
6. Acheter le pack à 59 € via Stripe.
7. Accéder aux fiches et QCM Premium.
8. Disposer de 100 crédits IA supplémentaires.
9. Conserver sa progression et ses favoris.

Le design doit rester cohérent avec l'identité actuelle d'Objectif Infirmière.

**Objectif commercial : convertir des visiteurs en étudiants utilisateurs, puis en clients Premium, sans abonnement obligatoire.**