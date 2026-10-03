# Architecture Objectif Infirmière

## Décision
WordPress natif + plugin propriétaire unique + Stripe Checkout + OpenAI Responses/File Search. PHP 8.3+, WordPress 6.8+. Pas de LearnDash, framework JavaScript ni service applicatif séparé. Docker sert uniquement aux tests locaux, pas au déploiement OVH. L'installation OVH, son thème et ses plugins restent à inventorier : aucun accès fourni.

## Modèle
- `oi_fiche` : contenu web natif, privé sur les routes WordPress publiques, éditeur WordPress classique. Quiz en métadonnée structurée.
- `oi_pack` : produit administrable, IDs Stripe configurables ; liste explicite des fiches accordées.
- Taxonomies administrables : `oi_formation`, `oi_semestre`, `oi_domaine`, `oi_enseignement`, `oi_theme`. Aucun référentiel officiel présumé : les données de démonstration ne sont pas une certification IFSI 2026.
- Rôle `oi_etudiant` : lecture seulement ; aucune édition des contenus ou des droits.
- Métadonnées utilisateur : packs accordés manuellement (administration seulement), favoris, révisions, consultations.
- Tables plugin : paiements avec session Stripe unique, événements webhook idempotents, réservations quota IA atomiques et consommation tokens. Préfixe WordPress, migrations à l'activation.

## Autorisation
Une fiche publiée est accessible si l'utilisateur possède un pack publié contenant son ID. Administrateurs autorisés. Vérification serveur à chaque lecture, recherche, quiz et appel IA. Les CPT ne sont pas exposés par le REST WordPress natif, les archives, le moteur public, les feeds ou les sitemaps. Médias premium : ne pas téléverser de données confidentielles dans la médiathèque publique sans protection serveur OVH spécifique.

## Endpoints `/wp-json/oi/v1`
`GET /catalog`, `/fiches`, `/fiches/{id}` ; `POST /fiches/{id}/state`, `/fiches/{id}/quiz`, `/checkout`, `/stripe/webhook`, `/ai`. Authentification WordPress et nonce REST pour les routes étudiant ; webhook public avec signature Stripe et fenêtre temporelle. Les prix et droits sont résolus côté serveur.

## Stripe
Mode TEST imposé pendant le développement. Secret serveur `OI_STRIPE_SECRET_KEY`, signature `OI_STRIPE_WEBHOOK_SECRET`. Checkout créé depuis un pack publié et Price ID serveur. Acquisition uniquement après événement signé, `livemode=false`, paiement payé et vérification serveur de la session et de ses lignes. Session unique ; rejouabilité sans doublon. Compte créé par WordPress, email de définition de mot de passe natif. Remboursements et abonnements : extension ultérieure explicite, pas support implicite.

## OpenAI
Secret serveur `OI_OPENAI_API_KEY`. Responses API, outil File Search, vector store configurable. Document publié extrait et uploadé ; ID fichier enregistré pour remplacement et suppression. Recherche limitée aux fiches autorisées par filtre d'IDs, citations résolues via IDs fichiers et permissions. Aucun nom, email ou paiement transmis. Quota atomique quotidien, indépendance totale des fiches si IA indisponible. Prompt administrable avec règles pédagogiques serveur obligatoires. Validation réelle nécessite une clé et un vector store.

## Interface et structure
Shortcode `[objectif_infirmiere]` : connexion native, dashboard, catalogue paginé, lecteur, recherche, favoris, progression, quiz et IA. CSS mobile-first, JavaScript natif, sorties échappées. Classes distinctes dans `includes` pour modèle, REST, Stripe, IA et administration. Contenus de démonstration fictifs à faire relire avant usage pédagogique.

## Sécurité et opérations
Nonces, capacités WordPress, validation stricte, SQL préparé, secrets hors Git, aucune donnée bancaire. Réponses privées sans cache partagé ; throttling par utilisateur. Sauvegarde DB et wp-content avant déploiement. Tests HTTP réels sur WordPress local et tests scénarios paiements avec transport simulé ; simulations distinguées des validations Stripe/OpenAI réelles. HTTPS, email délivré et compatibilité thème sont à vérifier sur OVH.
