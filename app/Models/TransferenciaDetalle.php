<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaDetalle extends Model
{
    protected $fillable = [
        'transferencia_id',
        'bateria_id',
        'cantidad',
        'costo_unitario',
    ];

    protected function casts(): array
    {
        return [
            'transferencia_id' => 'integer',
            'bateria_id' => 'integer',
            'cantidad' => 'integer',
            'costo_unitario' => 'decimal:2',
        ];
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(
            Transferencia::class
        );
    }

    public function bateria(): BelongsTo
    {
        return $this->belongsTo(
            Bateria::class
        );
    }
}