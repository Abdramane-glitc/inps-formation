# Architecture technique

## Choix généraux

- **Monolithe Laravel 13 modulaire**, rendu serveur (Blade). Pas d'API publique ni de SPA : moins de surface d'attaque,
  maintenance simple par une équipe réduite, fonctionnement sans Internet.
- **MySQL 8.0** (InnoDB, utf8mb4), clés étrangères et contraintes d'unicité systématiques, transactions sur toutes les opérations critiques.
- **Aucune dépendance superflue** : framework Laravel, dompdf (PDF), PhpSpreadsheet (Excel). Rôles/permissions, imports,
  sauvegardes et notifications sont implémentés en interne et testés.
- **Notifications synchrones** (aucun processus de file d'attente à superviser).

## Organisation du code

| Dossier | Contenu |
|---|---|
| `app/Services` | **Règles métier** : `BesoinService` (demandes envoyées directement : envoyée → acceptée / refusée / à compléter → formation organisée ; année automatique), `ActionService` (formations : une action + une session créées ensemble, séances, reports, annulations, statut de l'action aligné sur celui de la session), `InscriptionService` (capacité, liste d'attente, chevauchements, remplacements), `PresenceService` (pondération, achèvement), `AttestationService`, `DocumentService`, `ImportService`, `IndicateurService`, `RapportService`, `ExportService`, `NotificationService`, `SauvegardeService`, `Audit`, `GenerateurReference` |
| `app/Http/Controllers` | Contrôleurs minces : validation, autorisation, appel du service, réponse |
| `app/Http/Requests` | Form Requests des formulaires complexes (agents, besoins, formations) |
| `app/Http/Middleware` | `ExigerPermission` (contrôle d'accès par route), `VerifierCompteActif`, `EntetesSecurite` |
| `app/Policies` | Cloisonnement par objet : besoins, agents, documents |
| `app/Models` | Modèles Eloquent (champs autorisés explicitement listés, statuts typés par énumérations) |
| `app/Enums` | Statuts métier (codes stables + libellés français + couleurs) |
| `app/Support` | Catalogue des permissions et rôles proposés, protection du journal |
| `app/Console/Commands` | `inps:creer-admin`, `inps:sante`, `inps:sauvegarde`, `inps:restaurer`, `inps:echeances`, `inps:proteger-audit` |
| `resources/views` | Vues Blade par module, composants réutilisables (`components/`), gabarits PDF (`pdf/`) |
| `database/seeders` | `ReferentielInitialSeeder` (production), `DemoSeeder` (fictif, interdit en production) |

## Flux d'une requête

```
Navigateur ──HTTPS──▶ Nginx ──▶ PHP-FPM ──▶ Laravel
   middleware web : session chiffrée, CSRF, en-têtes de sécurité, compte actif / mot de passe à changer
   middleware auth : session authentifiée
   middleware permission:… : permission requise pour la route (refus par défaut)
   contrôleur : validation (Form Request) → policy (périmètre service / objet) → service métier
   service : transaction, règles, décisions tracées, journal d'audit, notifications
   ◀── vue Blade (échappement automatique) / PDF / Excel
```

## Modèle « formation » (depuis 1.3.0)

Une **formation** affichée à l'écran correspond à une ligne `actions_formation` (contenu, organisme, demande d'origine `besoin_id`)
et à **une** ligne `sessions_formation` (dates, lieu, places, déroulement), créées ensemble par `ActionService::creerFormation()`.
Les participants (inscriptions, avec « a suivi la formation ») et les attestations restent rattachés à la session ; les documents, à l'action.
`FormationController` affiche la fiche unique ; `SessionController` ne garde que les actions de déroulement (statut, report, annulation, PDF).
Les fiches « demande » et « formation » affichent le parcours et la **prochaine étape** (composant `x-parcours`, encadré `.prochaine`).

## Contrôle d'accès

- Tables `roles`, `permissions`, `permission_role`, `role_user`. 32 permissions regroupées par module ; 7 rôles proposés (modifiables).
- Une permission non détenue = accès refusé. Chaque permission est aussi une « ability » Laravel (`@can('rapports.voir')`).
- Cloisonnement : `besoins.voir_service` / `agents.voir_service` limitent au `service_id` du compte ; `*.voir_tous` lève la limite.
- Depuis 1.3.0, il n'y a plus de plan annuel ni de circuit de validation : la RH (`besoins.instruire`) décide des demandes et (`actions.gerer`) organise les formations.

## Règles de calcul centralisées

- Présence et achèvement : `PresenceService` (poids paramétrables, seuil paramétrable).
- Indicateurs : `IndicateurService` (définitions en tête de classe), rapports : `RapportService` (note de calcul affichée avec chaque rapport).

## Fichiers et documents

- Disque `prive` = `storage/app/private`, jamais exposé ; téléchargement via `DocumentController` après policy.
- Nom de stockage `documents/AAAA/MM/<uuid>.<ext>`, empreinte SHA-256, contrôle du type réel (finfo) et de l'extension.

## Gestion des erreurs

- `RegleMetierException` : rendue de façon centralisée (`bootstrap/app.php`) par un retour au formulaire avec un message français.
- Erreurs techniques : page générique sans détail lorsque `APP_DEBUG=false`, détail dans `storage/logs`.

## Évolutivité

- Référentiel du personnel : colonnes `source` et `reference_externe` prévues pour une future synchronisation avec un SIRH.
- Statuts métier : codes stables dans des énumérations ; étapes optionnelles activables par paramètre.
- Nouveaux rapports : ajouter une méthode dans `RapportService` et une entrée dans `RAPPORTS`.

## Points connus / limites

- Interface mono-langue (français).
- Pas d'authentification unique (annuaire LDAP/AD) : prévue comme évolution si l'INPS dispose d'un annuaire.
- Depuis la version 1.4.0, l'application ne traite ni budget ni dépenses (la table `depenses` est conservée sans usage).
- Depuis la version 1.5.0, une formation se gère en trois étapes (`FormationController` : participants, « Formation terminée » ; `ActionService::accepterEtProgrammer` et `ActionService::terminer`) ; les contrôleurs et services de présences, d'évaluations et d'inscriptions séparées sont retirés (tables conservées sans usage).
