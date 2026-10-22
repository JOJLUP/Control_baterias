<?php

use App\Models\Sede;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $sedeId = null;

    public string $codigo = '';
    public string $nombre = '';
    public string $departamento = '';
    public string $provincia = '';
    public string $distrito = '';
    public string $direccion = '';
    public string $telefono = '';
    public string $responsable = '';

    public string $buscar = '';

    /*
    |--------------------------------------------------------------------------
    | GUARDAR / ACTUALIZAR SEDE
    |--------------------------------------------------------------------------
    */
    public function guardar(): void
    {
        $datos = $this->validate([
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('sedes', 'codigo')->ignore($this->sedeId),
            ],

            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'departamento' => [
                'required',
                'string',
                'max:100',
            ],

            'provincia' => [
                'required',
                'string',
                'max:100',
            ],

            'distrito' => [
                'required',
                'string',
                'max:100',
            ],

            'direccion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:30',
            ],

            'responsable' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */
        if ($this->sedeId) {

            $sede = Sede::findOrFail($this->sedeId);

            $sede->update($datos);

            session()->flash(
                'mensaje',
                'Sede actualizada correctamente.'
            );

        /*
        |--------------------------------------------------------------------------
        | CREAR
        |--------------------------------------------------------------------------
        */
        } else {

            $datos['estado'] = true;

            Sede::create($datos);

            session()->flash(
                'mensaje',
                'Sede registrada correctamente.'
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
        $sede = Sede::findOrFail($id);

        $this->sedeId = $sede->id;

        $this->codigo = $sede->codigo;
        $this->nombre = $sede->nombre;
        $this->departamento = $sede->departamento;
        $this->provincia = $sede->provincia;
        $this->distrito = $sede->distrito;

        $this->direccion = $sede->direccion ?? '';
        $this->telefono = $sede->telefono ?? '';
        $this->responsable = $sede->responsable ?? '';

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
        $sede = Sede::findOrFail($id);

        $sede->update([
            'estado' => ! $sede->estado,
        ]);

        session()->flash(
            'mensaje',
            $sede->estado
                ? 'Sede activada correctamente.'
                : 'Sede desactivada correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'sedeId',
            'codigo',
            'nombre',
            'departamento',
            'provincia',
            'distrito',
            'direccion',
            'telefono',
            'responsable',
        ]);

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR / BUSCAR SEDES
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function sedes()
    {
        return Sede::query()

            ->when($this->buscar, function ($query) {

                $query->where(function ($q) {

                    $q->where(
                        'codigo',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhere(
                        'nombre',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhere(
                        'departamento',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhere(
                        'provincia',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )

                    ->orWhere(
                        'distrito',
                        'ilike',
                        '%' . $this->buscar . '%'
                    );

                });

            })

            ->orderBy('nombre')

            ->get();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- ============================================================= --}}
    {{-- TÍTULO --}}
    {{-- ============================================================= --}}

    <div>

        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Sedes
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Administración de las sedes del sistema de control de baterías.
        </p>

    </div>


    {{-- ============================================================= --}}
    {{-- MENSAJE DE ÉXITO --}}
    {{-- ============================================================= --}}

    @if (session('mensaje'))

        <div
            class="rounded-lg border border-green-200 bg-green-100 p-4 text-green-800"
        >
            {{ session('mensaje') }}
        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- FORMULARIO --}}
    {{-- ============================================================= --}}

    <div
        class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
    >

        <h2
            class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
        >

            {{ $sedeId ? 'Editar sede' : 'Nueva sede' }}

        </h2>


        <form wire:submit="guardar">

            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >

                {{-- CÓDIGO --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Código
                    </label>

                    <input
                        type="text"
                        wire:model="codigo"
                        placeholder="SED-001"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('codigo')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

                </div>


                {{-- NOMBRE --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Nombre
                    </label>

                    <input
                        type="text"
                        wire:model="nombre"
                        placeholder="Sede Juliaca"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('nombre')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

                </div>


                {{-- DEPARTAMENTO --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Departamento
                    </label>

                    <input
                        type="text"
                        wire:model="departamento"
                        placeholder="Puno"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('departamento')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

                </div>


                {{-- PROVINCIA --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Provincia
                    </label>

                    <input
                        type="text"
                        wire:model="provincia"
                        placeholder="San Román"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('provincia')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

                </div>


                {{-- DISTRITO --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Distrito
                    </label>

                    <input
                        type="text"
                        wire:model="distrito"
                        placeholder="Juliaca"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('distrito')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

                </div>


                {{-- TELÉFONO --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
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


                {{-- RESPONSABLE --}}

                <div>

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Responsable
                    </label>

                    <input
                        type="text"
                        wire:model="responsable"
                        placeholder="Nombre del responsable"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('responsable')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

                </div>


                {{-- DIRECCIÓN --}}

                <div class="md:col-span-2">

                    <label
                        class="mb-1 block text-sm font-medium"
                    >
                        Dirección
                    </label>

                    <input
                        type="text"
                        wire:model="direccion"
                        placeholder="Dirección de la sede"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >

                    @error('direccion')

                        <span class="mt-1 block text-sm text-red-600">

                            {{ $message }}

                        </span>

                    @enderror

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

                    {{ $sedeId ? 'Actualizar sede' : 'Guardar sede' }}

                </button>


                @if ($sedeId)

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


    {{-- ============================================================= --}}
    {{-- TABLA DE SEDES --}}
    {{-- ============================================================= --}}

    <div
        class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
    >

        <div
            class="mb-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >

            <h2 class="text-lg font-semibold">

                Sedes registradas

            </h2>


            <input
                type="text"
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar sede..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 md:max-w-sm dark:bg-zinc-800"
            >

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                {{-- CABECERA --}}

                <thead class="border-b">

                    <tr>

                        <th class="px-4 py-3">
                            Código
                        </th>

                        <th class="px-4 py-3">
                            Nombre
                        </th>

                        <th class="px-4 py-3">
                            Ubicación
                        </th>

                        <th class="px-4 py-3">
                            Responsable
                        </th>

                        <th class="px-4 py-3">
                            Estado
                        </th>

                        <th class="px-4 py-3">
                            Acciones
                        </th>

                    </tr>

                </thead>


                {{-- CUERPO --}}

                <tbody>

                    @forelse ($this->sedes as $sede)

                        <tr
                            wire:key="sede-{{ $sede->id }}"
                            class="border-b"
                        >

                            {{-- CÓDIGO --}}

                            <td class="px-4 py-3 font-medium">

                                {{ $sede->codigo }}

                            </td>


                            {{-- NOMBRE --}}

                            <td class="px-4 py-3">

                                {{ $sede->nombre }}

                            </td>


                            {{-- UBICACIÓN --}}

                            <td class="px-4 py-3">

                                {{ $sede->departamento }}
                                /
                                {{ $sede->provincia }}
                                /
                                {{ $sede->distrito }}

                            </td>


                            {{-- RESPONSABLE --}}

                            <td class="px-4 py-3">

                                {{ $sede->responsable ?: 'Sin responsable' }}

                            </td>


                            {{-- ESTADO --}}

                            <td class="px-4 py-3">

                                @if ($sede->estado)

                                    <span
                                        class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700"
                                    >

                                        Activa

                                    </span>

                                @else

                                    <span
                                        class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700"
                                    >

                                        Inactiva

                                    </span>

                                @endif

                            </td>


                            {{-- ACCIONES --}}

                            <td class="px-4 py-3">

                                <div class="flex flex-wrap gap-2">

                                    {{-- EDITAR --}}

                                    <button
                                        type="button"
                                        wire:click="editar({{ $sede->id }})"
                                        class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600"
                                    >

                                        Editar

                                    </button>


                                    {{-- ACTIVAR / DESACTIVAR --}}

                                    <button
                                        type="button"
                                        wire:click="cambiarEstado({{ $sede->id }})"
                                        wire:confirm="¿Deseas cambiar el estado de esta sede?"
                                        class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
                                    >

                                        {{ $sede->estado ? 'Desactivar' : 'Activar' }}

                                    </button>

                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-gray-500"
                            >

                                Todavía no existen sedes registradas.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>