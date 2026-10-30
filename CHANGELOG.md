# Historique des versions

## [2.6.0] — 2026-10-28 — Nouveau design de l'application

D'après le modèle de tableau de bord choisi par l'INPS, décliné aux couleurs de l'Institut (bleu #003B73 et bleu du
logo #064CFA à la place du violet du modèle).

- **Tableau de bord** :
  - quatre cartes chiffrées avec l'évolution par rapport à l'exercice précédent (vert en hausse, rouge en baisse) ;
  - « Formations par mois » (barres empilées : terminées, programmées, annulées) ;
  - « Participants par mois », avec le mois en cours mis en avant ;
  - « Formations par type » (demi-anneau : internes, externes, mixtes) ;
  - « À traiter » ;
  - formations par direction et service avec des barres ;
  - évolution par exercice ;
  - choix de l'exercice et filtres regroupés dans un bouton « Filtrer ».
- **Toutes les pages** :
  - menu blanc à rubriques en petites capitales, avec une pastille sur « Demandes » indiquant le nombre de demandes à décider (RH) ;
  - **recherche générale** dans la barre du haut (touche « / ») : agents, formations, demandes, organismes, formations diplômantes, selon les droits et le service de chacun ;
  - fil d'Ariane au-dessus du titre sur les fiches et les formulaires ;
  - cartes blanches arrondies, en-têtes de tableaux en petites capitales, étiquettes arrondies, nouvelle police Inter (embarquée, aucune ressource externe).
- Graphiques produits par le serveur en HTML/CSS/SVG : aucun script ajouté (politique CSP inchangée). Aucune migration.
- **157 tests réussis** (1963 assertions), dont la recherche (droits et cloisonnement) et les données mensuelles.

## [2.5.2] — 2026-10-28 — Nouvelle page de connexion

- Pages publiques (connexion, activation, mot de passe oublié, nouveau mot de passe) redessinées à la demande de
  l'INPS : fond bleu nuit aux couleurs de l'Institut avec une trame de points reliés, logo officiel (non modifié)
  sur un cartouche blanc, grand titre « Connectez-vous à votre espace formation », champs avec symbole (compte,
  cadenas, clé), bouton bleu de l'INPS. Sur grand écran, la **photographie officielle du siège** est présentée dans une
  bande oblique et un cercle ; sur téléphone, seul le formulaire est affiché.
- Aucun changement de fonctionnement ni de sécurité ; aucun script ajouté (politique CSP inchangée). Aucune migration.
- 155 tests réussis.

## [2.5.1] — 2026-10-28 — Correctif du plan annuel, vérification d'ensemble

- **Plan annuel** : « Plan validé le … par … » restait affiché après le retrait de toutes les formations du plan.
  Retirer la dernière formation efface maintenant la validation (journalisé : « validation effacée »), et la migration
  `2026_11_03_002400_plans_valides_vides` corrige les plans déjà dans ce cas. Un plan qui contient des formations n'est
  pas touché.
- **Vérification de l'ensemble du code** :
  - analyse statique (PHPStan + Larastan) : aucune erreur réelle de fonctionnement trouvée en dehors des deux points
    ci-dessous ; les autres signalements venaient de l'outil (types des champs convertis) ;
  - nouveau test de parcours : avec les données de démonstration, **toutes les pages** (avec et sans identifiant)
    s'ouvrent sans erreur serveur pour la RH, le responsable de structure et l'agent ;
  - dépendances PHP comparées à la base publique des failles connues, dépendances JavaScript (`npm audit`) :
    aucune faille connue pour les versions utilisées.
- **Sécurité** :
  - importation : un fichier HTML ou XML renommé en `.xlsx` n'est plus lu (seuls les vrais classeurs Excel ou
    OpenDocument sont acceptés), ce qui ferme une voie d'attaque connue de la bibliothèque des tableurs ;
  - revue des droits d'accès de chaque page, des téléchargements, des requêtes SQL et des redirections : rien d'autre à
    corriger.
- **Rangement** : commentaires des fichiers de configuration traduits en français ; fichier de fabrique de comptes
  inutilisé retiré ; le jeu de démonstration lit son mot de passe par la configuration (il fonctionne désormais
  après `php artisan optimize`).
- **155 tests réussis** (1929 assertions).

## [2.5.0] — 2026-10-28 — Formations diplômantes, « Responsable de structure », années simplifiées

Demandes de l'INPS du 28/10/2026.

- **Formations diplômantes** (nouveau menu, RH seulement, droit « diplomantes.gerer ») : licence, master, doctorat,
  BTS / DUT, certificat professionnel… suivis par un agent dans un établissement, **distincts des formations organisées
  par l'INPS**. Champs : agent, niveau, intitulé ou spécialité, établissement, ville, pays, année académique, durée,
  mode (présentiel, cours du soir, à distance), prise en charge (totale INPS, partielle INPS, à ses frais, bourse ou
  partenaire — **aucun montant**), situation (en cours, obtenu, ajourné, abandonné), date d'obtention, observations,
  pièces jointes. Liste filtrable avec compteurs par situation, export Excel, fiche, modification, suppression
  journalisée. Section sur la fiche de l'agent ; « Mes formations diplômantes » dans Mon espace. Référence DIP-AAAA-NNNN.
- **Le profil « Chef de service » s'appelle « Responsable de structure »** dans toute l'application (écrans, PDF,
  messages, documentation). Code technique `chef_service` et droits inchangés.
- **Référentiels › Années** : seulement l'année et une case « Demandes ouvertes » (les colonnes statut, date limite et
  date de clôture sont retirées de l'écran ; les données restent en base).
- **Correctif « mkdir(): Invalid path »** (page Sauvegardes) : une ligne `BACKUP_PATH=` vide dans le `.env` faisait
  chercher un dossier sans nom. Un réglage vide reprend maintenant la valeur par défaut (de même pour les autres
  réglages de sauvegarde et de sécurité).
