<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolMiddleware
{
    /**
     * Controla el acceso según el rol del usuario.
     *
     * Ejemplos:
     *
     * rol:super-administrador
     *
     * rol:super-administrador,administrador
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        /*
        |--------------------------------------------------------------------------
        | COMPROBAR USUARIO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR ESTADO DEL USUARIO
        |--------------------------------------------------------------------------
        |
        | Aunque el usuario ya tenga una sesión abierta, si posteriormente
        | un administrador lo desactiva, no podrá seguir entrando a módulos
        | protegidos con este middleware.
        |
        */

        if (! $usuario->estado) {
            abort(
                403,
                'Tu cuenta se encuentra desactivada.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CARGAR ROL
        |--------------------------------------------------------------------------
        */

        $usuario->loadMissing('rol');

        if (! $usuario->rol) {
            abort(
                403,
                'Tu usuario no tiene un rol asignado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR ESTADO DEL ROL
        |--------------------------------------------------------------------------
        */

        if (! $usuario->rol->estado) {
            abort(
                403,
                'El rol asignado a tu usuario se encuentra desactivado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SUPER ADMINISTRADOR
        |--------------------------------------------------------------------------
        |
        | El Super Administrador tendrá acceso completo a todos los módulos
        | que utilicen este middleware.
        |
        */

        if ($usuario->rol->slug === 'super-administrador') {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR ROLES PERMITIDOS
        |--------------------------------------------------------------------------
        */

        if (! in_array(
            $usuario->rol->slug,
            $roles,
            true
        )) {
            abort(
                403,
                'No tienes permiso para acceder a este módulo.'
            );
        }

        return $next($request);
    }
}