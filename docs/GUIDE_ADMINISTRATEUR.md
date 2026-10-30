# Guide administrateur

## 1. Premier démarrage

### Organisation retenue : deux profils

L'INPS a retenu (21/10/2026) une organisation à **deux profils** :

| Profil | Rôles attribués au compte | Ce qu'il fait |
|---|---|---|
| **RH** (gestionnaire principal) | « Responsable DRH / formation » **et** « Administrateur technique » | Tout : reçoit et décide des demandes, accepte et programme les formations, gère les participants et les attestations, gère les paramètres, les comptes et les sauvegardes |
| **Responsable de structure** | « Responsable de structure » | Déclare et suit les demandes de formation de son service |

Les agents n'ont pas de compte : ils figurent dans le référentiel du personnel et la RH les inscrit aux formations.
Les autres rôles (administrateur fonctionnel, autorité de validation, auditeur, agent) restent disponibles
pour une organisation future, sans être attribués.

### Étapes

1. `php artisan migrate --seed --force` : crée les tables, les 27 permissions, les rôles techniques (dont ceux des deux profils), les nomenclatures et paramètres par défaut.
   Aucun compte n'est créé.
2. `php artisan inps:creer-admin --drh` : crée le **compte RH** (profil RH ; e-mail, nom,
   mot de passe saisi deux fois de façon masquée et contrôlé par la politique). Sans `--drh`, la commande crée un administrateur technique seul.
3. Se connecter avec ce compte : identité institutionnelle, référentiels (directions, services, années), import du personnel.
4. Créer les comptes des responsables de structure (*Administration › Utilisateurs*) avec le profil « Responsable de structure » **et leur service** :
   un responsable de structure ne voit que les demandes, les agents et les formations de son service.
5. Pour une deuxième personne de la RH : créer son compte avec le profil « RH ».

## 2. Comptes utilisateurs (permission « Gérer les comptes »)

Administration → Utilisateurs.

- **Créer** : nom, e-mail, **profil** (RH ou Responsable de structure) direction puis service (deux listes : la direction choisie limite les services proposés ; service obligatoire pour un responsable de structure).
  Un **mot de passe temporaire** est affiché une seule fois ; l'utilisateur devra le changer à sa première connexion.
- **Générer un mot de passe temporaire** : en cas d'oubli lorsque la messagerie n'est pas configurée. Les sessions de l'utilisateur sont fermées.
- **Désactiver** : l'utilisateur est déconnecté immédiatement ; son historique est conservé.
- **Supprimer le compte** : seulement pour un compte créé par erreur et **jamais utilisé** (aucune connexion, aucune demande) ;
  la suppression est définitive et journalisée. Sinon, désactiver.
- Un compte créé avant la version 1.6.0 avec une autre combinaison de rôles affiche « Garder les droits actuels » ; choisir RH ou Responsable de structure pour l'aligner.
- Garde-fous : on ne modifie pas son propre profil ; on ne désactive ni ne supprime son propre compte ; le dernier administrateur est protégé ;
  le profil RH (droits d'administration) ne peut être attribué que par un détenteur de « Attribuer le profil RH ».
- **Personnaliser les droits** (2.0.0) : dans la fiche du compte, bloc « Personnaliser les droits de ce compte ».
  Cocher « Donner à ce compte des droits différents de ceux de son profil », puis cocher ou décocher les droits (classés par
  module ; sous chaque droit, le ou les profils qui l'ont par défaut). Le profil reste le point de départ : seuls les écarts
  sont enregistrés (droits ajoutés, droits retirés). Décocher « Donner à ce compte des droits différents » rend exactement les
  droits du profil ; choisir un autre profil remet ses droits par défaut. Exemple : une assistante RH avec le profil Responsable de
  structure, plus « Ajouter et retirer les participants » et « Voir les demandes de tous les services ».
  Garde-fous : **on ne donne pas un droit qu'on n'a pas soi-même** ; les droits d'administration (gérer les comptes, attribuer
  le profil RH, superviser) ne s'ajoutent ni ne se retirent sans « Attribuer le profil RH et consulter les droits » ; on ne
  modifie pas ses propres droits ; le dernier compte capable de gérer les comptes ne peut pas perdre ce droit.
  Les comptes personnalisés portent la mention « (droits personnalisés) » et sont listés dans Profils et droits.
- **Formations diplômantes (2.5.0)** : droit « Enregistrer et suivre les formations diplômantes des agents »
  (`diplomantes.gerer`), donné au profil RH. Il peut être ajouté à un autre compte avec les droits personnalisés.
- **Nom du profil (2.5.0)** : « Chef de service » devient « Responsable de structure » ; rien ne change pour les comptes
  existants (même code technique `chef_service`, mêmes droits).
- **Années (Référentiels, 2.5.0)** : seulement l'année et « Demandes ouvertes » (case décochée = l'année n'est plus
  proposée dans les demandes).
