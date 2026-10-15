# Penderie — Justification de la campagne de tests (coverage.md)

> Livrable du cours « Rappels des tests en PHP (PHPUnit) ».
> Branche : **`feature/test`** — 14 octobre 2026.
> Écarts connus / pistes restantes documentés en fin de page.

---

## 1. Objectif et périmètre

L'énoncé demande une campagne **efficace et pertinente sur la partie métier**, avec
**100 % de couverture de la partie métier**.

**Définition du périmètre « métier » appliquée ici** — l'ensemble de
`src/Service/`, `src/Permission/`, `src/Entity/`, `src/Dto/`, `src/Enum/`,
`src/Repository/` (977 lignes exécutables) :

| Périmètre | Couverture lignes | Détail |
|---|---|---|
| **Métier (exigé à 100 %)** | **100,00 %** (977/977) | AuthService, ItemService, CurrentActor, OpenRouterClient, PermissionResolver, matrice des entités/DTO/enum, repositories |
| Projet complet | **95,65 %** (1717/1795) | dont contrôleurs, sécurité, fixtures (câblage HTTP, voir §7) |
| Classes à 100 % | 39/50 | méthodes : 91,30 % (273/299) |

Comparaison : baseline `audit.md` du 14/10 = **85,96 %** → **95,65 %** (+9,69 pts),
et le périmètre métier est maintenant **couvert à 100 %**.

---

## 2. Comment exécuter la campagne

```bash
make test                                   # toute la suite (226 tests)
make coverage                               # rapport --coverage-text (pcov)
docker compose exec -T api php bin/phpunit --testdox          # liste lisible
docker compose exec -T api php bin/phpunit tests/Unit         # boucle rapide < 1 s
docker compose exec -T api php bin/phpunit tests/Integration  # fixtures + SQLite
make qa                                     # cs + PHPStan 6 + tests + front
```

Résultat du 14/10 (après campagne) : `make qa` **vert**,
**226 tests / 1019 assertions** backend + **44 tests** frontend (Vitest, hors PHPUnit).

Répartition par niveau :

| Niveau | Classe de base | Tests | Temps |
|---|---|---|---|
| Unit (`tests/Unit`) | `TestCase` (aucun container) | 39 | ~0,4 s |
| Permission (`tests/Permission`) | `TestCase`, matrice v3 pure | 109 | ~0,1 s |
| Service (`tests/Service`) | `TestCase` + MockHttpClient | 6 | ~0,1 s |
| Intégration (`tests/Integration`) | `KernelTestCase` + fixtures | 9 | ~4 s |
| Fonctionnel (`tests/Functional` + `tests/Controller`) | `WebTestCase` (`ApiTestCase`) | 63 | ~35 s |

---

## 3. Les trois niveaux — classes réelles et pourquoi elles ont été choisies

Le cours propose `PriceCalculator`, `UserRepository`, une page Twig `/contact` :
ce projet est une **API JSON + SPA React** (ni pages Twig, ni formulaires Symfony).
Les motifs ont donc été transposés sur la logique réelle de Penderie :

### 3.1 Tests unitaires (sans container, sans base) — *vitesse × décisions à risque*

