<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Super Administrador',
                'slug' => 'super-administrador',
                'descripcion' => 'Acceso completo a todos los módulos, sedes y configuraciones del sistema.',
                'estado' => true,
            ],
            [
                'nombre' => 'Administrador',
                'slug' => 'administrador',
                'descripcion' => 'Administra usuarios, baterías, movimientos y reportes.',
                'estado' => true,
            ],
            [
                'nombre' => 'Encargado de Sede',
                'slug' => 'encargado-sede',
                'descripcion' => 'Administra las operaciones correspondientes a una sede.',
                'estado' => true,
            ],
            [
                'nombre' => 'Almacén',
                'slug' => 'almacen',
                'descripcion' => 'Registra ingresos, salidas y movimientos de baterías.',
                'estado' => true,
            ],
            [
                'nombre' => 'Supervisor',
                'slug' => 'supervisor',
                'descripcion' => 'Supervisa inventario, movimientos y reportes.',
                'estado' => true,
            ],
            [
                'nombre' => 'Consulta',
                'slug' => 'consulta',
                'descripcion' => 'Acceso únicamente para visualizar información autorizada.',
                'estado' => true,
            ],
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(
                ['slug' => $rol['slug']],
                $rol
            );
        }
    }
}