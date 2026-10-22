<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-white dark:bg-zinc-800">

        @php
            /*
            |--------------------------------------------------------------------------
            | USUARIO Y ROL ACTUAL
            |--------------------------------------------------------------------------
            */

            $usuarioActual = auth()->user();

            $usuarioActual?->loadMissing([
                'rol',
                'sede',
            ]);

            $rolActual = $usuarioActual?->rol?->slug;

            /*
            |--------------------------------------------------------------------------
            | PERMISOS DEL MENÚ
            |--------------------------------------------------------------------------
            */

            $puedeAdministrar = in_array(
                $rolActual,
                [
                    'super-administrador',
                    'administrador',
                ],
                true
            );
        @endphp


        {{-- ============================================================= --}}
        {{-- SIDEBAR --}}
        {{-- ============================================================= --}}

        <flux:sidebar
            sticky
            collapsible="mobile"
            class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
        >

            {{-- LOGO --}}

            <flux:sidebar.header>

                <x-app-logo
                    :sidebar="true"
                    href="{{ route('dashboard') }}"
                    wire:navigate
                />

                <flux:sidebar.collapse class="lg:hidden" />

            </flux:sidebar.header>


            {{-- ========================================================= --}}
            {{-- NAVEGACIÓN --}}
            {{-- ========================================================= --}}

            <flux:sidebar.nav>


                {{-- PRINCIPAL --}}

                <flux:sidebar.group
                    heading="Principal"
                    class="grid"
                >

                    <flux:sidebar.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                    >
                        Dashboard
                    </flux:sidebar.item>

                </flux:sidebar.group>

                <flux:sidebar.group
    heading="Operaciones"
    class="grid"
>
    <flux:sidebar.item
        icon="bolt"
        :href="route('baterias.index')"
        :current="request()->routeIs('baterias.*')"
        wire:navigate
    >
        Baterías
    </flux:sidebar.item>
</flux:sidebar.group>
<flux:sidebar.item
    icon="archive-box"
    :href="route('inventario.index')"
    :current="request()->routeIs('inventario.*')"
    wire:navigate
>
    Inventario
</flux:sidebar.item>

<flux:sidebar.item
    icon="arrow-down-tray"
    :href="route('ingresos.index')"
    :current="request()->routeIs('ingresos.*')"
    wire:navigate
>
    Ingresos
</flux:sidebar.item>

<flux:sidebar.item
    icon="arrow-up-tray"
    :href="route('salidas.index')"
    :current="request()->routeIs('salidas.*')"
    wire:navigate
>
    Salidas
</flux:sidebar.item>

<flux:sidebar.item
    icon="arrows-right-left"
    :href="route('transferencias.index')"
    :current="request()->routeIs('transferencias.*')"
    wire:navigate
>
    Transferencias
</flux:sidebar.item>

<flux:sidebar.item
    icon="list-bullet"
    :href="route('kardex.index')"
    :current="request()->routeIs('kardex.*')"
    wire:navigate
>
    Kardex
</flux:sidebar.item>

<flux:sidebar.item
    icon="chart-bar"
    :href="route('reportes.index')"
    :current="request()->routeIs('reportes.*')"
    wire:navigate
>
    Reportes
