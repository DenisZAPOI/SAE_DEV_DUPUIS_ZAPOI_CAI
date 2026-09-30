# AUDIT.md — audit de sécurité du domaine « Socle »

- **Périmètre** : domaine Socle (`Git/index.php`, `Git/html_spe_char.php`, `Git/utils/`, `Git/composants/composant_menu/{composant,controleur}_menu.php`) et contrat `docs/API.md`.
- **Environnement du TP** : Ubuntu 24.04, PHP 8.3.6, PHPUnit 9.6.17, le 30/09/2026.
- **Méthode** : pour chaque faille, lecture du code sur GitHub, puis script ou test exécuté dans l'environnement du TP. Les sorties brutes sont reproduites ci-dessous. Un test de non-régression (`tests/AuditSecuriteTest.php`) affirme le comportement sécurisé attendu : il **échoue avant correctif** et deviendra vert une fois la faille corrigée.
- **Audit en lecture seule** : cette PR n'ajoute que `docs/AUDIT.md`, aucun code n'est modifié. Le test de non-régression `tests/AuditSecuriteTest.php` est volontairement rouge : il est conservé sur la branche `tests/non-regression-audit` (non fusionnée, pour ne pas casser `phpunit` sur `main`) et sera intégré avec chaque PR de correctif.

## Barème de gravité
Critique : compromission directe (secret exploitable, RCE). Haute : élévation de privilège ou vol de session. Moyenne : exploitation conditionnelle ou défense manquante. Faible : durcissement.

## Tableau de synthèse

