# CLAUDE.md

## Projet
« À-LADÉBAUCHE » est une SAE du DUT Info de Montreuil. C'est une application web de gestion de buvette pour
associations étudiantes. Elle couvre les comptes et le solde, le choix d'une association, les demandes de création
d'association (validées par un super-admin), les commandes, le stock et le réapprovisionnement, l'historique, le
récapitulatif de la journée et la gestion du staff.

## Stack
- PHP 8 (typage des retours, `??`), sans framework ni Composer ; PDO MySQL.
- Bootstrap 5.3 et Bootstrap Icons via CDN ; CSS maison `template9.css` (`template.css` n'est plus utilisé).
- Aucun schéma SQL versionné. Tables utilisées : `compte`, `Utilisateur`, `association`, `associationtemp`,
  `produits`, `type`, `menu`, `stock`, `inventaire`, `commande`, `lignecommande`, `achat`, `ligneAchat`.

## Dossiers importants (tout le code est dans `Git/`)
- `index.php` : routeur. Il fait `session_start`, génère le jeton CSRF, puis un `switch` sur `?module=`, inclut
  `template.php`.
- `template.php` : layout et navbar, avec des liens conditionnés par `$_SESSION['role']`.
- `modules/module_<nom>/` : un module MVC avec `module_`, `controleur_`, `modele_` et `vue_<nom>.php`.
- `utils/connexion.php` : classe `Connexion` (PDO statique `self::$bdd`) ; `token_csrf.php` ; `vue_generique.php`.
- `html_spe_char.php` : helper `h()` (htmlspecialchars) pour échapper les sorties.
- `composants/composant_menu/` : composant réutilisable (même découpage MVC).
- `images/` (logos uploadés), `docsLegaux/` (PDF uploadés des associations).

## Installation
1. Installer PHP ≥ 8 avec `pdo_mysql`, ainsi que MySQL ou MariaDB.
2. Créer la base (le schéma n'est pas fourni, il faut le reconstituer à partir des requêtes des modèles).
3. Renseigner le DSN dans `Git/utils/connexion.php` (des lignes localhost sont prévues en commentaire).
4. Rendre `Git/images/` et `Git/docsLegaux/` inscriptibles par le serveur.

## Lancement
- `php -S localhost:8000 -t Git`, puis ouvrir http://localhost:8000/index.php
- ou bien placer `Git/` dans un serveur XAMPP/WAMP (Apache).

## Tests
- Aucun test automatisé ni CI. Le test est manuel via le navigateur, avec un compte par rôle.
- Vérification syntaxique : `find Git -name '*.php' -exec php -l {} \;`

## Conventions observées
- Classes nommées `Mod_<nom>`, `Cont_<nom>`, `Modele_<nom> extends Connexion`, `Vue_<nom> extends VueGenerique`.
- Le module lit `$_GET['action']` et appelle le contrôleur ; `affiche()` renvoie le HTML bufferisé (`ob_start`).
- Pour ajouter un module, créer les 4 fichiers **et** ajouter un `case` dans `index.php`.
- Rôles (`Utilisateur.idRole`, par association) : 1 gestionnaire, 2 barman, 3 client, 4 super-admin (le compte n° 1).
- Chaque action de contrôleur vérifie le rôle (`if ($_SESSION['role']==1 || ...)`), sinon
  `vue->message('Droit requis non perçu.')`.
- Tout formulaire POST contient `<input type="hidden" name="token_csrf">` ; le contrôleur appelle
  `(new Token_CSRF)->check_csrf()`.
- SQL : toujours des requêtes préparées PDO avec des paramètres nommés ou `?`. Mots de passe : `password_hash` et
  `password_verify`.
- Session : `login`, `connecté`, `idCompte`, `idAsso`, `role`, `solde`, `token`.
- Le code, les noms et les messages sont en français ; l'indentation est de 4 espaces.

## Points d'attention
- Les identifiants de la base sont écrits en dur dans `connexion.php`, et le dépôt est public. Ne pas en ajouter
  d'autres ; il faut les externaliser.
- `modules/module_acceuil/` est un doublon obsolète de `module_accueil/`. Les dossiers `.idea/` sont versionnés.
- `recapJournee/` déroge au préfixe `module_`.
