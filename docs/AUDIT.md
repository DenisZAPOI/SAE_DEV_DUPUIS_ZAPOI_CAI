# AUDIT.md — audit de sécurité du domaine « Socle »

Date : 30/09/2026 — Audit en lecture seule : **aucun fichier du dépôt n'a été modifié**.

## Périmètre

Domaine **Socle (back)** défini dans `CLAUDE.md`, chemins relatifs à `Git/` :

- `index.php` (routeur, session)
- `html_spe_char.php` (helper `h()`)
- `utils/connexion.php`, `utils/token_csrf.php`, `utils/vue_generique.php`
- `composants/composant_menu/composant_menu.php`, `composants/composant_menu/controleur_menu.php`

Les autres domaines n'ont pas été lus. Pour les interactions, on s'appuie sur le contrat `docs/API.md`. On a aussi consulté `composer.json`, `.gitignore`, `.env.example` et l'historique Git des fichiers du socle. Quelques recherches par mots-clés ont été faites sur tout le dépôt pour mesurer l'impact des fonctions du socle, sans lire les autres modules.
Les constats situés hors du socle sont regroupés dans une section dédiée, à confier aux domaines concernés.

## Méthode

Sept passes indépendantes ont été faites, une par catégorie de faille :

1. Injections (SQL, commandes, templates)
2. XSS
3. Authentification et sessions
4. Contrôle d'accès et IDOR
5. Secrets, configuration, debug et CORS
6. Dépendances
7. Téléversements de fichiers et traversées de répertoires

Chaque passe combine lecture du code, recherche de motifs dangereux (`eval`, `exec`, `system`, `unserialize`, `header(`, `ini_set`, etc.) et vérification dans l'historique Git.

Gravités : **Critique** / **Élevée** / **Moyenne** / **Faible** / **Info**.

## Synthèse

| ID | Catégorie | Gravité | Fichier | Constat |
|---|---|---|---|---|
| SEC-01 | Secrets | **Critique** | `utils/connexion.php:9,11` + historique | Identifiants de base de données en clair, 3 jeux distincts dans l'historique |
| SEC-02 | Debug | Moyenne | `utils/connexion.php:9` | Exception PDO non capturée : le mot de passe peut fuiter dans la trace |
| SEC-03 | Configuration | Faible | `index.php` | Aucune gestion dev/prod, `display_errors` laissé au `php.ini` |
| SEC-04 | En-têtes / CORS | Faible | `index.php` | Pas de CORS (conforme), mais aucun en-tête de sécurité (clickjacking) |
| AUTH-01 | Sessions | **Élevée** | `index.php:2` (+ tout le dépôt) | Aucun `session_regenerate_id` et pas de mode strict : fixation de session |
| AUTH-02 | Sessions | Moyenne | `index.php:2` | Cookie de session sans `HttpOnly`, `Secure` ni `SameSite` explicites |
| AUTH-03 | CSRF | Moyenne | `index.php`, `utils/token_csrf.php` | Vérification CSRF non centralisée, oubliée dans plusieurs modules |
| AUTH-04 | CSRF | Faible | `utils/token_csrf.php:7`, `index.php:10` | Comparaison non constante et jeton non renouvelé à la connexion |
| AUTH-05 | Sessions | Moyenne | `html_spe_char.php:1` | Saut de ligne avant `<?php` : les `header()` échouent (redirections) |
| AUTH-06 | Sessions | Faible | `index.php` | Aucune expiration de session par inactivité |
| ACC-01 | Contrôle d'accès | Moyenne | `index.php:18-68` | Aucune garde centrale d'authentification ni de rôle dans le routeur |
| ACC-02 | IDOR | Moyenne | socle (absence) | Aucun mécanisme de cloisonnement par association ou par compte |
| ACC-03 | Contrôle d'accès | Faible | socle (convention) | Refus renvoyé en HTTP 200 au lieu de 403 |
| INJ-01 | Injection SQL | Faible | `utils/connexion.php:9` | DSN sans `charset`, requêtes préparées émulées par défaut |
| INJ-02 | Injections | Info | socle | Aucune requête, commande système ni moteur de template dans le socle |
| XSS-01 | XSS | Faible | `html_spe_char.php:5-7` | `h()` correct pour le HTML, insuffisant pour les contextes JS et URL |
| XSS-02 | XSS | Faible | `index.php` | Pas de Content-Security-Policy (aucune défense en profondeur) |
| DEP-01 | Dépendances | Moyenne | `composer.json` | `php >=8.0` autorise des versions PHP en fin de support |
| DEP-02 | Dépendances | Moyenne | racine | Pas de `composer.lock` : versions non figées, `composer audit` impossible |
| DEP-03 | Dépendances | Faible | `composer.json` | PHPUnit `^9.6`, branche ancienne (dépendance de dev uniquement) |
| FIL-01 | Traversée | Info | `index.php:18-68` | Routeur en liste blanche : pas d'inclusion de fichier arbitraire |
| FIL-02 | Traversée | Faible | `index.php`, `composant_menu/*` | `include` en chemins relatifs résolus via `include_path` |
| FIL-03 | Téléversement | Info | socle (absence) | Aucun utilitaire commun de validation des fichiers téléversés |

