# CLAUDE.md

## Projet
« À-LADÉBAUCHE » : SAE du DUT Info de Montreuil. Application web de gestion de buvette pour associations étudiantes :
comptes et solde, associations (création validée par un super-admin), commandes, stock, achats fournisseurs,
historique, récapitulatif de la journée, gestion du staff.

## Stack
- PHP 8 sans framework ni Composer ; PDO MySQL ; rendu côté serveur (pas de SPA).
- Bootstrap 5.3 et Bootstrap Icons via CDN ; CSS maison `Git/template9.css`.
- Aucun schéma SQL versionné. Tables : `compte`, `Utilisateur`, `association`, `associationtemp`, `produits`, `type`,
  `menu`, `stock`, `inventaire`, `commande`, `lignecommande`, `achat`, `ligneAchat`.

## Dossiers importants (tout le code est dans `Git/`)
- `index.php` : routeur (`?module=`), puis `template.php` (layout, navbar selon `$_SESSION['role']`).
- `modules/module_<nom>/` : un module MVC = `module_`, `controleur_`, `modele_`, `vue_<nom>.php`.
- `utils/` : `Connexion` (PDO), `Token_CSRF`, `VueGenerique` ; `html_spe_char.php` : helper `h()`.
- `docs/API.md` : contrat entre vues et contrôleurs (routes, champs, rôles).
- `tests/` : tests PHPUnit ; `.env.example` : variables attendues ; `.gitignore` : exclusions (secrets, vendor, logs, dumps).

## Domaines (un domaine = front OU back, jamais les deux)
Chemins relatifs à `Git/`. Un module contient du front (`vue_*`) et du back (`module_*`, `controleur_*`, `modele_*`).

| Domaine | Côté | Dossiers / fichiers |
|---|---|---|
| Gabarit et navigation | Front | `template.php`, `template9.css`, `composants/composant_menu/vue_menu.php`, `images/` |
| Écrans comptes, associations, solde | Front | `modules/module_connexion/vue_*`, `modules/module_solde/vue_*` |
| Écrans commande, stock, achats | Front | `modules/module_commande/vue_*`, `module_stock/vue_*`, `module_restock/vue_*` |
| Écrans suivi (historique, récap, staff, accueil) | Front | `module_historique/vue_*`, `recapJournee/vue_*`, `module_staff/vue_*`, `module_accueil/vue_*` |
| Socle (routage, session, BDD, CSRF) | Back | `index.php`, `html_spe_char.php`, `utils/`, `composants/composant_menu/{composant,controleur}_menu.php` |
| Comptes, associations, solde | Back | `module_connexion/{module,controleur,modele}_*`, `module_solde/{module,controleur,modele}_*` |
| Commande, stock, achats | Back | `module_commande/`, `module_stock/`, `module_restock/` (sauf `vue_*`) |
| Suivi (historique, récap, staff, accueil) | Back | `module_historique/`, `recapJournee/`, `module_staff/`, `module_accueil/` (sauf `vue_*`) |

## Périmètre de travail
- Travailler dans **un seul domaine à la fois** ; ne pas modifier un autre domaine dans la même PR.
- Front ↔ back : s'appuyer sur `docs/API.md`, sans lire l'autre partie en entier. Si le contrat change, mettre à
  jour `docs/API.md` dans la PR.
- Ne jamais lire : dépendances installées (`vendor/`, `node_modules/`), fichiers de verrouillage (`composer.lock`,
  `package-lock.json`), données (`*.sql`, dumps), logs (`*.log`), fichiers générés ou déposés (`images/`
  hors code, `docsLegaux/`, `.idea/`).

## Installation, lancement, tests
Vérifié le 30/09/2026 sur Ubuntu 24.04 (PHP 8.3.6, PHPUnit 9.6.17).
- Installer : `apt-get install -y php-cli php-sqlite3 php-mbstring php-xml phpunit`
  (ou `composer install` si Packagist est accessible : `composer.json` déclare `phpunit/phpunit ^9.6`).
