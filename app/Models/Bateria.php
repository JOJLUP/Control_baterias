<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bateria extends Model
{
    protected $fillable = [
        'codigo',
        'marca',
        'modelo',
        'tipo_vehiculo',
        'voltaje',
        'capacidad_ah',
        'cca',
        'tecnologia',
        'polaridad',
        'garantia_meses',
        'costo_referencial',
        'precio_venta',
        'observaciones',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'voltaje' => 'decimal:2',
            'capacidad_ah' => 'decimal:2',
            'costo_referencial' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'cca' => 'integer',
            'garantia_meses' => 'integer',
            'estado' => 'boolean',
        ];
    }
    public function inventarios(): HasMany
{
    return $this->hasMany(Inventario::class);
}
}