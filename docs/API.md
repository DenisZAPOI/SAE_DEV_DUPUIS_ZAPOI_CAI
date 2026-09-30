# API.md — contrat entre vues (front) et contrôleurs (back)

Il n'y a pas d'API REST : l'application est rendue côté serveur. Le « contrat » est l'ensemble des URL
`index.php?module=<module>&action=<action>`, des champs de formulaire envoyés et des rôles autorisés.
Ce document fait foi pour le front et le back ; en cas de changement, le mettre à jour dans la même PR.

## Règles communes
- Routage : `index.php` lit `?module=` puis le `module_<nom>.php` lit `?action=`. Module inconnu : « Module inconnu. »
- Session requise : `connecté`, `idCompte`, `idAsso`, `role` (voir `CLAUDE.md`). Rôles : 1 gestionnaire, 2 barman,
  3 client, 4 super-admin.
- Tout `POST` contient `token_csrf` (valeur de `$_SESSION['token']`). Sinon l'action est refusée.
- Refus de droit : message « Droit requis non perçu. » affiché dans la vue (pas de code HTTP 403).
- Sorties HTML : échapper avec `h()`.

## connexion
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `connexion` / `inscription` / `nouvelleAsso` | GET | — | public | Affiche le formulaire |
| `ajout_inscription` | POST | `login_inscription`, `mdp_inscription` | public | Crée le compte |
| `ajout_connexion` | POST | `login_connexion`, `mdp_connexion` | public | Ouvre la session, redirige vers `choisirAsso` |
| `choisirAsso` | GET | — | connecté | Liste des associations |
| `redirection` | POST | `association` (id ou `none`) | connecté | Fixe `idAsso`, redirige selon le rôle : 3 → `solde/page_solde`, 2 → `commande/finCommande`, 1 ou 4 → `stock/affiche_stock` ; nouvel arrivant = client |
| `ajout_association` | POST (multipart) | `nomAsso`, `siege_social`, `logo_asso` (fichier), documents légaux | connecté | Demande de création en attente |
| `newAsso` | GET | — | 4 | Liste des demandes en attente |
| `validationAsso` | POST | `asso[]` | 4 | Valide les demandes |
| `deconnexion` | GET | — | connecté | Détruit la session |

## solde
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `page_solde` | GET | — | 3 | Solde du client |
| `page_soldeAsso` | GET | — | 1, 4 | Trésorerie de l'association |
| `ajout_solde` | POST | `solde` | 3 | Crédite le compte, redirige vers `page_solde` |

## commande
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `ajout_debut_commande` | GET | — | 3, 4 | Formulaire de début de commande |
| `traiter_debut_commande` (défaut) | POST | `bouton_type` | 3 | Crée la commande puis affiche le formulaire |
| `reload` | GET | — | 3 | Réaffiche le formulaire |
| `commandeProduit` | GET | `idProd` | 3 | Détail d'un produit (quantité max) |
| `ajout_produit` | POST | `produits`, `idCommande` | 3 | Enregistre la commande |
| `prix_total` | POST | `produits` | 3 | Calcule le total |
| `finCommande` | GET, POST | `idCommande` | 2 | Le barman finalise la commande |
| `annulation` | GET | `idCommande` | non contrôlé | Annule la commande |

## stock (gestion du menu et de l'inventaire)
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `affiche_stock` | GET | — | 1, 4 | Stock (ou initialisation du stock du jour) |
| `recherche` | POST | `rechercher` | 1, 4 | Recherche de produit |
| `creeInventaire` | POST | — | 1, 4 | Crée l'inventaire du jour |
| `menu` | GET | — | 1, 4 | Menu |
| `changeInfoMenu` | POST | `produit` | 1 | Modifie le menu |
| `afficheProdAjouter` / `afficheProdRetirer` | GET | — | 1 | Formulaires |
| `ajouteProduit` / `retireProduit` | POST | `produit` | 1 | Ajoute / retire un produit du menu |
| `deduireStock` | POST | — | 2 | Déduit le stock d'une commande |

## restock (achats fournisseurs)
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `listProduits` (défaut) | GET | `idProd` (option) | 1, 4 (affichage : 1) | Produits à racheter |
| `fournisseurs` | GET | — | 1, 4 | Liste des fournisseurs |
| `produitsF` | GET | `fournisseur` | 1, 4 | Produits d'un fournisseur |
| `ajoutAchat` | POST | `produit` | 1, 4 | Crée un achat si les fonds suffisent |
| `afficherAchats` | GET | — | non contrôlé | Liste des achats |
| `detailsAchat` | GET | `idAchat` | non contrôlé | Détail d'un achat |
| `ajoutStock` | POST | `idAchat` | 1, 4 | Réceptionne l'achat et met le stock à jour |

## historique
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `historique_client` (défaut) | GET | — | 1, 3 | Commandes du client |
| `detailHistoClient` | GET | `idCommande` | 1, 2, 3 | Détail d'une commande |
| `historiqueAchatFournisseur` | GET | — | 1, 4 | Achats de l'association |
| `detailHistoAchat` | GET | `idCommande` | 1, 2, 3 | Détail d'un achat |

## recapJournee
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `recapDuJour` (défaut) | GET | — | 1, 4 | Recette, semaine, moyenne, produits vendus, écarts |
| `recapCertainJour` | GET | `jour` (décalage en jours) | 1, 4 | Même récap pour un autre jour |
| `ecartStockEntre2J` | GET | — | 1, 4 | Écart de stock entre deux jours |

## staff
| Action | Méthode | Champs | Rôles | Effet |
|---|---|---|---|---|
| `gestionMembre` | GET | — | 1, 4 | Liste des membres |
| `promoteMembre` | POST | `choix` (membre), `role` | 1 | Change le rôle d'un membre |

## Écarts connus (à corriger, pas à imiter)
- Aucun contrôle CSRF sur le module `stock` ni sur `restock/ajoutAchat`.
- `commande/annulation` change des données via GET, sans contrôle de rôle ni CSRF ; `deconnexion` se fait aussi en GET.
- `restock/afficherAchats` et `detailsAchat` n'ont pas de contrôle de rôle explicite.
- `connexion/choisirAsso` donne le rôle 4 à tout compte dont le login est `admin`.
