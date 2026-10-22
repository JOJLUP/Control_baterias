<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | SEDE DE ORIGEN
            |--------------------------------------------------------------------------
            | Sede desde donde salen las baterías.
            */

            $table->foreignId('sede_origen_id')
                ->constrained('sedes')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | SEDE DE DESTINO
            |--------------------------------------------------------------------------
            | Sede que recibirá las baterías.
            */

            $table->foreignId('sede_destino_id')
                ->constrained('sedes')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            | Usuario que registra la transferencia.
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | FECHA
            |--------------------------------------------------------------------------
            */

            $table->date('fecha');

            /*
            |--------------------------------------------------------------------------
            | NÚMERO DE TRANSFERENCIA
            |--------------------------------------------------------------------------
            | Ejemplo:
            | TRF-000001
            */

            $table->string('numero', 50)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | OBSERVACIONES
            |--------------------------------------------------------------------------
            */

            $table->text('observaciones')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            |
            | true  = transferencia registrada
            | false = transferencia anulada
            |
            */

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | ÍNDICES
            |--------------------------------------------------------------------------
            */

            $table->index('fecha');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};