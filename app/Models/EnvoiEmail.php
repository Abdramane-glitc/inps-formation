<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvoiEmail extends Model
{
    protected $table = 'envois_email';

    protected $fillable = ['user_id', 'sujet', 'statut', 'erreur'];

    protected function casts(): array
    {
        return [];
    }

    public const UPDATED_AT = null;
}
