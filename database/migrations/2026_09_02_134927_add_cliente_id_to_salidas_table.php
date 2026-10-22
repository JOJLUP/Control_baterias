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
        Schema::table('salidas', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | CLIENTE DE LA VENTA / SALIDA
            |--------------------------------------------------------------------------
            |
            | Es nullable porque:
            |
            | - Las salidas antiguas no tienen cliente relacionado.
            | - Algunas salidas pueden no corresponder a una venta.
            | - Si el cliente fuese eliminado, conservamos la salida.
            |
            */

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salidas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};