</flux:sidebar.item>


                {{-- ===================================================== --}}
                {{-- ADMINISTRACIÓN --}}
                {{-- Solo Super Administrador y Administrador --}}
                {{-- ===================================================== --}}

                @if ($puedeAdministrar)

                    <flux:sidebar.group
                        heading="Administración"
                        class="grid"
                    >
                        {{-- CLIENTES --}}
                        <flux:sidebar.item
                            icon="identification"
                            :href="route('clientes.index')"
                            :current="request()->routeIs('clientes.*')"
                            wire:navigate
                        >
                            Clientes
                        </flux:sidebar.item>

                        {{-- SEDES --}}

                        <flux:sidebar.item
                            icon="building-office-2"
                            :href="route('sedes.index')"
                            :current="request()->routeIs('sedes.*')"
                            wire:navigate
                        >
                            Sedes
                        </flux:sidebar.item>


                        {{-- USUARIOS --}}

                        <flux:sidebar.item
                            icon="users"
                            :href="route('usuarios.index')"
                            :current="request()->routeIs('usuarios.*')"
                            wire:navigate
                        >
                            Usuarios
                        </flux:sidebar.item>

                    </flux:sidebar.group>

                @endif

            </flux:sidebar.nav>


            {{-- EMPUJA EL PERFIL HACIA ABAJO --}}

            <flux:spacer />


            {{-- ========================================================= --}}
            {{-- INFORMACIÓN DEL USUARIO --}}
            {{-- ========================================================= --}}

            <div
                class="mx-3 mb-3 rounded-lg border border-zinc-200
                       bg-white p-3
                       dark:border-zinc-700 dark:bg-zinc-800"
            >

                <div class="text-sm font-semibold text-zinc-900 dark:text-white">
                    {{ $usuarioActual->name }}
                </div>

                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">

                    {{ $usuarioActual->rol?->nombre ?? 'Sin rol asignado' }}

                </div>


                @if ($usuarioActual->sede)

                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $usuarioActual->sede->nombre }}
                    </div>

                @elseif ($rolActual === 'super-administrador')

                    <div class="mt-1 text-xs text-blue-500">
                        Todas las sedes
                    </div>

                @endif

            </div>


            {{-- MENÚ DEL USUARIO EN ESCRITORIO --}}

            <x-desktop-user-menu
                class="hidden lg:block"
                :name="$usuarioActual->name"
            />

        </flux:sidebar>


        {{-- ============================================================= --}}
        {{-- MENÚ MÓVIL --}}
        {{-- ============================================================= --}}

        <flux:header class="lg:hidden">

            <flux:sidebar.toggle
                class="lg:hidden"
                icon="bars-2"
                inset="left"
            />

            <flux:spacer />


            <flux:dropdown
                position="top"
                align="end"
            >

                <flux:profile
                    :initials="$usuarioActual->initials()"
                    icon-trailing="chevron-down"
                />


                <flux:menu>

                    {{-- DATOS DEL USUARIO --}}

                    <flux:menu.radio.group>

                        <div class="p-0 text-sm font-normal">

                            <div
                                class="flex items-center gap-2
                                       px-1 py-1.5
                                       text-start text-sm"
                            >

                                <flux:avatar
                                    :name="$usuarioActual->name"
                                    :initials="$usuarioActual->initials()"
                                />


                                <div
                                    class="grid flex-1
                                           text-start text-sm
                                           leading-tight"
                                >

                                    <flux:heading class="truncate">
                                        {{ $usuarioActual->name }}
                                    </flux:heading>

                                    <flux:text class="truncate">
                                        {{ $usuarioActual->email }}
                                    </flux:text>

                                    <flux:text class="truncate text-xs">
                                        {{ $usuarioActual->rol?->nombre ?? 'Sin rol' }}
                                    </flux:text>


                                    @if ($usuarioActual->sede)

                                        <flux:text class="truncate text-xs">
                                            {{ $usuarioActual->sede->nombre }}
                                        </flux:text>

                                    @elseif ($rolActual === 'super-administrador')

                                        <flux:text class="truncate text-xs">
                                            Todas las sedes
                                        </flux:text>

                                    @endif

                                </div>

                            </div>

                        </div>

                    </flux:menu.radio.group>


                    <flux:menu.separator />


                    {{-- CONFIGURACIÓN --}}

                    <flux:menu.radio.group>

                        <flux:menu.item
                            :href="route('profile.edit')"
                            icon="cog"
                            wire:navigate
                        >
                            Configuración
                        </flux:menu.item>

                    </flux:menu.radio.group>


                    <flux:menu.separator />


                    {{-- CERRAR SESIÓN --}}

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                        class="w-full"
                    >

                        @csrf

                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            Cerrar sesión
                        </flux:menu.item>

                    </form>

                </flux:menu>

            </flux:dropdown>

        </flux:header>


        {{-- ============================================================= --}}
        {{-- CONTENIDO DE LA PÁGINA --}}
        {{-- ============================================================= --}}

        {{ $slot }}


        {{-- ============================================================= --}}
        {{-- NOTIFICACIONES --}}
        {{-- ============================================================= --}}

        @persist('toast')

            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>

        @endpersist


        @fluxScripts

    </body>
</html>