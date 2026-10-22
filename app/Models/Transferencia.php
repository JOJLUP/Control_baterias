<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transferencia extends Model
{
    protected $fillable = [
        'sede_origen_id',
        'sede_destino_id',
        'user_id',
        'fecha',
        'numero',
        'observaciones',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'sede_origen_id' => 'integer',
            'sede_destino_id' => 'integer',
            'user_id' => 'integer',
            'fecha' => 'date',
            'estado' => 'boolean',
        ];
    }

    public function sedeOrigen(): BelongsTo
    {
        return $this->belongsTo(
            Sede::class,
            'sede_origen_id'
        );
    }

    public function sedeDestino(): BelongsTo
    {
        return $this->belongsTo(
            Sede::class,
            'sede_destino_id'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            TransferenciaDetalle::class
        );
    }
}