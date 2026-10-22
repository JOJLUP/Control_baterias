<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | IDENTIFICACIÓN INTERNA
            |--------------------------------------------------------------------------
            */

            $table->string('codigo', 20)->unique();

            /*
            |--------------------------------------------------------------------------
            | DOCUMENTO
            |--------------------------------------------------------------------------
            |
            | Valores que manejaremos inicialmente:
            | DNI
            | RUC
            | CE
            | SIN_DOCUMENTO
            |
            */

            $table->string('tipo_documento', 20)
                ->default('DNI');

            $table->string('numero_documento', 20)
                ->nullable()
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | DATOS DEL CLIENTE
            |--------------------------------------------------------------------------
            */

            $table->string('nombre', 150);

            $table->string('telefono', 20)
                ->nullable();

            $table->string('email', 150)
                ->nullable();

            $table->string('direccion', 255)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | INFORMACIÓN ADICIONAL
            |--------------------------------------------------------------------------
            */

            $table->text('observaciones')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | ÍNDICES
            |--------------------------------------------------------------------------
            */

            $table->index('nombre');
            $table->index('telefono');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};