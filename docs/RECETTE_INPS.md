# Scénarios de recette à faire valider par l'INPS

Environnement : serveur de **recette** (`APP_ENV=staging`), base distincte de la production, données fictives (`DemoSeeder`) ou jeu de test anonymisé.
Pour chaque scénario : noter Conforme / Non conforme, la date, le testeur et les observations.

| Réf. | Scénario | Profil | Résultat attendu | Statut | Observations |
|---|---|---|---|---|---|
| **A. Accès et sécurité** |||||
| R01 | Connexion avec un mot de passe correct, puis déconnexion | Tous | Accès à la page d'accueil du profil ; retour à la connexion | | |
| R02 | 6 tentatives avec un mauvais mot de passe | — | Blocage temporaire (message « Trop de tentatives ») | | |
| R03 | Désactiver un compte pendant qu'il est connecté | Admin technique | L'utilisateur est déconnecté à l'action suivante et ne peut plus se connecter | | |
| R04 | Mot de passe temporaire : création d'un compte, première connexion | Admin / nouvel utilisateur | Changement de mot de passe imposé | | |
| R05 | Ouvrir l'URL d'une demande d'un autre service | Responsable de structure | Accès refusé (403) | | |
| R06 | Ouvrir le menu Administration | Responsable de structure, agent | Menus absents ; accès direct refusé | | |
| R07 | Tenter de s'attribuer un rôle supplémentaire | Admin technique | Refusé | | |
| R08 | Inactivité de 30 minutes | Tous | Session expirée, reconnexion demandée | | |
| R09 | Accès à `https://…/.env`, `/storage/…` et à un document par son chemin | — | 404 / refusé | | |
| **B. Personnel et imports** |||||
| R10 | Créer, modifier, rechercher un agent ; matricule en double | DRH | Enregistré ; doublon refusé | | |
| R11 | Désactiver puis réactiver un agent | DRH | Historique conservé ; agent non inscriptible lorsqu'inactif | | |
| R12 | Importer `exemples/demo-import-agents.csv` | DRH | Aperçu : 20 valides, erreurs et doublons signalés ; confirmation n'écrit que les valides | | |
| R13 | Importer l'historique (`exemples/demo-import-historique.csv`) | DRH | Formations visibles dans les fiches agents | | |
| R14 | Exporter le référentiel en Excel | DRH | Fichier lisible ; export tracé dans le journal | | |
| **C. Demandes** |||||
| R15 | Créer une demande en cochant deux agents (intitulé, raison, période) ; essayer sans agent | Responsable de structure | Pas de champ « nombre » ; sans agent : refus ; sinon demande « Envoyée » directement, 2 participants ; la RH est notifiée | | |
| R16 | Demander des précisions sans motif, puis avec | DRH | Refus sans motif ; statut « À compléter » ; le chef voit le message de la RH | | |
| R17 | Compléter et renvoyer la demande | Chef | Statut « Envoyée » ; historique des décisions complet (auteur, date, commentaire) | | |
| R18 | Refuser une demande avec motif | DRH | Statut « Refusée », motif visible par le chef | | |
| **D. Accepter et programmer** (version 1.5.0) |||||
| R19 | Ouvrir une demande envoyée | DRH | Encadré « Prochaine étape » : « Accepter et programmer », « Demander des précisions », « Refuser » ; pas de bouton « Organiser la formation » | | |
| R20 | « Accepter et programmer » sans dates, puis avec dates, lieu et organisme | DRH | Sans dates : refus ; sinon arrivée sur la fiche de la formation, agents de la demande inscrits ; demande « Formation programmée » ; une notification au chef | | |
| R21 | Même demande vue par le responsable de structure | Chef | Référence, dates, lieu et statut de la formation ; aucun bouton de décision | | |
| R22 | Tenter de programmer une seconde fois la même demande | DRH | Refus motivé | | |
| R23 | Créer une formation sans demande (Formations → Nouvelle formation) ; puis « Nouvelle formation similaire » | DRH | Formulaire court ; formation créée ; le modèle reprend le contenu, pas les dates | | |
| R24 | Suivre uniquement les encadrés « Prochaine étape » de la fiche jusqu'aux attestations | DRH | Trois étapes : la formation, les participants, les attestations ; la barre d'étapes progresse | | |
| R25 | Rapport « Suivi des demandes de formation » ; tuiles « Demandes reçues » et « Demandes à organiser » | DRH | Chiffres conformes | | |
| **E. Participants** |||||
| R26 | Ajouter des participants (agents cochés, puis tout un service) | DRH | Ajoutés sans limite de places ; agents déjà inscrits signalés | | |
| R27 | Ajouter un agent inactif | DRH | Refus motivé | | |
| R28 | Inscrire un agent sur deux formations aux dates qui se chevauchent | DRH | Refus motivé | | |
| R29 | Retirer un participant | DRH | Retiré de la liste ; historique conservé | | |
| R30 | Imprimer la liste des participants (PDF) | DRH | PDF correct | | |
| R31 | Annuler une formation (avec et sans motif) | DRH | Motif exigé ; statut « Annulée » ; participants conservés | | |
| **F. Fin de la formation et attestations** |||||
| R32 | Après la formation : décocher un absent, « Formation terminée » | DRH | Statut « Terminée » ; liste figée ; « A suivi » conforme | | |
| R33 | Émettre les attestations (bouton de l'encadré) | DRH | Attestations pour les seuls participants cochés | | |
| R34 | Émettre une attestation pour un participant non coché | DRH | Refus motivé | | |
| R35 | Décocher un participant déjà attesté, puis annuler son attestation et recommencer | DRH | Refus, puis correction acceptée | | |
| R36 | Télécharger une attestation ; l'annuler | DRH / agent | PDF avec référence ; l'agent la voit dans son espace ; annulée = non téléchargeable | | |
| R37 | Vérifier qu'aucun écran ne propose convocations, présences jour par jour, liste d'attente ni évaluations | DRH, chef | Aucun | | |
| **G. Documents, rapports** |||||
| R39 | Vérifier qu'aucun écran n'affiche de budget, de coût ni de dépense | DRH | Aucun montant affiché | | |
| R41 | Déposer un fichier non autorisé (ex. `.exe` renommé en `.pdf`) | DRH | Refus | | |
| R42 | Document confidentiel consulté par un auditeur | Auditeur | Refus | | |
| R43 | Tableau de bord : vérifier 3 indicateurs par recomptage manuel | DRH | Valeurs identiques | | |
| R44 | Générer chaque rapport, exporter en PDF et Excel | DRH | 10 rapports conformes, règles de calcul affichées | | |
| **H. Administration et exploitation** |||||
| R45 | Modifier les paramètres (logo, couleurs, délai de rappel) | Admin fonctionnel | Pris en compte immédiatement | | |
| R46 | Consulter et exporter le journal d'audit | Auditeur | Événements des scénarios précédents présents | | |
| R47 | Rappels automatiques (`php artisan inps:echeances`) | Exploitant | Notifications émises une seule fois | | |
| R48 | Sauvegarde puis restauration sur un serveur de recette | Exploitant | Données et documents restaurés, `inps:sante` OK | | |
| R49 | Mise à jour avec `deploiement/mettre-a-jour.sh` puis retour arrière | Exploitant | Procédures fonctionnelles | | |
| R50 | Affichage sur tablette et téléphone ; navigation au clavier | Tous | Pages lisibles et utilisables | | |

Procès-verbal : date, participants, scénarios conformes / non conformes, réserves, décision (acceptation, acceptation avec réserves, refus).
