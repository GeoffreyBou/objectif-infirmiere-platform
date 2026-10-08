# Proposition : créer et mettre à jour les QCM en masse

Statut : proposition de fonctionnement, non développée.

## Point de départ recommandé

Un modèle Excel `.xlsx`, avec une ligne par question, et un onglet administrateur « Gérer les QCM ». Même parcours que les fiches : importer, contrôler, prévisualiser, publier une sélection. Un formulaire permet les petites corrections sans réimporter un classeur entier.

Colonnes : code question permanent, code fiche, énoncé, réponses A à E, bonne(s) réponse(s) (ex. `A;C`), explication du corrigé. Le code fiche fournit déjà l’UE et le thème ; ne pas demander de recopier ces informations. Exemple d’identifiant question : `B1-UGR-002-Q001`. Un import comportant le même code propose une mise à jour, pas une copie. Un fichier peut couvrir plusieurs fiches.

La première version alimente les QCM rattachés aux fiches existantes. Le moteur actuel stocke les questions par fiche et accepte au maximum 20 questions et 8 propositions : toute limite dépassée doit être signalée, jamais tronquée silencieusement. Une banque de questions indépendante et des séries de partiels transversales pourront venir ensuite.

## Contrôle et publication

- Signalement par ligne : fiche inconnue, code répété, question vide, moins de deux propositions, correction absente ou désignant une proposition vide, explication manquante.
- QCU/QCM déduit du nombre de réponses correctes ; vrai/faux saisi avec deux propositions.
- Aperçu tel que vu par l’étudiant, corrigé visible à l’administrateur ; publication par fiche ou sélection.
- Les lignes absentes d’un nouvel import ne suppriment pas automatiquement les questions en ligne. Archivage explicite.
- Version précédente restaurable. Les anciens résultats ne sont pas recalculés à partir des nouvelles réponses ; prévoir les identifiants/versions nécessaires pour conserver la signification des tentatives.
- Lecture de données uniquement : ne pas exécuter les formules/macros et ne pas suivre les liens externes du classeur.

## Drive et aide à la rédaction

D’abord le dépôt du fichier Excel dans l’interface : aucun nouveau service à configurer. Ensuite, lecture d’un classeur Excel placé dans le Drive déjà connecté. Un Google Sheet natif demanderait de prévoir son export et ses limites, plutôt que promettre implicitement sa prise en charge.

Une génération depuis les fiches peut préparer des brouillons à relire (réponse exacte, distracteurs, explication et référence à la fiche). Elle ne doit pas publier automatiquement du contenu pédagogique médical. L’import Excel n’exige aucun abonnement supplémentaire ; la génération IA aurait son propre coût d’usage.
