<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RELACIONES
            |--------------------------------------------------------------------------
            */

            $table->foreignId('bateria_id')
                ->constrained('baterias')
                ->restrictOnDelete();

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | STOCK
            |--------------------------------------------------------------------------
            */

            $table->integer('stock_actual')
                ->default(0);

            $table->integer('stock_minimo')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | COSTO PROMEDIO
            |--------------------------------------------------------------------------
            |
            | Más adelante se actualizará automáticamente con los ingresos
            | de mercadería.
            |
            */

            $table->decimal('costo_promedio', 12, 2)
                ->default(0);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | UNA BATERÍA SOLO PUEDE APARECER UNA VEZ POR SEDE
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'bateria_id',
                'sede_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};