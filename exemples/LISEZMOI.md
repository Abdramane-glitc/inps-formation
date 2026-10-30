# Exemples d'importation (données entièrement fictives)

| Fichier | Usage |
|---|---|
| `modele-import-agents.csv` | Modèle vide (en-têtes) pour l'import du personnel |
| `modele-import-historique.csv` | Modèle vide pour la reprise de l'historique des formations |
| `demo-import-agents.csv` | 20 agents fictifs + 3 lignes volontairement fautives (doublon, nom manquant, service inconnu) |
| `demo-import-historique.csv` | Historique fictif (à importer APRÈS les agents) + 1 ligne au matricule inconnu |

Les fichiers de démonstration utilisent les codes de directions, services et domaines créés par le jeu de
démonstration (`php artisan db:seed --class=DemoSeeder`). Séparateur : point-virgule ; encodage UTF-8.
Les modèles sont aussi téléchargeables dans l'application (menu Importations).

Ne jamais déposer de fichier contenant des données réelles de l'INPS dans le dépôt du code.
