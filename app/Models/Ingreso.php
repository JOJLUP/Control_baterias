<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingreso extends Model
{
    protected $fillable = [
        'sede_id',
        'user_id',
        'fecha',
        'tipo_documento',
        'numero_documento',
        'proveedor',
        'observaciones',
        'total',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'sede_id' => 'integer',
            'user_id' => 'integer',
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

    public function detalles(): HasMany
    {
        return $this->hasMany(IngresoDetalle::class);
    }
}