| ID | Titre | Localisation | Gravité | Statut | Auteur |
|---|---|---|---|---|---|
| SEC-01 | Identifiants de BDD en clair (code + historique) | `Git/utils/connexion.php:9` + historique | **Critique** | Code corrigé (`fix/F1-secrets-bdd-env`) ; rotation et purge de l'historique à faire | IA, vérifiée manuellement |
| AUTH-01 | Fixation de session (pas de régénération d'ID) | tout le dépôt / `Git/index.php:2` | **Haute** | Confirmée | IA, vérifiée manuellement |
| AUTH-05 | Sortie parasite avant `<?php` → `headers already sent` | `Git/html_spe_char.php:1` | Moyenne | Confirmée | IA, vérifiée manuellement |
| AUTH-02 | Cookie de session sans HttpOnly/Secure/SameSite | `Git/index.php:2` | Moyenne | Confirmée | IA, vérifiée manuellement |
| XSS-01 | `h()` laisse passer les URL `javascript:` | `Git/html_spe_char.php:5` | Moyenne | Confirmée | Étudiant |
| AUTH-03 | CSRF non centralisé + pas de garde dans le routeur | `Git/index.php`, `docs/API.md` | Moyenne | Confirmée | IA, vérifiée manuellement |
| INJ-01 | DSN sans `charset` et aucune option PDO de sécurité | `Git/utils/connexion.php:9` | Faible | Confirmée | IA, vérifiée manuellement |
| INJ-02 | Injection SQL directe dans le socle | socle | — | Faux positif écarté | IA |
| FIL-01 | Traversée / inclusion via `?module=` | `Git/index.php:18` | — | Non confirmée (routeur en liste blanche) | IA |

Cinq failles confirmées au minimum sont exigées, dont deux critiques/hautes : ce rapport en confirme sept, dont **SEC-01 (critique)** et **AUTH-01 (haute)**.

---

## SEC-01 — Identifiants de base de données en clair (Critique, Confirmée)
**Localisation** : `Git/utils/connexion.php:9` (ligne active) et l'historique Git.
**Gravité — justification** : accès direct en lecture/écriture à la base de production d'un dépôt public ; secret directement exploitable, sans condition. Trois jeux d'identifiants distincts, dont un hébergeur tiers.
**Auteur** : signalée par l'IA, confirmée manuellement sur GitHub et par script.

Preuve (extrait, mots de passe masqués dans ce rapport, complets dans la sortie du TP) :
```
--- fichier courant (ligne active) ---
9: self::$bdd = new PDO($dsn='mysql:host=database-etudiants.iut.univ-paris8.fr;
   dbname=dutinfopw201699',$user='dutinfopw201699',$password='juh*****');
--- jeux d'identifiants distincts dans TOUT l'historique ---
dbname=4724048_jj',$user='4724048_jj',$password='Ste*****'        (hébergeur awardspace)
dbname=dutinfopw20167',$user='dutinfopw20167',$password='dys*****'
dbname=dutinfopw201699',$user='dutinfopw201699',$password='juh*****'
--- nombre de commits contenant un mot de passe en clair ---
47
```
**Test de non-régression** : `testSec01AucunSecretEnClair` — échoue tant qu'un `$password='...'` figure dans le fichier.
**Correctif attendu** : changer les trois mots de passe (déjà compromis), lire les identifiants depuis `.env`, réécrire l'historique.
**État du correctif** : identifiants lus depuis `.env` ou l'environnement par `Git/utils/config.php` (branche `fix/F1-secrets-bdd-env`), lignes commentées contenant d'anciens mots de passe supprimées. Restent à faire hors PR : changer les trois mots de passe auprès des hébergeurs, puis réécrire l'historique (`git filter-repo`) et forcer la mise à jour de toutes les branches.

## AUTH-01 — Fixation de session (Haute, Confirmée)
**Localisation** : aucun `session_regenerate_id()` dans le dépôt ; `session_start()` en `Git/index.php:2`.
**Gravité — justification** : un attaquant qui impose un identifiant de session à la victime conserve la session après connexion, avec le rôle obtenu (jusqu'à super-admin) ; vol de session complet.
**Auteur** : signalée par l'IA, confirmée manuellement par démonstration exécutable.

Preuve :
```
--- occurrences de session_regenerate_id dans TOUT le depot ---
0
--- session.use_strict_mode est-il active par le code ? ---
 -> jamais defini (defaut PHP = 0, desactive)
--- demonstration : l'ID de session ne change pas apres 'connexion' ---
ID avant connexion : IDFIXEPARATTAQUANT
ID apres connexion : IDFIXEPARATTAQUANT
RESULTAT: ID inchange -> session fixee par lattaquant conserve la session admin
```
**Test de non-régression** : `testAuth01RegenerationSession` — échoue tant que `session_regenerate_id` est absent.
**Correctif attendu** : activer `session.use_strict_mode` et régénérer l'ID + le jeton CSRF à chaque connexion/changement de rôle.

## AUTH-05 — Sortie parasite avant `<?php` (Moyenne, Confirmée)
**Localisation** : `Git/html_spe_char.php:1` (un `\n` avant la balise), inclus par `Git/index.php:5`.
**Gravité — justification** : casse tous les `header()` (16 appels dans le dépôt), donc les redirections et tout futur en-tête de sécurité ou régénération de session ; prérequis bloquant pour AUTH-01, AUTH-02 et les en-têtes.
**Auteur** : signalée par l'IA, confirmée manuellement.

Preuve :
```
--- premiers octets de html_spe_char.php (od) ---
0000000  \n   <   ?   p   h   p  \n  ...
--- inclusion puis header() ---
ERREUR PHP CAPTUREE: Cannot modify header information - headers already sent
   by (output started at /tmp/proof/Git/html_spe_char.php:1)
headers_sent() = true -> sortie deja envoyee depuis .../html_spe_char.php ligne 1
```
**Test de non-régression** : `testAuth05PasDeSortieAvantPhp` — échoue tant que le fichier ne commence pas par `<?php`.
**Correctif attendu** : supprimer la ligne vide initiale et la balise fermante `?>`.

## AUTH-02 — Cookie de session sans HttpOnly/Secure/SameSite (Moyenne, Confirmée)
**Localisation** : `Git/index.php:2` (`session_start()` sans configuration de cookie).
**Gravité — justification** : cookie de session volable par XSS (pas de HttpOnly), envoyé en clair (pas de Secure), transmis en intersite (pas de SameSite), ce qui aggrave AUTH-03.
**Auteur** : signalée par l'IA, confirmée manuellement.

Preuve :
```
--- session_set_cookie_params / ini_set(session.cookie_*) dans index.php ? ---
 -> jamais appele : les valeurs par defaut de php.ini s'appliquent
--- valeurs par defaut effectives (aucun reglage) ---
httponly=false  secure=false  samesite=""
```
**Correctif attendu** : `session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Lax'])` avant `session_start()`.

## XSS-01 — `h()` laisse passer les URL `javascript:` (Moyenne, Confirmée)
**Localisation** : `Git/html_spe_char.php:5-7`.
**Gravité — justification** : `h()` protège le texte HTML mais pas le contexte URL ; un attribut `href`/`src` construit avec `h()` accepte `javascript:` et exécute du script au clic. Le contrat impose `h()` « partout » sans signaler cette limite.
**Auteur** : trouvée par l'étudiant (revue du helper), confirmée par script.

Preuve :
```
entree : javascript:alert(document.cookie)
h() -> : javascript:alert(document.cookie)
HTML genere : <a href="javascript:alert(document.cookie)">lien</a>
RESULTAT: identique -> href javascript: passe intact -> XSS possible au clic
```
**Test de non-régression** : `testXss01HelperUrlSure` — échoue tant qu'aucun helper d'URL sûre n'existe.
**Correctif attendu** : helper `url()` qui n'autorise que les schémas `http`/`https`, à imposer dans le contrat pour les attributs d'URL.

## AUTH-03 — CSRF non centralisé, pas de garde dans le routeur (Moyenne, Confirmée)
**Localisation** : `Git/index.php:18-68` ; écarts recensés dans `docs/API.md`.
**Gravité — justification** : la vérification CSRF et le contrôle de rôle dépendent de chaque module ; le routeur ne fait aucune garde, ce qui a déjà produit des oublis documentés (module `stock`, `restock/ajoutAchat`, `commande/annulation` en GET, `restock/afficherAchats`/`detailsAchat` sans rôle).
**Auteur** : signalée par l'IA, confirmée manuellement.

Preuve :
```
--- index.php verifie-t-il CSRF ou role avant de charger un module ? ---
 -> le routeur ne verifie NI le CSRF NI le role
--- ecarts deja documentes dans docs/API.md ---
| annulation | GET | idCommande | non contrôlé | Annule la commande |
| afficherAchats | GET | — | non contrôlé | Liste des achats |
- Aucun contrôle CSRF sur le module stock ni sur restock/ajoutAchat.
```
**Correctif attendu** : garde centrale dans `index.php` (POST → CSRF obligatoire, table module→rôle, `http_response_code(403)`).

## INJ-01 — DSN sans `charset` et aucune option PDO de sécurité (Faible, Confirmée)
**Localisation** : `Git/utils/connexion.php:9`.
**Gravité — justification** : sans `charset` fixé et sans `ATTR_EMULATE_PREPARES=false`, la protection contre l'injection repose sur le jeu de caractères par défaut du serveur ; risque de contournement d'échappement avec un jeu multioctet. Impact conditionnel, d'où « Faible ».
**Auteur** : signalée par l'IA, confirmée manuellement.
**Note d'honnêteté** : la conséquence « prepares émulées » est un comportement MySQL documenté et **n'a pas pu être exécutée** dans le TP (pas de serveur MySQL ; le pilote SQLite témoin ne supporte pas cet attribut). Seule la partie vérifiable a été prouvée : absence de `charset` et de toute option de sécurité sur la ligne active.

Preuve :
```
occurrences de charset sur la ligne active : 0
le code ne fixe jamais EMULATE_PREPARES ni ERRMODE ni setAttribute
 -> aucune de ces options n'est definie
```
**Test de non-régression** : `testInj01DsnCharset` — échoue tant que le DSN actif n'a pas de `charset`. (Le test ignore correctement les lignes commentées : un premier jet le passait à tort, corrigé.)

---

## Failles écartées

### INJ-02 — Injection SQL directe dans le socle (Faux positif)
Une lecture rapide pourrait suspecter le paramètre `?module=`. Vérifié : le socle n'exécute aucune requête SQL, et `?module=` est comparé à une liste fermée de `case`, jamais concaténé. **Écartée.**

### FIL-01 — Traversée de répertoires via `?module=` (Non confirmée)
Hypothèse testée : `?module=../../etc/passwd` provoquerait une inclusion arbitraire. Vérifié sur `Git/index.php:18-68` : la valeur n'est jamais utilisée pour bâtir un chemin ; toute valeur inconnue tombe sur le `default`. Pas d'inclusion contrôlable. **Non confirmée** (comportement sain). Un durcissement mineur reste possible (`__DIR__` sur les `include`), sans impact de sécurité démontré.

---

## Reproduire les preuves dans le TP
```
git clone https://github.com/DenisZAPOI/SAE_DEV_DUPUIS_ZAPOI_CAI.git && cd SAE_DEV_DUPUIS_ZAPOI_CAI
git checkout origin/tests/non-regression-audit -- tests/AuditSecuriteTest.php
phpunit --testdox tests/AuditSecuriteTest.php
```
Sortie obtenue avant tout correctif (état RED de la non-régression), le 30/09/2026 :
```
 ✘ Sec 01 aucun secret en clair
 ✘ Auth 05 pas de sortie avant php
 ✘ Auth 01 regeneration session
 ✘ Inj 01 dsn charset
 ✘ Xss 01 helper url sure
Tests: 5, Assertions: 5, Failures: 5.
```
La suite existante reste intacte : `phpunit` complet donne `Tests: 42, Failures: 5` (37 tests d'origine verts + 5 tests d'audit rouges).

---

## Retour critique

**Faux positifs produits par l'IA.** Deux points annoncés au premier tour ne tiennent pas après vérification. INJ-02 (injection SQL dans le socle) : aucune requête n'y est exécutée, l'alerte venait d'une association trop rapide « paramètre utilisateur = danger ». FIL-01 (traversée via `?module=`) : le routeur est en liste blanche, l'inclusion arbitraire est impossible. Un troisième point, INJ-01, était partiellement surévalué : la partie « prepares émulées » n'est pas exécutable ici et a été requalifiée en fait documenté, non en preuve. Cela confirme la consigne : une alerte d'IA n'est retenue qu'après lecture du code et exécution d'une preuve. Mon propre test a d'ailleurs produit un faux négatif (INJ-01 passait à cause des lignes commentées) : sans relecture de la sortie, j'aurais validé un test qui ne prouvait rien.

**Faille trouvée par l'étudiant.** XSS-01 vient d'une revue manuelle du helper `h()`, pas d'une alerte d'IA : en testant `h('javascript:alert(1)')`, on constate que la chaîne ressort intacte. L'IA traitait `h()` comme « la » protection XSS ; c'est l'inspection du contexte URL, absent de son raisonnement initial, qui a révélé le trou.

**Prompt le plus utile.** « Pour chaque faille, lis le code incriminé puis écris un script/test qui la démontre, exécute-le et garde la sortie ; classe ensuite en confirmée / non confirmée / faux positif. » Ce prompt a été le plus rentable : il a transformé une liste d'hypothèses en constats prouvés, éliminé deux faux positifs et un tiers surévalué, et produit directement les tests de non-régression réutilisables.
