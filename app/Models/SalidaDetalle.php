<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalidaDetalle extends Model
{
    protected $fillable = [
        'salida_id',
        'bateria_id',
        'cantidad',
        'costo_unitario',
        'precio_unitario',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'salida_id' => 'integer',
            'bateria_id' => 'integer',
            'cantidad' => 'integer',
            'costo_unitario' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class);
    }

    public function bateria(): BelongsTo
    {
        return $this->belongsTo(Bateria::class);
    }
}