- **Sauvegardes** : laisser `BACKUP_PATH` vide (ou supprimer la ligne) range les sauvegardes dans
  `storage/app/sauvegardes`. Après toute modification du `.env`, lancer `php artisan optimize`.
- **Comptes des agents (version 2.4.0 : codes d'activation)** : ils se gèrent depuis **Personnel › Accès des agents**
  (droit « Gérer les comptes »). Cocher les agents, puis **« Donner un code aux agents cochés »** : chaque agent reçoit un
  **code de 6 chiffres** (exemple : 482 913), **affiché dans la colonne « Code »** tant qu'il n'a pas servi. Recliquer ne
  change pas un code encore valable. « Imprimer la liste des codes » donne une liste (une ligne par agent) selon les filtres.
  L'agent clique sur **« Activer mon compte »** (page de connexion), saisit son matricule et son code, et choisit son mot
  de passe ; il se connecte ensuite avec son matricule. Un code sert une fois, reste valable 15 jours, et est annulé après
  5 essais erronés. Mot de passe oublié ou code perdu : **« Nouveau code »** (sur la ligne de l'agent ou sa fiche) ; le
  mot de passe actuel reste valable jusqu'à l'utilisation du nouveau code. Les codes sont chiffrés en base et n'apparaissent
  jamais dans le journal. Désactiver un agent désactive son accès. Dépannage depuis le serveur :
  `php artisan inps:code-agent MATRICULE` affiche un nouveau code.
- Toutes les opérations sont journalisées.

## 3. Profils et droits (lecture seule depuis la version 1.6.0 ; droits personnalisés listés depuis la 2.0.0)

Administration → Profils et droits. Deux profils fixes, décidés par l'INPS le 22/10/2026 :

| Profil | Rôles techniques attribués | Ce qu'il fait |
|---|---|---|
| **RH** | Responsable DRH / formation + Administrateur technique | Tout : demandes, formations, participants, attestations, personnel, rapports, paramètres, comptes, journal, sauvegardes, corbeille |
| **Responsable de structure** | Responsable de structure | Demandes de son service ; consultation des agents et des formations de son service |

L'écran présente le tableau « Qui peut faire quoi », lu en base à partir des rôles réellement attribués. La création, la modification
et la suppression de rôles depuis l'application sont retirées. Les autres rôles du catalogue (administrateur fonctionnel, autorité de validation,
auditeur, agent) restent en base pour une organisation future, sans être proposés ; un encadré signale les comptes qui en ont encore.
Les codes techniques des permissions (`besoins.instruire`, `actions.gerer`…) sont inchangés ; seuls leurs libellés ont été simplifiés.

## 4. Paramètres (permission « Gérer les référentiels et paramètres »)

Administration → Paramètres. Les paramètres marqués **« À valider par l'INPS »** ont des valeurs provisoires.

| Groupe | Exemples |
|---|---|
| Identité | Nom, sigle, intitulé de la direction, adresse, téléphone, couleur de l'interface, logo officiel (PNG/JPG, déposé par l'INPS), qualité du signataire des attestations |
| Notifications | E-mail activé/désactivé, délai du rappel des formations à venir, date du rappel de bilan annuel |
| Exports | Mention de pied de page, taille maximale des documents |
| Conservation | Durées de conservation **à fixer** (aucune purge automatique n'est réalisée) |

Aucun logo officiel n'est fourni : tant qu'aucun logo n'est déposé, un pictogramme textuel (sigle) est affiché ; hors production,
les documents portent la mention « document non officiel ».

### Logo et photographie de la page de connexion

Deux moyens, au choix :

1. **Depuis l'application** — Paramètres → Identité : « Logo officiel » (PNG/JPG, 1 Mo max.) et « Photographie du siège »
   (JPG/PNG/WebP, au moins 1200 × 700 px, 6 Mo max.). Les fichiers sont conservés dans le stockage privé et inclus dans les sauvegardes.
2. **Par le serveur** — copier les fichiers dans `public/images/institution/` sous les noms `logo.svg` (ou `logo.png`, `logo.jpg`)
   et `siege.jpg` (ou `siege.webp`, `siege.png`). Voir `public/images/institution/LISEZMOI.txt`.

Le fichier déposé dans l'application est prioritaire. N'utiliser que des fichiers officiels fournis ou validés par la Direction
de la communication de l'INPS : pas de logo redessiné, pas de photographie d'un autre bâtiment. Recommandations pour la photographie :
format paysage, 1600 px de large ou plus, façade lisible, sans personne identifiable. Sans photographie, la page de connexion affiche
un aplat bleu institutionnel et une trame architecturale abstraite.

La couleur de l'interface (par défaut #003B73) se modifie dans le même groupe ; une valeur invalide est ignorée.

## 5. Référentiels

**Structure de l'INPS (1.6.1).** Les directions et services peuvent être créés en une fois : Personnel › Importations ›
« Structure de l'INPS (directions et services) », avec un fichier dont la feuille ouverte contient les colonnes
`direction_code`, `direction_nom`, `service_code`, `service_nom` (une ligne par service ; les divisions sont des services,
nommées « Service … › Division … »). Après l'aperçu, « Confirmer » crée ce qui manque ; une direction ou un service déjà présent
(même code) n'est ni renommé ni modifié. Le fichier « structure-inps-organisee.xlsx » remis le 22/10/2026 suit ce format :
vérifier d'abord les lignes en jaune (codes proposés, divisions déduites des fonctions).


Administration → Référentiels : directions, services (rattachés à une direction), domaines, années de référence (ouverte/clôturée,
date limite de recensement, date de clôture), statuts professionnels et catégories de documents.
Les éléments ne se suppriment pas : décocher « Actif » pour les retirer des listes sans altérer l'historique.

Une année doit être **ouverte** pour que les services puissent y déclarer des besoins ; une année clôturée refuse les nouvelles soumissions.

**Supprimer un élément (2.1.0)** : le bouton « Supprimer » n'apparaît que pour un élément utilisé nulle part (créé par
erreur, doublon). Sinon, la ligne indique où il est utilisé (« Utilisé : 12 agent(s), 3 demande(s) ») et il se désactive
en décochant « Actif ». Les utilisations sont lues dans la structure de la base (toutes les tables qui y font référence) ;
pour une année, ce sont les demandes, formations et plans de cette année. Chaque suppression est journalisée.

