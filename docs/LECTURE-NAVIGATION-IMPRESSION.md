# Lecture, menu et impression — 13 octobre 2026

Livraison `0.3.0-rc.21`, sur demande du propriétaire.

- Navigation plus grasse, invitation « Passer Premium », description et CTA « Découvrir l’offre ». Même destination Premium et masquage conservé pour les membres Premium.
- Signature « Réviser aujourd’hui, soigner demain ! » avec cœur décoratif et police Caveat livrée localement sous licence OFL.
- Pastilles UE et thème cliquables : l’UE ouvre ses thèmes, le thème ouvre ses fiches. La recherche et le filtre semestre sont réinitialisés pour ne pas masquer les résultats. Le retour « Mes révisions » reste disponible.
- Sommaire et titres numérotés harmonisés, sans répéter le numéro déjà présent dans le Word. La transformation concerne uniquement le DOM affiché ; le HTML en base n’est pas modifié. Liens et emphases internes conservés.
- Encarts objectifs bleus et essentiel en 30 secondes lavande, avec pictogrammes. Les tableaux à cellule unique restent les encarts d’origine ; aucun texte pédagogique ajouté.
- Bouton « Imprimer » à côté des actions d’une fiche ouverte et accessible. L’impression affiche le cours et son filigrane, sans navigation, illustration décorative, actions, QCM, recommandations ou promotion. Elle utilise la boîte d’impression du navigateur (imprimante ou PDF), sans service tiers. Cette demande remplace la précédente interdiction d’impression ; la protection de sélection/copie à l’écran et les contrôles d’accès restent en place.

## Contrôles

Quatre tests Playwright passent sur desktop et iPhone : retour UE/thème depuis recherche/semestre et historique ; encarts et numérotation ; appel au dialogue d’impression simulé ; cours et filigrane visibles en média print et interface masquée ; absence du bouton sur les QCM ; HTML source inchangé. Contrôle visuel complémentaire à 390, 820, 1280 et 1600 px, et PDF A4 sur la fiche AVC réelle.

La version complète rc.20 (commit `3eb40e6`) et son empreinte sont conservées dans `.runtime/reader-rollback-2026-10-13/` hors Git. Les cinq assets précédents ont été comparés exactement à la production avant déploiement. Une réinstallation de cette archive permet le retour visuel sans restaurer la base.
