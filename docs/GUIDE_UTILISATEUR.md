# Guide utilisateur

L'application s'utilise avec un navigateur récent (Edge, Chrome, Firefox) sur ordinateur, tablette ou téléphone.
Le menu latéral n'affiche que les fonctions autorisées pour votre profil. Sur petit écran, le bouton « Menu » l'ouvre.

**Le parcours (depuis la version 1.5.0)** :

1. le responsable de structure fait une **demande** en cochant les agents à former ;
2. la RH **accepte et programme** : elle fixe les dates (et, si besoin, le lieu et l'organisme) ; la formation est créée
   et les agents de la demande y sont inscrits automatiquement ;
3. sur la fiche **Formation**, trois étapes : **la formation** → **les participants** (après la formation, cocher ceux qui l'ont
   suivie, puis « Formation terminée ») → **les attestations**.

Il n'y a plus de plan annuel, de convocations, de présences jour par jour, de liste d'attente ni d'évaluations.
Sur chaque fiche, une barre d'étapes montre où l'on en est et un encadré **« Prochaine étape »** indique quoi faire maintenant,
avec le bouton correspondant.

## Connexion et compte

- Identifiant : adresse e-mail professionnelle. Après 5 tentatives erronées en une minute, la connexion est temporairement bloquée.
- Session fermée automatiquement après 30 minutes d'inactivité (valeur paramétrable) et à la fermeture du navigateur.
- « Mot de passe » (en haut à droite) : changement du mot de passe (12 caractères minimum, majuscule, minuscule, chiffre, caractère spécial).
- Mot de passe temporaire remis par l'administrateur : il doit être changé dès la première connexion.
- « Mot de passe oublié » : lien par e-mail si un serveur de messagerie est configuré ; sinon, s'adresser à l'administrateur.

## Page d'accueil selon le profil

| Profil | Page d'accueil |
|---|---|
| Responsable DRH, autorité de validation, auditeur, administrateurs | Tableau de bord |
| Responsable de structure | Demandes de son service |
| Agent | Mon espace (ses formations et attestations) |

## 0. Recherche générale (2.6.0)

La zone « Rechercher » en haut de chaque page (raccourci : touche « / ») trouve un agent (nom, matricule), une
formation, une demande (intitulé ou référence), un organisme ou une formation diplômante. Chacun ne voit que ce que
ses droits lui permettent : un responsable de structure, par exemple, ne trouve que les agents de son service.

## 1. Tableau de bord

Filtres : année, direction, service, domaine, type (interne/externe). Les chiffres sont calculés en direct sur la base ; une case vide ou « — » signifie qu'aucune donnée ne permet le calcul.

### Définitions des indicateurs

| Indicateur | Définition |
|---|---|
| Formations programmées / terminées | Nombre de formations de l'année (année de la date de début) selon leur statut ; les annulées sont indiquées à part |
| Agents formés | Nombre d'**agents distincts** ayant suivi au moins une formation (un agent formé deux fois compte pour un) |
| Participants ayant suivi la formation | Participants cochés « a suivi la formation » ÷ participants des formations terminées |
| Demandes reçues | Demandes envoyées à la RH, en attente de décision |
| Demandes à organiser | Demandes acceptées avant la version 1.5.0 sans formation programmée (depuis 1.5.0, accepter programme la formation) |
| À traiter | Formations des prochains jours, formations passées pas encore marquées terminées, demandes à compléter, participants sans attestation |

Les filtres direction/service portent sur le rattachement des **agents participants**.

## 2. Demandes de formation (responsable de structure, RH)

1. Menu **Demandes** → « Nouvelle demande » : **formation souhaitée** (intitulé), **période souhaitée** (facultative),
   **analyse du besoin** et **agents à former**. L'analyse du besoin (version 2.1.0) est obligatoire :
   - **quel problème constatez-vous dans le service ?**
   - **d'où vient ce problème ?** trois questions : les agents savent-ils faire ce travail (non / en partie / oui) ? ont-ils
     les moyens (matériel, logiciel, procédure) ? ont-ils le temps ? L'application affiche aussitôt sa conclusion :
     vert « une formation est la bonne réponse » (savoir-faire manquant, moyens et temps disponibles), orange « la formation
     seule ne suffira pas » (il manque aussi des moyens ou du temps), rouge « une formation ne réglera probablement pas ce
     problème » (les agents savent déjà faire). La demande peut toujours être envoyée ;
   - **gravité** : gêne légère / retards ou erreurs réguliers / risque pour les assurés ou pour l'Institut ;
   - **comment mesurer l'amélioration** : la situation aujourd'hui et l'objectif après la formation ;
   - **ce qui a déjà été essayé** : rien / accompagnement par un collègue / note ou procédure / changement d'organisation ou d'outil / autre ;
   - **pourquoi une formation réglerait-elle ce problème ?**
   - **après la formation, que devront savoir faire les agents ?** (rappelé au responsable de structure lors de l'évaluation à 3–6 mois).

   **Aide à la décision (RH)** : sur la fiche d'une demande, un encadré calculé par l'application réunit la conclusion du
   diagnostic, les agents de la demande qui ont déjà suivi une formation proche (3 dernières années), le résultat à 3–6 mois
   des formations semblables et les demandes semblables d'autres services (pour regrouper les agents). La RH décide.

   Pour les **agents à former** : cocher au moins un agent. Tous les agents visibles sont affichés (toute l'INPS pour la RH,
   son service pour un responsable de structure), **rangés par direction** puis par service ; le compteur indique combien sont cochés.
   La recherche réduit la liste : matricule, nom, prénom ou sigle de direction (ex. « DRH »), mots dans n'importe quel ordre,
   avec ou sans accents. Même principe pour « Ajouter des participants » sur la fiche d'une formation. Il n'y a pas de nombre à saisir :
   le nombre de participants est celui des agents cochés. Le service est celui du compte ; l'année est déterminée automatiquement.
2. **« Envoyer la demande »** : elle part aussitôt à la RH (statut **Envoyée**), qui est notifiée. Un justificatif peut être joint
   ensuite depuis la fiche, tant que la RH n'a pas décidé.
3. La RH décide depuis l'encadré « Prochaine étape » de la fiche :
   - **Accepter et programmer** : une fenêtre demande la **date de début**, la **date de fin** (obligatoires), le **lieu**,
     l'**organisme formateur** (sinon formation interne) et un commentaire facultatif. En validant, la demande est acceptée,
     la formation est créée avec l'intitulé et la raison de la demande, et les agents cochés y sont inscrits.
     La RH arrive directement sur la fiche de la formation ;
   - **Demander des précisions** (motif obligatoire) ;
   - **Refuser** (motif obligatoire).
4. Une demande **« À compléter »** affiche au responsable de structure le message de la RH ; « Compléter la demande » puis « Renvoyer la demande ».
   Si un agent coché ne peut pas être inscrit (déjà inscrit à une autre formation aux mêmes dates, ou inactif), un message
   l'indique aussitôt et la fiche de la formation garde un encadré « agent(s) de la demande non inscrits » avec la raison.
5. Le responsable de structure suit sa demande sur sa fiche : décision de la RH (et motif en cas de refus), puis référence, dates, lieu
   et statut de la formation.

Statuts : Envoyée → Formation programmée (puis Formation terminée ou annulée, selon la formation) / Inscrite au plan / Refusée / À compléter. Chaque décision est conservée avec son auteur, sa date
et son commentaire (« Historique des décisions »). Une demande ne donne lieu qu'à une seule formation. Une demande acceptée
avant la version 1.5.0 sans formation affiche « Programmer la formation » (même fenêtre).

## 2 b. Plan annuel

Menu **Plan annuel** (choisir l'année). Les formations prévues sont regroupées par trimestre, avec leur état :
Prévue, Programmée, Terminée ou Annulée.
- **Ajouter une formation au plan** (RH) : formation prévue, période (mois, trimestre ou « dans l'année »), service concerné
  (ou plusieurs services), nombre d'agents prévus, interne/externe, organisme envisagé, observations.
- Depuis une demande : **« Accepter et inscrire au plan »** (année et période) ; la demande devient « Inscrite au plan »
  et son auteur est notifié. Retirer une demande du plan demande un motif ; la demande redevient « Envoyée ».
- **Programmer** (RH) : dates, lieu, organisme ; la formation est créée (et les agents de la demande inscrits), puis on suit
  les trois étapes habituelles de la formation.
- **Valider le plan** : la date, le nom et un commentaire sont enregistrés ; le plan peut encore être complété.
- **PDF** et **Excel** : le plan à présenter ou à transmettre.
- Le responsable de structure voit seulement les formations prévues pour son service.

## 3. Formations (RH) — trois étapes

Une **formation** réunit sur une seule fiche : ce qui est enseigné, les dates et le lieu, les participants, les attestations et les documents.

**Créer une formation** :
- depuis une demande, avec « Accepter et programmer » (voir ci-dessus) ;
- ou directement, pour une formation décidée par la RH : Formations → **« Nouvelle formation »** : intitulé, dates, lieu,
  type **Interne**, **Externe** ou **Mixte**, organisme (obligatoire si externe ou mixte) et **formateurs** (1.8.0) : on coche
  un ou plusieurs formateurs internes (agents de l'INPS) et externes (prestataires). Un interne et un externe ensemble :
  type « Mixte » ; un type incohérent avec les formateurs cochés est refusé avec une explication. Les formateurs restent
  modifiables après la formation. Le bloc **« Plus de détails (facultatif) »** contient le domaine, la modalité, les horaires,
  la durée, le public, les objectifs et la description.
  La référence (ACT-AAAA-NNNN) et l'année (celle de la date de début) sont automatiques.
- Un second groupe à d'autres dates = une autre formation : « Nouvelle formation similaire » (contenu repris, dates à fixer).

**Étape 1 — La formation** : « Modifier » tant qu'elle n'est ni terminée ni annulée. « Annuler la formation » (motif obligatoire) :
la formation et ses participants restent consultables.

**Étape 2 — Les participants** :
- Les participants sont regroupés par service. Quand la formation vient d'une demande, ses agents sont déjà inscrits : le bloc « Ajouter d'autres participants (facultatif) » est replié et ne sert qu'à compléter la liste.
- Un responsable de structure ne voit que les formations qui concernent son service, et seulement les participants de son service.
- « Ajouter des participants » : cocher des agents (avec recherche) ou choisir tout un service. Il n'y a pas de limite de places.
- Refus signalés automatiquement : agent inactif, déjà inscrit, ou déjà inscrit à une autre formation aux mêmes dates.
- « Retirer » un participant tant que la formation n'est pas terminée (il reste dans l'historique).
- « Liste des participants (PDF) » : liste imprimable (peut servir de feuille de signature).
- **Après la formation** : la colonne « A suivi ? » est cochée pour tous ; **décocher les absents**, puis cliquer
  **« Formation terminée »**. La liste des participants ne peut plus changer ensuite.
- Pour corriger : modifier les cases puis « Enregistrer la correction ». Si une attestation a déjà été émise pour un participant,
  il faut d'abord l'annuler avant de le décocher.

**Convoquer les participants** : depuis le 28/10/2026, les agents sont convoqués **dès l'acceptation** de la demande :
la case **« Convoquer aussitôt les agents »**, cochée par défaut, figure dans les fenêtres « Accepter et programmer »,
« Programmer la formation » et « Programmer » du plan annuel (la décocher pour convoquer plus tard). Sinon, la prochaine
étape de la formation est **« Convoquer les participants »**. Dans les deux cas, la convocation :
- marque chaque participant « Convoqué » (date enregistrée) ;
- prévient chaque **responsable de structure** avec la liste de ses agents convoqués (sauf l'auteur de la demande, déjà
  informé de l'acceptation) ;
- prévient les agents qui ont un **accès** : notification et convocation dans « Mon espace » ;
- envoie à **chaque agent qui a une adresse e-mail** (celle de son compte, sinon celle de sa fiche) un e-mail avec **sa
  convocation en PDF**, qu'il ait un accès ou non, si la messagerie est configurée.
Le message affiché indique combien d'e-mails sont partis, combien d'agents n'ont pas d'adresse (convocation à imprimer), et
si la messagerie n'est pas configurée (aucun e-mail ne part alors). « Convocations (PDF) » imprime une convocation par
participant (modèle proposé, à valider par l'INPS). Un participant ajouté après la convocation est **convoqué
automatiquement**. Dates, horaires ou lieu modifiés après la convocation : l'application le signale et propose « Envoyer la
convocation rectificative » (à tous les participants).

**Étape 3 — Les attestations** :
- « Émettre les attestations » (encadré « Prochaine étape ») les émet pour tous les participants qui ont suivi la formation ;
  le bouton « Émettre » de chaque ligne le fait pour un seul participant.
- Conditions : formation marquée terminée, participant coché « a suivi la formation », pas d'attestation valide existante.
- Chaque attestation a une référence unique, un code de vérification et un PDF. Annulation : motif obligatoire ; le PDF n'est plus
  téléchargeable.
- **Nombre d'impressions** (28/10/2026) : la liste « Attestations » (colonne « Imprimée ») et la fiche de la formation indiquent
  combien de fois chaque attestation a été téléchargée pour impression, et la date de la dernière fois. Le compte vient du
  journal d'audit (chaque téléchargement du PDF y est inscrit) ; l'application ne peut pas savoir si la feuille a réellement
  été imprimée.
- Les attestations sont **remises par la RH** : l'agent ne peut pas les télécharger depuis « Mon espace ».

**Après la formation (1.9.0)** — bloc « Après la formation » de la fiche, une fois la formation terminée :
- **Ont-ils compris ?** (RH, en fin de formation, version 2.2.0) : « Indiquer qui a compris » ouvre la liste des participants
  qui ont suivi la formation ; pour chacun : **Oui / En partie / Non**, une note sur 20 et un commentaire facultatifs ;
  un seul enregistrement pour toute la liste. Une ligne vidée efface la réponse. Le résumé (« Oui 4, En partie 1, Non 0 »)
  s'affiche sur la fiche formation et dans le rapport.
- **Rapport de réalisation** (RH) : « Rédiger le rapport » demande le déroulement (obligatoire), les difficultés et les
  recommandations ; le PDF ajoute automatiquement la formation, le besoin d'origine, la participation (par service),
  les résultats de l'évaluation à chaud et, plus tard, ceux de la mise en pratique.
- **Attestations d'intervention** (RH) : pour chaque formateur **externe**, « Émettre l'attestation » crée une attestation
  numérotée (ATI-AAAA-NNNN), téléchargeable ensuite en PDF ; la réémettre redonne la même. Modèle proposé, à valider par l'INPS.
- **Appliquent-ils ce qu'ils ont appris ? (3 à 6 mois après)** : la RH choisit le délai (3, 4, 5 ou 6 mois ; 6 par défaut).
  À cette date, le responsable de structure reçoit une notification ; menu **Évaluations** (onglets « À faire », « À venir », « Faites »,
  une ligne par formation) → « Évaluer » ouvre la liste de **ses** agents formés : pour chacun **Oui / En partie / Non** et un
  commentaire facultatif, un seul enregistrement. Le résultat attendu et la mesure indiqués dans la demande sont rappelés.
  La RH voit et peut évaluer tous les agents. Les réponses restent modifiables.

Statuts d'une formation : Programmée → Terminée, ou Annulée.

## 4. Organismes et formateurs

Coordonnées, personnes de contact, formateurs internes (liés à un agent) ou externes, historique des prestations, appréciations (1 à 5).
Les conventions, l'identifiant fiscal et les documents contractuels ne sont visibles qu'avec le droit « données contractuelles ».

## 5. Documents

Bloc « Documents » des demandes, formations et organismes : formats PDF, images, Word, Excel, OpenDocument, CSV ; taille maximale paramétrable (10 Mo par défaut).
Les documents ne sont accessibles que par l'application, selon vos droits ; un document marqué confidentiel exige un droit spécifique.

## 6. Référentiel du personnel et importations

- Recherche (matricule, nom, prénom, fonction), filtres direction/service/statut, tri, fiche individuelle avec historique des formations (PDF).
- Désactivation (motif) au lieu de suppression ; réactivation possible.
- **Importations** : choisir le type (structure de l'INPS, agents ou historique ; importer d'abord la structure), déposer le fichier CSV/Excel → aperçu ligne par ligne (valides, erreurs, doublons, références inconnues) → *Confirmer* (écriture des seules lignes valides, en une seule transaction) ou *Abandonner*. Un import n'écrase ni ne supprime jamais de données existantes. Dans un fichier Excel, les dates peuvent être de vraies dates ou du texte (JJ/MM/AAAA).
- **Listes extraites du système du personnel** : la liste des directions (colonnes UA_CODE, DESIGNATION, SIGLE) s'importe telle quelle avec le type « Structure de l'INPS » (une ligne sans service crée seulement la direction) ; la liste du personnel (MATRICULE, NOM, PRENOM, UACODE_DIRECTION, FONCTION) s'importe telle quelle avec le type « Référentiel du personnel ». Les autres colonnes (n° de compte, banque, n° INPS, dates…) sont ignorées et ne sont jamais enregistrées. Un agent dont la direction n'existe pas est refusé : ajoutez la direction dans *Administration → Référentiels → Directions*, puis importez de nouveau le même fichier (les agents déjà présents sont ignorés, seuls les manquants sont ajoutés). Le sigle d'une direction se corrige dans ce même écran.

## 7. Rapports

Menu Rapports : 10 rapports filtrables (année, direction, service, domaine, type ; période pour « agents sans formation »),
affichés à l'écran avec leur règle de calcul, exportables en Excel et PDF, imprimables. Notamment « Suivi des demandes de formation »
(chaque demande, sa décision et la formation programmée) et « Suivi des formations par les participants » (par formation terminée :
participants, nombre et taux de ceux qui l'ont suivie). L'application ne traite pas de budget ni de dépenses.

## 8. Notifications

Icône « Notifications » : nouvelles demandes, précisions demandées, décisions (dont « Demande acceptée : formation programmée »),
formations à venir, formations passées à marquer terminées, demandes toujours à compléter, bilan annuel, échéances de clôture.
Cliquer sur une notification la marque comme lue et ouvre l'élément concerné. L'envoi par e-mail est facultatif et dépend de la configuration du serveur de messagerie.

## 9. Mon espace (agents) — version 2.3.0

Chaque agent peut avoir un accès personnel (version 2.4.0). La première fois, sur la page de connexion, il clique sur
**« Activer mon compte »**, saisit son **matricule** et le **code à 6 chiffres** donné par la RH, et choisit son mot de passe.
Ensuite, il se connecte avec son matricule (champ « Adresse e-mail ou matricule ») et ce mot de passe.
Il ne voit que « Mon espace » :
- **À venir** : ses formations prévues (dates, horaires, lieu) et « Télécharger ma convocation » dès qu'il est convoqué ;
- **Formations suivies et passées** : s'il a suivi chaque formation, et si son attestation est délivrée (« Délivrée — à retirer
  auprès de la RH ») ; depuis le 28/10/2026, il ne la télécharge plus lui-même.
Il reçoit une notification à chaque convocation. En cas d'oubli du mot de passe, il demande un nouveau code à la RH.

## 9 b. Formations diplômantes (RH) — version 2.5.0

Menu **Formations diplômantes** : les licences, masters, doctorats… que les agents suivent dans un établissement
(université, institut), en dehors des formations organisées par l'INPS.
- **« Nouvelle formation diplômante »** (ou « Ajouter une formation diplômante » sur la fiche d'un agent) : agent, niveau,
  intitulé ou spécialité, établissement, ville et pays, année académique (année de début : 2026 pour 2026-2027), durée
  prévue, mode, **prise en charge** (totale ou partielle par l'INPS, à ses frais, bourse ou partenaire) et situation.
  Aucun montant n'est demandé.
- Quand le diplôme est obtenu : « Modifier », situation **« Diplôme obtenu »** et date d'obtention. Joindre ensuite les
  pièces (inscription, diplôme) dans « Documents ».
- La liste se filtre (agent, niveau, situation, prise en charge, année, direction) ; les quatre compteurs du haut
  ouvrent la liste par situation ; **« Exporter (Excel) »** reprend les filtres.
- L'agent voit ses formations diplômantes dans « Mon espace » (consultation seulement).

## 10. Supprimer une erreur de saisie

- Bouton **« Supprimer »** sur une demande (par son auteur tant que la RH n'a pas décidé ; par la RH tant qu'aucune formation
  n'est programmée), une formation (non terminée, sans attestation), un agent (jamais inscrit, cité dans aucune demande) ou un compte (jamais utilisé).
  Dans les autres cas, le bouton n'apparaît pas et l'application indique quoi faire (annuler la formation, désactiver l'agent ou le compte).
- Dans les listes Demandes, Formations et Personnel : cocher plusieurs lignes, puis **« Supprimer la sélection »**.
- Les demandes et formations supprimées vont dans la **Corbeille** (RH), d'où « Restaurer » les remet en place.