---

## 1. Injections SQL, de commandes système et de templates

### INJ-02 — Aucune injection directe dans le socle (Info)
Le socle n'exécute aucune requête SQL : `Connexion` ne fait qu'ouvrir la connexion PDO. On n'y trouve aucun appel à `exec`, `system`, `shell_exec`, `passthru`, `popen`, `proc_open`, `eval`, `unserialize` ou `extract`, ni de variable variable. Il n'y a pas non plus de moteur de template : les vues sont du PHP inclus. Le seul point d'entrée contrôlé par l'utilisateur est `$_GET['module']` (`index.php:18`), comparé à une liste fermée de `case` et jamais concaténé à un chemin ou à une requête.

### INJ-01 — Connexion PDO sans jeu de caractères ni préparation native (Faible)
`utils/connexion.php:9` : le DSN actif ne précise pas `charset` (les lignes commentées 18 et 21 le faisaient). Or PDO MySQL émule les requêtes préparées par défaut (`ATTR_EMULATE_PREPARES = true`). Dans ce cas, l'échappement se fait côté client selon le jeu de caractères de la connexion. Avec un jeu multioctet comme GBK ou SJIS, des contournements d'échappement sont connus. Le risque est théorique avec un serveur en UTF-8, mais la protection des modèles contre l'injection dépend entièrement de ce réglage.
**Recommandation** : ajouter `charset=utf8mb4` au DSN et fixer `PDO::ATTR_EMULATE_PREPARES => false` et `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` explicitement.

## 2. XSS

### XSS-01 — Portée limitée de `h()` (Faible)
`html_spe_char.php:5-7` : `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` est correct pour le texte HTML et les attributs **entre guillemets**. Il ne protège pas :
- l'intérieur d'un `<script>` ou d'un gestionnaire `on*=` ;
- les attributs non entre guillemets ;
- les URL : un `href="<?= h($url) ?>"` accepte `javascript:alert(1)`.

Le contrat (`docs/API.md`) impose `h()` partout sans préciser ces limites.
**Recommandation** : documenter les contextes couverts par `h()`. Ajouter au socle des helpers dédiés, par exemple `js()` à base de `json_encode` avec les options `JSON_HEX_*`, et un validateur d'URL qui n'accepte que `http`/`https`.

### XSS-02 — Absence de Content-Security-Policy (Faible)
Aucun en-tête CSP n'est envoyé. Une XSS dans n'importe quelle vue s'exécute donc sans restriction, et le cookie de session est lisible en JavaScript (voir AUTH-02).
**Recommandation** : envoyer une CSP depuis le socle, au minimum `default-src 'self'` avec le CDN Bootstrap autorisé et `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'none'`.

Point vérifié : le message du cas par défaut du routeur (`index.php:66`) est statique et ne reflète pas la valeur de `module`. Il n'y a pas de XSS réfléchie à cet endroit.

## 3. Authentification et sessions