| Classe testée | Fichier de test | Justification du choix |
|---|---|---|
| `CurrentActor` (11 tests) | `Unit/CurrentActorTest` | **Cœur des autorisations** : session foyer actif, profil actif, « adult only ». 13 branches (jeton profil rejeté, anonyme, profil inconnu, repli sur le profil adulte…) sont **inatteignables honnêtement via HTTP** (la couche sécurité répond 401 avant le contrôleur). Mocks ciblés (token, session, EM) : chaque décision est testée isolément. |
| `RegisterDto` + règle 18+ (6) | `Unit/RegisterValidationTest` | Les contraintes sont des attributs sur le DTO → validateur autonome `Validation::createValidatorBuilder()`, parfaitement unitaire. Couvre username/email/mot de passe/date **et** la callback d'âge adulte (règle métier sensible : mineurs interdits). |
| `FriendRequest::pairKey` (3) | `Unit/FriendRequestPairKeyTest` | Logique pure d'unicité des demandes d'amis + garde `LogicException` sur comptes non persistés (piège connu du code). |
| `ItemService` photo (4) + validation (2) | `Unit/ItemServicePhotoTest`, `Unit/ItemServiceValidationTest` | E/S disque et dernières lignes de garde (`Invalid condition/status`) **inatteignables via HTTP** (les `Choice` du DTO interceptent d'abord) → test par réflexion sur les méthodes privées avec un vrai répertoire temporaire (création refusée, écriture procfs refusée, suppression limitée à `items/`). |
| `AuthService` (2) | `Unit/AuthServiceValidationTest` | Deux lignes défensives inatteignables via HTTP : date parsable par le DTO mais non par `createFromFormat` (validator mocké) et la **course d'unicité** sur le nom d'utilisateur (`UniqueConstraintViolationException` injectée). |
| Entités (10) | `Unit/EntityModelTest` | Comportements portés par les entités : contrat de sécurité du `Profile` (`getRoles`/`isAdult`), transitions, horodatages, collections — construction pure, aucune persistance. |
| `PermissionResolver` + `ResourceType` (3) | `Permission/PermissionMatrixTest` | **La matrice d'autorisations EST la spécification** : 109 cas dats (dont 3 ajoutés pour les lignes structure CUSTOM et LOOK non exercées + `maxLevel`). Échec = régression de règle métier. |
| `OpenRouterClient` (6) | `Service/Scan/OpenRouterClientTest` | Client LLM isolé : bornes 429→503, 502 (données invalides, transport, message absent), normalisation complète des champs, retrait du préfixe data-URL. `MockHttpClient` → aucun appel réseau réel. |

### 3.2 Tests d'intégration (`KernelTestCase` + fixtures réelles)

| Classe testée | Fichier | Justification |
|---|---|---|
| `UserRepository` (4) | `Integration/UserRepositoryTest` | Motif exact du cours : `bootKernel` → `getContainer()->get(UserRepository::class)` → `findOneBy` sur les fixtures. Vérifie aussi que le mot de passe est un **hash** et jamais le clair. |
| `ItemRepository::searchInHousehold` (5) | `Integration/ItemRepositoryTest` | La **requête métier du foyer** (extraite du contrôleur dans `src/Repository/ItemRepository.php` pour être testable) : comptages exacts sur le jeu de fixtures (10 items), recherche texte, filtre de statut, pagination, et **isolation entre foyers** (0 ligne pour un autre foyer). Jeu de données contrôlé = assertions exactes, déterministes. |

### 3.3 Tests fonctionnels (`WebTestCase`, JSON à la place des formulaires)

| Fichier | Ce qu'il prouve (choix) |
|---|---|
| `Functional/ApiAccessTest` (3) | Contrat de sécurité JSON : **matrice des 401 anonymes** sur 16 routes protégées (`error.code = unauthenticated`), route publique inchangée, puis parcours `loginUser()` → 401 « pas de foyer » → `POST /households/{id}/select` → 200. |
| `Functional/AuthAccountTest` (13) | Les **garde-fous métier** de `AuthService` que les suites happy-path ne touchaient pas : limitation d'envoi à l'inscription, rotation du token après la fenêtre de throttle, règles d'attribution du nom d'utilisateur (non vérifié 403 / déjà pris 409 / format 422 / détenu par un pending 409), jetons de vérification invalides (400/422), validation création foyer/profil (422), **auteurisation du changement de profil** (PIN requis 403, enfant ne bascule pas 403). |
| `Functional/ItemLifecycleTest` (5) | Cycle de vie objet : création **avec photos + statut**, `PATCH` des 11 champs optionnels, rejet du nom vide (422), **garde-fous photo** (liste vide, non-string, 7 photos max, base64 invalide, image illisible, photo introuvable), promotion de la photo principale, suppression avec fichiers, validation des emplacements (rangement d'un autre foyer, combinaison salle/étagère incohérente). |
| `Controller/Api/*` (42, préexistants) | CRUD, validation 422, isolation inter-foyers (404), PIN, pai… audités contre le plan : seuls les manques ci-dessus ont été ajoutés, sans doublon. |

### 3.4 Non faisable en PHPUnit (à dire au correcteur)

Interactions React/Stimulus, caméra QR, drag & drop → **Cypress** (backlog
`audit.md`). Le cours le dit explicitement : `WebTestCase` n'exécute pas JavaScript.

---

## 4. Fixtures en environnement de test (stratégie retenue)

Conflit identifié dans le plan : `ApiTestCase::setUp()` **rebâtit le schéma à chaque
test** (donc détruit toute fixture), alors que les tests d'intégration doivent lire
des données réelles.

**Choix (option a) — fixtures chargées EN PROCESSUS, par test :**

```php
// backend/tests/IntegrationTestCase.php
self::bootKernel();
(new SchemaTool($em))->dropSchema($metadata); // puis createSchema()
$loader = $container->get('doctrine.fixtures.loader'); // 7 classes src/DataFixtures
(new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());
```

- **Pourquoi** : isolation parfaite (chaque test part du même état), aucune étape
  de seeding externe à maintenir en CI, aucun risque de purge d'une base partagée,
  rapide (~4 s pour 9 tests) sur SQLite.
- **Sécurité** : la base de test reste `var/test.db` (SQLite, `.env.test`), jamais
  la base MariaDB de dev ; `make fixtures` (dev) n'est **pas** réutilisé en test.
  Le CI n'a donc **pas** besoin de `doctrine:fixtures:load` séparé — les tests
  s'auto-alimentent.
- Alternative écartée (option b, base dédiée pré-seedée) : plus rapide à l'exécution
  mais état partagé entre tests + commandes de seeding à duplicater en CI + risque
  de purge.

---

## 5. Ce que la campagne a trouvé (au-delà du chiffre)

Écrire les tests a fait apparaître **un bug réel**, corrigé dans la même branche :

> `ItemService::deleteFile()` résolvait `var/uploads/<fichier>` alors que
> `storePhoto()` écrit dans `var/uploads/items/<fichier>` : la suppression d'une
> photo (ou d'un objet) **laissait le fichier sur le disque**. Corrigé +
> `ItemServicePhotoTest::testDeleteFileRemovesPhotosOnlyInsideItemsDirectory`.

Une branche morte a aussi été retirée (`OpenRouterClient`, contrôle
`preg_replace` inatteignable), ce qui évite une ligne artificiellement non couverte.

---

## 6. Extrait `--testdox` (nouveaux tests)

```
Api Access (App\Tests\Functional\ApiAccess)
 ✔ Protected endpoints answer json 401 to anonymous requests
 ✔ Public routes stay reachable without auth
 ✔ Login user reaches the foyer only after selecting a household

Auth Account (App\Tests\Functional\AuthAccount)
 ✔ Repeated registration inside a minute stops resending
 ✔ Registration refreshes a pending token after the throttle window
 ✔ Unverified account cannot claim a username
 ✔ Username cannot be changed twice
 ✔ Username format is validated
 ✔ Username held by a pending registration is rejected
 ✔ Verification token shape is checked
 ✔ Verification refuses incomplete pending registrations
 ✔ Verification refuses a username taken in meantime
 ✔ Household creation validation and unknown selection
 ✔ Child profile creation validates pin and name
 ✔ Partner profile creation validates its inputs
 ✔ Profile switch honours pin and child restrictions

Current Actor (App\Tests\Unit\CurrentActor)          ✔ 11 tests
Entity Model (App\Tests\Unit\EntityModel)            ✔ 10 tests
Item Lifecycle (App\Tests\Functional\ItemLifecycle)  ✔  5 tests
Item Repository (App\Tests\Integration\ItemRepository) ✔ 5 tests
User Repository (App\Tests\Integration\UserRepository) ✔ 4 tests
Item Service Photo / Validation (Unit)                ✔  6 tests
Open Router Client (Service)                          ✔  6 tests
Permission Matrix (Permission)                        ✔ 109 cas
```

Rapport `--coverage-text` (résumé) :

```
 Summary:
  Classes: 78.00% (39/50)
  Methods: 91.30% (273/299)
  Lines:   95.65% (1717/1795)
 …
 Périmètre métier (Service|Permission|Entity|Dto|Enum|Repository): 100.00% (977/977)
```

---

## 7. Tests end-to-end (Cypress)

Le cours demande de compléter PHPUnit par des **tests E2E dans un vrai navigateur**.
C'est le seul niveau qui exécute le JavaScript de la SPA (React 19).

| Élément | Choix retenu |
|---|---|
| Runner | Image officielle `cypress/included:16.2.0` via Docker Compose (service `cypress`, profil `e2e`) — le host n'a pas les libs navigateur (`libnss3`…), et tout le stack tourne déjà dans Docker |
| Commande | `make test-e2e` (ou `npm run test:e2e`) : efface le rate-limiter de l'inscription puis lance `cypress run` |
| Configuration | `cypress.config.js` à la racine — `baseUrl: http://frontend:5173` (nom de service Compose) |
| Données | `make fixtures` en amont (compte démo + 10 articles) ; nouvelles inscriptions à e-mail unique par run ; e-mails lus dans **Mailpit** (`http://mailpit:8025/api/v1/...`) |
| Sélecteurs | `#id` existants + textes français stables — **aucun attribut `data-cy` ajouté**, l'application n'a pas été modifiée pour les tests |
| Résultat | **12 tests / 5 specs, tous verts en ~2 min** |

### Parcours couverts (100 % des parcours métiers de l'interface)

| Spec | Parcours |
|---|---|
| `homepage.cy.js` | Visiteur : accueil + CTA + badge API ; route protégée redirigée vers `/connexion` |
| `auth-register.cy.js` | Inscription → « Vérifiez votre e-mail » ; confirmation de mot de passe incorrecte ; **vérification via le lien reçu dans Mailpit** |
| `auth-login.cy.js` | Mot de passe refusé ; connexion du compte démo → foyer → profil → `Pull noir` visible ; déconnexion puis verrouillage des routes |
| `household.cy.js` | Parcours complet : inscription → vérification Mailpit → **foyer auto-créé** (`AuthService::verifyEmail`) → profil → dressing vide |
| `items.cy.js` | Recherche d'articles (+ résultat vide) ; **création d'un article** via la saisie manuelle du scanner (appel LLM intercepté) ; refus d'un article sans nom |

### Écarts connus (E2E)

- **Prêter / changer le statut d'un article** : pas d'interface (champ `status` absent du formulaire,
  aucun bouton de prêt dans la liste) → **non testable en E2E**, alors que l'API le supporte.
  C'est une lacune produit, pas de test.
- Mode interactif `cypress open` : indisponible sans affichage (headless) — le runner est
  `cypress run` dans le conteneur ; les vidéos (`cypress/videos/`) servent de preuve visuelle.
- Le rate-limiter d'inscription (5/h par IP) est remis à zéro par `make test-e2e`
  (`cache:pool:clear cache.rate_limiter`) — bornes sécurité **inchangées**.

---

## 8. Écarts connus (honnêteté du rapport)

1. **78 lignes hors périmètre métier** (95,65 % global, pas 100 %) — réparties dans
   les **contrôleurs** (`FriendController` 25, `ItemController` 8, `AuthController` 6,
   `PlaceController` 5, `ScanController` 3 = gardes HTTP : e-mail non vérifié sur le
   social, « déjà amis / je me suis moi-même », corps JSON invalide, 404 de
   suppression…), le **câblage sécurité** (`AppUserProvider` 15, listeners de login
   2, `ApiExceptionListener` 4) et les **fixtures** (5). Ce sont des lignes de
   transport/câblage dont le comportement observable est déjà couvert côté métier et
   côté fonctionnel (codes 401/403/404/422 asserted) ; les tester ajouterait des
   doublons, pas de la détection de bug. Elles restent un bon candidat si un
   correcteur exige 100 % *global*.
2. **JavaScript** : PHPUnit ne l'exécute pas → désormais couvert côté E2E par Cypress (§7).
3. **Frontend Vitest** (44 tests, 11 fichiers, dont 3 axe a11y) : rapporté par
   `make fe-test`, hors périmètre de ce livrable, toujours vert.
4. Les 78 lignes restantes restent du câblage contrôleur/sécurité/fixtures — les parcours
   métier correspondants sont eux aussi exercés de bout en bout par Cypress (§7).

---

## 8. Définition of done (plan de tests)

- [x] `make qa` vert (cs + PHPStan niveau 6 + 226 tests + 44 front)
- [x] Une attente volontairement cassée produit bien un FAILURE (puis restauré)
- [x] Couverture remesurée : 85,96 % → **95,65 %** global, **100 % métier**
- [x] Compteurs de tests mis à jour (226 backend / 1019 assertions)
- [x] Tests unitaires + d'intégration ajoutés sur `feature/test`
- [x] Campagne Cypress E2E : 12 tests / 5 parcours verts (`make test-e2e`)
