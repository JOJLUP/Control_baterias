<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salida extends Model
{
    protected $fillable = [
        'sede_id',
        'user_id',
        'cliente_id',
        'fecha',
        'tipo_salida',
        'numero_documento',
        'cliente_destino',
        'observaciones',
        'total',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'sede_id' => 'integer',
            'user_id' => 'integer',
            'cliente_id' => 'integer',
            'fecha' => 'date',
            'total' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SalidaDetalle::class);
    }
}