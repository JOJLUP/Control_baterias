<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'tipo_documento',
        'numero_documento',
        'nombre',
        'telefono',
        'email',
        'direccion',
        'observaciones',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function salidas(): HasMany
    {
    return $this->hasMany(Salida::class);
    }
}