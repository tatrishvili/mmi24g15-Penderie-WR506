# Présentation de Penderie — Mon espace et le back-office

> Document de préparation : ne pas le lire pendant l'oral. La consigne est de
> parler au commanditaire en regardant le public et de s'appuyer sur le site,
> pas sur le téléphone ou sur des notes.

## Objectif en une phrase

**Mon espace aide chaque foyer à agir sur ses affaires ; le back-office aide
l'équipe à comprendre l'état du service et à traiter des signalements sans
ouvrir les inventaires privés.**

## Déroulé de 3 minutes — 3 diapositives ou visite du site

### 1. Mon espace : voir ce qui demande une action — 0:00 à 1:00

**À montrer**

1. Depuis **Profil**, ouvrir **Mon espace** (`/my-space`).
2. Montrer les cartes Objets, Lieux, Foyer et Prêts en cours.
3. Ouvrir une carte, puis montrer rapidement les objets à compléter ou
   l'activité récente.

**Idée à faire passer**

> « Mon espace transforme les données du dressing en actions simples : retrouver
> ses objets et leurs lieux, voir les prêts en cours, compléter une fiche et
> retrouver qui a changé quoi. Les changements d'objets ouvrent directement la
> fiche à modifier. »

Les cartes **Profils** et **Membres** ont été regroupées sous **Foyer** pour
éviter de présenter deux nombres comme s'ils comptaient la même chose. Dans le
détail, les profils du foyer et les comptes de connexion restent visibles
séparément.

### 2. Le tableau de bord admin : piloter le service — 1:00 à 2:00

**À montrer**

1. Ouvrir le tableau de bord `/admin`.
2. Montrer une période de mesure et deux ou trois indicateurs, puis le journal
   d'activité.
3. Ne pas essayer d'expliquer chaque chiffre : choisir ceux qui répondent à une
   question concrète.

**Idée à faire passer**

> « Le tableau de bord répond à des questions de gestion : combien de foyers et
> de comptes sont actifs, si les usages progressent, et quels volumes de prêts,
> partages ou erreurs techniques sont observés. La période permet de comparer
> les tendances. Ce sont des indicateurs de suivi, pas une mesure de la
> satisfaction des utilisateurs. »

Les indicateurs sont agrégés. Le journal aide à comprendre les actions
importantes ; il ne remplace pas un outil complet de supervision ou d'analyse
commerciale.

### 3. Modérer avec mesure : comprendre et agir sans tout supprimer — 2:00 à 3:00

**À montrer**

1. Ouvrir **Utilisateurs** pour montrer la recherche et les états de compte.
2. Ouvrir **Signalements** pour montrer la file de revue. Présenter les actions
   sans suspendre un vrai compte ni désactiver un vrai lien pendant la démo.
3. Terminer sur le périmètre de confidentialité.

**Idée à faire passer**

> « Pour un compte adulte, l'équipe peut suspendre puis rétablir l'accès ; elle
> ne supprime pas le compte et son historique. Pour un contenu signalé, elle
> agit sur le lien de partage précis, pas sur l'objet privé d'origine. Les
> décisions sont limitées, réversibles et consignées. »

**Conclusion**

> « Le choix principal est de rendre le produit utile au quotidien tout en
> donnant à l'équipe assez d'informations pour agir, sans lui donner accès à
> tout le contenu privé. »

## Préparation de la démonstration

- Se connecter avant de commencer avec un compte adulte de démonstration.
- Pour `/admin`, utiliser le compte de démonstration auquel le rôle
  `ROLE_SUPER_ADMIN` a été accordé. Ne jamais montrer ni inscrire le mot de passe
  dans ce document ou sur une diapositive.
- Vérifier que les deux pages s'ouvrent et que les données de démonstration sont
  présentables. Si la file de signalements est vide, expliquer le parcours sans
  inventer un signalement.
- Utiliser des données fictives ou masquer les adresses e-mail et noms réels.
- Garder les clics courts : une carte My Space, le tableau de bord, puis les
  pages Utilisateurs et Signalements.
- Ne pas promettre que chaque section visible est déjà fonctionnelle : voir
  **Limites à présenter honnêtement** ci-dessous.

## Questions probables — réponses courtes

### Questions de commanditaire / produit

#### Pourquoi distinguer Mon espace du back-office ?

Mon espace sert aux personnes qui gèrent leur foyer et leurs affaires. Le
back-office sert à l'équipe qui suit le service et traite des incidents. Les
besoins et les niveaux d'accès ne sont pas les mêmes.

