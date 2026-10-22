<?php

use App\Models\Rol;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $usuarioId = null;

    public string $name = '';
    public string $email = '';
    public string $telefono = '';
    public string $rol_id = '';
    public string $sede_id = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $buscar = '';

    /*
    |--------------------------------------------------------------------------
    | GUARDAR / ACTUALIZAR
    |--------------------------------------------------------------------------
    */
    public function guardar(): void
    {
        $reglas = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($this->usuarioId),
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:30',
            ],

            'rol_id' => [
                'required',
                'integer',
                'exists:rols,id',
            ],

            'sede_id' => [
                'nullable',
                'integer',
                'exists:sedes,id',
            ],
        ];

        if ($this->usuarioId) {
            $reglas['password'] = [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ];

            $reglas['password_confirmation'] = [
                'nullable',
                'string',
                'min:8',
            ];
        } else {
            $reglas['password'] = [
                'required',
                'string',
                'min:8',
                'confirmed',
            ];

            $reglas['password_confirmation'] = [
                'required',
                'string',
                'min:8',
            ];
        }

        $this->validate($reglas);

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR ROL
        |--------------------------------------------------------------------------
        */

        $rol = Rol::query()
            ->whereKey((int) $this->rol_id)
            ->where('estado', true)
            ->first();

        if (! $rol) {
            $this->addError(
                'rol_id',
                'El rol seleccionado no está disponible.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR SEDE
        |--------------------------------------------------------------------------
        |
        | El Super Administrador no pertenece a una sede específica.
        | Los demás usuarios sí deben tener una sede.
        |
        */

        $sedeId = null;

        if ($rol->slug !== 'super-administrador') {

            if ($this->sede_id === '') {
                $this->addError(
                    'sede_id',
                    'Debes seleccionar una sede.'
                );

                return;
            }

            $sede = Sede::query()
                ->whereKey((int) $this->sede_id)
                ->where('estado', true)
                ->first();

            if (! $sede) {
                $this->addError(
                    'sede_id',
                    'La sede seleccionada no está activa.'
                );

                return;
            }

            $sedeId = $sede->id;
        }

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR USUARIO
        |--------------------------------------------------------------------------
        */

        if ($this->usuarioId) {

            $usuario = User::findOrFail($this->usuarioId);

            $usuario->name = $this->name;
            $usuario->email = $this->email;
            $usuario->telefono = $this->telefono ?: null;
            $usuario->rol_id = $rol->id;
            $usuario->sede_id = $sedeId;

            if ($this->password !== '') {
                $usuario->password = $this->password;
            }

            $usuario->save();

            session()->flash(
                'mensaje',
                'Usuario actualizado correctamente.'
            );

        /*
        |--------------------------------------------------------------------------
        | CREAR USUARIO
        |--------------------------------------------------------------------------
        */

        } else {

            $usuario = new User();

            $usuario->name = $this->name;
            $usuario->email = $this->email;
            $usuario->email_verified_at = now();
            $usuario->telefono = $this->telefono ?: null;
            $usuario->rol_id = $rol->id;
            $usuario->sede_id = $sedeId;
            $usuario->password = $this->password;
            $usuario->estado = true;

            $usuario->save();

            session()->flash(
                'mensaje',
                'Usuario registrado correctamente.'
            );
        }

        $this->limpiarFormulario();
    }

    /*
    |--------------------------------------------------------------------------
    | EDITAR
    |--------------------------------------------------------------------------
    */
    public function editar(int $id): void
    {
        $usuario = User::findOrFail($id);

        $this->usuarioId = $usuario->id;

        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->telefono = $usuario->telefono ?? '';
        $this->rol_id = $usuario->rol_id
            ? (string) $usuario->rol_id
            : '';

        $this->sede_id = $usuario->sede_id
            ? (string) $usuario->sede_id
            : '';

        $this->password = '';
        $this->password_confirmation = '';

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | CANCELAR
    |--------------------------------------------------------------------------
    */
    public function cancelarEdicion(): void
    {
        $this->limpiarFormulario();
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVAR / DESACTIVAR
    |--------------------------------------------------------------------------
    */
    public function cambiarEstado(int $id): void
    {
        if ((int) auth()->id() === $id) {
            session()->flash(
                'error',
                'No puedes desactivar tu propia cuenta.'
            );

            return;
        }

        $usuario = User::findOrFail($id);

        $usuario->estado = ! $usuario->estado;

        $usuario->save();

        session()->flash(
            'mensaje',
            $usuario->estado
                ? 'Usuario activado correctamente.'
                : 'Usuario desactivado correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR
    |--------------------------------------------------------------------------
    */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'usuarioId',
            'name',
            'email',
            'telefono',
            'rol_id',
            'sede_id',
            'password',
            'password_confirmation',
        ]);

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | ROLES
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function roles()
    {
        return Rol::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SEDES
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function sedes()
    {
        return Sede::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function usuarios()
    {
        return User::query()
            ->with([
                'rol',
                'sede',
            ])
            ->when($this->buscar, function ($query) {

                $query->where(function ($q) {

                    $q->where(
                        'name',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhere(
                        'email',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhere(
                        'telefono',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhereHas('rol', function ($rol) {
                        $rol->where(
                            'nombre',
                            'ilike',
                            '%' . $this->buscar . '%'
                        );
                    })

                    ->orWhereHas('sede', function ($sede) {
                        $sede->where(
                            'nombre',
                            'ilike',
                            '%' . $this->buscar . '%'
                        );
                    });
                });
            })
            ->orderBy('name')
            ->get();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- TÍTULO --}}

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Usuarios
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Administración de usuarios, roles y sedes del sistema.
        </p>
    </div>


    {{-- MENSAJES --}}

    @if (session('mensaje'))
        <div class="rounded-lg border border-green-200 bg-green-100 p-4 text-green-800">
            {{ session('mensaje') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-100 p-4 text-red-800">
            {{ session('error') }}
        </div>
    @endif


    {{-- FORMULARIO --}}

    <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

        <h2 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">
            {{ $usuarioId ? 'Editar usuario' : 'Nuevo usuario' }}
        </h2>

        <form wire:submit="guardar">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

                {{-- NOMBRE --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Nombre completo
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Juan Pérez"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('name')
                        <span class="mt-1 block text-sm text-red-600">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                {{-- CORREO --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        wire:model="email"
                        placeholder="usuario@empresa.com"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('email')
                        <span class="mt-1 block text-sm text-red-600">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                {{-- TELÉFONO --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Teléfono
                    </label>

                    <input
                        type="text"
                        wire:model="telefono"
                        placeholder="987654321"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('telefono')
                        <span class="mt-1 block text-sm text-red-600">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                {{-- ROL --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Rol
                    </label>

                    <select
                        wire:model="rol_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >
                        <option value="">
                            Seleccionar rol
                        </option>

                        @foreach ($this->roles as $rol)
                            <option value="{{ $rol->id }}">
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>

                    @error('rol_id')
                        <span class="mt-1 block text-sm text-red-600">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                {{-- SEDE --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Sede
                    </label>

                    <select
                        wire:model="sede_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >
                        <option value="">
                            Seleccionar sede
                        </option>

                        @foreach ($this->sedes as $sede)
                            <option value="{{ $sede->id }}">
                                {{ $sede->codigo }} - {{ $sede->nombre }}
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-xs text-gray-500">
                        Solo Super Administrador puede quedar sin sede.
                    </p>

                    @error('sede_id')
                        <span class="mt-1 block text-sm text-red-600">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                {{-- CONTRASEÑA --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Contraseña
                    </label>

                    <input
                        type="password"
                        wire:model="password"
                        placeholder="{{ $usuarioId ? 'Dejar vacío para conservar' : 'Mínimo 8 caracteres' }}"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('password')
                        <span class="mt-1 block text-sm text-red-600">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                {{-- CONFIRMAR CONTRASEÑA --}}

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Confirmar contraseña
                    </label>

                    <input
                        type="password"
                        wire:model="password_confirmation"
                        placeholder="Repetir contraseña"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >
                </div>

            </div>


            {{-- BOTONES --}}

            <div class="mt-5 flex gap-3">

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="guardar"
                    class="rounded-lg bg-blue-600 px-5 py-2.5 font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ $usuarioId ? 'Actualizar usuario' : 'Guardar usuario' }}
                </button>

                @if ($usuarioId)
                    <button
                        type="button"
                        wire:click="cancelarEdicion"
                        class="rounded-lg border border-gray-500 px-5 py-2.5 font-medium hover:bg-gray-100 dark:hover:bg-zinc-800"
                    >
                        Cancelar
                    </button>
                @endif

            </div>

        </form>

    </div>


    {{-- LISTADO --}}

    <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

        <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

            <h2 class="text-lg font-semibold">
                Usuarios registrados
            </h2>

            <input
                type="text"
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar usuario..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 md:max-w-sm dark:bg-zinc-800"
            >

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b">
                    <tr>
                        <th class="px-4 py-3">
                            Usuario
                        </th>

                        <th class="px-4 py-3">
                            Correo
                        </th>

                        <th class="px-4 py-3">
                            Rol
                        </th>

                        <th class="px-4 py-3">
                            Sede
                        </th>

                        <th class="px-4 py-3">
                            Estado
                        </th>

                        <th class="px-4 py-3">
                            Acciones
                        </th>
                    </tr>
                </thead>


                <tbody>

                    @forelse ($this->usuarios as $usuario)

                        <tr
                            wire:key="usuario-{{ $usuario->id }}"
                            class="border-b"
                        >

                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $usuario->name }}
                                </div>

                                @if ($usuario->telefono)
                                    <div class="text-xs text-gray-500">
                                        {{ $usuario->telefono }}
                                    </div>
                                @endif
                            </td>


                            <td class="px-4 py-3">
                                {{ $usuario->email }}
                            </td>


                            <td class="px-4 py-3">
                                {{ $usuario->rol?->nombre ?? 'Sin rol' }}
                            </td>


                            <td class="px-4 py-3">

                                @if ($usuario->sede)

                                    {{ $usuario->sede->nombre }}

                                @elseif ($usuario->rol?->slug === 'super-administrador')

                                    <span class="text-blue-500">
                                        Todas las sedes
                                    </span>

                                @else

                                    <span class="text-gray-500">
                                        Sin sede
                                    </span>

                                @endif

                            </td>


                            <td class="px-4 py-3">

                                @if ($usuario->estado)

                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">
                                        Activo
                                    </span>

                                @else

                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
                                        Inactivo
                                    </span>

                                @endif

                            </td>


                            <td class="px-4 py-3">

                                <div class="flex flex-wrap gap-2">

                                    <button
                                        type="button"
                                        wire:click="editar({{ $usuario->id }})"
                                        class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600"
                                    >
                                        Editar
                                    </button>


                                    @if ((int) auth()->id() !== $usuario->id)

                                        <button
                                            type="button"
                                            wire:click="cambiarEstado({{ $usuario->id }})"
                                            wire:confirm="¿Deseas cambiar el estado de este usuario?"
                                            class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
                                        >
                                            {{ $usuario->estado ? 'Desactivar' : 'Activar' }}
                                        </button>

                                    @else

                                        <span
                                            class="rounded-lg bg-blue-100 px-3 py-1.5 text-xs font-medium text-blue-700"
                                        >
                                            Tu cuenta
                                        </span>

                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Todavía no existen usuarios registrados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>