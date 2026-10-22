<?php

use App\Models\Bateria;
use App\Models\Inventario;
use App\Models\Sede;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $inventarioId = null;

    public string $bateria_id = '';
    public string $sede_id = '';
    public string $stock_minimo = '0';

    public string $buscar = '';
    public string $filtroSede = '';

    /*
    |--------------------------------------------------------------------------
    | INICIALIZACIÓN
    |--------------------------------------------------------------------------
    */
    public function mount(): void
    {
        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        /*
        | Los usuarios que pertenecen a una sede
        | solo podrán visualizar su propia sede.
        */
        if (! in_array(
            $usuario?->rol?->slug,
            [
                'super-administrador',
                'administrador',
            ],
            true
        )) {
            $this->filtroSede = $usuario?->sede_id
                ? (string) $usuario->sede_id
                : '';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR
    |--------------------------------------------------------------------------
    */
    public function guardar(): void
    {
        $this->autorizarConfiguracion();

        $this->validate([
            'bateria_id' => [
                'required',
                'integer',
                'exists:baterias,id',
            ],

            'sede_id' => [
                'required',
                'integer',
                'exists:sedes,id',
            ],

            'stock_minimo' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR BATERÍA
        |--------------------------------------------------------------------------
        */

        $bateria = Bateria::query()
            ->whereKey((int) $this->bateria_id)
            ->where('estado', true)
            ->first();

        if (! $bateria) {
            $this->addError(
                'bateria_id',
                'La batería seleccionada no está activa.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR SEDE
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | EDITAR CONFIGURACIÓN
        |--------------------------------------------------------------------------
        */

        if ($this->inventarioId) {

            $inventario = Inventario::findOrFail(
                $this->inventarioId
            );

            /*
            | Al editar NO cambiamos batería ni sede.
            | Solamente el stock mínimo.
            */
            $inventario->stock_minimo =
                (int) $this->stock_minimo;

            $inventario->save();

            session()->flash(
                'mensaje',
                'Configuración de inventario actualizada correctamente.'
            );

        /*
        |--------------------------------------------------------------------------
        | CREAR INVENTARIO
        |--------------------------------------------------------------------------
        */

        } else {

            $existe = Inventario::query()
                ->where(
                    'bateria_id',
                    (int) $this->bateria_id
                )
                ->where(
                    'sede_id',
                    (int) $this->sede_id
                )
                ->exists();

            if ($existe) {
                $this->addError(
                    'bateria_id',
                    'Esta batería ya está registrada en la sede seleccionada.'
                );

                return;
            }

            Inventario::create([
                'bateria_id' => (int) $this->bateria_id,
                'sede_id' => (int) $this->sede_id,

                /*
                | El stock comienza en cero.
                | Los ingresos posteriores aumentarán este valor.
                */
                'stock_actual' => 0,

                'stock_minimo' =>
                    (int) $this->stock_minimo,

                'costo_promedio' => 0,
            ]);

            session()->flash(
                'mensaje',
                'Batería asignada al inventario correctamente.'
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
        $this->autorizarConfiguracion();

        $inventario = Inventario::findOrFail($id);

        $this->inventarioId = $inventario->id;

        $this->bateria_id =
            (string) $inventario->bateria_id;

        $this->sede_id =
            (string) $inventario->sede_id;

        $this->stock_minimo =
            (string) $inventario->stock_minimo;

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
    | PERMISO PARA CONFIGURAR INVENTARIO
    |--------------------------------------------------------------------------
    */
    private function autorizarConfiguracion(): void
    {
        $rol = auth()->user()?->rol?->slug;

        if (! in_array(
            $rol,
            [
                'super-administrador',
                'administrador',
            ],
            true
        )) {
            abort(
                403,
                'No tienes permiso para configurar el inventario.'
            );
        }
    }

    #[Computed]
    public function puedeConfigurar(): bool
    {
        return in_array(
            auth()->user()?->rol?->slug,
            [
                'super-administrador',
                'administrador',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SEDES ACTIVAS
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
    | BATERÍAS ACTIVAS
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function baterias()
    {
        return Bateria::query()
            ->where('estado', true)
            ->orderBy('marca')
            ->orderBy('modelo')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTARIOS
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function inventarios()
    {
        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        $query = Inventario::query()
            ->with([
                'bateria',
                'sede',
            ]);

        /*
        |--------------------------------------------------------------------------
        | RESTRICCIÓN POR SEDE
        |--------------------------------------------------------------------------
        |
        | Super Administrador y Administrador pueden consultar todas.
        | Los demás usuarios solamente su sede.
        |
        */

        if (! in_array(
            $usuario?->rol?->slug,
            [
                'super-administrador',
                'administrador',
            ],
            true
        )) {

            if ($usuario?->sede_id) {
                $query->where(
                    'sede_id',
                    $usuario->sede_id
                );
            } else {
                /*
                | Usuario sin sede:
                | no se muestra inventario.
                */
                $query->whereRaw('1 = 0');
            }

        } elseif ($this->filtroSede !== '') {

            $query->where(
                'sede_id',
                (int) $this->filtroSede
            );
        }

        /*
        |--------------------------------------------------------------------------
        | BUSCADOR
        |--------------------------------------------------------------------------
        */

        if ($this->buscar !== '') {

            $buscar = $this->buscar;

            $query->where(function ($q) use ($buscar) {

                $q->whereHas(
                    'bateria',
                    function ($bateria) use ($buscar) {

                        $bateria
                            ->where(
                                'codigo',
                                'ilike',
                                '%' . $buscar . '%'
                            )
                            ->orWhere(
                                'marca',
                                'ilike',
                                '%' . $buscar . '%'
                            )
                            ->orWhere(
                                'modelo',
                                'ilike',
                                '%' . $buscar . '%'
                            );
                    }
                )

                ->orWhereHas(
                    'sede',
                    function ($sede) use ($buscar) {

                        $sede
                            ->where(
                                'codigo',
                                'ilike',
                                '%' . $buscar . '%'
                            )
                            ->orWhere(
                                'nombre',
                                'ilike',
                                '%' . $buscar . '%'
                            );
                    }
                );
            });
        }

        return $query
            ->orderBy('sede_id')
            ->orderBy('bateria_id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'inventarioId',
            'bateria_id',
            'sede_id',
            'stock_minimo',
        ]);

        $this->stock_minimo = '0';

        $this->resetValidation();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- ============================================================= --}}
    {{-- TÍTULO --}}
    {{-- ============================================================= --}}

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Inventario
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Control de existencias de baterías por sede.
        </p>
    </div>


    {{-- ============================================================= --}}
    {{-- MENSAJES --}}
    {{-- ============================================================= --}}

    @if (session('mensaje'))

        <div
            class="rounded-lg border border-green-200
                   bg-green-100 p-4 text-green-800"
        >
            {{ session('mensaje') }}
        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- CONFIGURACIÓN --}}
    {{-- ============================================================= --}}

    @if ($this->puedeConfigurar)

        <div
            class="rounded-xl bg-white p-6 shadow
                   dark:bg-zinc-900"
        >

            <h2
                class="mb-5 text-lg font-semibold
                       text-gray-900 dark:text-white"
            >
                {{ $inventarioId
                    ? 'Editar configuración'
                    : 'Asignar batería a una sede'
                }}
            </h2>


            <form wire:submit="guardar">

                <div
                    class="grid grid-cols-1 gap-4
                           md:grid-cols-3"
                >

                    {{-- BATERÍA --}}

                    <div>

                        <label
                            class="mb-1 block text-sm font-medium"
                        >
                            Batería
                        </label>

                        <select
                            wire:model="bateria_id"
                            @disabled($inventarioId)
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   disabled:opacity-60
                                   dark:bg-zinc-800"
                        >

                            <option value="">
                                Seleccionar batería
                            </option>

                            @foreach ($this->baterias as $bateria)

                                <option value="{{ $bateria->id }}">
                                    {{ $bateria->codigo }}
                                    -
                                    {{ $bateria->marca }}
                                    {{ $bateria->modelo }}
                                </option>

                            @endforeach

                        </select>

                        @error('bateria_id')

                            <span
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ $message }}
                            </span>

                        @enderror

                    </div>


                    {{-- SEDE --}}

                    <div>

                        <label
                            class="mb-1 block text-sm font-medium"
                        >
                            Sede
                        </label>

                        <select
                            wire:model="sede_id"
                            @disabled($inventarioId)
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   disabled:opacity-60
                                   dark:bg-zinc-800"
                        >

                            <option value="">
                                Seleccionar sede
                            </option>

                            @foreach ($this->sedes as $sede)

                                <option value="{{ $sede->id }}">
                                    {{ $sede->codigo }}
                                    -
                                    {{ $sede->nombre }}
                                </option>

                            @endforeach

                        </select>

                        @error('sede_id')

                            <span
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ $message }}
                            </span>

                        @enderror

                    </div>


                    {{-- STOCK MÍNIMO --}}

                    <div>

                        <label
                            class="mb-1 block text-sm font-medium"
                        >
                            Stock mínimo
                        </label>

                        <input
                            type="number"
                            min="0"
                            wire:model="stock_minimo"
                            placeholder="5"
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   dark:bg-zinc-800"
                        >

                        @error('stock_minimo')

                            <span
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ $message }}
                            </span>

                        @enderror

                    </div>

                </div>


                <div class="mt-5 flex gap-3">

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        class="rounded-lg bg-blue-600
                               px-5 py-2.5
                               font-medium text-white
                               hover:bg-blue-700
                               disabled:opacity-50"
                    >
                        {{ $inventarioId
                            ? 'Actualizar configuración'
                            : 'Asignar al inventario'
                        }}
                    </button>


                    @if ($inventarioId)

                        <button
                            type="button"
                            wire:click="cancelarEdicion"
                            class="rounded-lg border
                                   border-gray-500
                                   px-5 py-2.5
                                   font-medium
                                   hover:bg-gray-100
                                   dark:hover:bg-zinc-800"
                        >
                            Cancelar
                        </button>

                    @endif

                </div>

            </form>

        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- INVENTARIO --}}
    {{-- ============================================================= --}}

    <div
        class="rounded-xl bg-white p-6 shadow
               dark:bg-zinc-900"
    >

        <div
            class="mb-5 flex flex-col gap-4
                   lg:flex-row
                   lg:items-center
                   lg:justify-between"
        >

            <div>

                <h2 class="text-lg font-semibold">
                    Existencias por sede
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Stock disponible actualmente.
                </p>

            </div>


            <div
                class="flex w-full flex-col gap-3
                       md:flex-row lg:w-auto"
            >

                {{-- FILTRO SEDE PARA ADMINISTRADORES --}}

                @if ($this->puedeConfigurar)

                    <select
                        wire:model.live="filtroSede"
                        class="rounded-lg border
                               border-gray-300
                               px-3 py-2
                               dark:bg-zinc-800"
                    >

                        <option value="">
                            Todas las sedes
                        </option>

                        @foreach ($this->sedes as $sede)

                            <option value="{{ $sede->id }}">
                                {{ $sede->nombre }}
                            </option>

                        @endforeach

                    </select>

                @endif


                {{-- BUSCAR --}}

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Buscar batería..."
                    class="w-full rounded-lg
                           border border-gray-300
                           px-3 py-2
                           md:w-72
                           dark:bg-zinc-800"
                >

            </div>

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b">

                    <tr>
                        <th class="px-4 py-3">
                            Sede
                        </th>

                        <th class="px-4 py-3">
                            Código
                        </th>

                        <th class="px-4 py-3">
                            Batería
                        </th>

                        <th class="px-4 py-3">
                            Stock
                        </th>

                        <th class="px-4 py-3">
                            Mínimo
                        </th>

                        <th class="px-4 py-3">
                            Costo promedio
                        </th>

                        <th class="px-4 py-3">
                            Situación
                        </th>

                        @if ($this->puedeConfigurar)

                            <th class="px-4 py-3">
                                Acciones
                            </th>

                        @endif
                    </tr>

                </thead>


                <tbody>

                    @forelse ($this->inventarios as $inventario)

                        <tr
                            wire:key="inventario-{{ $inventario->id }}"
                            class="border-b"
                        >

                            {{-- SEDE --}}

                            <td class="px-4 py-3">

                                <div class="font-medium">
                                    {{ $inventario->sede?->nombre }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $inventario->sede?->codigo }}
                                </div>

                            </td>


                            {{-- CÓDIGO --}}

                            <td
                                class="px-4 py-3 font-medium"
                            >
                                {{ $inventario->bateria?->codigo }}
                            </td>


                            {{-- BATERÍA --}}

                            <td class="px-4 py-3">

                                <div class="font-medium">
                                    {{ $inventario->bateria?->marca }}
                                    {{ $inventario->bateria?->modelo }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $inventario->bateria?->voltaje }} V
                                    /
                                    {{ $inventario->bateria?->capacidad_ah }} Ah
                                </div>

                            </td>


                            {{-- STOCK ACTUAL --}}

                            <td class="px-4 py-3">

                                <span class="text-lg font-bold">
                                    {{ $inventario->stock_actual }}
                                </span>

                                <span class="text-xs text-gray-500">
                                    und.
                                </span>

                            </td>


                            {{-- STOCK MÍNIMO --}}

                            <td class="px-4 py-3">
                                {{ $inventario->stock_minimo }}
                            </td>


                            {{-- COSTO PROMEDIO --}}

                            <td class="px-4 py-3">

                                S/
                                {{ number_format(
                                    (float) $inventario->costo_promedio,
                                    2
                                ) }}

                            </td>


                            {{-- SITUACIÓN --}}

                            <td class="px-4 py-3">

                                @if ($inventario->stock_actual <= 0)

                                    <span
                                        class="rounded-full
                                               bg-red-100
                                               px-3 py-1
                                               text-xs font-medium
                                               text-red-700"
                                    >
                                        Sin stock
                                    </span>

                                @elseif (
                                    $inventario->stock_actual
                                    <=
                                    $inventario->stock_minimo
                                )

                                    <span
                                        class="rounded-full
                                               bg-yellow-100
                                               px-3 py-1
                                               text-xs font-medium
                                               text-yellow-700"
                                    >
                                        Stock bajo
                                    </span>

                                @else

                                    <span
                                        class="rounded-full
                                               bg-green-100
                                               px-3 py-1
                                               text-xs font-medium
                                               text-green-700"
                                    >
                                        Normal
                                    </span>

                                @endif

                            </td>


                            {{-- ACCIONES --}}

                            @if ($this->puedeConfigurar)

                                <td class="px-4 py-3">

                                    <button
                                        type="button"
                                        wire:click="editar({{ $inventario->id }})"
                                        class="rounded-lg
                                               bg-amber-500
                                               px-3 py-1.5
                                               text-xs font-medium
                                               text-white
                                               hover:bg-amber-600"
                                    >
                                        Editar mínimo
                                    </button>

                                </td>

                            @endif

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="{{ $this->puedeConfigurar ? 8 : 7 }}"
                                class="px-4 py-8
                                       text-center
                                       text-gray-500"
                            >
                                Todavía no existen registros de inventario.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>