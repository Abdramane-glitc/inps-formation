<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Où un élément de référentiel est-il utilisé ? (version 2.1.0)
 * Les références sont lues dans la structure même de la base (clés étrangères) : aucune table n'est oubliée,
 * y compris celles ajoutées plus tard. Sert à n'autoriser la suppression que d'un élément utilisé nulle part.
 */
final class Utilisations
{
    private const LIBELLES = [
        'agents' => 'agent(s)', 'users' => 'compte(s)', 'services' => 'service(s)', 'besoins_formation' => 'demande(s)',
        'actions_formation' => 'formation(s)', 'documents' => 'document(s)', 'plan_annuel_lignes' => 'ligne(s) du plan annuel',
        'plans_annuels' => 'plan(s) annuel(s)',
    ];

    /** @var array<string, list<array{0:string,1:string}>> */
    private static array $cache = [];

    /** Colonnes (table, colonne) qui pointent vers la table donnée. @return list<array{0:string,1:string}> */
    public static function references(string $table): array
    {
        if (isset(self::$cache[$table])) {
            return self::$cache[$table];
        }
        $refs = [];
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("select name from sqlite_master where type = 'table'") as $t) {
                foreach (DB::select('pragma foreign_key_list("'.str_replace('"', '', $t->name).'")') as $fk) {
                    if ($fk->table === $table) {
                        $refs[] = [$t->name, $fk->from];
                    }
                }
            }
        } else {
            foreach (DB::select('select TABLE_NAME as t, COLUMN_NAME as c from information_schema.KEY_COLUMN_USAGE where REFERENCED_TABLE_SCHEMA = database() and REFERENCED_TABLE_NAME = ?', [$table]) as $r) {
                $refs[] = [$r->t, $r->c];
            }
        }

        return self::$cache[$table] = $refs;
    }

    /**
     * Nombre d'utilisations de chaque élément, par type d'élément utilisateur.
     *
     * @param  list<int>  $ids
     * @param  array<string, string>  $parValeur  références sans clé étrangère : table => colonne (comparées à $valeurs)
     * @param  array<int, int|string>  $valeurs  id => valeur comparée (ex. l'année d'une année de référence)
     * @return array<int, array<string,int>> id => [libellé => nombre]
     */
    public static function compter(string $table, array $ids, array $parValeur = [], array $valeurs = []): array
    {
        $res = array_fill_keys($ids, []);
        if ($ids === []) {
            return $res;
        }
        $ajouter = function (string $t, int $id, int $n) use (&$res) {
            if ($n > 0) {
                $lib = self::LIBELLES[$t] ?? 'élément(s) ('.$t.')';
                $res[$id][$lib] = ($res[$id][$lib] ?? 0) + $n;
            }
        };
        foreach (self::references($table) as [$t, $c]) {
            foreach (DB::table($t)->whereIn($c, $ids)->selectRaw("$c as id, count(*) as n")->groupBy($c)->get() as $l) {
                $ajouter($t, (int) $l->id, (int) $l->n);
            }
        }
        foreach ($parValeur as $t => $c) {
            $inverse = array_flip(array_map('strval', $valeurs));
            foreach (DB::table($t)->whereIn($c, array_values($valeurs))->selectRaw("$c as v, count(*) as n")->groupBy($c)->get() as $l) {
                if (isset($inverse[(string) $l->v])) {
                    $ajouter($t, (int) $inverse[(string) $l->v], (int) $l->n);
                }
            }
        }

        return $res;
    }

    /** « 12 agent(s), 3 service(s) » */
    public static function texte(array $usages): string
    {
        return collect($usages)->map(fn ($n, $lib) => "$n $lib")->implode(', ');
    }
}
