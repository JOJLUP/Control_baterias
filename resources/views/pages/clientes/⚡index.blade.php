<?php

use App\Models\Cliente;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $clienteEditando = null;

    public string $tipo_documento = 'DNI';
    public string $numero_documento = '';
    public string $nombre = '';
    public string $telefono = '';
    public string $email = '';
    public string $direccion = '';
    public string $observaciones = '';

    public string $buscar = '';

    /*
    |--------------------------------------------------------------------------
    | CAMBIO DE TIPO DE DOCUMENTO
    |--------------------------------------------------------------------------
    */

    public function updatedTipoDocumento(): void
    {
        if ($this->tipo_documento === 'SIN_DOCUMENTO') {
            $this->numero_documento = '';
        }

        $this->resetValidation('numero_documento');
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR / ACTUALIZAR
    |--------------------------------------------------------------------------
    */

    public function guardar(): void
    {
        $this->nombre = trim($this->nombre);
        $this->telefono = trim($this->telefono);
        $this->email = trim($this->email);
        $this->direccion = trim($this->direccion);
        $this->observaciones = trim($this->observaciones);
        $this->numero_documento = trim($this->numero_documento);

        $reglasDocumento = [
            'nullable',
            'string',
            'max:20',
        ];

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN SEGÚN DOCUMENTO
        |--------------------------------------------------------------------------
        */

        if ($this->tipo_documento === 'DNI') {
            $reglasDocumento = [
                'required',
                'regex:/^\d{8}$/',
                Rule::unique('clientes', 'numero_documento')
                    ->ignore($this->clienteEditando),
            ];
        }

        if ($this->tipo_documento === 'RUC') {
            $reglasDocumento = [
                'required',
                'regex:/^\d{11}$/',
                Rule::unique('clientes', 'numero_documento')
                    ->ignore($this->clienteEditando),
            ];
        }

        if ($this->tipo_documento === 'CE') {
            $reglasDocumento = [
                'required',
                'regex:/^[A-Za-z0-9\-]{3,20}$/',
                Rule::unique('clientes', 'numero_documento')
                    ->ignore($this->clienteEditando),
            ];
        }

        if ($this->tipo_documento === 'OTRO') {
            $reglasDocumento = [
                'required',
                'string',
                'max:20',
                Rule::unique('clientes', 'numero_documento')
                    ->ignore($this->clienteEditando),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR
        |--------------------------------------------------------------------------
        */

        $this->validate(
            [
                'tipo_documento' => [
                    'required',
                    Rule::in([
                        'DNI',
                        'RUC',
                        'CE',
                        'OTRO',
                        'SIN_DOCUMENTO',
                    ]),
                ],

                'numero_documento' => $reglasDocumento,

                'nombre' => [
                    'required',
                    'string',
                    'min:2',
                    'max:150',
                ],

                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:150',
                ],

                'direccion' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'observaciones' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ],
            [
                'tipo_documento.required' =>
                    'Selecciona el tipo de documento.',

                'numero_documento.required' =>
                    'Ingresa el número de documento.',

                'numero_documento.regex' =>
                    match ($this->tipo_documento) {
                        'DNI' => 'El DNI debe contener exactamente 8 dígitos.',
                        'RUC' => 'El RUC debe contener exactamente 11 dígitos.',
                        'CE' => 'El carnet de extranjería no tiene un formato válido.',
                        default => 'El documento no tiene un formato válido.',
                    },

                'numero_documento.unique' =>
                    'Este número de documento ya está registrado.',

                'nombre.required' =>
                    'Ingresa el nombre o razón social.',

                'nombre.min' =>
                    'El nombre debe tener al menos 2 caracteres.',

                'email.email' =>
                    'Ingresa un correo electrónico válido.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        if ($this->clienteEditando) {

            $cliente = Cliente::findOrFail(
                $this->clienteEditando
            );

            $cliente->update([
                'tipo_documento' =>
                    $this->tipo_documento,

                'numero_documento' =>
                    $this->tipo_documento === 'SIN_DOCUMENTO'
                        ? null
                        : $this->numero_documento,

                'nombre' =>
                    $this->nombre,

                'telefono' =>
                    $this->telefono !== ''
                        ? $this->telefono
                        : null,

                'email' =>
                    $this->email !== ''
                        ? $this->email
                        : null,

                'direccion' =>
                    $this->direccion !== ''
                        ? $this->direccion
                        : null,

                'observaciones' =>
                    $this->observaciones !== ''
                        ? $this->observaciones
                        : null,
            ]);

            session()->flash(
                'success',
                'Cliente actualizado correctamente.'
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | NUEVO CLIENTE
            |--------------------------------------------------------------------------
            */

            Cliente::create([
                'codigo' =>
                    $this->generarCodigo(),

                'tipo_documento' =>
                    $this->tipo_documento,

                'numero_documento' =>
                    $this->tipo_documento === 'SIN_DOCUMENTO'
                        ? null
                        : $this->numero_documento,

                'nombre' =>
                    $this->nombre,

                'telefono' =>
                    $this->telefono !== ''
                        ? $this->telefono
                        : null,

                'email' =>
                    $this->email !== ''
                        ? $this->email
                        : null,

                'direccion' =>
                    $this->direccion !== ''
                        ? $this->direccion
                        : null,

                'observaciones' =>
                    $this->observaciones !== ''
                        ? $this->observaciones
                        : null,

                'estado' =>
                    true,
            ]);

            session()->flash(
                'success',
                'Cliente registrado correctamente.'
            );
        }

        $this->limpiarFormulario();
    }

    /*
    |--------------------------------------------------------------------------
    | GENERAR CÓDIGO
    |--------------------------------------------------------------------------
    */

    private function generarCodigo(): string
    {
        $ultimoId = (int) (
            Cliente::query()->max('id') ?? 0
        );

        $numero = $ultimoId + 1;

        return 'CLI-' . str_pad(
            (string) $numero,
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDITAR
    |--------------------------------------------------------------------------
    */

    public function editar(int $id): void
    {
        $cliente = Cliente::findOrFail($id);

        $this->clienteEditando =
            $cliente->id;

        $this->tipo_documento =
            $cliente->tipo_documento;

        $this->numero_documento =
            $cliente->numero_documento ?? '';

        $this->nombre =
            $cliente->nombre;

        $this->telefono =
            $cliente->telefono ?? '';

        $this->email =
            $cliente->email ?? '';

        $this->direccion =
            $cliente->direccion ?? '';

        $this->observaciones =
            $cliente->observaciones ?? '';

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | CANCELAR EDICIÓN
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
        $cliente = Cliente::findOrFail($id);

        $cliente->update([
            'estado' => ! $cliente->estado,
        ]);

        session()->flash(
            'success',
            $cliente->estado
                ? 'Cliente activado correctamente.'
                : 'Cliente desactivado correctamente.'
        );

        /*
        | Si estábamos editando este cliente,
        | limpiamos el formulario.
        */

        if ($this->clienteEditando === $cliente->id) {
            $this->limpiarFormulario();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */

    private function limpiarFormulario(): void
    {
        $this->clienteEditando = null;

        $this->tipo_documento = 'DNI';
        $this->numero_documento = '';
        $this->nombre = '';
        $this->telefono = '';
        $this->email = '';
        $this->direccion = '';
        $this->observaciones = '';

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | CLIENTES
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function clientes()
    {
        $query = Cliente::query();

        if (trim($this->buscar) !== '') {

            $buscar =
                '%' .
                mb_strtolower(
                    trim($this->buscar)
                )
                . '%';

            $query->where(
                function ($q) use ($buscar) {

                    $q
                        ->whereRaw(
                            'LOWER(codigo) LIKE ?',
                            [$buscar]
                        )
                        ->orWhereRaw(
                            'LOWER(nombre) LIKE ?',
                            [$buscar]
                        )
                        ->orWhereRaw(
                            'LOWER(COALESCE(numero_documento, \'\')) LIKE ?',
                            [$buscar]
                        )
                        ->orWhereRaw(
                            'LOWER(COALESCE(telefono, \'\')) LIKE ?',
                            [$buscar]
                        )
                        ->orWhereRaw(
                            'LOWER(COALESCE(email, \'\')) LIKE ?',
                            [$buscar]
                        );
                }
            );
        }

        return $query
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- ============================================================= --}}
    {{-- CABECERA --}}
    {{-- ============================================================= --}}

    <div>
        <h1 class="text-2xl font-bold text-white">
            Clientes
        </h1>

        <p class="mt-1 text-sm text-zinc-400">
            Administración de clientes del negocio.
        </p>
    </div>


    {{-- ============================================================= --}}
    {{-- MENSAJE --}}
    {{-- ============================================================= --}}

    @if (session('success'))

        <div
            class="rounded-xl border border-green-800
                   bg-green-950/50 px-5 py-4
                   text-sm text-green-300"
        >
            {{ session('success') }}
        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- FORMULARIO --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <div class="mb-6 flex items-center justify-between">

            <div>

                <h2 class="text-lg font-semibold text-white">

                    @if ($clienteEditando)
                        Editar cliente
                    @else
                        Nuevo cliente
                    @endif

                </h2>

                @if ($clienteEditando)

                    <p class="mt-1 text-sm text-zinc-500">
                        Modifica la información del cliente seleccionado.
                    </p>

                @endif

            </div>


            @if ($clienteEditando)

                <button
                    type="button"
                    wire:click="cancelarEdicion"
                    class="rounded-lg border border-zinc-600
                           px-4 py-2 text-sm text-zinc-300
                           transition hover:bg-zinc-800"
                >
                    Cancelar edición
                </button>

            @endif

        </div>


        <form wire:submit="guardar">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">

                {{-- TIPO DE DOCUMENTO --}}

                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Tipo de documento
                    </label>

                    <select
                        wire:model.live="tipo_documento"
                        class="w-full rounded-lg border border-zinc-600
                               bg-zinc-800 px-3 py-2"
                    >
                        <option value="DNI">
                            DNI
                        </option>

                        <option value="RUC">
                            RUC
                        </option>

                        <option value="CE">
                            Carnet de extranjería
                        </option>

                        <option value="OTRO">
                            Otro documento
                        </option>

                        <option value="SIN_DOCUMENTO">
                            Sin documento
                        </option>
                    </select>

                    @error('tipo_documento')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                {{-- NÚMERO DE DOCUMENTO --}}

                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Número de documento
                    </label>

                    <input
                        type="text"
                        wire:model="numero_documento"

                        @disabled(
                            $tipo_documento === 'SIN_DOCUMENTO'
                        )

                        @if ($tipo_documento === 'DNI')
                            maxlength="8"
                            inputmode="numeric"
                            placeholder="12345678"
                        @elseif ($tipo_documento === 'RUC')
                            maxlength="11"
                            inputmode="numeric"
                            placeholder="20123456789"
                        @elseif ($tipo_documento === 'CE')
                            maxlength="20"
                            placeholder="Documento"
                        @else
                            maxlength="20"
                            placeholder="Número de documento"
                        @endif

                        class="w-full rounded-lg border border-zinc-600
                               bg-zinc-800 px-3 py-2
                               disabled:cursor-not-allowed
                               disabled:opacity-50"
                    >

                    @if ($tipo_documento === 'SIN_DOCUMENTO')
                        <p class="mt-1 text-xs text-zinc-500">
                            No se requiere número de documento.
                        </p>
                    @endif

                    @error('numero_documento')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                {{-- NOMBRE --}}

                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Nombre / Razón social
                    </label>

                    <input
                        type="text"
                        wire:model="nombre"
                        placeholder="Nombre completo o empresa"
                        maxlength="150"
                        class="w-full rounded-lg border border-zinc-600
                               bg-zinc-800 px-3 py-2"
                    >

                    @error('nombre')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
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
                        maxlength="20"
                        class="w-full rounded-lg border border-zinc-600
                               bg-zinc-800 px-3 py-2"
                    >

                    @error('telefono')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
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
                        placeholder="cliente@correo.com"
                        maxlength="150"
                        class="w-full rounded-lg border border-zinc-600
                               bg-zinc-800 px-3 py-2"
                    >

                    @error('email')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                {{-- DIRECCIÓN --}}

                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Dirección
                    </label>

                    <input
                        type="text"
                        wire:model="direccion"
                        placeholder="Dirección del cliente"
                        maxlength="255"
                        class="w-full rounded-lg border border-zinc-600
                               bg-zinc-800 px-3 py-2"
                    >

                    @error('direccion')
                        <p class="mt-1 text-xs text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

            </div>


            {{-- OBSERVACIONES --}}

            <div class="mt-4">

                <label class="mb-1 block text-sm font-medium">
                    Observaciones
                </label>

                <textarea
                    wire:model="observaciones"
                    rows="3"
                    maxlength="1000"
                    placeholder="Información adicional del cliente..."
                    class="w-full rounded-lg border border-zinc-600
                           bg-zinc-800 px-3 py-2"
                ></textarea>

                @error('observaciones')
                    <p class="mt-1 text-xs text-red-400">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- BOTÓN --}}

            <div class="mt-5 flex items-center gap-3">

                <button
                    type="submit"
                    class="rounded-lg bg-blue-600
                           px-5 py-2.5 font-medium text-white
                           transition hover:bg-blue-500
                           disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:target="guardar"
                >

                    <span wire:loading.remove wire:target="guardar">

                        @if ($clienteEditando)
                            Guardar cambios
                        @else
                            Guardar cliente
                        @endif

                    </span>

                    <span wire:loading wire:target="guardar">
                        Guardando...
                    </span>

                </button>


                @if ($clienteEditando)

                    <button
                        type="button"
                        wire:click="cancelarEdicion"
                        class="rounded-lg bg-zinc-700
                               px-5 py-2.5 font-medium
                               text-white hover:bg-zinc-600"
                    >
                        Cancelar
                    </button>

                @endif

            </div>

        </form>

    </div>


    {{-- ============================================================= --}}
    {{-- LISTADO --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <div
            class="mb-5 flex flex-col gap-4
                   md:flex-row md:items-center
                   md:justify-between"
        >

            <div>

                <h2 class="text-lg font-semibold text-white">
                    Clientes registrados
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    Consulta y administra los clientes.
                </p>

            </div>


            <input
                type="text"
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar cliente..."
                class="w-full rounded-lg border border-zinc-600
                       bg-zinc-800 px-3 py-2 md:w-80"
            >

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-zinc-600">

                    <tr>
                        <th class="px-4 py-3">
                            Código
                        </th>

                        <th class="px-4 py-3">
                            Documento
                        </th>

                        <th class="px-4 py-3">
                            Cliente
                        </th>

                        <th class="px-4 py-3">
                            Contacto
                        </th>

                        <th class="px-4 py-3">
                            Dirección
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

                    @forelse ($this->clientes as $cliente)

                        <tr
                            wire:key="cliente-{{ $cliente->id }}"
                            class="border-b border-zinc-700"
                        >

                            {{-- CÓDIGO --}}

                            <td class="px-4 py-4 font-medium text-white">
                                {{ $cliente->codigo }}
                            </td>


                            {{-- DOCUMENTO --}}

                            <td class="px-4 py-4">

                                @if ($cliente->tipo_documento === 'SIN_DOCUMENTO')

                                    <span class="text-zinc-500">
                                        Sin documento
                                    </span>

                                @else

                                    <div class="font-medium text-white">
                                        {{ $cliente->numero_documento }}
                                    </div>

                                    <div class="text-xs text-zinc-500">
                                        {{ $cliente->tipo_documento }}
                                    </div>

                                @endif

                            </td>


                            {{-- CLIENTE --}}

                            <td class="px-4 py-4">

                                <div class="font-medium text-white">
                                    {{ $cliente->nombre }}
                                </div>

                                @if ($cliente->email)

                                    <div class="mt-1 text-xs text-zinc-500">
                                        {{ $cliente->email }}
                                    </div>

                                @endif

                            </td>


                            {{-- CONTACTO --}}

                            <td class="px-4 py-4">

                                @if ($cliente->telefono)

                                    {{ $cliente->telefono }}

                                @else

                                    <span class="text-zinc-600">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- DIRECCIÓN --}}

                            <td class="px-4 py-4">

                                @if ($cliente->direccion)

                                    <div class="max-w-xs truncate">
                                        {{ $cliente->direccion }}
                                    </div>

                                @else

                                    <span class="text-zinc-600">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- ESTADO --}}

                            <td class="px-4 py-4">

                                @if ($cliente->estado)

                                    <span
                                        class="rounded-full bg-green-100
                                               px-3 py-1 text-xs
                                               font-medium text-green-700"
                                    >
                                        Activo
                                    </span>

                                @else

                                    <span
                                        class="rounded-full bg-red-100
                                               px-3 py-1 text-xs
                                               font-medium text-red-700"
                                    >
                                        Inactivo
                                    </span>

                                @endif

                            </td>


                            {{-- ACCIONES --}}

                            <td class="px-4 py-4">

                                <div class="flex items-center gap-2">

                                    <button
                                        type="button"
                                        wire:click="editar({{ $cliente->id }})"
                                        class="rounded-lg bg-amber-500
                                               px-3 py-2 text-xs
                                               font-medium text-white
                                               transition hover:bg-amber-400"
                                    >
                                        Editar
                                    </button>


                                    @if ($cliente->estado)

                                        <button
                                            type="button"
                                            wire:click="cambiarEstado({{ $cliente->id }})"
                                            wire:confirm="¿Deseas desactivar este cliente?"
                                            class="rounded-lg bg-zinc-700
                                                   px-3 py-2 text-xs
                                                   font-medium text-white
                                                   transition hover:bg-zinc-600"
                                        >
                                            Desactivar
                                        </button>

                                    @else

                                        <button
                                            type="button"
                                            wire:click="cambiarEstado({{ $cliente->id }})"
                                            class="rounded-lg bg-blue-600
                                                   px-3 py-2 text-xs
                                                   font-medium text-white
                                                   transition hover:bg-blue-500"
                                        >
                                            Activar
                                        </button>

                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-zinc-500"
                            >
                                No existen clientes registrados.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>