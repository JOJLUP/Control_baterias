<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoDetalle extends Model
{
    protected $fillable = [
        'ingreso_id',
        'bateria_id',
        'cantidad',
        'costo_unitario',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_id' => 'integer',
            'bateria_id' => 'integer',
            'cantidad' => 'integer',
            'costo_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    public function bateria(): BelongsTo
    {
        return $this->belongsTo(Bateria::class);
    }
}