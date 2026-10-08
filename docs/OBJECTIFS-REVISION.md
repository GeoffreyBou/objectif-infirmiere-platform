# Objectifs personnels et planning — rc.10

La page **Ma progression** remplace le compteur global par un objectif choisi par l’étudiant. L’ancienne phrase de présentation et les boutons de bas de page vers Favoris/Premium sont retirés.

## Parcours

1. Donner un nom à l’objectif et préciser le type de partiel : QCM/QCU, QROC, cas clinique, oral, pratique ou plusieurs formats.
2. Naviguer par **UE → thème → fiche**, comme dans la bibliothèque. Une UE/un thème peut être coché en entier ; une recherche facilite la sélection fine.
3. Enregistrer. L’objectif est personnel, persiste après reconnexion et reste modifiable. Un sélecteur permet de reprendre un précédent objectif ou de créer un nouveau cycle.
4. Cocher les notions révisées dans l’objectif, ou utiliser « Valider pour mon objectif » depuis la fiche associée.

Le pourcentage est le nombre de fiches cochées pour cet objectif divisé par la sélection enregistrée. L’ouverture d’une fiche ou un score QCM ne vaut pas validation automatique. Un nouveau cycle démarre à zéro, sans effacer les anciens objectifs ni l’historique global de révision. Modifier la sélection conserve les validations des fiches maintenues ; une fiche retirée puis réintroduite repart non validée dans cet objectif.

La sélection d’un thème inclut ses fiches actuelles : des publications ultérieures ne modifient pas silencieusement le périmètre. Si une fiche devient indisponible, elle reste signalée dans l’objectif et dans son dénominateur jusqu’à modification de la sélection ; elle ne transforme pas artificiellement une progression incomplète en 100 %.

La planification peut inclure une fiche verrouillée sans la débloquer. Il est possible de déclarer une notion révisée à partir de ses propres cours ; la progression est déclarative. Lire le contenu de la plateforme conserve ses contrôles d’accès et de crédits habituels. Créer un objectif, planifier et cocher une notion ne consomment aucun crédit.

## Organisation dans le temps

Trois modes : sans échéance, date de partiel, ou durée en jours/semaines. L’étudiant choisit le premier jour, les jours de la semaine, le temps disponible par jour et une estimation de temps par fiche (20 minutes proposées, modifiables).

Le serveur répartit les fiches restantes sur les jours à venir, jusqu’à la date limite comprise. Il respecte le budget quotidien et signale le nombre de fiches non planifiables si la capacité est insuffisante. Une échéance dépassée invite à ajuster les dates ; aucun rattrapage irréaliste n’est présenté comme acquis. Le planning se recalcule à la consultation, selon la date locale du fuseau enregistré et l’avancement. Il est indicatif, sans estimation pédagogique individualisée automatique et sans notification/calendrier externe.

Limites : période jusqu’à un an, 1 à 1 000 fiches par objectif, jusqu’à 50 objectifs par compte ; disponibilité de 10 à 240 minutes/jour et estimation de 5 à 180 minutes/fiche. Le calcul utilise des dates calendaires et gère les changements d’heure.

## Assistant IA

Le dernier bloc ouvre l’assistant avec une demande préremplie et modifiable : nom de l’objectif, format du partiel, échéance et disponibilités, progression et jusqu’à quinze notions à travailler (ou à consolider lorsque tout est coché). La demande n’est pas envoyée automatiquement et n’entraîne pas de débit à l’ouverture. L’envoi conserve les conditions d’activation et crédits IA déjà en place ; ce travail n’active pas un service IA encore indisponible.

## Conservation et sécurité

Métadonnée privée `oi_goals` par utilisateur. Les routes n’acceptent pas de compte cible fourni par le client ; UUID d’un objectif étranger = 404. Le catalogue n’expose que les titres/classements et l’état d’accès, aucun contenu protégé. Les dates, sélections, types de partiel et durées sont contrôlés côté serveur.

Écritures sérialisées par utilisateur et version de chaque objectif vérifiée avant modification : une page obsolète reçoit une erreur de conflit plutôt que d’écraser les progrès enregistrés dans un autre onglet. Le lecteur conserve le contexte de l’objectif auquel sa validation se rapporte.

## Vérifications

Tests serveur : isolation entre comptes, absence de fuite/déblocage/débit, hiérarchie du programme, progression indépendante des anciennes révisions, conflits concurrents, édition et reprise, nouveau cycle, dates invalides, échéance passée, disponibilité insuffisante, répartition sur les jours choisis, changement d’heure et fiche devenue indisponible.

Tests navigateur ordinateur/iPhone : création, sélection, planning, rechargement, progression depuis l’objectif et depuis la fiche, nouveau cycle, reprise et édition, demande IA préremplie sans envoi, second compte vierge et absence de débordement horizontal.


Validation du 8 octobre 2026 : 34 assertions objectifs/planning et 13 révision réussies ; deux parcours objectifs (ordinateur/iPhone) et quatre parcours membre existants (ordinateur) réussis. Vérification en production de la création, planification, sauvegarde, modification, progression, nouveau cycle et reprise sur un compte technique temporaire, supprimé avec ses objectifs après contrôle. Aucun compte étudiant ni contenu pédagogique modifié.