### AUTH-01 — Fixation de session (Élevée)
Aucun appel à `session_regenerate_id()` n'existe dans le dépôt (0 occurrence), et `session.use_strict_mode` n'est pas activé (défaut PHP : désactivé). PHP accepte donc un identifiant de session choisi par un attaquant. Le scénario est le suivant :
1. L'attaquant impose un identifiant à la victime.
2. La victime se connecte avec cet identifiant.
3. L'attaquant réutilise l'identifiant et hérite de la session authentifiée, y compris de son rôle.

L'aggravation vient du jeton CSRF : il est créé avant la connexion (`index.php:10-12`) et conservé après. L'attaquant connaît donc aussi ce jeton.
**Recommandation** : le socle doit activer `session.use_strict_mode` et fournir une fonction commune à appeler à chaque changement de privilège : connexion, choix d'association, changement de rôle, déconnexion. Cette fonction régénère l'identifiant (`session_regenerate_id(true)`) et le jeton CSRF. Son appel à la connexion relève du domaine « Comptes ».

### AUTH-02 — Paramètres du cookie de session non définis (Moyenne)
`index.php:2` : `session_start()` est appelé sans `session_set_cookie_params()` ni `ini_set()`. Avec les valeurs par défaut de PHP, le cookie `PHPSESSID` est sans `HttpOnly` (volable par XSS), sans `Secure` (envoyé en HTTP clair) et sans `SameSite` (envoyé dans les requêtes intersites, ce qui aggrave les CSRF en GET).
**Recommandation** : avant `session_start()`, définir `httponly => true`, `secure => true` en production, `samesite => 'Lax'` (ou `'Strict'`) et `path => '/'`.

### AUTH-03 — Protection CSRF non centralisée (Moyenne)
`Token_CSRF::check_csrf()` n'est appelé que dans 6 fichiers. Chaque action doit y penser elle-même, et `docs/API.md` recense déjà des oublis : tout le module `stock`, `restock/ajoutAchat`, ainsi que `commande/annulation` et `deconnexion` qui modifient l'état en GET. Le routeur a pourtant l'emplacement idéal pour une vérification unique.
**Recommandation** : dans `index.php`, refuser toute requête `POST` dont le jeton est invalide avant de charger le module. Interdire aussi les changements d'état en GET, ce qui relève des domaines concernés.

### AUTH-04 — Détails d'implémentation du jeton CSRF (Faible)
- `utils/token_csrf.php:7` : la comparaison `!==` n'est pas à temps constant. Il faut préférer `hash_equals($_SESSION['token'], $_POST['token_csrf'])` après avoir vérifié que les deux sont des chaînes.
- Si `check_csrf()` est appelé hors du routeur, `$_SESSION['token']` peut être indéfini (avertissement PHP). Il faut le tester avec `isset`.
- Le jeton n'est jamais renouvelé, en particulier pas à la connexion (voir AUTH-01).

La génération reste correcte : `random_bytes(32)` produit 256 bits d'entropie.

### AUTH-05 — Sortie parasite avant les en-têtes (Moyenne)
`html_spe_char.php:1` : la ligne vide avant `<?php` est envoyée au navigateur dès l'inclusion (`index.php:5`). Sans `output_buffering` dans `php.ini`, tous les `header()` suivants échouent avec « headers already sent » : 16 appels à `header(` dans le dépôt. Il en va de même pour tout futur `session_regenerate_id()` et tout en-tête de sécurité (AUTH-01, AUTH-02, XSS-02, SEC-04). Si une action compte sur une redirection pour arrêter le traitement sans appeler `exit`, l'exécution continue.
**Recommandation** : supprimer la ligne vide et la balise fermante `?>` des fichiers purement PHP du socle. Ajouter un test qui vérifie qu'aucun octet n'est émis à l'inclusion.

### AUTH-06 — Pas d'expiration de session (Faible)
Aucune durée d'inactivité ni durée de vie absolue n'est gérée : une session barman ou gestionnaire laissée ouverte sur un poste partagé reste valable indéfiniment.
**Recommandation** : stocker l'horodatage de la dernière activité en session et détruire la session au-delà d'un seuil, par exemple 30 minutes.

## 4. Contrôle d'accès et IDOR

