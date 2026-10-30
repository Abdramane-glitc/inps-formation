# Revue de sécurité — INPS Formation 2.6.0 (30/10/2026)

**But :** chercher, avant la mise en service, ce qu'une personne malveillante pourrait faire : voir ce qui ne la regarde pas, se donner des droits, voler un compte, effacer des traces, faire planter l'application.

**Méthode :** lecture du code (routes, contrôleurs, droits, requêtes à la base, pages, connexion, fichiers, sauvegardes), essais réels avec la série de tests (171 tests réussis) et un test supplémentaire pour prouver le seul défaut trouvé dans le code.

**Ce qui a été modifié :** rien. Les corrections proposées n'auront lieu qu'avec votre accord.

---

## Résumé

| Niveau | Nombre | Où se règle le problème |
|---|---|---|
| Élevé | 3 | Réglages du serveur, le jour de la mise en service |
| Moyen | 4 | Réglages du serveur (3), vérification à faire (1) |
| Faible | 3 | Code (1 correction proposée, 2 facultatives) |

**En bref :** le code est solide. Il n'y a pas de faille grave dans le code lui-même. Les vrais risques viennent des **réglages du jour J** : comptes de démonstration, mode débogage, HTTPS. Ils sont déjà dans `docs/MISE_EN_PRODUCTION_A_FAIRE.md` ; cette revue confirme qu'ils sont **obligatoires**.

---

## Niveau ÉLEVÉ — à régler avant d'ouvrir l'application aux agents

### E1. Comptes de démonstration encore actifs
- **Ce qui ne va pas :** 52 comptes d'essai (adresses en `@exemple.invalid`) sont actifs. Ils ont tous le même mot de passe, connu de toute personne ayant installé la démonstration. Certains ont tous les droits. Vous-même vous connectez aujourd'hui avec un de ces comptes.
- **Ce qu'un malveillant pourrait faire :** se connecter comme administrateur de démonstration, voir les 3 671 agents, créer des comptes, changer des droits.
- **Correction proposée :**
  1. Sur le serveur, créer d'abord **votre vrai compte** : `php artisan inps:creer-admin`.
  2. Se connecter avec ce compte pour vérifier qu'il marche.
  3. Désactiver les comptes d'essai : `php artisan inps:comptes-test --desactiver` (ils sont désactivés, pas supprimés ; la commande refuse s'il ne reste aucun vrai administrateur).

### E2. Mode « débogage » (`APP_DEBUG=true`)
- **Ce qui ne va pas :** sur le poste, une erreur affiche une page technique détaillée (code, chemins, certains réglages).
- **Ce qu'un malveillant pourrait faire :** provoquer une erreur et lire ces informations pour préparer une attaque.
- **Correction proposée :** dans le `.env` du serveur, mettre `APP_ENV=production` et `APP_DEBUG=false`, puis `php artisan optimize`. Le jeu de démonstration est alors refusé automatiquement.

