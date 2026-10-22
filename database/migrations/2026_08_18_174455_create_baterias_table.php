<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baterias', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('codigo', 30)->unique();
            $table->string('marca', 100);
            $table->string('modelo', 100);

            // Aplicación
            $table->string('tipo_vehiculo', 50);

            // Especificaciones eléctricas
            $table->decimal('voltaje', 6, 2);
            $table->decimal('capacidad_ah', 8, 2);
            $table->unsignedInteger('cca')->nullable();

            // Características adicionales
            $table->string('tecnologia', 50)->nullable();
            $table->string('polaridad', 50)->nullable();
            $table->unsignedInteger('garantia_meses')->nullable();

            // Valores económicos de referencia
            $table->decimal('costo_referencial', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);

            // Información adicional
            $table->text('observaciones')->nullable();

            // Activo / inactivo dentro del catálogo
            $table->boolean('estado')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baterias');
    }
};