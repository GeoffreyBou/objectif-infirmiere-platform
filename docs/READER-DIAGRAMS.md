# Schémas et reprise de lecture — rc.33

Les schémas Word composés de cellules uniques reliées par des paragraphes contenant uniquement une flèche descendante deviennent des listes d’étapes numérotées. Les connecteurs sont dessinés en CSS, centrés, avec des espacements réduits. Les textes, liens, emphases et leur ordre sont conservés. Les tableaux comparatifs, branches, flèches dans les phrases et encarts isolés ne sont pas transformés en séquences.

Le formatage est appliqué à chaque ouverture dans le lecteur : fiches déjà publiées et prochains imports, sans réécriture en base ni réimport. Les encarts génériques n’ont plus d’étoile décorative ; les objectifs et l’essentiel conservent leurs icônes dédiées. Le libellé décoratif en haut à droite du fil d’Ariane des fiches/QCM est supprimé. Les mentions IFSI dans les contenus pédagogiques et commerciaux sont conservées.

Audit en lecture du 4 novembre 2026 : export WordPress de 775 fiches incluant la corbeille ; 417 fiches publiées contiennent les structures visées, soit 418 schémas et 2 095 étapes. Vérification automatisée sur ces 417 contenus : aucune flèche descendante isolée restante au premier niveau, textes et liens conservés, formatage idempotent. L’export est conservé dans le répertoire privé `.runtime`, hors Git.

La page courante est représentée par `view` dans l’URL ; une fiche ouverte ajoute `fiche=<identifiant>`. F5 réouvre la même fiche via l’API authentifiée, et conserve une section du sommaire si son ancre est présente. Le changement d’onglet retire l’identifiant devenu obsolète. Aucun contenu pédagogique ni droit d’accès n’est conservé dans le stockage du navigateur. Le serveur contrôle toujours l’accès ; aucun déblocage n’est déclenché lors d’une ouverture par URL. Le rechargement d’un QCM retrouve sa page, mais ne restaure pas les réponses d’un entraînement interrompu.

Validation navigateur ordinateur/iPhone : séquences, contre-exemples, liens, ordre, connecteurs centrés, encarts, sommaire, impression, accès par URL, rechargement des fiches et pages QCM, accès refusé sans déblocage, changement d’onglet sans identifiant résiduel. Archive du code rc.32 conservée pour retour arrière.