### E3. Pas encore de HTTPS
- **Ce qui ne va pas :** sans HTTPS, le mot de passe tapé à la connexion circule en clair sur le réseau.
- **Ce qu'un malveillant pourrait faire :** sur le même réseau, « écouter » et récupérer des mots de passe ou des sessions.
- **Correction proposée :** certificat HTTPS sur le serveur (à demander à l'informatique, ou Let's Encrypt si le serveur est visible d'Internet), puis `APP_URL=https://…` et `SESSION_SECURE_COOKIE=true`. L'application ajoute alors d'elle-même l'en-tête qui impose HTTPS au navigateur.

---

## Niveau MOYEN

### M1. Journal d'audit non protégé sur le poste
- **Ce qui ne va pas :** la protection qui empêche de modifier ou d'effacer le journal d'audit n'est pas installée sur le poste (`inps:sante` le signale).
- **Ce qu'un malveillant pourrait faire :** quelqu'un qui accède directement à la base (HeidiSQL, par exemple) pourrait effacer la trace de ce qu'il a fait.
- **Correction proposée :** sur le serveur, `php artisan inps:proteger-audit`, puis vérifier avec `php artisan inps:sante`.

### M2. Sauvegardes non chiffrées
- **Ce qui ne va pas :** une sauvegarde contient toute la base, dont les 3 671 agents. Elle est lisible par quiconque obtient le fichier.
- **Ce qu'un malveillant pourrait faire :** avec une copie (clé USB perdue, dossier partagé), il lirait toutes les données du personnel.
- **Correction proposée :** dans le `.env` du serveur, `BACKUP_PASSWORD=…` (mot de passe long, noté en lieu sûr : **sans lui, une sauvegarde ne se restaure pas**). Les sauvegardes sont alors chiffrées en AES-256. Garder une copie hors du serveur.

### M3. Serveur placé derrière un « proxy » (à demander à l'informatique)
- **Ce qui ne va pas :** l'application limite les essais de connexion **par adresse IP**. Si le serveur est derrière un proxy (un serveur intermédiaire), tous les utilisateurs semblent venir de la même adresse.
- **Ce qu'un malveillant pourrait faire :** en ratant 30 connexions en une minute, bloquer **tout le monde** pendant une minute. Les adresses notées dans le journal seraient aussi fausses.
- **Correction proposée :** demander à l'informatique s'il y a un proxy. Si oui, mettre `TRUSTED_PROXIES=<adresse du proxy>` dans le `.env`. Ne **jamais** mettre `*`, car cela permettrait de mentir sur son adresse.

### M4. Bibliothèques PHP non vérifiées
- **Ce qui ne va pas :** la vérification des bibliothèques PHP (`composer audit`) n'a pas pu tourner ici, faute d'accès à Internet. Celle des bibliothèques d'affichage (`npm audit`) n'a trouvé **aucune** faille.
- **Ce qu'un malveillant pourrait faire :** exploiter une faille connue d'une bibliothèque, si l'une est concernée.
- **Correction proposée :** sur le poste (terminal Laragon, dans le dossier du projet), lancer `composer audit`. Si une faille apparaît, me montrer le résultat. À refaire environ une fois par mois.

---

## Niveau FAIBLE

### F1. Réinitialisation par lien e-mail : les autres connexions restent ouvertes — *défaut du code, prouvé par un test*
- **Ce qui ne va pas :** quand quelqu'un change son mot de passe avec le lien reçu par e-mail, ses autres connexions ouvertes (sur un autre poste) **ne sont pas coupées**. Dans tous les autres cas, elles le sont : changement depuis « Mon mot de passe », réinitialisation par l'administrateur, activation par code, désactivation.
- **Ce qu'un malveillant pourrait faire :** s'il avait pris la session de quelqu'un (poste resté ouvert), il la garde après que la victime a changé son mot de passe. Il la perd ensuite après 30 minutes d'inactivité.
- **Pourquoi « faible » :** la messagerie n'est pas encore réglée, donc ce chemin ne sert pas aujourd'hui. Il passera à « moyen » quand la messagerie sera active.
- **Correction proposée (1 ligne)** dans `app/Http/Controllers/Auth/MotDePasseController.php`, méthode `reinitialiser` :
  ```php
  // Couper toutes les connexions ouvertes du compte, comme dans les autres cas de changement de mot de passe
  DB::table('sessions')->where('user_id', $u->id)->delete();
  ```
  Plus un test qui le vérifie.

### F2. Pas de limite d'essais par compte quand l'attaque vient de plusieurs postes (facultatif)
- **Ce qui ne va pas :** la limite est de 5 essais par minute pour un même couple « compte + poste ». Depuis beaucoup de postes à la fois, on peut essayer davantage de mots de passe sur un même compte.
- **Pourquoi « faible » :** les mots de passe imposés sont longs (12 caractères, avec majuscules, chiffres et symboles), et l'application est interne.
- **Correction proposée (facultative) :** ajouter une limite de 20 essais par heure pour un même compte, quel que soit le poste. **Inconvénient :** un malveillant pourrait alors bloquer volontairement le compte de quelqu'un pendant une heure. Je conseille de **ne pas** la mettre tant que l'application reste interne.

### F3. Un matricule ayant un code d'activation en attente se reconnaît (facultatif)
- **Ce qui ne va pas :** si l'on tape un matricule dont le compte attend son activation, la page d'activation s'ouvre au lieu du message « Identifiants incorrects ».
- **Pourquoi « faible » :** un matricule n'est pas secret, et le code lui-même est protégé : 5 essais maximum, durée limitée, 10 essais par minute et par poste, code chiffré dans la base.
- **Correction proposée :** **aucune** ; je conseille de garder ce comportement, qui aide les agents à leur première connexion.

---

## Ce qui a été vérifié et qui est correct

- **Droits :** chaque page sensible exige un droit précis. Un responsable de structure ne voit que son service. Un agent ne voit que ses propres formations, convocations, attestations et documents. Personne ne peut se donner un droit qu'il n'a pas, ni retirer le dernier administrateur, ni se désactiver lui-même.
- **Demandes de formation :** les agents d'un autre service envoyés « à la main » sont écartés (vérifié par un test existant).
- **Base de données :** aucune saisie n'est collée dans une requête SQL, donc pas d'« injection SQL ».
- **Affichage :** tout ce qui s'affiche est protégé, et aucun script n'est permis dans les pages (règle CSP stricte), ce qui bloque l'injection de script.
- **Formulaires :** protection contre les envois venus d'un autre site (jeton CSRF), et les champs non prévus sont ignorés.
- **Fichiers :** le type des documents est contrôlé par leur contenu. Ils sont rangés hors du dossier public. Les documents confidentiels exigent un droit à part. Le téléchargement des sauvegardes est réservé et limité à des noms de fichiers précis.
- **Exports Excel :** les cellules sont écrites comme du texte, donc une formule piégée ne s'exécute pas à l'ouverture.
- **Connexion :** même message que le compte existe ou non, même durée de réponse, nouvelle session à la connexion (contre le vol de session), essais limités, déconnexion après 30 minutes d'inactivité, cookies protégés.
- **Codes d'activation :** chiffrés dans la base, 5 essais maximum, durée limitée.
- **Secrets :** aucun mot de passe ni clé dans le code. Le jeu de démonstration est refusé en production.
- **En-têtes de sécurité :** l'application interdit l'affichage dans un cadre d'un autre site, interdit de deviner le type des fichiers, n'utilise ni caméra ni micro, et désactive le cache sur les pages connectées.

## Pour le serveur Ubuntu (rappel, au moment du déploiement)

Je vous donnerai les commandes au moment voulu ; vous les tapez vous-même :
- pare-feu (seuls les ports web et SSH ouverts) ;
- base MySQL avec un utilisateur dédié, jamais `root` ;
- droits des dossiers `storage` et `bootstrap/cache` limités au serveur web ;
- `.env` lisible seulement par le serveur web ;
- mises à jour automatiques de sécurité d'Ubuntu.

---

## Décisions à prendre

| Point | Proposition | Votre choix |
|---|---|---|
| F1 | Corriger (1 ligne + 1 test) | à décider |
| F2 | Ne pas ajouter de limite par compte pour l'instant | à décider |
| F3 | Garder tel quel | à décider |
| E1 à E3, M1 à M3 | Jour J, sur le serveur (déjà dans la liste « À faire ») | — |
| M4 | Lancer `composer audit` sur le poste | à faire |
