<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);

            $table->string('departamento', 100);
            $table->string('provincia', 100);
            $table->string('distrito', 100);

            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('responsable', 150)->nullable();

            $table->boolean('estado')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};