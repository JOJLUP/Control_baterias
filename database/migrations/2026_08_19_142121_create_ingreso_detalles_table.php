<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingreso_detalles', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | INGRESO
            |--------------------------------------------------------------------------
            |
            | Cada detalle pertenece a una cabecera de ingreso.
            |
            */

            $table->foreignId('ingreso_id')
                ->constrained('ingresos')
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
            */

            $table->decimal('costo_unitario', 12, 2);

            /*
            |--------------------------------------------------------------------------
            | SUBTOTAL
            |--------------------------------------------------------------------------
            |
            | cantidad × costo_unitario
            |
            */

            $table->decimal('subtotal', 14, 2);

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
        Schema::dropIfExists('ingreso_detalles');
    }
};