### ACC-01 — Aucune garde centrale dans le routeur (Moyenne)
`index.php:18-68` charge n'importe quel module, sans exiger ni session connectée ni rôle. Toute la sécurité repose sur les vérifications faites action par action. `docs/API.md` montre que ce modèle produit déjà des oublis : `commande/annulation`, `restock/afficherAchats` et `restock/detailsAchat` sont signalés « non contrôlé ».
**Recommandation** : ajouter au socle une table indiquant pour chaque module s'il est public ou réservé aux sessions connectées, plus un helper du type `exigerRole([1, 4])` qui arrête le traitement (`exit`) en cas de refus. Refuser par défaut tout ce qui n'est pas déclaré.

### ACC-02 — Aucun cloisonnement par association ou par compte : IDOR probables (Moyenne)
Plusieurs actions prennent un identifiant fourni par le client, comme `idCommande`, `idAchat`, `idProd` ou `fournisseur` (voir `docs/API.md`). Le socle ne fournit aucun outil pour vérifier que l'objet visé appartient à l'association (`$_SESSION['idAsso']`) ou au compte (`$_SESSION['idCompte']`) de la session. Par exemple, `historique/detailHistoClient?idCommande=` est ouvert aux rôles 1, 2 et 3, et le contrat ne précise aucune vérification de propriétaire. Un client pourrait donc lire la commande d'un autre client en changeant l'identifiant. Ce point n'a **pas été vérifié** dans le code, qui est hors périmètre.
**Recommandation** : ajouter au socle un helper de vérification d'appartenance, puis faire auditer chaque action à identifiant par les domaines Back concernés (voir la section « Hors périmètre »).

### ACC-03 — Refus sans code HTTP 403 (Faible)
Les refus sont affichés comme un message dans une page servie en HTTP 200. Il n'y a pas d'impact direct, mais cela empêche de repérer les tentatives dans les journaux et cela brouille les tests automatisés.
**Recommandation** : envoyer `http_response_code(403)` dans le helper de refus proposé en ACC-01.

## 5. Secrets, configuration, mode debug et CORS

### SEC-01 — Identifiants de base de données dans le code et l'historique (Critique)
- `utils/connexion.php:9` (ligne active) : hôte `database-etudiants.iut.univ-paris8.fr`, utilisateur `dutinfopw201699`, mot de passe `juh*****` en clair.
- `utils/connexion.php:11` (commentée) : un second compte, `dutinfopw20167`, mot de passe `dys*****`.
- L'historique Git (145 commits) contient en plus un troisième jeu d'identifiants, pour un hébergeur tiers : `fdb1033.awardspace.net`, utilisateur `4724048_jj`, mot de passe `Ste*****`.

Le dépôt est public : ces trois jeux sont à considérer comme **compromis**. Supprimer les lignes du fichier ne suffit pas, puisqu'elles restent dans l'historique. Le fichier `.env.example` existe mais n'est pas lu par le code.
**Recommandation, par ordre** :
1. Changer immédiatement les trois mots de passe auprès de l'IUT et de l'hébergeur, et vérifier que ce mot de passe n'est pas réutilisé ailleurs.
2. Faire lire les identifiants par `Connexion` depuis `.env` (déjà ignoré par Git) ou depuis des variables d'environnement.
3. Réécrire l'historique (`git filter-repo`) si possible.
4. Ajouter un outil de détection de secrets (gitleaks, par exemple) avant chaque commit.

Le test `ProjetTest` qui fige cette dette devra être mis à jour dans la même PR.

### SEC-02 — Fuite possible des identifiants dans les traces d'erreur (Moyenne)
`utils/connexion.php:9` : l'appel `new PDO(...)` n'est entouré d'aucun `try/catch`. En cas d'échec (base indisponible, par exemple), la `PDOException` n'est pas capturée. Avec `display_errors=On` et `zend.exception_ignore_args=Off`, qui sont les réglages de `php.ini-development`, la trace affichée contient les arguments du constructeur : DSN, utilisateur **et mot de passe**. La connexion est ouverte dès l'inclusion des modèles, donc sur presque toutes les pages.
**Recommandation** : capturer l'exception, journaliser un message neutre et afficher une page d'erreur générique. Désactiver `display_errors` en production.

