# Décisions institutionnelles à valider par l'INPS

Aucune règle institutionnelle n'a été inventée. Lorsque l'information manquait, une **valeur provisoire configurable** a été retenue.
Chaque point ci-dessous doit être confirmé (ou corrigé dans l'application) par l'autorité compétente avant la mise en production.

| # | Sujet | Valeur provisoire retenue | Où la modifier | Décision INPS |
|---|---|---|---|---|
| 1 | Profils et périmètres des rôles | **Décidé le 21/10/2026** : deux profils — la RH gère tout (rôles Responsable DRH + Administrateur technique), le responsable de structure déclare les besoins de son service ; les agents n'ont pas de compte (`GUIDE_ADMINISTRATEUR.md` § 1) | Administration → Profils et droits (lecture seule) | Validé |
| 2 | Plan annuel et circuit de validation | **Décidé le 23/10/2026** : plan annuel simple (formations prévues par période, « Programmer », validation en un clic sans circuit) ; une demande acceptée est programmée tout de suite ou inscrite au plan (version 1.7.0) | — | Validé |
| 3 | Une formation = une seule période (un second groupe = une autre formation) | **Décidé le 21/10/2026** (version 1.3.0) | — | Validé |
| 1 d | Droits personnalisables par compte | **Décidé le 26/10/2026** : profil RH ou Responsable de structure comme point de départ, puis droits ajoutés ou retirés compte par compte ; on ne donne pas un droit qu'on n'a pas (version 2.0.0) | Utilisateurs → fiche du compte | Validé |
| 1 b | Profils | **Décidé le 22/10/2026** : deux profils fixes (RH, Responsable de structure), droits présentés en lecture seule (version 1.6.0) | — | Validé |
| 1 c | Suppression des erreurs de saisie | **Décidé le 22/10/2026** : suppression limitée aux éléments sans suite (conditions : `GUIDE_ADMINISTRATEUR.md` § 8 b), corbeille pour demandes et formations (version 1.6.0) | — | Validé |
| 4 | Instruction des besoins : qui accepte / rejette ? | La RH (permission `besoins.instruire`) | Profils et droits | Validé |
| 5 | Étapes d'une demande | **Décidé le 22/10/2026** : envoyée → acceptée et programmée / refusée / à compléter ; formulaire minimal (intitulé, raison, agents cochés, période) ; accepter = fixer les dates (version 1.5.0) | — | Validé |
| 5 b | Étapes d'une formation | **Décidé le 22/10/2026** : la formation → les participants (cocher ceux qui l'ont suivie, puis « Formation terminée ») → les attestations ; plus de convocations, présences jour par jour, liste d'attente ni évaluations (version 1.5.0) | — | Validé |
| 6 | Condition d'émission d'une attestation | **Décidé le 22/10/2026** : le participant est coché « a suivi la formation » par la RH (sans seuil de présence calculé) | — | Validé |
| 10 g | Accès des agents | **Décidé le 28/10/2026** (version 2.4.0) : compte par agent, identifiant = matricule ; activation avec un code de 6 chiffres donné par la RH (15 jours, 5 essais) ; l'agent choisit son mot de passe | `AccesAgentService::JOURS_VALIDITE`, `ESSAIS_MAX` | Validé |
| 10 h | Convocation des participants (2.3.0) | **Modèle proposé, à valider** : texte de la convocation et signataire ; moyens retenus : PDF, notification au responsable de structure, « Mon espace », e-mail avec la convocation jointe à chaque agent qui a une adresse ; **décidé le 28/10/2026 : convocation envoyée dès l'acceptation de la demande** (case cochée par défaut) ; pas de confirmation de présence | `resources/views/pdf/convocation.blade.php` | |
| 10 i | Formations diplômantes (2.5.0) | **Décidé le 28/10/2026** : saisie par la RH seulement, sans montants (type de prise en charge seulement) ; listes proposées (niveaux, modes, prises en charge, situations) à confirmer | `app/Models/FormationDiplomante.php` | Listes à valider |
| 10 j | Attestations de participation (28/10/2026) | **Décidé le 28/10/2026 (demande de la RH)** : l'agent ne télécharge plus son attestation dans « Mon espace » (elle lui est remise par la RH) ; la RH voit combien de fois chaque attestation a été téléchargée pour impression (d'après le journal d'audit) | `resources/views/espace/index.blade.php`, `app/Models/Attestation.php` | Validé |
| 1 e | Nom du profil des responsables | **Décidé le 28/10/2026** : « Responsable de structure » (au lieu de « Chef de service ») | `app/Support/Profils.php`, `CataloguePermissions.php` | Validé |
| 10 b | Évaluations après la formation | **Décidé le 27/10/2026** (version 2.2.0) : à chaud, la RH indique si chaque participant a compris (oui / en partie / non, note sur 20 facultative) ; à 3–6 mois, le responsable de structure indique s'il applique ce qu'il a appris (oui / en partie / non) ; plus d'avis des participants. Reste à confirmer : délai de 6 mois par défaut | `SessionFormation::DELAI_FROID_DEFAUT` | Partiellement validé |
| 10 c | Attestation d'intervention des formateurs externes (1.9.0) | **Modèle proposé, à valider** : texte, mentions et signataire (même signataire que les attestations de participation) | `resources/views/pdf/attestation-intervenant.blade.php` | |
| 10 d | Analyse du besoin (2.1.0) | **Décidé le 27/10/2026** : diagnostic en trois questions (savoir-faire, moyens, temps) avec conclusion, gravité, mesure avant/objectif, solutions essayées, aide à la décision pour la RH. Formulations et listes proposées, à confirmer | `BesoinFormation::DIAG_SAVOIR`, `GRAVITES`, `SOLUTIONS` | Partiellement validé |
| 10 e | Suppression des référentiels (2.1.0) | **Décidé le 27/10/2026** : seulement un élément utilisé nulle part | — | Validé |
| 10 f | Évaluation à chaud | **Décidé le 27/10/2026** : nominative ; c'est la RH qui vérifie la compréhension (version 2.2.0) | — | Validé |
| 10 k | Paramètres de l'écran *Administration › Paramètres* | **Décidé le 30/10/2026 (utilisateur)** : plus de mention « À valider par l'INPS » sur l'écran ; ce sont des réglages ordinaires, renseignés au besoin (adresse, téléphone, signataire, pied de page, durées de conservation…). Un champ vide n'empêche rien de fonctionner. Les points 10, 11, 16 et 19 ci-dessous restent des réglages à faire quand l'information est disponible. | Paramètres | Validé |
| 10 | Qualité du signataire des attestations, modèle et mentions | Non renseignée (« signataire habilité à configurer ») | Paramètres → Identité ; modèle `resources/views/pdf/attestation.blade.php` | |
| 11 | Logo, couleurs, intitulés officiels, adresse | Aucun logo ; couleurs #003B73 / #0056A3 (à confirmer par la charte de l'INPS) ; intitulés génériques | Paramètres → Identité | |
| 11 b | Photographie officielle du siège (page de connexion) et droits d'utilisation | Photographie fournie le 20/10/2026 (602 × 509 px, basse résolution) : confirmer les droits d'utilisation et fournir si possible une version ≥ 1600 px ; logo fourni en JPG 447 px : une version vectorielle (SVG) est souhaitable | Paramètres → Identité ou `public/images/institution/siege.jpg` | |
| 11 c | Textes de la page de connexion (2.5.2) : « Connectez-vous à votre espace formation » ; la phrase « Former les agents, c'est renforcer la qualité du service rendu aux assurés. » est retirée | Proposition à valider | `resources/views/auth/connexion.blade.php`, `resources/views/components/invite.blade.php` | |
| 12 | Intitulés officiels des directions, services, fonctions | **Fait le 29/10/2026** : 35 directions et 48 services importés depuis les listes de l'INPS ; noms des services à relire (fautes reprises du fichier, sigles) ; sigle du bureau de Paris à fournir | Référentiels ; import | |
| 13 | Nomenclatures (statuts professionnels, catégories de documents) | Listes génériques | Référentiels | |
| 14 | Budget et finances | **Décidé le 22/10/2026** : non gérés dans l'application (version 1.4.0) | — | Validé |
| 16 | Durées de conservation (dossiers, documents, journal d'audit, agents inactifs, sauvegardes) | Non fixées ; **aucune purge automatique** | Paramètres → Conservation | |
| 17 | Conformité aux textes maliens sur les données personnelles (déclaration éventuelle, information des agents) | À vérifier par le service juridique | — | |
| 18 | Données minimales du personnel collectées | Matricule, nom, prénom, direction, service, fonction, statut, coordonnées pro facultatives | — | |
| 19 | Délais des rappels (formation à venir, bilan annuel) | 7 j, 15 janvier | Paramètres → Notifications | |
| 20 | Notifications par e-mail et serveur SMTP | Désactivées (notifications internes uniquement) | `.env` + Paramètres → Notifications | |
| 21 | Politique de mots de passe et durée d'inactivité de session | 12 caractères complexes ; 30 min | `.env` (`PASSWORD_MIN_LENGTH`, `SESSION_LIFETIME`) | |
| 22 | Taille maximale et formats des pièces jointes | 10 Mo ; PDF, images, Word, Excel, OpenDocument, CSV | Paramètres → Exports ; `DocumentService` | |
| 23 | Fréquence, rétention et copie hors site des sauvegardes | Quotidienne, 14 archives, copie hors site à organiser | `.env`, cron | |
| 24 | Accès des responsables de structure aux agents de leur service | Autorisé (consultation) | Profils et droits | |
| 25 | Ajout et retrait des participants : qui en a le droit ? | Détenteurs de `inscriptions.gerer` (RH) | Profils et droits | |
| 26 | Accès des agents à leur espace (création de comptes pour tous les agents ?) | Comptes créés individuellement par l'administrateur | — | |
| 27 | Intégration future avec un SIRH / annuaire (authentification unique) | Non réalisée ; colonnes `source`, `reference_externe` prévues | Évolution | |

Après validation, renseigner la colonne « Décision INPS », reporter les valeurs dans l'application et conserver ce document signé avec le procès-verbal de recette.
