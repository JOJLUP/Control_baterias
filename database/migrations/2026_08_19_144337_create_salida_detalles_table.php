<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salida_detalles', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | SALIDA
            |--------------------------------------------------------------------------
            */

            $table->foreignId('salida_id')
                ->constrained('salidas')
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
            | Guardaremos aquí el costo promedio que tenía la batería
            | al momento de realizar la salida.
            |
            | Esto será importante después para calcular rentabilidad.
            |
            */

            $table->decimal('costo_unitario', 12, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | PRECIO UNITARIO
            |--------------------------------------------------------------------------
            |
            | Precio al que se vende o valoriza la batería.
            | Para merma, garantía u otro movimiento puede ser 0.
            |
            */

            $table->decimal('precio_unitario', 12, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | SUBTOTAL
            |--------------------------------------------------------------------------
            |
            | cantidad × precio_unitario
            |
            */

            $table->decimal('subtotal', 14, 2)
                ->default(0);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | ÍNDICES
            |--------------------------------------------------------------------------
            */

            $table->index('bateria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salida_detalles');
    }
};