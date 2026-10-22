<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'departamento',
        'provincia',
        'distrito',
        'direccion',
        'telefono',
        'responsable',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }
    public function inventarios(): HasMany
{
    return $this->hasMany(Inventario::class);
}
}