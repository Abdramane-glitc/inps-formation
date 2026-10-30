<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** Filtres communs (année, direction, service, domaine, type) validés. */
    protected function filtres(Request $r): array
    {
        return array_filter($r->validate([
            'annee' => ['nullable', 'integer', 'between:2000,2100'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'domaine_id' => ['nullable', 'integer', 'exists:domaines,id'],
            'type' => ['nullable', 'in:interne,externe,mixte'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]), fn ($v) => $v !== null && $v !== '');
    }
}
