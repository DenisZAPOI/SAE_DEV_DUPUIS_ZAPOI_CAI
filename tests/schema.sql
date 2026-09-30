-- Schéma MINIMAL reconstitué à partir des requêtes des modèles (le vrai schéma MySQL n'est pas versionné).
CREATE TABLE compte (idCompte INTEGER PRIMARY KEY AUTOINCREMENT, nom TEXT, mdp TEXT, solde INTEGER DEFAULT 0);
CREATE TABLE association (idAsso INTEGER PRIMARY KEY AUTOINCREMENT, nomAsso TEXT, siege_social TEXT, chemin_logo TEXT, tresorerie INTEGER);
CREATE TABLE Utilisateur (idCompte INTEGER, idRole INTEGER, idAsso INTEGER);
CREATE TABLE associationtemp (IDTemp INTEGER PRIMARY KEY AUTOINCREMENT, nomAsso TEXT, siege_social TEXT, idCompte INTEGER, chemin_logo TEXT);
