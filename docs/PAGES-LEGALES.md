# Pages légales publiques

Textes repris le 7 novembre 2026 depuis les pages fournies par le propriétaire :

- https://objectif-infirmiere.fr/mentions-legales/ (page WordPress 528) : mentions légales, conditions d’utilisation, remboursement et règlement sécurisé.
- https://objectif-infirmiere.fr/politique-de-confidentialite/ : définitions et treize rubriques, puis informations sur les cookies. La date d’origine du 04/09/2024 est conservée.

Il s’agit d’une reprise éditoriale, sans réécriture ni actualisation juridique. Les menus, bandeaux de présentation et anciens pieds de page sont remplacés par ceux de l’application. Les retours à la ligne artificiels du constructeur de pages sont retirés ; les listes et le tableau de conservation sont remis en forme. Le contrôle inactif « Gérer vos Préférences Cookies » (ancien lien `#`) n’est pas importé ; les explications et les liens de paramétrage des navigateurs sont conservés. Aucun traceur ou gestionnaire de consentement du site source n’est importé.

`content/mentions-legales.html` et `content/politique-de-confidentialite.html` sont les copies initiales. `OI_Legal::install()` crée les deux pages WordPress à la première utilisation, avec verrou d’installation et marqueur `oi_legal_pages_version`. Les pages déjà présentes ne sont pas remplacées. Le contenu devient ensuite modifiable dans **Pages** ; les mises à jour du plugin ne l’écrasent pas.

Options : `oi_page_legal`, `oi_page_privacy`. La page de confidentialité est associée à `wp_page_for_privacy_policy`, ce qui relie aussi le formulaire d’inscription à cette page. L’ancien réglage est mémorisé dans `oi_legal_previous_privacy_page`.

Le gabarit commun comprend un sommaire, une colonne de lecture et le pied de page public partagé. Les liens légaux sont centrés sur ordinateur et passent sur leur propre ligne sur mobile. Les contenus publics restent consultables sans compte et sans JavaScript.
