<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    protected $fillable = [
        'bateria_id',
        'sede_id',
        'stock_actual',
        'stock_minimo',
        'costo_promedio',
    ];

    protected function casts(): array
    {
        return [
            'bateria_id' => 'integer',
            'sede_id' => 'integer',
            'stock_actual' => 'integer',
            'stock_minimo' => 'integer',
            'costo_promedio' => 'decimal:2',
        ];
    }

    public function bateria(): BelongsTo
    {
        return $this->belongsTo(Bateria::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}