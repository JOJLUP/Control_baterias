<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | SEDE
            |--------------------------------------------------------------------------
            |
            | Sede donde ingresará físicamente la mercadería.
            |
            */

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            |
            | Usuario que registró el ingreso.
            |
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
            | DOCUMENTO
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Factura
            | Boleta
            | Guía
            | Nota de ingreso
            | Sin documento
            |
            */

            $table->string('tipo_documento', 50)
                ->nullable();

            $table->string('numero_documento', 100)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | PROVEEDOR
            |--------------------------------------------------------------------------
            */

            $table->string('proveedor', 150)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | OBSERVACIONES
            |--------------------------------------------------------------------------
            */

            $table->text('observaciones')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TOTAL DEL INGRESO
            |--------------------------------------------------------------------------
            */

            $table->decimal('total', 14, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            |
            | true  = ingreso registrado
            | false = ingreso anulado
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
            $table->index('numero_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos');
    }
};