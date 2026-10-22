<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PÁGINA PRINCIPAL
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')
    ->name('home');


/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::livewire('/dashboard', 'pages::dashboard.index')
    ->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | BATERÍAS
    |--------------------------------------------------------------------------
    */

    Route::livewire('/baterias', 'pages::baterias.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('baterias.index');


    /*
    |--------------------------------------------------------------------------
    | INVENTARIO
    |--------------------------------------------------------------------------
    */

    Route::livewire('/inventario', 'pages::inventario.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('inventario.index');


    /*
    |--------------------------------------------------------------------------
    | INGRESOS
    |--------------------------------------------------------------------------
    */

    Route::livewire('/ingresos', 'pages::ingresos.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('ingresos.index');


    /*
    |--------------------------------------------------------------------------
    | SALIDAS
    |--------------------------------------------------------------------------
    */

    Route::livewire('/salidas', 'pages::salidas.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('salidas.index');
    /*
    |--------------------------------------------------------------------------
    | TRANSFERENCIAS
    |--------------------------------------------------------------------------
    */

    Route::livewire('/transferencias', 'pages::transferencias.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('transferencias.index');
    /*
    |--------------------------------------------------------------------------
    | KARDEX
    |--------------------------------------------------------------------------
    */
    Route::livewire('/kardex', 'pages::kardex.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('kardex.index');

    /*
    |--------------------------------------------------------------------------
    | REPORTES
    |--------------------------------------------------------------------------
    */

    Route::livewire('/reportes', 'pages::reportes.index')
        ->middleware(
            'rol:super-administrador,administrador,encargado-sede,almacen,supervisor,consulta'
        )
        ->name('reportes.index');
    /*
    |--------------------------------------------------------------------------
    | CLIENTES
    |--------------------------------------------------------------------------
    */

    Route::livewire('/clientes', 'pages::clientes.index')
        ->middleware(
            'rol:super-administrador,administrador'
        )
        ->name('clientes.index');
    /*
    |--------------------------------------------------------------------------
    | SEDES
    |--------------------------------------------------------------------------
    */

    Route::livewire('/sedes', 'pages::sedes.index')
        ->middleware(
            'rol:super-administrador,administrador'
        )
        ->name('sedes.index');


    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */

    Route::livewire('/usuarios', 'pages::usuarios.index')
        ->middleware(
            'rol:super-administrador,administrador'
        )
        ->name('usuarios.index');

});


/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE USUARIO
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';