- Migration `2026_11_02_002300_formations_diplomantes` (table, droit pour la RH, nouveau nom du profil).
  **152 tests réussis** (1917 assertions).

## [2.4.0] — 2026-10-28 — Accès des agents simplifié : codes d'activation

Demande de l'INPS du 28/10/2026 : la méthode des feuilles d'accès (PDF, mot de passe compliqué, remplacé à chaque clic)
était trop complexe.

- La RH clique sur **« Donner un code aux agents cochés »** : chaque agent reçoit un **code de 6 chiffres**, affiché à
  l'écran tant qu'il n'a pas servi. Recliquer ne le change pas. « Imprimer la liste des codes » (une ligne par agent).
- L'agent clique sur **« Activer mon compte »** (page de connexion), saisit son matricule et son code, choisit son mot
  de passe, et arrive dans « Mon espace ». S'il tente de se connecter avant, il est guidé vers l'activation.
- Sécurité : code à usage unique, valable 15 jours, annulé après 5 essais erronés, chiffré en base, absent du journal ;
  10 essais par minute et par poste ; message d'erreur neutre.
- « Nouveau code » remplace les « Nouveau mot de passe » ; la commande `inps:code-agent MATRICULE` remplace
  `inps:mot-de-passe-agent`. Les feuilles d'accès PDF sont retirées. Les comptes créés avec une feuille (2.3) restent
  valables.
- Migration `2026_11_01_002200_codes_activation_agents`. Tests des accès réécrits ; un test a révélé, avant livraison,
  que le compteur d'essais était annulé avec l'erreur : corrigé. **147 tests réussis.**

## [2.3.3] — 2026-10-27 — Nouveaux mots de passe : un seul par minute

- Constaté sur un poste de l'INPS : le mot de passe d'un agent avait été changé trois fois en trois secondes (clics répétés
  ou envois répétés du navigateur) ; seule la dernière feuille était valable, et ce n'était pas celle téléchargée.
  L'application **refuse désormais un second changement dans la minute** pour un même agent (message : « utilisez la feuille
  déjà téléchargée »), en plus du blocage du bouton (2.3.2).
- Nouvelle commande de dépannage : `php artisan inps:mot-de-passe-agent MATRICULE` affiche un nouveau mot de passe
  temporaire dans la console (journalisé).

## [2.3.2] — 2026-10-27 — Feuilles d'accès : un seul clic