- Configurer : copier `.env.example` en `.env` ; le code lit encore les identifiants dans `Git/utils/connexion.php`.
- Lancer : `php -S localhost:8000 -t Git`, puis http://localhost:8000/index.php (MySQL requis, schéma non fourni).
- Tests : `phpunit` (config `phpunit.xml.dist`, dossier `tests/`). Résultat : **OK, 37 tests, 84 assertions, 1 ignoré**
  en ~1,5 s ; le test ignoré (`docs/API.md` absent) vérifie 49 actions dès que ce fichier est fusionné.
- Syntaxe seule : `find Git -name '*.php' -exec php -l {} \;` → aucune erreur.
- Les tests n'utilisent **pas MySQL** : `tests/stubs/utils/connexion.php` (SQLite en mémoire, schéma minimal
  `tests/schema.sql`) est placé en tête de l'`include_path` par `tests/bootstrap.php`.
- Couverture : CSRF, `h()`, inscription/connexion/rôles (dont injection SQL), droits des contrôleurs solde, stock,
  staff, commande, syntaxe, routage, secrets en dur, cohérence avec `docs/API.md`.

### Problèmes rencontrés
- Sandbox : `repo.packagist.org`, `getcomposer.org` et `phar.phpunit.de` répondent 403. `composer validate` réussit,
  `composer install` échoue : aucun `composer.lock` n'a été généré (à faire depuis un poste avec accès réseau).
- `apt-get update` signale le dépôt nodesource en 403 : sans effet sur l'installation.
- Les modèles appellent `Connexion::initConnexion()` dès l'`include` : sans le stub, tout test tenterait une connexion MySQL.
- `Git/html_spe_char.php` commence par un saut de ligne avant `<?php` : la sortie part avant les `header()`
  (« headers already sent »). Sans `output_buffering` dans php.ini, les redirections échouent ; les tests mettent `@`.
- SQL propre à MySQL (`CURRENT_DATE()`, `CURRENT_DATE-1`) : non exécutable en SQLite, donc les modèles solde, stock,
  commande et restock ne sont pas testés (seuls leurs contrôleurs le sont, sur les refus de droits).
- `.env.example` n'est pas encore lu par le code : externaliser les identifiants demande une PR dédiée, avec test.

## Conventions observées
- Classes `Mod_<nom>`, `Cont_<nom>`, `Modele_<nom> extends Connexion`, `Vue_<nom> extends VueGenerique`.
- Nouveau module : les 4 fichiers **et** un `case` dans `index.php`.
- Rôles (`Utilisateur.idRole`) : 1 gestionnaire, 2 barman, 3 client, 4 super-admin. Chaque action de contrôleur
  vérifie le rôle, sinon `vue->message('Droit requis non perçu.')`.
- Formulaire POST : champ caché `token_csrf`, vérifié par `Token_CSRF::check_csrf()`.
- SQL : requêtes préparées PDO uniquement. Mots de passe : `password_hash` / `password_verify`.
- Sorties HTML échappées avec `h()`. Code, noms et messages en français, indentation de 4 espaces.

## Règles de travail
- **Jamais de secret dans un commit** (mot de passe, jeton, DSN de production). Utiliser un fichier de configuration
  ignoré par Git. Les identifiants actuellement en dur dans `connexion.php` sont à externaliser, pas à recopier.
- **Une branche et une PR par modification** ; ne jamais pousser directement sur `main`.
- **Tout correctif de sécurité est accompagné d'un test** qui échouait avant le correctif.
- **Lancer les tests avant d'ouvrir une PR** et indiquer le résultat dans la description.
- **Tout changement de dépendance est expliqué** dans la PR (pourquoi, version, impact).
- **Répondre en français** (échanges, commits, descriptions de PR).

## Points d'attention
- `modules/module_acceuil/` est un doublon obsolète de `module_accueil/` ; `.idea/` est versionné.
- Les écarts de sécurité connus sont listés en fin de `docs/API.md`.
- Le montant du rechargement de solde n'est borné (10–100) que dans le HTML, pas côté serveur.
- Les tests `ProjetTest` figent deux dettes (secrets dans `connexion.php`, CSRF absent de `stock`) : les corriger
  oblige à mettre ces tests à jour.
