# Remplacer les agents, directions et services de test par les vraies listes

À faire **une seule fois**, quand les agents, directions et services de l'application sont encore ceux des essais.

**Ce qui est supprimé** : les agents, les directions, les services, et ce qui n'a plus de sens sans eux :
inscriptions aux formations (présences, évaluations, attestations), formations diplômantes, demandes de formation.

**Ce qui reste tel quel** : tous les comptes d'utilisateurs (mêmes adresses, mots de passe et profils), les formations
(sans participants), les organismes, les formateurs, les paramètres, le journal d'audit.
Un compte relié à un agent, un service ou une direction de test perd seulement ce lien (à refaire dans *Administration → Utilisateurs*).

## 1. Préparer

Dans le dossier de l'application (`C:\laragon\www\inps-formation-2.6.0\inps-formation`), dans un terminal :

```
php artisan migrate
php artisan inps:vider-personnel
```

- `migrate` ajoute la colonne « sigle » des directions.
- `inps:vider-personnel` affiche d'abord ce qui sera supprimé et les comptes qui perdront leur lien,
  puis demande de taper **OUI**. Toute autre réponse annule sans rien supprimer.
  En cas d'erreur pendant la suppression, rien n'est supprimé (tout ou rien).

Puis `php artisan optimize` et `php artisan serve`.

## 2. Importer les directions

Menu *Importations* (écran « Importation et reprise des données ») :

1. Type « Structure de l'INPS (directions et services) », fichier de la liste des directions (UA_CODE, DESIGNATION, SIGLE).
2. « Analyser le fichier », vérifier l'aperçu, puis « Confirmer ».

## 3. Ajouter les directions qui manquent dans la liste

*Administration → Référentiels → Directions*, dernière ligne du tableau : code, nom, sigle (facultatif), « Ajouter ».
Exemple du 29/10/2026 : le « Bureau de sécurité Sociale de Paris » (code 1070000) est utilisé dans la liste du personnel
mais absent de la liste des directions ; son sigle est à demander.

## 4. Importer le personnel

Même écran, type « Référentiel du personnel (agents) », fichier de la liste du personnel.
Les colonnes inutiles (comptes bancaires, banque, n° INPS…) sont ignorées et ne sont jamais enregistrées.
Les agents dont la direction est inconnue sont signalés en erreur : ajoutez la direction (étape 3), puis réimportez le même fichier
(les agents déjà présents sont ignorés, seuls les manquants sont ajoutés).

## 5. Après l'import

- Dans *Administration → Utilisateurs*, rattachez de nouveau les comptes signalés à l'étape 1 (service ou direction).
- Les anciens comptes d'agents de test n'ont plus de fiche : à leur connexion, « Mon espace » l'indique.
- Les agents n'ont pas encore de service : la liste du personnel ne donne que la direction.
- Ne conservez pas le fichier du personnel dans le dossier de l'application et ne l'envoyez pas avec l'application :
  il contient des données personnelles et bancaires.
