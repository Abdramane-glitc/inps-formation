# Rapport de livraison — version 2.6.0 (28/10/2026)

## Version 2.6.0 — nouveau design
Tableau de bord refait d'après le modèle choisi (cartes avec évolution, graphiques mensuels, demi-anneau), style décliné
sur toutes les pages, recherche générale. Aucune migration. 157 tests réussis.

## Version 2.5.2 — page de connexion
Pages publiques redessinées (bleu nuit, photographie du siège en bande oblique et en cercle). Aucune migration.

## Version 2.5.1 — correctif et vérification d'ensemble
Faux « Plan validé » d'un plan vide corrigé (une migration) ; analyse statique, parcours de toutes les pages pour les
trois profils, contrôle des failles connues des dépendances ; importation limitée aux vrais classeurs ; commentaires
de configuration en français. 155 tests réussis.

## Version 2.5.0 — formations diplômantes
Module « Formations diplômantes » (RH, sans montants, pièces jointes, export Excel, fiche agent, Mon espace) ; profil
« Chef de service » renommé « Responsable de structure » ; années simplifiées (année + demandes ouvertes) ; correctif de
la page Sauvegardes avec un `BACKUP_PATH` vide. Une migration. 152 tests réussis.

## Version 2.4.0 — codes d'activation
Accès des agents par code de 6 chiffres affiché à l'écran, activation par l'agent, sans feuille PDF. Une migration.

## Version 2.3.3 — correctif
Un seul nouveau mot de passe par minute et par agent ; commande `inps:mot-de-passe-agent`.

## Version 2.3.2 — correctif
Boutons des feuilles d'accès bloqués après un clic (un second clic remplaçait le mot de passe imprimé).

## Version 2.3.1 — correctif
Comptes d'agents et feuilles d'accès produits ensemble (aucun compte sans feuille) ; lots de 50 ; nouveaux mots de passe en lot ;
délai de production des PDF porté à 5 minutes.

