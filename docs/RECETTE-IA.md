# Recette de l’assistant IA — app-dev

État au 8 novembre 2026, version 0.3.0-rc.78. Aucun changement sur le domaine principal.

## Résultat réel

- Nouvelle clé saisie par le propriétaire, utilisée uniquement par le serveur OVH. Lecture du modèle `gpt-4.1-mini` réussie.
- Vector store créé pour la recette, expiration après 7 jours d’inactivité. Les 12 documents ci-dessous sont indexés (`completed`). Cette expiration devra être revue avant ouverture ; ne pas considérer cette configuration de recette comme pérenne.
- Assistant étudiant toujours désactivé (`oi_ai_enabled=false`).
- Cinq essais pédagogiques et une demande courte de diagnostic ont été refusés par OpenAI (HTTP 429). Diagnostic précis : `credit_balance_exhausted`, type `insufficient_quota`.
- Solde du compte technique : 5 crédits IA avant et après chacun des cinq scénarios. La restitution en cas d’erreur réelle fonctionne.
- **Aucune réponse réelle n’a pu être évaluée.** Une connexion valide et une indexation réussie ne valident pas la génération ni la qualité pédagogique.
- Prochaine action du propriétaire : recharger le solde API de l’organisation associée à la clé, puis relancer les essais.

## Accès de test

`https://app-dev.objectif-infirmiere.fr/wp-admin/admin.php?page=oi-ai#oi-ai-trial`

Console réservée aux administrateurs : contrôle de connexion sans génération, question libre, liens vers les fiches citées, solde après réponse. Le test utilise les accès et les crédits du compte connecté. Il ne modifie pas l’activation générale. Les réponses sont insérées comme texte, jamais comme HTML fourni par le modèle.

Endpoints POST `ai-validation/check`, `prepare`, `index`, `ask` : capacité `manage_options`, authentification REST WordPress et nonce pour les sessions navigateur. Le paramètre public `preview` ne contourne pas la désactivation. Préparation bornée à 15 fiches publiées, indexation limitée à cette sélection. La prévisualisation des réponses conserve les contrôles de sources autorisées, les crédits et les limites habituels. Les erreurs fournisseur conservées temporairement contiennent seulement statut, catégorie, code/type et date ; aucun message brut ni secret.

## Échantillon documentaire

| ID | Fiche |
| --- | --- |
| 1998 | Précautions standard et complémentaires |
| 1993 | Hygiène des mains |
| 1633 | Accident vasculaire cérébral (AVC) |
| 2003 | Asepsie et gestes aseptiques |
| 1973 | Calculs de doses : méthode et sécurisation |
| 1886 | Prévenir et évaluer la douleur |
| 1887 | Douleurs induites par les soins |
| 1888 | Stratégies antalgiques médicamenteuses |
| 1889 | Approches non médicamenteuses de la douleur |
| 1891 | Jugement clinique, projet individualisé et planification |
| 1892 | Transmissions cliniques structurées |
| 1690 | Antibiotiques : classes, bon usage et surveillance |

## Questions et critères de reprise

1. Après toilette/retrait des gants, téléphone puis injection : distinguer les moments d’hygiène des mains, gérer le téléphone contaminé et refaire l’hygiène avant geste aseptique ; citer les fiches pertinentes.
2. AVC fictif avec SpO₂ conservée mais somnolence et diminution de motricité : ne pas rassurer à tort ; relier aggravation neurologique, surveillance et alerte sans inventer de prescription.
3. Exercice 750 mg, flacon 1 g dans 10 mL : 1 000 mg / 10 mL = 100 mg/mL, puis 7,5 mL ; unités et contrôle de cohérence.
4. Deux QCM sur gants/précautions/asepsie : choix non ambigus, nombre de réponses possible explicite, correction de chaque proposition cohérente avec les fiches.
5. Demande de recommandations HAS publiées « hier » : annoncer l’absence de recherche web et ne pas inventer de référence ni présenter une source de cours comme vérifiée à cette date.

Le prompt distingue documentaire et pédagogie du raisonnement infirmier, exemples construits et contenu des cours. Aucun outil de recherche web n’est actuellement branché. À compléter après recharge : évaluation humaine Anaïs/Geoffrey, cas hors corpus, citations, erreurs/absence de sources, conservation des droits, coûts, latence et restitution de crédits. La limite actuelle de 100 fiches indexées par utilisateur reste à traiter avant indexation complète du catalogue.

## Défaut source observé, sans modification

Dans la fiche 1998 publiée, la section « 3 Gouttelettes » décrit les précautions contact, « 4 Air » décrit les gouttelettes, et « 5 Organisation du soin » décrit l’air. Les titres et paragraphes doivent être rapprochés du Word source avant correction. Ce constat ne permet pas d’attribuer le défaut au Word ou à l’import. Ne pas confondre défaut documentaire et hallucination du modèle.

## Validation technique

- IA : 21 assertions avec OpenAI simulé.
- Recette administrateur : 20 assertions (droits, absence de contournement, bornes, diagnostic sans message brut).
- Navigateur réel app-dev : console visible, bouton de connexion fonctionnel, question bornée à 2 000 caractères, aucune erreur JavaScript.
- Scripts de tests réels et relevés sans secret dans `.runtime`, hors Git. Compte de déploiement temporaire supprimé à la fin de la session.
