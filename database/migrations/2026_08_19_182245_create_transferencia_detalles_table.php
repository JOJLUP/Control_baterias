<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencia_detalles', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | TRANSFERENCIA
            |--------------------------------------------------------------------------
            */

            $table->foreignId('transferencia_id')
                ->constrained('transferencias')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | BATERÍA
            |--------------------------------------------------------------------------
            */

            $table->foreignId('bateria_id')
                ->constrained('baterias')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CANTIDAD
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('cantidad');

            /*
            |--------------------------------------------------------------------------
            | COSTO UNITARIO
            |--------------------------------------------------------------------------
            |
            | Se guardará el costo promedio de la batería
            | en la sede de origen al momento de transferir.
            |
            */

            $table->decimal('costo_unitario', 12, 2)
                ->default(0);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | EVITAR BATERÍA REPETIDA
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'transferencia_id',
                'bateria_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencia_detalles');
    }
};