### SEC-03 — Pas de distinction entre développement et production (Faible)
La variable `APP_ENV` de `.env.example` n'est lue nulle part, et le socle ne règle ni `display_errors` ni `error_reporting` ni `log_errors`. Le comportement dépend donc entièrement du `php.ini` du serveur. Par exemple, en cas de module inconnu (`index.php:65-67`), `$moduleContent` n'est jamais défini et `template.php:82` l'affiche : cela produit un avertissement « Undefined variable » qui révèle le chemin des fichiers si l'affichage des erreurs est actif.
**Recommandation** : lire `APP_ENV` dans le socle, désactiver l'affichage des erreurs hors développement et initialiser `$moduleContent`.

### SEC-04 — CORS et en-têtes de sécurité (Faible)
- **CORS** : aucun en-tête `Access-Control-*` n'est envoyé, donc la politique same-origin du navigateur s'applique. Ce comportement est **conforme** pour une application sans API.
- **En-têtes absents** : `X-Frame-Options` / `frame-ancestors` (l'application peut être affichée dans un cadre, d'où un risque de clickjacking sur les boutons de commande ou de promotion), `X-Content-Type-Options: nosniff`, `Referrer-Policy`, et `Strict-Transport-Security` en HTTPS.

**Recommandation** : envoyer ces en-têtes depuis `index.php`. Cela suppose d'avoir d'abord corrigé AUTH-05.

## 6. Dépendances vulnérables ou obsolètes

### DEP-01 — Versions PHP en fin de support autorisées (Moyenne)
`composer.json` déclare `"php": ">=8.0"`. PHP 8.0 ne reçoit plus de correctifs de sécurité depuis novembre 2023, et PHP 8.1 depuis fin 2025. Déployer sur ces versions serait donc accepté sans alerte. L'environnement vérifié dans `CLAUDE.md` (PHP 8.3.6 packagé par Ubuntu) est, lui, dans une branche maintenue.
**Recommandation** : relever la contrainte à `>=8.3` et l'indiquer dans `CLAUDE.md`. Vérifier les dates exactes sur php.net/supported-versions, car ce calendrier évolue.

### DEP-02 — Pas de fichier de verrouillage (Moyenne)
Aucun `composer.lock` n'est versionné, faute d'accès à Packagist selon `CLAUDE.md`. Les versions installées ne sont donc ni reproductibles ni auditables, et `composer audit` ne peut pas s'exécuter.
**Recommandation** : générer et versionner `composer.lock` depuis un poste avec accès réseau, puis lancer `composer audit` en intégration continue. Ce changement de dépendance doit être expliqué dans la PR, conformément aux règles du dépôt.

### DEP-03 — PHPUnit 9 (Faible)
`"phpunit/phpunit": "^9.6"` correspond à une branche ancienne, alors que des versions majeures plus récentes existent. L'installation décrite dans `CLAUDE.md` (paquet Ubuntu 9.6.17) est aussi figée à une version mineure ancienne. Des avis de sécurité ont été publiés sur PHPUnit ces dernières années : il faut le vérifier avec `composer audit`, ce rapport n'ayant pas pu interroger la base d'avis. L'impact reste limité, car PHPUnit n'est qu'une dépendance de développement, non déployée.
**Recommandation** : exiger au minimum la dernière 9.6.x corrigée, ou migrer vers une branche maintenue compatible avec la version de PHP retenue.

Remarque : le socle ne charge lui-même aucune bibliothèque externe. Bootstrap est chargé par CDN dans `template.php`, qui relève du domaine Front (voir « Hors périmètre »).

## 7. Téléversements de fichiers et traversées de répertoires

### FIL-01 — Routeur en liste blanche (Info, conforme)
`index.php:18-68` : la valeur de `?module=` n'est jamais utilisée pour construire un chemin, elle est seulement comparée à des `case` littéraux. Une valeur comme `../../etc/passwd` ou `php://filter` aboutit au cas par défaut. Aucune inclusion de fichier locale ou distante n'est possible par ce paramètre.

