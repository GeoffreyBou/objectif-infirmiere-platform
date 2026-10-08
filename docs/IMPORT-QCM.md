# QCM par fiche, thème et UE — rc.9

## Préparer un Excel

Dans l’espace administrateur, **Gérer les QCM → Télécharger le modèle Excel**. Le modèle possède un onglet `Questions` et un mode d’emploi. Remplacer sa ligne d’exemple ; aucun contenu pédagogique n’est généré automatiquement.

Un classeur par fiche, par exemple `B1-UGR-002_Sante-sexuelle_qcm.xlsx`, à côté de son Word dans Drive. Le préfixe `B1-UGR-002` lie la banque à la fiche publiée, quelle que soit la suite du nom. La connexion Google existante suffit ; seul l’accès en lecture est utilisé.

Colonnes :

| Colonne | Contenu |
| --- | --- |
| code_question | Identifiant permanent, ex. B1-UGR-002-Q001. Ne pas réutiliser pour une autre question. |
| enonce | Texte de la question |
| A à E | Propositions ; au moins deux. F, G et H possibles en colonnes supplémentaires. |
| bonnes_reponses | Lettre seule, ou lettres séparées par `;`, ex. A;C |
| explication | Corrigé expliqué, obligatoire |
| statut | actif (par défaut) ou archive |

`code_fiche` est optionnel et doit correspondre au fichier s’il est fourni. Le classement UE/thème provient de la fiche, sans recopie dans Excel. Une seule bonne réponse donne un QCU ; plusieurs donnent un QCM ; Vrai/Faux s’exprime avec deux propositions.

Limites : `.xlsx` uniquement, 5 Mo, 200 questions stockées par fiche (archives comprises), huit propositions maximum (1 000 caractères chacune), énoncé de 2 000 caractères et explication de 6 000 caractères maximum. Les colonnes manquantes, codes répétés, bonnes réponses incohérentes et cellules avec formules sont signalés. Aucun calcul, macro ou lien externe de classeur n’est exécuté. Les Google Sheets natifs et `.xls` ne sont pas importés dans cette version.

## Revue et publication

1. Analyser les QCM du Drive, sélectionner jusqu’à dix Excel et préparer, ou téléverser directement les fichiers.
2. Corriger si besoin énoncés, propositions, réponses, explications et statuts dans la préparation.
3. Prévisualiser les changements avant/après. La prévisualisation porte sur les corrections comprises.
4. Publier une série ou jusqu’à vingt séries prévisualisées. Chaque résultat est explicite.
5. Consulter les versions précédentes et restaurer si la banque n’a pas été modifiée entre-temps.

Les dossiers de versions sont comparés numériquement et la dernière version par code est retenue. Les doublons de même version sont bloqués. Le scan Word et le scan Excel ont des états distincts. Une préparation ne publie rien. Un Excel identique déjà publié est ignoré ; un fichier déjà préparé est réutilisé.

Les questions sont mises à jour par code. Les questions absentes du nouvel Excel sont **conservées** ; utiliser `archive` pour les retirer des entraînements. Les anciennes questions sans code sont conservées sous un identifiant technique stable. Leurs résultats historiques ne sont pas réécrits.

La prévisualisation est liée au compte administrateur et à l’état de la banque ; une modification concurrente invalide sa publication. Publication/restauration sont sérialisées et sauvegardent la banque précédente. L’éditeur JSON WordPress est désactivé pour les banques importées, pour éviter une troncature ou la perte des identifiants.

## Entraînements

**QCM & partiels → Fais le point sur tes acquis** : choisir fiche, thème ou UE, puis 5, 10, 20 ou 40 questions. Un stock insuffisant réduit explicitement la taille de la session, sans répétition. Les questions sont réparties entre les fiches disponibles, tirées sans remise et présentées dans un ordre aléatoire. Les fiches n’ont pas besoin de dupliquer leurs questions pour alimenter les niveaux supérieurs.

Seules les séries auxquelles l’étudiant a déjà accès participent au tirage. Un entraînement transversal ne débite aucun crédit supplémentaire ; les déblocages QCM existants restent le point d’entrée. Les administrateurs peuvent tester l’ensemble. Un renvoi vers une fiche n’octroie pas gratuitement son accès si elle est verrouillée.

Les questions/corrigés sont figés côté serveur au début de la session. Le navigateur reçoit les propositions, sans réponses exactes ni explications, jusqu’à la soumission. Les réponses sont corrigées côté serveur ; une seconde soumission renvoie le même résultat et ne recompte pas la progression. Les corrections indiquent les réponses attendues et renvoient à la fiche source. Le score porte sur le tirage, sans prétendre certifier toute l’UE.

Sessions limitées à 24 heures et aux 50 dernières par compte ; nombre cumulé de sessions corrigées conservé pour la progression. Les résultats antérieurs ne sont pas recalculés lorsqu’un Excel change. Une révocation d’accès empêche la correction d’une session devenue inaccessible.

## Validation

Tests serveur dédiés : parseur XLSX, erreurs de ligne, formules/XML malveillants, authentification, preview/publication, doublons, changements concurrents, historique/restauration, archivage, conservation des questions absentes, transport Google simulé pour Excel et indépendance du scan Word, droits étudiants, tirages aux trois niveaux, confidentialité des réponses, stabilité après modification, absence de débit, correction idempotente.

Tests navigateur ordinateur/iPhone : import multipart Excel, revue et publication, entraînements aux trois niveaux, correction et retour au cours. Le classeur réel fourni par le propriétaire reste à qualifier ; les tests utilisent des fixtures techniques, jamais publiées en production.


Validation du 8 octobre 2026 : 39 assertions QCM dédiées, 50 imports Word, 13 révision et 18 administration réussies. Deux parcours QCM navigateur (ordinateur/iPhone), quatre parcours existants ordinateur réussis. Modèle relu avec openpyxl indépendamment du parseur serveur.

Production rc.9 : atelier/API et assets disponibles, téléchargement XLSX vérifié, connexion Google conservée, sélecteurs de périmètre disponibles. Analyse réelle du Drive : aucun `_qcm.xlsx` présent à cette date ; aucune préparation/publication de questions techniques. Le classeur réel du propriétaire reste à tester lorsqu’il sera déposé.
