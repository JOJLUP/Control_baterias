<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | SEDE
            |--------------------------------------------------------------------------
            | Sede desde donde salen físicamente las baterías.
            */

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            | Usuario que registra la salida.
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
            | TIPO DE SALIDA
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | Venta
            | Garantía
            | Merma
            | Uso interno
            | Ajuste
            |
            */

            $table->string('tipo_salida', 50);

            /*
            |--------------------------------------------------------------------------
            | DOCUMENTO
            |--------------------------------------------------------------------------
            */

            $table->string('numero_documento', 100)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | CLIENTE / DESTINO
            |--------------------------------------------------------------------------
            */

            $table->string('cliente_destino', 150)
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
            | TOTAL
            |--------------------------------------------------------------------------
            |
            | Importe total de la salida.
            | En una venta será el importe vendido.
            |
            */

            $table->decimal('total', 14, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            |
            | true  = salida registrada
            | false = salida anulada
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
            $table->index('tipo_salida');
            $table->index('numero_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};