#### Que compte la carte Foyer : les profils ou les membres ?

Le nombre résumé est celui des profils. Un profil représente une personne dans
le foyer ; un compte membre représente une identité qui peut se connecter. Ces
notions peuvent se recouper, mais ne sont pas interchangeables. Le détail les
sépare pour éviter les confusions.

#### Pourquoi ne pas supprimer un compte signalé ?

Un signalement doit être vérifié. La suspension est réversible et conserve les
objets, prêts et éléments d'audit nécessaires ; une suppression définitive aurait
des conséquences plus larges et demande une politique dédiée.

#### Pourquoi un signalement ne supprime-t-il pas l'objet ?

Le signalement concerne un partage précis. Désactiver ce lien réduit le risque
sans retirer l'objet privé de son propriétaire ni affecter ses autres partages.

#### Les indicateurs disent-ils si les utilisateurs sont satisfaits ?

Non. Ils décrivent l'activité observée — comptes actifs, visites, prêts,
partages ou erreurs de requête — pas la satisfaction, la fidélité ni la qualité
perçue. Il faudrait des retours utilisateurs pour répondre à ces questions.

#### Quelles sont les limites actuelles ?

Les sections « À vendre / peu utilisés » et « Commentaires reçus » sont encore
des emplacements avec un état « bientôt disponible » : leur logique métier n'est
pas livrée. Je ne les présente donc pas comme des fonctions terminées. Les
indicateurs de visite ne sont pas non plus un outil complet de supervision.

### Questions techniques

#### Comment l'accès à Mon espace est-il protégé ?

La page React appelle l'API Symfony. Le serveur détermine le compte, le profil
actif et le foyer autorisés ; il ne fait pas confiance à un simple filtre ou à
un bouton masqué dans l'interface. Un enfant est limité à ses propres objets et
prêts, et ne peut pas gérer les membres ou révoquer des partages.

#### Qui peut accéder à `/admin` ?

Un compte adulte doté de `ROLE_SUPER_ADMIN`. Le serveur vérifie ce rôle sur les
routes admin. Le rôle est accordé par une commande d'administration, pas par un
bouton public ; un compte normal reçoit un refus d'accès.

#### Que voit un administrateur dans un signalement ?

Les informations du signalement, des métadonnées de compte et les champs
autorisés du partage (nom, type, couleur). Pas les photos, notes, souvenirs,
emplacements privés ni les autres objets du propriétaire.

#### Comment un lien est-il rétabli ?

Le rétablissement n'est possible que si le propriétaire n'a pas révoqué le lien,
qu'il n'a pas expiré et que les règles de sécurité actuelles autorisent toujours
l'accès.

#### Comment les actions admin sont-elles protégées ?

Les décisions utilisent des requêtes POST protégées contre les soumissions
inter-sites (CSRF), des contrôles de rôle côté serveur et des vérifications
d'état. Les changements de modération sont ajoutés au journal d'activité.

#### Quelle est la différence entre le journal admin et l'activité de Mon espace ?

Le journal admin est l'historique opérationnel ; il reste conservé selon la
politique de rétention. Dans Mon espace, une personne peut masquer une entrée de
sa propre vue sans effacer l'événement du journal admin.

#### Quelles technologies et quels tests ?

L'interface utilisateur est en React et TypeScript ; le back-office est rendu
par Twig dans Symfony ; Doctrine relie l'application à MariaDB. Les changements
ont des tests backend d'autorisation et de cycle de vie, ainsi que des tests
frontend. Les vérifications récentes couvrent 279 tests backend et 121 tests
frontend, plus PHPStan niveau 6, oxlint, le contrôle TypeScript et le build.

#### Une erreur d'API signifie-t-elle toujours une panne du service ?

Non. Les erreurs peuvent être attendues (par exemple un compte non autorisé) ou
révéler un vrai problème. Il faut regarder le code HTTP, la route, la période et
les journaux avant de conclure ; un indicateur agrégé ne donne pas à lui seul la
cause.

## Trois messages à retenir sans lire

1. **Mon espace :** une vue personnelle qui mène à une action concrète.
2. **Back-office :** des indicateurs utiles pour comprendre l'activité.
3. **Choix de conception :** agir de façon réversible et protéger les contenus
   privés.

## Documentation de référence

- [Back-office et décisions](./BACKOFFICE_DESIGN.md)
- [Spécification produit](./PRODUCT_SPEC.md)
- [Parcours utilisateur](./WORKFLOWS.md)
- [Règles de permissions](./PERMISSIONS.md)