## Version 2.3.0 — accès des agents et convocations
Comptes « Agent » créés en masse (identifiant = matricule, feuille d'accès PDF) ; « Mon espace » ; convocation des
participants (PDF, responsables de structure, agents, e-mail si possible, rectificative). Une migration.

## Version 2.2.0 — évaluations simplifiées
À chaud : la RH indique si chaque participant a compris (oui / en partie / non). À froid : le responsable de structure indique si
ses agents appliquent ce qu'ils ont appris (même modèle). Avis des participants retiré. Une migration.

## Version 2.1.0 — analyse du besoin revue
Diagnostic savoir-faire / moyens / temps avec conclusion ; gravité, mesure, solutions essayées ; aide à la décision pour la RH ;
suppression des éléments de référentiel jamais utilisés. Une migration.

## Version 2.0.0 — droits personnalisables
Fiche d'un compte : droits ajoutés ou retirés par rapport au profil, avec garde-fous (on ne donne pas un droit qu'on n'a pas).
Les six demandes de la présentation du 26/10/2026 sont couvertes par les versions 1.8.0, 1.9.0 et 2.0.0.

## Version 1.9.0 — après la formation
Évaluation à chaud (fiches papier saisies par la RH), évaluation de la mise en pratique 3 à 6 mois après par le responsable de
structure (délai choisi par la RH, rappel automatique), rapport de réalisation (PDF), attestation d'intervention des
formateurs externes. Une migration. Prochaine version prévue : 2.0.0 (droits personnalisables par compte).

## Version 1.8.0 — analyse du besoin et formateurs multiples
Demande : problème constaté, origine (cases), pourquoi une formation, résultat attendu ; avertissement si aucune cause
liée aux compétences. Formation : plusieurs formateurs internes et externes, type « Mixte ». Deux migrations.
Prochaines versions prévues : 1.9.0 (évaluations à chaud et à 3–6 mois, rapport de réalisation, attestation du prestataire),
2.0.0 (droits personnalisables par compte).

## Version 1.7.4 — choix de la direction puis du service
Comptes et agents : deux listes liées (Direction, puis Service de cette direction) au lieu d'une longue liste unique.
Correctif : la case « Actif » des référentiels (directions, services, domaines) était perdue à l'enregistrement d'une ligne,
ce qui désactivait l'élément. Recocher les éléments désactivés par erreur après la mise à jour.

## Version 1.7.3 — revue d'ensemble
Revue complète (voir `docs/SECURITE.md`) : sélecteur d'année du plan corrigé, redirection des notifications et import de la structure renforcés.

## Version 1.7.2 — statut des demandes
Le statut affiché d'une demande suit sa formation (programmée, terminée, annulée) ; formations « À terminer » signalées.

## Version 1.7.1 — correctif
Erreur 500 en modifiant les référentiels (journal d'audit) corrigée.

## Version 1.7.0 — plan annuel simple
Formations prévues par période, « Programmer » pour créer la formation, validation en un clic, export PDF/Excel ;
une demande peut être acceptée et inscrite au plan. Détail dans `CHANGELOG.md`.

## Version 1.6.1 — structure de l'INPS
Import des directions et services (fichier organisé à partir de la nomenclature de la DRH, remis à part) ; services regroupés par direction dans les listes ; personnel limité au service du responsable de structure.

## Version 1.6.0 — profils fixes, journal en clair, sauvegardes, suppression et corbeille
Correction des agents de la demande non inscrits sans message ; deux profils fixes (RH, Responsable de structure) ; journal d'audit lisible ;
écran Sauvegardes avec téléchargement ; suppression des erreurs de saisie, cases à cocher dans les listes et corbeille.
Détail dans `CHANGELOG.md`.

## Version 1.5.1 — participants par service
Le responsable de structure ne voit que les formations et les participants de son service ; sur la fiche, les participants sont regroupés par service et l'ajout d'autres participants est replié quand la demande les a déjà fournis.

## Version 1.5.0 — formation en trois étapes
Décision de l'INPS (22/10/2026) : le responsable de structure coche les agents à former (plus de nombre à saisir) ; « Accepter et programmer »
crée la formation et y inscrit les agents ; la formation se gère en trois étapes (formation, participants avec « a suivi la formation »,
attestations). Convocations, présences jour par jour, liste d'attente, évaluations et report retirés. Migration non destructive.
Détail dans `CHANGELOG.md`.

## Version 1.4.0 — demandes simplifiées, sans budget ni finances
Décision de l'INPS (22/10/2026) : formulaire de demande minimal (intitulé, raison, agents concernés ou nombre, période), envoyé
directement à la RH ; étapes Envoyée → Acceptée / Refusée / À compléter ; plus aucun budget, coût ni dépense dans l'application.
Migration non destructive (données financières conservées en base, non affichées). Détail dans `CHANGELOG.md`.

## Version 1.3.1 — correctif d'importation
Les dates saisies comme de vraies dates dans Excel (.xlsx) sont désormais reconnues à l'import de l'historique.

## Version 1.3.0 — parcours simplifié
Décision de l'INPS (21/10/2026) : plus de plan annuel ; action et session fusionnées en une seule **formation**.
Parcours : demande → acceptée → « Organiser la formation » → participants → présences → attestations, avec, sur chaque fiche,
une barre d'étapes et un encadré « Prochaine étape ». Migration non destructive (données du plan conservées en base, non utilisées).
Détail dans `CHANGELOG.md`, `docs/GUIDE_UTILISATEUR.md` et `docs/BASE_DE_DONNEES.md`.

## Version 1.2.0 — organisation à deux profils
La RH gère tout (y compris les paramètres et les comptes) ; le responsable de structure déclare les besoins
de son service ; les agents n'ont pas de compte. Détail dans `CHANGELOG.md` et `docs/GUIDE_ADMINISTRATEUR.md` § 1.

## Version 1.1.0 — refonte visuelle
Refonte complète de l'interface (charte bleue #003B73 / #0056A3, page de connexion en deux zones, menu latéral à icônes,
barre haute, tableau de bord réorganisé, composants harmonisés, accessibilité). Aucune règle métier, permission ou donnée modifiée.
Logo et photographie du siège fournis par l'INPS et intégrés (`public/images/institution/`). Souhaitable : logo vectoriel et photographie en haute résolution, droits d'utilisation à confirmer.
Détail dans `CHANGELOG.md`.

## Fonctionnalités terminées (implémentées et testées)
Modules A à N du cahier des charges : tableau de bord, référentiel du personnel et imports, besoins, formations (sans plan annuel depuis 1.3.0), organismes/formateurs, participants (« a suivi la formation ») et attestations, documents, rapports (10),
notifications et échéances, paramétrage ; rôles et permissions configurables ; journal d'audit protégé ; sauvegarde/restauration.

## Fonctionnalités partielles
- Notifications par e-mail : implémentées et désactivées par défaut ; non testées avec un serveur SMTP réel.
- Docker : fichiers de référence fournis, non construits (démon indisponible dans l'environnement de construction).
- Conservation des données : durées paramétrables mais aucune purge automatique (en attente de la politique de l'INPS).

## Restant / évolutions possibles
Authentification unique (annuaire), synchronisation SIRH, procédure outillée d'archivage/purge, modèles de documents éditables en ligne.

## Versions
PHP 8.3+ (testé 8.3.6, compatible 8.4) · Laravel 13.37.0 · MySQL 8.0 (testé 8.0.46) · Tailwind 4.3 · Vite 8.3 · dompdf 3.1.6 · PhpSpreadsheet 5.10.0 · PHPUnit 12.5.40

## Résultats des tests
126 tests exécutés, 126 réussis, 0 échec, 0 ignoré (1361 assertions) — détail dans `docs/TESTS.md`.
Version 1.0.0 : suite également réexécutée sur une installation neuve réalisée à partir de l'archive (base vierge).

## Limites de l'environnement de construction
Packagist et Docker inaccessibles : dépendances PHP récupérées depuis leurs dépôts GitHub officiels aux versions verrouillées ;
`composer audit`, construction Docker, installation Windows et PHP 8.4 restent à vérifier par l'INPS.

## Avant la mise en production
Voir la checklist de `docs/INSTALLATION_PRODUCTION.md` § 10, les décisions de `docs/DECISIONS_A_VALIDER.md` et la recette `docs/RECETTE_INPS.md`.
