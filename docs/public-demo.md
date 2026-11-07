# Démonstration publique de révision

La page d’accueil embarque neuf questions du catalogue et neuf extraits limités des fiches. Aucune requête à la banque privée n’est effectuée pendant la démonstration. Les réponses restent en mémoire dans l’onglet, sans toucher aux crédits ni à la progression du membre. Un rafraîchissement les réinitialise.

## Sources

Sélection vérifiée le 7 novembre 2026 sur les contenus publiés :

| Sujet public | Fiche | Questions, dans l’ordre |
| --- | --- | --- |
| Précautions standard | 1998 — B3-HYG-007, Précautions standard et complémentaires | Q006, Q007, Q009 |
| Calculs de doses | 1973 — B3-MDE-010, Calculs de doses : méthode et sécurisation | Q006, Q007, Q009 |
| Pathologie – AVC | 1633 — B1-NEU-001, Accident vasculaire cérébral (AVC) | Q003, Q006, Q005 |

`assets/demo-content.js` conserve les codes, énoncés, options, bonnes réponses et explications issus de ces séries. Seuls les préfixes de difficulté et les mentions du nombre de réponses ont été retirés des énoncés : le type de réponse est affiché séparément. L’explication principale reprend celles des bonnes réponses ; le détail conserve celles de toutes les propositions. Les extraits sont des passages exacts des fiches associées, sélectionnés pour la question. Ils deviennent consultables après validation.

Ce fichier est un instantané éditorial : lors d’une correction des sources, reporter les modifications dans la sélection publique, puis vérifier les réponses et les extraits avant publication. Ne pas exporter l’intégralité des banques ou des fiches.

## Parcours

- Trois questions fixes par sujet, choix unique ou multiple, sans correction avant validation.
- Correspondance exacte des choix attendus : une sélection partielle ne vaut pas une réponse juste.
- Réponses verrouillées après validation ; progression indépendante conservée au changement de sujet.
- Correction explicite par le texte et les couleurs, extrait repliable, puis question suivante.
- Bilan sur trois avec détail des choix, invitation gratuite et recommencement du sujet.
- L’inscription conserve la préférence existante pour hygiène/calculs. AVC mène à l’inscription générale, car le formulaire ne propose pas encore cette préférence.
- Fallback d’inscription sans JavaScript, contrôles natifs au clavier, focus sur la correction/question suivante, animation désactivée avec préférence de mouvement réduit.

Validation : `npx playwright test tests/browser/landing.spec.js --project=desktop --project=iPhone` ; contrôle visuel complémentaire de 320 à 1440 px.