## 6. Plan annuel

**Depuis la version 1.7.0**, un plan annuel simple est disponible (menu « Plan annuel », géré par la RH avec la permission
« Créer, modifier, terminer et annuler les formations ») : formations prévues par période, « Programmer », validation en un
clic sans circuit, export PDF/Excel ; voir `docs/GUIDE_UTILISATEUR.md` § 2 b. Le responsable de structure le consulte pour son service.

Historique : décision INPS du 21/10/2026 : pour simplifier, le plan annuel et ses circuits de validation ont été retirés. Une demande acceptée
est directement organisée en formation par la RH. Les menus « Plans annuels » et « Circuits de validation » n'existent plus.
Sur une base existante, la migration de la version 1.3.0 conserve toutes les données du plan en base (tables `plans_formation`,
`plan_versions`, `plan_actions`, `circuits_validation`, `etapes_validation`), sans les afficher ; voir `docs/BASE_DE_DONNEES.md`.

## 6 b. Évaluations (1.9.0)

Deux droits : **« evaluations.gerer »** (profil RH : indiquer en fin de formation qui a compris, suivre toutes les
évaluations) et **« evaluations.froid »** (profil Responsable de structure : indiquer 3 à 6 mois après si ses agents appliquent
ce qu'ils ont appris). Les deux évaluations suivent le même modèle depuis la 2.2.0 : Oui / En partie / Non.
Le rappel « Évaluation de la mise en pratique » est émis par la tâche quotidienne `php artisan inps:echeances`
(une fois par formation et par service) ; un service sans responsable de structure actif renvoie le rappel à la RH.
Le délai par défaut (6 mois) est à confirmer (`DECISIONS_A_VALIDER.md`, ligne 10 b).

## 7. Journal d'audit (permission « Consulter le journal d'audit »)

Administration → Journal d'audit. Chaque ligne indique **la date, qui, et ce qui s'est passé** en français (par exemple
« Demande acceptée — BES-2026-0003 : Envoyée → Acceptée »), avec un lien « Voir » vers l'élément s'il existe encore.
Filtres : **type** (connexions, demandes, formations, attestations, personnel, comptes, administration), **personne**, **période**
et recherche dans le texte. Le code technique, l'adresse IP et le contexte sont dans « Détails », replié. Export Excel (lui-même journalisé).
Le journal ne contient ni mot de passe ni jeton ; les échecs de connexion enregistrent une empreinte de l'identifiant, pas l'e-mail.
Il reste protégé contre la modification et la suppression (application et déclencheurs MySQL).

## 8. Sauvegardes (permission « Superviser l'état technique »)

Administration → Sauvegardes :
- bouton **« Faire une sauvegarde »** (base complète + documents déposés) ;
- voyant **« Tout va bien / À vérifier »** : sauvegarde de moins de 2 jours, sauvegardes protégées par mot de passe (`BACKUP_PASSWORD`),
  journal protégé, rappels automatiques actifs (tâche planifiée), et en production mode débogage désactivé ;
- **liste des sauvegardes** avec « Télécharger » (seuls les fichiers de la liste sont téléchargeables ; chaque téléchargement est journalisé) ;
- « Informations techniques (pour l'informaticien) », replié : environnement, sessions, e-mail, versions, derniers envois d'e-mails.
La **restauration** reste une opération de l'informaticien (`php artisan inps:restaurer`, voir `docs/SAUVEGARDE_RESTAURATION.md`).
Sur Laragon, si la sauvegarde échoue faute de `mysqldump`, renseigner `MYSQLDUMP_PATH` dans `.env` (chemin complet de `mysqldump.exe`).
En ligne de commande : `php artisan inps:sante`.

## 8 b. Suppression des erreurs de saisie et corbeille

| Élément | Supprimable quand | Sinon | Après suppression |
|---|---|---|---|
| Demande | Par son auteur tant que la RH n'a pas décidé ; par la RH tant qu'aucune formation n'est programmée | — | Corbeille (restaurable) |
| Formation | Non terminée et sans attestation (ni issue de l'import de l'historique) | Annuler la formation | Corbeille ; participants retirés ; la demande d'origine redevient « Envoyée » |
| Agent | Jamais inscrit, cité dans aucune demande, sans compte ni rôle de formateur | Désactiver | Définitive |
| Compte | Jamais utilisé | Désactiver | Définitive |

Dans les listes Demandes, Formations et Personnel, des **cases à cocher** et « Supprimer la sélection » traitent plusieurs lignes ;
celles qui ne peuvent pas être supprimées sont listées avec la raison. **Corbeille** (menu Administration, RH) : formations et demandes supprimées,
avec la date et l'auteur ; « Restaurer » remet la formation (et réinscrit ses participants, sauf conflit de dates signalé) ou la demande.
Une formation restaurée retrouve sa demande si celle-ci attend toujours une décision. Toute suppression et restauration est journalisée.

## 9. Messagerie

Par défaut `MAIL_MAILER=log` : **aucun e-mail n'est envoyé** (les notifications internes fonctionnent). Pour activer l'envoi :
renseigner `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` dans `.env`,
exécuter `php artisan optimize:clear`, puis activer « Envoyer aussi les notifications par e-mail ». Chaque tentative est tracée avec son résultat réel.

## 10. Données de démonstration

Uniquement hors production : `DEMO_PASSWORD='…' php artisan db:seed --class=DemoSeeder`. Pour une base opérationnelle, partir d'une installation propre
(`migrate --seed`), jamais d'une base de démonstration.
