# Images d’ouverture des Word et profil — 13 octobre 2026

Livraison `0.3.0-rc.22`.

Le bloc avatar, nom du compte et « Espace personnel » est déplacé du bas du menu vers le bandeau, juste avant « Déconnexion ». Il est rendu côté serveur dès le chargement et reste visible sur mobile, avec troncature des noms longs.

Les véritables en-têtes Word (`word/header*.xml`) étaient déjà ignorés. Certaines fiches placent cependant le logo dans un paragraphe du corps, avant le titre. Le convertisseur retire désormais les paragraphes d’ouverture composés uniquement d’images, à condition de reconnaître le titre Word ou un titre court immédiatement suivi de sa ligne UE. Les images après le titre, les images légendées, les tableaux et les structures ambiguës sont conservés. Une image réutilisée dans le cours reste disponible.

Cette règle s’applique à la conversion et au rendu des anciens contenus, y compris les prévisualisations. Les fiches existantes bénéficient donc de la correction sans réimportation ni modification du contenu enregistré. Les nouveaux imports ne stockent plus les octets des images exclues, sauf si elles sont également utilisées dans le corps. Les contrôles d’accès aux illustrations restent identiques.

## Validation

- 23 assertions ciblées : images d’ouverture, titres Word stylés, ambiguïtés, illustrations du corps, réutilisation d’image, anciens contenus et absence de mutation de leurs données.
- 50 assertions d’import existantes : conversion, publication, restauration, images protégées et transport Drive simulé.
- Quatre parcours de lecture Playwright sur ordinateur et iPhone : navigation, encarts, numérotation et impression.
- Profil contrôlé visuellement à 1600, 820, 390 et 320 px, sans chevauchement ni débordement.
- Exemple réel « Accompagnement de l’arrêt du tabac et substituts nicotiniques » : seul le paragraphe du logo initial est retiré, le reste du HTML est conservé exactement.

Archive complète rc.21 (`317838e`) et manifeste conservés hors Git dans `.runtime/header-rollback-2026-10-13/`. Les assets précédents ont été comparés à la production avant remplacement. Aucune migration de base de données.