- Après « Créer les accès » ou « Nouveau mot de passe », la page ne change pas (seul le PDF se télécharge) : un second clic
  produisait un nouveau mot de passe et rendait la première feuille caduque (constaté sur un poste de l'INPS).
  Le bouton est désormais bloqué 20 secondes avec « Téléchargement en cours… », la page le rappelle, et chaque feuille
  indique que seule la dernière éditée est valable. Vérifié : le mot de passe imprimé est bien celui enregistré.

## [2.3.1] — 2026-10-27 — Correctif : accès des agents sur un poste lent

- **Corrigé** : créer les accès de nombreux agents d'un coup pouvait dépasser 30 secondes (production du PDF) ; les comptes
  étaient alors créés **sans** que les feuilles d'accès soient produites. Désormais, comptes et PDF sont produits ensemble :
  si le PDF échoue, aucun compte n'est créé. La production des PDF dispose de 5 minutes au lieu de 30 secondes.
- Lots de **50 agents** au plus (la page « Accès des agents » affiche 50 agents ; « Tout cocher » en sélectionne autant).
- Nouveau bouton **« Nouveaux mots de passe »** : feuilles d'accès pour plusieurs agents qui ont déjà un accès (par exemple
  ceux dont la feuille n'a pas été imprimée) ; filtre « Accès jamais utilisé ».
- Installation : `php artisan optimize` recommandé après chaque mise à jour (pages nettement plus rapides sous Windows).

## [2.3.0] — 2026-10-27 — Accès des agents et convocations

- **Accès des agents** : nouveau profil « Agent ». Personnel › **Accès des agents** : la RH coche des agents (ou tout un
  service) et crée leurs accès ; un PDF donne une feuille par agent (identifiant = matricule, mot de passe temporaire,
  adresse de l'application), à changer à la première connexion. « Nouveau mot de passe » en cas d'oubli. Connexion par
  e-mail **ou matricule**. L'agent ne voit que « Mon espace » : formations à venir (avec sa convocation), formations
  suivies (avec ses attestations). Désactiver un agent désactive son accès. L'e-mail d'un compte devient facultatif.
- **Convocations** : « Convoquer les participants » (nouvelle prochaine étape) marque les participants convoqués, prévient
  chaque chef de service (liste de ses agents), les agents qui ont un accès, et envoie un e-mail avec la convocation jointe
  aux agents sans accès qui ont une adresse (si la messagerie est configurée). « Convocations (PDF) » : une page par
  participant (modèle proposé). « Convoquer les nouveaux » pour les participants ajoutés ensuite ; convocation
  **rectificative** proposée si les dates, horaires ou lieu changent après la convocation.
- Sécurité : connexion à durée constante (pas d'indice sur l'existence d'un identifiant) ; mots de passe temporaires jamais
  enregistrés en clair.
- Migration `2026_10_31_002100_acces_agents_et_convocations`. Nouveaux tests (`AccesConvocationTest`). **146 tests réussis.**

## [2.2.0] — 2026-10-27 — Évaluations simplifiées

Précision de l'INPS du 27/10/2026 : l'évaluation à chaud sert à la RH pour vérifier que les participants ont compris
la formation (ce n'est pas un avis des participants).

- **À chaud (RH, fin de formation)** : « Ont-ils compris la formation ? » — une liste des participants, pour chacun
  Oui / En partie / Non, note sur 20 et commentaire facultatifs, un seul enregistrement.
- **À froid (chef de service, 3 à 6 mois après)** : « Appliquent-ils ce qu'ils ont appris ? » — même modèle : une liste
  par formation de ses agents, Oui / En partie / Non et commentaire facultatif. Le menu Évaluations présente une ligne
  par formation (à faire, à venir, faites). Les niveaux 1 à 4 et la question « résultat atteint » sont retirés.
- **Retiré** : fiches d'avis des participants (PDF à imprimer et saisie). Les fiches déjà saisies restent en base.
- Fiche formation, rapport de réalisation (web et PDF) et aide à la décision reprennent les nouvelles réponses.
- Migration `2026_10_30_002000_evaluations_simplifiees` (colonne `evaluations_acquis.resultat`, niveau à froid facultatif,
  reprise des réponses existantes, libellés des droits). **144 tests réussis.**

## [2.1.0] — 2026-10-27 — Analyse du besoin revue, aide à la décision, suppression des référentiels

- **« D'où vient ce problème ? »** devient un diagnostic en trois questions : les agents savent-ils faire ce travail ?
  ont-ils les moyens ? ont-ils le temps ? L'application en tire aussitôt une conclusion (vert : une formation est la bonne
  réponse ; orange : elle ne suffira pas seule ; rouge : elle ne réglera probablement pas le problème).
- Trois questions de plus dans la demande : **gravité**, **mesure** (situation actuelle → objectif après la formation),
  **solutions déjà essayées**. La mesure est rappelée au chef de service lors de l'évaluation à 3–6 mois et figure dans le rapport.
- **Aide à la décision** sur la fiche d'une demande, pour la RH : conclusion du diagnostic, agents déjà formés à un sujet
  proche, résultat à 3–6 mois des formations semblables, demandes semblables d'autres services. Rien n'est décidé automatiquement.
- **Référentiels** : bouton « Supprimer » pour un élément utilisé nulle part ; sinon, ses utilisations sont affichées et il
  se désactive. Suppression journalisée.
- Les demandes déjà enregistrées gardent leur analyse (causes à cocher de la 1.8.0, ou texte libre).
- Migration `2026_10_29_001900_diagnostic_du_besoin` (7 colonnes facultatives sur les demandes). Nouveaux tests
  (`AideDecisionTest`, analyse du besoin revue). **144 tests réussis.**

## [2.0.0] — 2026-10-26 — Droits personnalisables par compte

Dernier ajout demandé lors de la présentation du 26/10/2026.

- Fiche d'un compte (Administration › Utilisateurs) : bloc **« Personnaliser les droits de ce compte »**. Le profil (RH ou
  Chef de service) reste le point de départ ; on coche ou décoche ensuite chaque droit, présenté en clair et classé par module.
  Seuls les écarts avec le profil sont enregistrés (nouvelle table `permission_user`) ; décocher « Donner à ce compte des droits
  différents » rend exactement les droits du profil.
- Garde-fous : on ne donne pas un droit qu'on n'a pas soi-même ; les droits d'administration exigent « Attribuer le profil RH
  et consulter les droits » ; on ne modifie pas ses propres droits ; le dernier compte capable de gérer les comptes est protégé.
  Chaque changement est inscrit au journal d'audit.
- Les droits effectifs (profil + personnalisation) s'appliquent partout : menus, pages, actions et destinataires des notifications.
- Liste des comptes : mention « (droits personnalisés) » ; Profils et droits : tableau des comptes personnalisés
  (droits ajoutés, droits retirés).
- Migration `2026_10_28_001800_droits_personnalises`. Nouveaux tests (`DroitsPersonnalisesTest`, 4 tests). **141 tests réussis.**

## [1.9.0] — 2026-10-26 — Après la formation : évaluations, rapport, attestation du prestataire

Suite des demandes de la présentation du 26/10/2026. Nouveau bloc « Après la formation » sur la fiche d'une formation terminée.

- **Évaluation à chaud** (RH) : fiches papier à imprimer (une par participant), puis saisie des fiches rendues
  (5 points notés de 1 à 5, deux commentaires) ; moyennes sur la fiche formation et dans le rapport.
- **Évaluation de la mise en pratique, 3 à 6 mois après** (chef de service) : nouveau menu **Évaluations** (à faire,
  à venir, faites) ; niveau 1 à 4 et « résultat attendu atteint » (le résultat attendu de la demande est rappelé) ;
  délai choisi par la RH pour chaque formation (6 mois par défaut) ; notification automatique au chef de service à la date
  (à la RH si le service n'a pas de chef de service). Le chef ne voit et n'évalue que les agents de son service.
- **Rapport de réalisation** (RH) : trois zones à rédiger (déroulement, difficultés, recommandations) ; PDF complété
  automatiquement (formation, besoin, participation par service, évaluations).
- **Attestation d'intervention** pour chaque formateur externe (ATI-AAAA-NNNN, PDF ; modèle proposé, à valider par l'INPS).
- Prochaine étape de la fiche formation : après les attestations, « évaluations à chaud et rapport de réalisation ».
- Migration `2026_10_27_001700_evaluations_rapport_attestations_intervenants` : colonnes sur `sessions_formation`
  (délai, rapport), `evaluations_impact.resultat_atteint`, table `attestations_intervenants`, droits `evaluations.gerer`
  (RH) et `evaluations.froid` (chef de service).
- Nouveaux tests (`ApresFormationTest`, 5 tests). **137 tests réussis.**

## [1.8.0] — 2026-10-26 — Analyse du besoin et formateurs multiples

Premiers ajouts demandés lors de la présentation du 26/10/2026.

- **Demande de formation — analyse du besoin.** « Pourquoi cette formation ? » devient quatre questions obligatoires :
  le problème constaté dans le service, son origine (cases à cocher), pourquoi une formation le réglerait, et ce que les
  agents devront savoir faire après la formation. Si aucune origine cochée n'est un manque de compétences, un avertissement
  s'affiche dans le formulaire et, pour la RH, sur la fiche de la demande ; la fenêtre « Refuser » invite à proposer une
  autre action. Les demandes antérieures gardent leur texte, affiché sous « Pourquoi cette formation ? ».
- **Plusieurs formateurs par formation, internes et externes.** Nouveau type « Mixte (interne et externe) ». Les formateurs
  se cochent dans le formulaire de la formation et restent modifiables après la formation ; chaque changement est journalisé.
  Fiche formation, liste d'émargement, tableau de bord, rapports, plan annuel et import de l'historique connaissent le type « Mixte ».
- Migrations : `2026_10_26_001500_analyse_du_besoin` (3 colonnes facultatives sur les demandes) et
  `2026_10_26_001600_formateurs_multiples` (table `session_formateur` ; le formateur unique de chaque formation y est recopié).
- Nouveaux tests : analyse du besoin, formation mixte, reprise de l'ancien formateur. **132 tests réussis.**

## [1.7.4] — 2026-10-25 — Choix de la direction puis du service

- Fiche **compte utilisateur** et fiche **agent** : le choix du service se fait en deux listes, **Direction** puis **Service**.
  Une fois la direction choisie, seuls ses services sont proposés ; choisir directement un service remplit sa direction.
  Auparavant, une seule liste de plus de 200 services (groupés par direction) s'ouvrait souvent sur un seul groupe
  (par exemple DSI), ce qui donnait l'impression que les autres services manquaient.
- Sans JavaScript, la liste des services reste complète. Aucune règle, permission, route ni table modifiée ; aucune migration.
- **Corrigé** (Paramètres › Référentiels) : sur les onglets Directions, Services et Domaines, la case **Actif** (et la
  description d'un domaine) n'était pas envoyée avec le bouton « Enregistrer » de sa ligne. Enregistrer une ligne
  **désactivait** donc la direction, le service ou le domaine, et effaçait la description du domaine. Après la mise à jour,
  recochez « Actif » sur les lignes désactivées par erreur, puis cliquez sur « Enregistrer ».
- Le choix direction → service propose aussi la direction d'un service actif même si cette direction a été désactivée.
- Nouveaux tests : tous les services et directions sont proposés ; chaque champ d'une ligne de référentiel part avec
  son bouton ; enregistrer une direction la garde active. **129 tests réussis.**

## [1.7.3] — 2026-10-25 — Revue d'ensemble : corrections et renforcements

Revue complète du projet (erreurs, propreté du code, sécurité) ; détail des contrôles dans `docs/SECURITE.md` § « Revue du 25/10/2026 ».

- **Corrigé** : le choix de l'**année** sur la page Plan annuel ne rechargeait pas la page (script en ligne bloqué par la
  politique de sécurité du navigateur). Remplacé par un comportement du fichier JavaScript de l'application, et un bouton « Afficher ».
- **Sécurité** : la redirection après lecture d'une notification n'accepte plus qu'une adresse de l'application elle-même
  (même schéma, hôte et port) ; une adresse du type `http://serveur:8000.autre-site/…` était acceptée auparavant.
- **Sécurité** : la confirmation d'un import de la structure exige les mêmes droits que son analyse (référentiels).
- Plan annuel : années limitées à 2000–2100 dans les adresses de validation et d'export ; suppression : type inconnu refusé explicitement.
- Nouveau test `ParcoursPagesTest` : chaque page s'ouvre sans erreur serveur pour chacun des 7 rôles ; tests de la redirection
  piégée, de l'import réservé et des années. **126 tests réussis (1361 assertions).** Aucune migration.

## [1.7.2] — 2026-10-25 — Statut des demandes selon l'avancement de la formation

- Une demande dont la formation est terminée affichait toujours « Formation programmée ». Le statut affiché suit désormais
  la formation : **Formation programmée** (bleu) → **Formation terminée** (vert) ou **Formation annulée** (rouge), dans la liste
  des demandes, sur la fiche (encadré « Demande satisfaite — formation terminée ») et dans le filtre « Statut ».
  Le statut enregistré de la demande ne change pas (aucune migration).
- Formations dont les dates sont passées sans « Formation terminée » : étiquette **« À terminer »** dans la liste et sur la
  fiche, et filtre « À terminer (dates passées) ».
- Rapport « Suivi des demandes » : la colonne « Décision » indique « Acceptée » ; l'avancement est dans « Statut de la formation ».
- Test ajouté. **125 tests réussis.**

## [1.7.1] — 2026-10-25 — Correctif : erreur en modifiant les référentiels

- **Corrigé** : « Aucune morph map définie pour le modèle [App\Models\Direction] » (erreur 500) en créant ou modifiant une direction,
  un service, un domaine, une année ou une liste de référence, et en créant un formateur. La modification était enregistrée,
  mais l'écriture dans le journal d'audit échouait. Tous les modèles ont désormais un type stable pour le journal, et un type
  manquant ne peut plus faire échouer une action.
- Nouveau `ReferentielTest` (3 tests : chaque modèle, chaque type de référentiel en création et modification, journal robuste).
  **124 tests réussis.** Aucune migration.

## [1.7.0] — 2026-10-23 — Plan annuel simple

Décision de l'INPS (23/10/2026) : remettre un plan annuel, sans la complexité retirée en 1.3.0 (versions, circuit de validation).

- **Menu « Plan annuel »** : les formations prévues pour l'année, regroupées par trimestre (période : un mois, un trimestre
  ou « dans l'année »), avec service, nombre d'agents prévus, type et organisme envisagé. Indicateurs : prévues, programmées
  ou terminées (avec le pourcentage du plan), encore à programmer, agents prévus. Export PDF et Excel.
- La RH **ajoute**, **modifie** ou **retire** une formation prévue ; **« Programmer »** fixe les dates (lieu, organisme) et
  crée la formation, rattachée à sa ligne du plan. État de chaque ligne : Prévue, Programmée, Terminée ou Annulée
  (une ligne annulée peut être programmée à nouveau).
- **Validation simple** : « Valider le plan » enregistre la date, l'auteur et un commentaire ; le plan peut encore être
  complété (les ajouts ultérieurs sont signalés « Ajoutée après validation »).
- **Demandes** : la RH choisit entre « Accepter et programmer » (dates tout de suite) et **« Accepter et inscrire au plan »**
  (année et période prévues). Nouveau statut « Inscrite au plan » ; le chef de service est notifié et voit la période prévue.
  Retirer une demande du plan exige un motif et la remet « Envoyée ».
- Le chef de service consulte le plan, limité aux lignes de son service (et de ses demandes), sans bouton de gestion.
- Supprimer une formation issue du plan remet sa ligne « Prévue » (la demande reste au plan) ; la restaurer la rattache à nouveau.
- Données de démonstration : un plan de l'année en cours (trois formations prévues, dont une programmée).
- Données : migration `2026_10_23_001400_plan_annuel_simple` (tables `plans_annuels`, `plan_annuel_lignes`,
  colonne `actions_formation.ligne_plan_id`). Les tables de l'ancien plan (avant 1.3.0) restent en base, sans usage.
- Tests : nouveau `PlanAnnuelTest` (6 tests). **121 tests réussis (1063 assertions).**

## [1.6.1] — 2026-10-22 — Structure de l'INPS et cloisonnement du personnel

- **Import de la structure** (Personnel › Importations › « Structure de l'INPS (directions et services) ») : une ligne par
  service avec sa direction (`direction_code`, `direction_nom`, `service_code`, `service_nom`) ; aperçu avant écriture ;
  directions et services créés s'ils n'existent pas, jamais renommés ni supprimés ; réservé aux gestionnaires des référentiels.
  Le fichier « structure organisée » préparé à partir de la nomenclature de la DRH (30 directions, 208 services et divisions)
  est remis à part : il n'est pas inclus dans l'archive de l'application.
- **Direction dans les comptes** : la liste « Direction et service » présente les services regroupés par direction
  (même présentation dans les fiches agent, les demandes et les filtres) ; la direction actuelle du compte est affichée.
- **Chef de service** : la liste du personnel n'affiche que son service (filtres direction / service masqués et ignorés).
  Vérifié sur toutes ses pages : aucun agent ni service d'un autre service n'apparaît.
- Tests : import de la structure, cloisonnement du personnel. **115 tests réussis (976 assertions).** Aucune migration.

## [1.6.0] — 2026-10-22 — Profils fixes, journal en clair, sauvegardes, suppression et corbeille

### Correction : agents de la demande non inscrits
- Un agent coché dans la demande mais déjà inscrit à une autre formation aux mêmes dates (ou devenu inactif) n'était pas inscrit,
  **sans aucun message** (3 agents demandés, 2 inscrits). Désormais : avertissement immédiat après « Accepter et programmer »,
  et encadré permanent sur la fiche de la formation listant les agents de la demande non inscrits, avec la raison (formation en conflit)
  et quoi faire.

### Profils et droits
- Deux profils fixes : **RH** et **Chef de service**. À la création d'un compte, on choisit le profil (et le service pour un chef) ;
  la direction est déduite du service ; plus de case par rôle ni de fiche agent associée.
- « Rôles et permissions » devient **« Profils et droits »**, en lecture seule : tableau « Qui peut faire quoi ». La création,
  la modification et la suppression de rôles sont retirées. Libellés des droits en langage courant (codes inchangés).
  Les comptes antérieurs avec d'autres rôles gardent leurs droits (« Garder les droits actuels ») jusqu'à alignement.

### Journal d'audit en clair
- Colonnes Date, Qui, Ce qui s'est passé (libellé français + description) et lien « Voir » ; filtres type, personne, période, texte ;
  code technique, IP et contexte dans « Détails » replié. Export Excel avec le libellé lisible. Protection du journal inchangée.

### Sauvegardes
- Écran « Sauvegardes » : « Faire une sauvegarde », voyant « Tout va bien / À vérifier » avec explications, liste avec
  **téléchargement** (contrôlé et journalisé), informations techniques repliées ; message d'aide si `mysqldump` est introuvable.

### Suppression des erreurs et corbeille
- « Supprimer » sur une demande, une formation, un agent ou un compte, seulement pour une erreur de saisie (conditions dans
  `docs/GUIDE_ADMINISTRATEUR.md` § 8 b) ; sinon la raison est donnée (annuler, désactiver).
- Cases à cocher et « Supprimer la sélection » dans les listes Demandes, Formations et Personnel.
- **Corbeille** (RH) : demandes et formations supprimées, restaurables. Une formation supprimée libère ses participants et remet
  sa demande à « Envoyée » ; restaurée, elle réinscrit ses participants et retrouve sa demande si possible.
- Formations à la corbeille exclues des rapports, du tableau de bord et des historiques.

### Données
- Migrations `2026_10_22_001200_profils_fixes_et_libelles` (libellés des permissions) et `2026_10_22_001300_corbeille_des_formations`
  (`actions_formation.deleted_at`, `besoin_id_origine`). Aucune donnée supprimée.

### Tests
- Nouveau `SuppressionCorbeilleTest` (7 tests) ; tests des profils, du journal, de l'écran Sauvegardes, des agents non inscrits.
  **113 tests réussis (954 assertions).**

## [1.5.1] — 2026-10-22 — Participants par service

- **Chef de service** : la liste « Formations » ne montre que les formations demandées par son service ou suivies par au moins
  un de ses agents ; sur la fiche, il ne voit que les participants de son service (« Participants de votre service »).
  Une autre formation, sa liste PDF et ses documents lui sont refusés. La RH, l'autorité de validation et l'auditeur voient tout.
- **Fiche de la formation** : participants regroupés par service (nombre par service) ; quand les agents de la demande sont déjà
  inscrits, le bloc d'ajout devient « Ajouter d'autres participants (facultatif) », replié par défaut ; agents disponibles
  regroupés par service. La liste des participants (PDF) est triée par service.
- **Demande** (RH, tous services) : agents à cocher regroupés par service ; la recherche masque les services sans résultat.
- Aucune migration. Nouveau test du cloisonnement par service. **103 tests réussis (818 assertions).**

## [1.5.0] — 2026-10-22 — Formation en trois étapes ; accepter = programmer

Décision de l'INPS : l'organisation d'une formation comportait trop d'étapes ; le nombre de participants saisi pouvait contredire
les agents cochés ; le bouton « Organiser la formation » était une étape de trop.

### Demandes
- Le chef de service **coche les agents à former** (au moins un) ; le champ « nombre de participants » est retiré :
  le nombre est celui des agents cochés. Les agents hors du périmètre du chef de service ou inactifs sont écartés.
- **Accepter = programmer** : « Accepter et programmer » demande à la RH la date de début, la date de fin, le lieu, l'organisme
  (sinon formation interne) et un commentaire facultatif ; la demande est acceptée, la formation est créée (intitulé et raison
  de la demande) et les agents de la demande y sont inscrits, en une seule opération. La RH arrive sur la fiche de la formation.
  Le bouton « Organiser la formation » et l'acceptation sans dates sont retirés. Statut final : « Formation programmée ».
  Une demande acceptée avant cette version propose « Programmer la formation » (même fenêtre).
- Une seule notification au chef de service : « Demande acceptée : formation programmée ».

### Formation en trois étapes
- **La formation** → **Participants** → **Attestations**, avec la barre d'étapes et l'encadré « Prochaine étape ».
- Participants : ajout par agents cochés ou par service, sans limite de places ni liste d'attente ; refus signalés (agent inactif,
  déjà inscrit, dates qui se chevauchent) ; « Retirer » un participant (historique conservé).
- Après la formation : colonne **« A suivi ? »** (cochée par défaut), puis **« Formation terminée »** ; correction possible
  ensuite (« Enregistrer la correction »), sauf pour un participant dont l'attestation est émise (l'annuler d'abord).
- Attestations : émises pour les participants qui ont suivi une formation terminée (individuellement ou toutes ensemble).
- Formulaire de formation réduit (intitulé, dates, lieu, interne/externe, organisme) ; le reste dans « Plus de détails (facultatif) ».
- Retirés : convocations (et leur PDF), confirmation, démarrage, report, présences jour par jour et leur validation, seuil
  et poids de présence, liste d'attente, désistement/remplacement, évaluations (satisfaction, acquis, impact), bilan pédagogique.
  Statuts d'une formation : Programmée → Terminée, ou Annulée.

### Autres écrans
- Tableau de bord : formations programmées / terminées ; « Participants ayant suivi la formation » ; alertes sans convocations.
- Rapports : 10 (rapport des évaluations retiré) ; « Suivi des formations par les participants » remplace « Présence et achèvement ».
- Fiche agent, historique PDF, attestation PDF et « Mon espace » : « A suivi la formation » (Oui/Non) au lieu du taux de présence.
- Rappels : plus de rappel de convocation ni d'évaluation ; rappel « formation passée à marquer terminée ».

### Permissions et paramètres
- Permissions `presences.*` et `evaluations.*` retirées (27 permissions, 7 rôles) ; `inscriptions.gerer` devient
  « Gérer les participants des formations ». Le chef de service : demandes et agents de son service, consultation des formations.
- Paramètres `regles.*` (seuil, poids, conditions d'attestation) et délais de convocation / d'évaluation d'impact retirés.

### Données
- Migration `2026_10_22_001100_formation_en_trois_etapes` (non destructive) : formations « confirmées », « en cours » ou
  « reportées » → « programmées » ; permissions et paramètres retirés ; résumé dans le journal d'audit. Présences, évaluations,
  convocations et séances restent en base, sans usage. Les résultats déjà calculés (achevée / non achevée) valent « a suivi » / « n'a pas suivi ».

### Tests
- `SessionParticipationTest` et `BesoinFormationTest` réécrits pour le nouveau parcours ; `OrganisationDeuxProfilsTest`,
  `RapportNotificationTest`, `AutorisationTest`, `AuditSecuriteTest` adaptés ; test de la migration 1.5.0.
  **102 tests réussis (803 assertions).**

## [1.4.0] — 2026-10-22 — Demandes simplifiées, sans budget ni finances

Décision de l'INPS : le côté « demande de formation » restait trop complexe, et le budget ne doit pas apparaître dans l'application.

### Demandes de formation
- Formulaire minimal : **formation souhaitée**, **pourquoi cette formation ?**, **agents concernés** (cases à cocher avec recherche)
  ou **nombre de participants**, **période souhaitée**. Plus de coût, priorité, type, domaine, durée, objectifs, publics ni année à choisir :
  le service est celui du compte (la RH le choisit) et l'année est automatique (année en cours, créée ouverte si besoin ; année
  suivante ouverte si l'année en cours est clôturée).
- **Envoi direct** à la RH (plus de brouillon). Étapes : **Envoyée → Acceptée / Refusée / À compléter** ; l'étape « En examen »
  et ses paramètres d'activation sont retirés. La RH décide depuis l'encadré « Prochaine étape » (Accepter, Demander des précisions,
  Refuser) ; le chef de service voit le message de la RH et « Compléter la demande » renvoie la demande.
- Le service demandeur peut joindre un justificatif tant que la RH n'a pas décidé.
- Menu « Demandes » ; libellés « Envoyée », « Refusée », « Demande de précisions ».

### Budget et finances retirés
- Menu Finances, saisie et export des dépenses, coût sur les demandes et les formations, encadré « Coûts » et liste des dépenses
  de la fiche formation, budget du tableau de bord, colonne « Dépensé », rapports « Budget » et « Coût moyen » (11 rapports),
  colonne « Montant payé » du rapport des prestataires, montant des conventions, listes « catégories de dépenses », « priorités »,
  « types de formation » et « axes ».
- Permissions `finances.*` et rôle « Gestionnaire financier » retirés (32 permissions, 7 rôles).

### Données
- Migration `2026_10_22_001000_demandes_simplifiees_sans_finances` (non destructive) : demandes « en examen » → « envoyées » ;
  paramètres, permissions et rôle retirés ; table `depenses`, coûts et montants conservés en base sans usage ; résumé dans le journal d'audit.

### Tests
- `FinanceDocumentTest` devient `DocumentTest` (tests des documents conservés) ; `BesoinFormationTest`, `OrganisationDeuxProfilsTest`
  et les autres fichiers adaptés ; nouveaux tests du formulaire minimal, de l'année automatique, de l'absence de montants et de la migration.
  **109 tests réussis (756 assertions).**

## [1.3.1] — 2026-10-22 — Correctif d'importation

- Import de l'historique depuis un fichier Excel (.xlsx) : une date saisie comme vraie date dans Excel (cellule au format date, stockée
  comme un nombre) était refusée (« Date invalide »). Le format des cellules est désormais lu et ces dates sont reconnues ;
  les dates saisies en texte (JJ/MM/AAAA ou AAAA-MM-JJ) et les fichiers CSV fonctionnent comme avant.
- Nouveau test `test_import_historique_excel_avec_de_vraies_cellules_date`. **111 tests réussis (749 assertions).**
- Aucune migration.

## [1.3.0] — 2026-10-21 — Parcours simplifié : demande → formation

Décision de l'INPS : l'enchaînement besoin → plan annuel → action → session était trop complexe.

### Parcours
- **Plan annuel retiré** : écrans, exports, révisions, circuits de validation (menu « Circuits de validation »), permissions `plans.*`
  et paramètre `plans.interdire_auto_validation`.
- **Formation = action + une session**, saisies dans un seul formulaire (contenu, organisme, coût, dates, horaires, lieu, places,
  formateur) et affichées sur une seule fiche (participants, présences jour par jour, évaluations, attestations, dépenses, documents,
  historique). Menu « Formations » à la place de « Plans annuels », « Actions » et « Sessions ». Un second groupe = une autre formation
  (bouton « Nouvelle formation similaire »).
- Le statut de la formation suit automatiquement son déroulement (démarrée → en cours ; clôturée → réalisée ; annulée ; reportée).
  Les boutons « Déclarer réalisée / Reporter / Annuler l'action » sont supprimés. L'année est celle de la date de début.
- **Demande acceptée → « Organiser la formation »** : le formulaire reprend l'intitulé, le domaine, les objectifs, le public,
  le nombre de participants et le coût de la demande ; la demande passe au statut **« Formation organisée »** (décision tracée,
  auteur notifié) ; une seule formation par demande. Les agents nommés dans la demande s'inscrivent en un clic.
- **Guidage** : barre d'étapes et encadré « Prochaine étape » (avec le bouton correspondant) sur les fiches demande et formation,
  adaptés au profil (la RH voit l'action à faire ; le chef de service voit où en est sa demande).
- Tableau de bord : « Demandes à organiser » remplace « Réalisation du plan » ; budget « Prévu dans les formations ».
- Rapports : « Suivi des demandes de formation » remplace « Taux de réalisation du plan » ; libellés « formation » au lieu d'« action » / « session ».
- Les anciennes adresses `/sessions/{id}` redirigent vers la fiche de la formation.

### Données
- Migration `2026_10_21_000900_formations_sans_plan_annuel` (non destructive) : colonne `actions_formation.besoin_id` ;
  rattachement des formations aux demandes de leur ligne de plan ; statuts « intégrée au plan » convertis ; actions à plusieurs
  sessions séparées en formations (`-G2`…) ; statuts alignés ; tables du plan conservées sans usage ; résumé dans le journal d'audit.
- Correction trouvée par les tests : l'import de l'historique renseigne l'année hors affectation de masse.

### Tests
- `BesoinPlanTest` remplacé par `BesoinFormationTest` (8 tests) ; `SessionParticipationTest` (18) et `OrganisationDeuxProfilsTest` (6)
  adaptés ; autres fichiers ajustés au nouveau formulaire. **110 tests réussis (745 assertions).**
- Parcours complet vérifié dans un navigateur sur la démonstration ; migration rejouée sur des copies des bases 1.1.0 et 1.2.0.

### Documentation
- Guides utilisateur et administrateur, architecture, base de données, décisions, recette, tests, installation Windows mis à jour.
- La formation technique (`docs/formation/`) décrit la version 1.1.0 : un encadré en tête liste les écarts.

## [1.2.0] — 2026-10-21 — Organisation à deux profils

Décision de l'INPS : la RH gère tout ; le chef de service déclare les besoins de son service ; les agents n'ont pas de compte.

- Rôle « Responsable DRH / formation » : ajout de la validation du plan (`plans.valider`), de la saisie et de l'export des dépenses
  (`finances.gerer`, `finances.exporter`) et du paramétrage (`parametres.gerer`).
- Circuit de validation du plan : une étape « Validation par la Direction des ressources humaines », active ; paramètre
  `plans.interdire_auto_validation` désactivé (la RH prépare puis valide le plan).
- `php artisan inps:creer-admin --drh` : crée le compte RH (Responsable DRH + Administrateur technique).
- Migration `2026_10_21_000800_organisation_deux_profils` : applique cette organisation à une base existante (aucun compte créé ni supprimé ;
  l'ancien circuit est conservé, désactivé ; rien n'est changé si un plan est en cours de validation).
- Démonstration (`DemoSeeder`) : deux comptes seulement, `demo.responsable_drh` (RH + administration) et `demo.chef_service`.
- Tests : nouveau fichier `OrganisationDeuxProfilsTest` (6 tests) ; trois assertions adaptées. 108 tests réussis (708 assertions).


## [1.1.0] — 2026-10-20 — Refonte visuelle

Aucune règle métier, aucun calcul, aucune permission ni aucun parcours fonctionnel n'a été modifié.

### Interface
- Nouvelle identité visuelle : bleu institutionnel #003B73 / #0056A3, blancs et gris neutres, couleurs d'état discrètes (succès, alerte, erreur, information).
- Police Public Sans embarquée localement (licence SIL OFL, paquet `@fontsource-variable/public-sans`) : aucune ressource en ligne.
- Pictogrammes vectoriels intégrés (composant `<x-icone>`, tracés dérivés de Lucide, licence ISC).
- Page de connexion en deux zones : zone institutionnelle (photographie officielle du siège si elle est fournie, sinon aplat bleu et trame architecturale abstraite) et formulaire « Accédez à votre espace de formation » ; bouton afficher/masquer le mot de passe ; erreurs reliées aux champs ; mention de confidentialité.
- Pages « Mot de passe oublié », « Réinitialisation » et « Changer mon mot de passe » harmonisées.
- Menu latéral avec icônes, rubriques, état actif, réduction mémorisée (bureau) et tiroir mobile ; barre haute avec fil d'Ariane, notifications non lues et menu du compte (clavier et touche Échap).
- Tableau de bord réorganisé : bande de synthèse unique, liste « À traiter », participation, budget, répartition par direction, évolution par exercice — uniquement des indicateurs déjà calculés.
- Formulaires, tableaux, badges, messages, boîtes de dialogue et pagination harmonisés ; focus visible, contrastes AA, cibles tactiles ≥ 40 px, animations désactivées si « réduire les animations » est demandé, styles d'impression.

### Identité institutionnelle
- Emplacements prévus pour le logo officiel et la photographie officielle du siège : `public/images/institution/` (logo.svg|png, siege.jpg) ou dépôt dans Administration → Paramètres. Aucun logo ni aucune photographie n'est fourni.
- Nouveau paramètre `institution.photo_connexion` (JPG/PNG/WebP, ≥ 1200 × 700 px, 6 Mo max.).
- Couleur principale par défaut : #003B73 (migration `2026_10_20_000700_identite_visuelle_inps` : remplace l'ancienne valeur provisoire uniquement si elle n'avait pas été modifiée).
- Nouvelle route publique en lecture seule `GET /identite/{logo|photo}` (types de fichiers contrôlés) ; la route `admin.parametres.logo` est supprimée : elle exigeait la permission « Gérer les paramètres » pour afficher le logo dans le menu de tous les utilisateurs.

### Ressources fournies par l'INPS (20/10/2026)
- Logo (`public/images/institution/logo.png`, marges blanches recadrées, logo non modifié) : menu, page de connexion, en-têtes des PDF.
- Photographie du siège (`public/images/institution/siege.jpg`, 602 × 509 px) : page de connexion, voile bleu pour la lisibilité du texte.

### Documentation (21/10/2026)
- Formation technique complète pour les développeurs : `docs/formation/FORMATION_TECHNIQUE.md` et sa version PDF (architecture, code de A à Z, base de données, modules, installation Windows, maintenance), extraits de code insérés depuis les sources.
- `docs/INSTALLATION_WINDOWS.md` : retrait de la mention d'un script `scripts\installer-windows.ps1` qui n'existe pas.

### Tests
- Nouveau fichier `tests/Feature/InterfaceIdentiteTest.php` (7 tests, indépendants des fichiers présents dans `public/images/institution`) ; deux assertions d'interface adaptées.
- 102 tests, 668 assertions, tous réussis.


## [1.0.0] — 2026-10-20 — Version initiale présentée pour recette

### Fonctionnalités
- Tableau de bord institutionnel (indicateurs calculés en base, filtres année/direction/service/domaine/type, alertes, comparatif annuel).
- Référentiel du personnel : fiche, recherche, historique, désactivation logique, export Excel, import CSV/Excel contrôlé (agents et historique).
- Recensement des besoins : cycle brouillon → soumise → en examen → à compléter → acceptée / rejetée → intégrée au plan, décisions tracées, justificatifs.
- Plan annuel : versions, intégration des besoins, contrôle des doublons, regroupements, budget, circuit de validation configurable multi-étapes, verrouillage, révision contrôlée, prévisionnel/réalisé, exports PDF/Excel/impression.
- Actions et sessions : fiches complètes, séances journalières, reports et annulations historisés.
- Organismes, contacts, conventions (accès restreint), formateurs internes/externes, appréciations des prestataires.
- Inscriptions : individuelles et collectives, capacité, liste d'attente, chevauchements, convocations (notification + PDF), confirmation, désistement, remplacement, historique.
- Présences par séance, pondération et seuil paramétrables, validation du relevé, corrections historisées, liste d'émargement PDF.
- Évaluations de satisfaction, des acquis (restreintes), d'impact différé ; bilan pédagogique ; attestations conditionnées (PDF, référence, code de vérification, annulation).
- Suivi financier des coûts (engagé, payé, écart, coût par agent formé), corrections journalisées, export.
- Documents sécurisés (stockage privé, contrôle du type réel, noms aléatoires, confidentialité).
- 13 rapports statistiques avec règles de calcul, exports PDF/Excel, impression.
- Notifications internes et rappels d'échéances quotidiens ; e-mail optionnel avec traçabilité réelle des envois.
- Paramétrage institutionnel, référentiels, rôles et permissions, circuits de validation.
- Journal d'audit en ajout seul (protégé par déclencheurs MySQL), consultation et export.
- Sauvegarde chiffrée / restauration vérifiée, contrôle de santé, création sécurisée du premier administrateur.

### Technique
- Laravel 13.37, PHP 8.3+ (compatible 8.4), MySQL 8.0, Tailwind CSS 4, Vite 8, dompdf 3.1, PhpSpreadsheet 5.10, PHPUnit 12.5.
- 95 tests automatisés (622 assertions) réussis.
- Configuration Docker de référence (non testée dans l'environnement de construction).

### À valider
- Règles institutionnelles listées dans `docs/DECISIONS_A_VALIDER.md`.
