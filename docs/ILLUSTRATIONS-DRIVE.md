# Illustrations des fiches — rc.27

Les illustrations préparées dans Drive sont importées dans le site par code permanent (`B1-NEU-001`, par exemple). Le titre affiché et le libellé du fichier peuvent changer sans casser cette association. Les Word, classifications, droits, crédits et états de révision ne sont pas modifiés.

Dans **Mise à jour Fiches → Les illustrations de tes fiches** :

1. Analyser les images du Drive.
2. Tout sélectionner, ou sélectionner quelques images.
3. Importer la sélection. Garder l’onglet ouvert : un suivi affiche l’avancement, les intégrations et les erreurs. Les lots contiennent au plus trois images. Une nouvelle analyse reconnaît les images déjà importées et permet de reprendre après une interruption.

PNG transparent recommandé ; JPEG et WebP acceptés. Le nom doit commencer par le code permanent de la fiche. Les dossiers de versions et suffixes `_v0.3` suivent les règles de l’import Word. Les archives sont ignorées et la version numérique la plus récente est retenue. Les codes `PAR-IFSI-001` du parcours IFSI sont aussi reconnus. Des copies strictement identiques (même empreinte MD5) sont regroupées en une seule illustration. Plusieurs fichiers différents pour le même code et la même version sont signalés comme doublons, jamais choisis arbitrairement. Une image sans code n’est pas associée par ressemblance de titre.

Une illustration peut être enregistrée avant publication de son Word. Elle sera affichée automatiquement lorsque la fiche portant le même code sera publiée. La suppression d’un fichier Drive ne supprime pas l’image du site. Une illustration existante reste disponible si Drive est indisponible.

Les images sont converties en WebP, qualité 82, dimensions maximales 1024 × 1024, sans agrandissement et avec transparence conservée. Les cartes utilisent une vignette de 256 pixels, calculée et conservée à la première consultation, pour éviter de charger toutes les grandes images. Limites : 10 Mo en entrée, 16 mégapixels, 768 Ko après conversion. Les octets optimisés sont conservés dans des métadonnées privées `oi_fiche_art`, séparément du texte des fiches, sans copie du fichier original dans les médias publics. Un compte membre autorisé voit les illustrations des fiches publiées du catalogue, y compris leurs vignettes verrouillées ; cela ne débloque pas le contenu du cours. Les images des fiches non publiées et l’historique sont réservés à l’administration.

Le lecteur utilise l’image propre à la fiche, ainsi que les cartes de bibliothèque, favoris et consultations récentes. Sans illustration, le visuel générique reste présent. L’image décorative d’en-tête est masquée à l’impression. Les schémas pédagogiques internes au Word restent gérés par l’import Word.

**Retour arrière** : « Images et versions précédentes » permet de restaurer la version précédente de chaque illustration. Si l’on annule un premier import, le visuel générique réapparaît. Cette restauration est elle-même réversible. Une vérification d’empreinte empêche de restaurer une version depuis un écran périmé. Un verrou par code et une transaction protègent les imports concurrents.

## Validation locale

```sh
scripts/dc.sh wp eval-file /tests/fiche-art.php
scripts/dc.sh wp eval-file /tests/imports.php
npx playwright test tests/browser/fiche-art.spec.js --project=desktop --project=iPhone
```

Les tests vérifient notamment la transparence, le redimensionnement, les correspondances malgré changement de titre, les doublons, les versions, l’idempotence, les restaurations, les autorisations, le parcours Drive paginé et l’import des octets. Les tests navigateur vérifient la lecture des images privées et leur cache conditionnel, le mobile, l’impression et l’import de 101 images avec erreur partielle.
