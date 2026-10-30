<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RappelEnvoye extends Model
{
    protected $table = 'rappels_envoyes';

    protected $fillable = ['cle'];

    protected function casts(): array
    {
        return [];
    }

    public const UPDATED_AT = null;
}
