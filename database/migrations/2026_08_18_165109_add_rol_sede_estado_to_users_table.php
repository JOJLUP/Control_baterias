<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->foreignId('rol_id')
                ->nullable()
                ->constrained('rols')
                ->restrictOnDelete();

            $table->foreignId('sede_id')
                ->nullable()
                ->constrained('sedes')
                ->restrictOnDelete();

            $table->string('telefono', 30)
                ->nullable();

            $table->boolean('estado')
                ->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['rol_id']);
            $table->dropForeign(['sede_id']);

            $table->dropColumn([
                'rol_id',
                'sede_id',
                'telefono',
                'estado',
            ]);
        });
    }
};