### FIL-02 — `include` en chemins relatifs (Faible)
`index.php:5-6, 21-62`, `composant_menu.php:2` et `controleur_menu.php:2` utilisent des chemins relatifs sans `./` ni `__DIR__`. PHP les résout via `include_path`, puis via le dossier courant. Les tests exploitent d'ailleurs ce comportement pour substituer `utils/connexion.php`. Ce n'est pas contrôlable par un attaquant, mais un `include_path` mal configuré sur le serveur pourrait charger un autre fichier du même nom.
**Recommandation** : utiliser `__DIR__ . '/…'`, tout en gardant un moyen explicite d'injecter le stub de test.

### FIL-03 — Aucun utilitaire commun pour les téléversements (Info)
Le socle ne traite aucun fichier, mais il ne fournit pas non plus de fonction commune de validation des fichiers téléversés : type MIME réel via `finfo`, liste blanche d'extensions, taille maximale, nom de fichier régénéré, `is_uploaded_file`, dossier hors de la racine web. Chaque module doit donc tout réimplémenter (voir « Hors périmètre »).

---

## Hors périmètre : à transmettre aux autres domaines

Ces points ont été repérés via `docs/API.md`, `.gitignore` ou l'arborescence, sans lire le code des autres domaines.

| Domaine | Constat | Gravité estimée |
|---|---|---|
| Back Comptes | `ajout_association` téléverse un logo et des documents légaux stockés dans `Git/docsLegaux/`, **sous la racine web** (`php -S … -t Git`), sans `.htaccess` ni configuration interdisant l'exécution. Si l'extension n'est pas filtrée, un fichier `.php` téléversé serait exécuté (exécution de code à distance). À auditer en priorité. | Élevée (à confirmer) |
| Back Comptes | 3 PDF de `Git/docsLegaux/` sont versionnés dans ce dépôt public malgré la règle `.gitignore` (ajoutée après coup). Ils peuvent contenir des données personnelles. | Moyenne |
| Back Comptes | `choisirAsso` accorde le rôle 4 (super-admin) à tout compte dont le login est `admin` (`docs/API.md`) : quiconque crée ce login en premier devient super-admin. | Élevée |
| Back Comptes | La connexion doit appeler la régénération de session prévue en AUTH-01. `deconnexion` se fait en GET. | Moyenne |
| Back Commande / Suivi | Actions à identifiant (`idCommande`, `idAchat`, `idProd`) à auditer pour les IDOR (ACC-02). `annulation` modifie des données en GET sans rôle ni CSRF. | Moyenne |
| Back Commande | Module `stock` et `restock/ajoutAchat` sans CSRF ; `afficherAchats` et `detailsAchat` sans contrôle de rôle. | Moyenne |
| Back Comptes | Montant de rechargement du solde borné (10–100) uniquement dans le HTML, pas côté serveur (`CLAUDE.md`). | Moyenne |
| Front Gabarit | Bootstrap 5.3 et Bootstrap Icons chargés par CDN : vérifier que la version est à jour et que l'attribut `integrity` (SRI) et `crossorigin` sont présents. | Faible |

## Ordre de correction proposé

Une PR par point, dans le domaine Socle, chacune accompagnée d'un test qui échoue avant le correctif :

1. **SEC-01** : changer les mots de passe (sans attendre la PR), puis externaliser les identifiants.
2. **AUTH-05** : supprimer la sortie parasite, prérequis de tous les correctifs à base d'en-têtes.
3. **AUTH-01 + AUTH-04** : mode strict, fonction de régénération de session et de jeton, `hash_equals`.
4. **AUTH-02, SEC-04, XSS-02** : paramètres du cookie et en-têtes de sécurité.
5. **ACC-01, ACC-03, AUTH-03** : garde centrale (authentification, rôle, CSRF sur tout POST, code 403).
6. **SEC-02, SEC-03, INJ-01** : configuration PDO, gestion des erreurs, `APP_ENV`.
7. **DEP-01 à DEP-03** : contrainte PHP, `composer.lock`, `composer audit`.
8. **ACC-02, FIL-02, FIL-03, XSS-01, AUTH-06** : helpers d'appartenance, d'upload et d'échappement contextuel, expiration de session.
