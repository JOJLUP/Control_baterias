<?php

use App\Models\Bateria;
use App\Models\Inventario;
use App\Models\Salida;
use App\Models\Sede;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $fecha = '';
    public string $sede_id = '';

    public string $tipo_salida = 'Venta';
    public string $numero_documento = '';
    public string $cliente_destino = '';
    public string $observaciones = '';

    public array $detalles = [];

    public string $buscar = '';
    public string $filtroSede = '';

    /*
    |--------------------------------------------------------------------------
    | INICIALIZACIÓN
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');

        $usuario = auth()->user();

        $usuario?->loadMissing([
            'rol',
            'sede',
        ]);

        /*
        | Los usuarios operativos trabajan solamente
        | con su sede asignada.
        */

        if (! $this->puedeElegirSede()) {

            if ($usuario?->sede_id) {
                $this->sede_id = (string) $usuario->sede_id;
                $this->filtroSede = (string) $usuario->sede_id;
            }
        }

        $this->agregarDetalle();
    }

    /*
    |--------------------------------------------------------------------------
    | AGREGAR BATERÍA
    |--------------------------------------------------------------------------
    */

    public function agregarDetalle(): void
    {
        $this->detalles[] = [
            'bateria_id' => '',
            'cantidad' => '1',
            'precio_unitario' => '',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUITAR BATERÍA
    |--------------------------------------------------------------------------
    */

    public function quitarDetalle(int $indice): void
    {
        if (count($this->detalles) <= 1) {
            return;
        }

        unset($this->detalles[$indice]);

        $this->detalles = array_values(
            $this->detalles
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CAMBIO DE SEDE
    |--------------------------------------------------------------------------
    */

    public function updatedSedeId(): void
    {
        $this->detalles = [];

        $this->agregarDetalle();

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR SALIDA
    |--------------------------------------------------------------------------
    */

    public function guardar(): void
    {
        $this->autorizarRegistro();

        $datos = $this->validate([
            'fecha' => [
                'required',
                'date',
            ],

            'sede_id' => [
                'required',
                'integer',
                'exists:sedes,id',
            ],

            'tipo_salida' => [
                'required',
                Rule::in([
                    'Venta',
                    'Garantía',
                    'Merma',
                    'Uso interno',
                    'Ajuste',
                ]),
            ],

            'numero_documento' => [
                'nullable',
                'string',
                'max:100',
            ],

            'cliente_destino' => [
                'nullable',
                'string',
                'max:150',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'detalles' => [
                'required',
                'array',
                'min:1',
            ],

            'detalles.*.bateria_id' => [
                'required',
                'integer',
                'distinct',
                'exists:baterias,id',
            ],

            'detalles.*.cantidad' => [
                'required',
                'integer',
                'min:1',
            ],

            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | VALIDAR SEDE DEL USUARIO
        |--------------------------------------------------------------------------
        */

        $this->autorizarSede(
            (int) $datos['sede_id']
        );

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR SEDE ACTIVA
        |--------------------------------------------------------------------------
        */

        $sede = Sede::query()
            ->whereKey(
                (int) $datos['sede_id']
            )
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
        | SI ES VENTA, EL PRECIO DEBE SER MAYOR A CERO
        |--------------------------------------------------------------------------
        */

        if ($datos['tipo_salida'] === 'Venta') {

            foreach (
                $datos['detalles']
                as $indice => $detalle
            ) {

                if (
                    (float) $detalle['precio_unitario']
                    <= 0
                ) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.precio_unitario" =>
                            'En una venta el precio debe ser mayor a cero.',
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($datos) {

            /*
            |--------------------------------------------------------------------------
            | CABECERA
            |--------------------------------------------------------------------------
            */

            $salida = Salida::create([
                'sede_id' =>
                    (int) $datos['sede_id'],

                'user_id' =>
                    auth()->id(),

                'fecha' =>
                    $datos['fecha'],

                'tipo_salida' =>
                    $datos['tipo_salida'],

                'numero_documento' =>
                    $datos['numero_documento']
                        ?: null,

                'cliente_destino' =>
                    $datos['cliente_destino']
                        ?: null,

                'observaciones' =>
                    $datos['observaciones']
                        ?: null,

                'total' => 0,

                'estado' => true,
            ]);

            $totalSalida = 0;

            /*
            |--------------------------------------------------------------------------
            | DETALLES
            |--------------------------------------------------------------------------
            */

            foreach (
                $datos['detalles']
                as $indice => $detalle
            ) {

                /*
                |--------------------------------------------------------------------------
                | BATERÍA ACTIVA
                |--------------------------------------------------------------------------
                */

                $bateria = Bateria::query()
                    ->whereKey(
                        (int) $detalle['bateria_id']
                    )
                    ->where('estado', true)
                    ->first();

                if (! $bateria) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.bateria_id" =>
                            'La batería seleccionada no está activa.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | INVENTARIO
                |--------------------------------------------------------------------------
                |
                | Se bloquea mientras realizamos
                | la reducción del stock.
                |
                */

                $inventario = Inventario::query()
                    ->where(
                        'sede_id',
                        (int) $datos['sede_id']
                    )
                    ->where(
                        'bateria_id',
                        (int) $detalle['bateria_id']
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $inventario) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.bateria_id" =>
                            'Esta batería no pertenece al inventario de la sede seleccionada.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | CANTIDAD
                |--------------------------------------------------------------------------
                */

                $cantidad =
                    (int) $detalle['cantidad'];

                /*
                |--------------------------------------------------------------------------
                | VALIDAR STOCK
                |--------------------------------------------------------------------------
                */

                if (
                    $cantidad >
                    (int) $inventario->stock_actual
                ) {

                    throw ValidationException::withMessages([
                        "detalles.$indice.cantidad" =>
                            'Stock insuficiente. Disponible: '
                            . $inventario->stock_actual
                            . ' unidades. Solicitado: '
                            . $cantidad
                            . ' unidades.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | COSTO PROMEDIO ACTUAL
                |--------------------------------------------------------------------------
                |
                | Guardamos el costo que tenía la batería
                | exactamente al momento de la salida.
                |
                */

                $costoUnitario =
                    round(
                        (float) $inventario->costo_promedio,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | PRECIO UNITARIO
                |--------------------------------------------------------------------------
                */

                $precioUnitario =
                    round(
                        (float) $detalle['precio_unitario'],
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | SUBTOTAL
                |--------------------------------------------------------------------------
                */

                $subtotal =
                    round(
                        $cantidad * $precioUnitario,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | CREAR DETALLE
                |--------------------------------------------------------------------------
                */

                $salida->detalles()->create([
                    'bateria_id' =>
                        (int) $detalle['bateria_id'],

                    'cantidad' =>
                        $cantidad,

                    'costo_unitario' =>
                        $costoUnitario,

                    'precio_unitario' =>
                        $precioUnitario,

                    'subtotal' =>
                        $subtotal,
                ]);

                /*
                |--------------------------------------------------------------------------
                | DESCONTAR STOCK
                |--------------------------------------------------------------------------
                */

                $inventario->stock_actual =
                    (int) $inventario->stock_actual
                    - $cantidad;

                /*
                | El costo promedio NO cambia en una salida.
                */

                $inventario->save();

                /*
                |--------------------------------------------------------------------------
                | TOTAL
                |--------------------------------------------------------------------------
                */

                $totalSalida += $subtotal;
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR TOTAL DE LA SALIDA
            |--------------------------------------------------------------------------
            */

            $salida->total =
                round(
                    $totalSalida,
                    2
                );

            $salida->save();
        });

        session()->flash(
            'mensaje',
            'Salida registrada correctamente. El inventario fue actualizado.'
        );

        $this->limpiarFormulario();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTORIZAR REGISTRO
    |--------------------------------------------------------------------------
    */

    private function autorizarRegistro(): void
    {
        if (! $this->puedeRegistrar()) {

            abort(
                403,
                'No tienes permiso para registrar salidas.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AUTORIZAR SEDE
    |--------------------------------------------------------------------------
    */

    private function autorizarSede(
        int $sedeId
    ): void {

        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        /*
        | Super Administrador y Administrador
        | pueden trabajar con cualquier sede.
        */

        if (
            in_array(
                $usuario?->rol?->slug,
                [
                    'super-administrador',
                    'administrador',
                ],
                true
            )
        ) {
            return;
        }

        /*
        | Los demás solamente pueden operar
        | dentro de su propia sede.
        */

        if (
            ! $usuario?->sede_id
            ||
            (int) $usuario->sede_id
            !== $sedeId
        ) {

            abort(
                403,
                'No tienes permiso para registrar movimientos en esta sede.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PUEDE REGISTRAR
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function puedeRegistrar(): bool
    {
        $usuario = auth()->user();

        $rol =
            $usuario?->rol?->slug;

        /*
        | Acceso completo.
        */

        if (
            in_array(
                $rol,
                [
                    'super-administrador',
                    'administrador',
                ],
                true
            )
        ) {
            return true;
        }

        /*
        | Roles operativos.
        */

        return in_array(
            $rol,
            [
                'encargado-sede',
                'almacen',
            ],
            true
        )
        &&
        $usuario?->sede_id !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | PUEDE ELEGIR SEDE
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function puedeElegirSede(): bool
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
    | SEDES
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function sedes()
    {
        $usuario = auth()->user();

        if ($this->puedeElegirSede()) {

            return Sede::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get();
        }

        return Sede::query()
            ->whereKey(
                $usuario?->sede_id
            )
            ->where('estado', true)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | BATERÍAS CON STOCK
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function baterias()
    {
        if ($this->sede_id === '') {
            return collect();
        }

        $sedeId =
            (int) $this->sede_id;

        return Bateria::query()
            ->where('estado', true)

            ->whereHas(
                'inventarios',
                function ($query) use ($sedeId) {

                    $query
                        ->where(
                            'sede_id',
                            $sedeId
                        )
                        ->where(
                            'stock_actual',
                            '>',
                            0
                        );
                }
            )

            ->with([
                'inventarios' =>
                    function ($query) use ($sedeId) {

                        $query->where(
                            'sede_id',
                            $sedeId
                        );
                    },
            ])

            ->orderBy('marca')
            ->orderBy('modelo')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL DEL FORMULARIO
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function totalFormulario(): float
    {
        $total = 0;

        foreach (
            $this->detalles
            as $detalle
        ) {

            $cantidad =
                is_numeric(
                    $detalle['cantidad']
                        ?? null
                )
                ? (float) $detalle['cantidad']
                : 0;

            $precio =
                is_numeric(
                    $detalle['precio_unitario']
                        ?? null
                )
                ? (float) $detalle['precio_unitario']
                : 0;

            $total +=
                $cantidad * $precio;
        }

        return round(
            $total,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORIAL
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function salidas()
    {
        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        $query = Salida::query()
            ->with([
                'sede',
                'usuario',
                'detalles.bateria',
            ]);

        /*
        |--------------------------------------------------------------------------
        | RESTRICCIÓN POR SEDE
        |--------------------------------------------------------------------------
        */

        if (
            ! in_array(
                $usuario?->rol?->slug,
                [
                    'super-administrador',
                    'administrador',
                ],
                true
            )
        ) {

            if ($usuario?->sede_id) {

                $query->where(
                    'sede_id',
                    $usuario->sede_id
                );

            } else {

                $query->whereRaw(
                    '1 = 0'
                );
            }

        } elseif (
            $this->filtroSede !== ''
        ) {

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

            $buscar =
                $this->buscar;

            $query->where(
                function ($q) use ($buscar) {

                    $q
                        ->where(
                            'numero_documento',
                            'ilike',
                            '%' . $buscar . '%'
                        )

                        ->orWhere(
                            'cliente_destino',
                            'ilike',
                            '%' . $buscar . '%'
                        )

                        ->orWhere(
                            'tipo_salida',
                            'ilike',
                            '%' . $buscar . '%'
                        )

                        ->orWhereHas(
                            'sede',
                            function ($sede) use ($buscar) {

                                $sede->where(
                                    'nombre',
                                    'ilike',
                                    '%' . $buscar . '%'
                                );
                            }
                        )

                        ->orWhereHas(
                            'detalles.bateria',
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
                        );
                }
            );
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */

    private function limpiarFormulario(): void
    {
        $usuario =
            auth()->user();

        $this->fecha =
            now()->format('Y-m-d');

        $this->tipo_salida =
            'Venta';

        $this->numero_documento =
            '';

        $this->cliente_destino =
            '';

        $this->observaciones =
            '';

        /*
        | Administradores deben volver
        | a seleccionar una sede.
        */

        if ($this->puedeElegirSede()) {

            $this->sede_id = '';

        } else {

            $this->sede_id =
                $usuario?->sede_id
                    ? (string) $usuario->sede_id
                    : '';
        }

        $this->detalles = [];

        $this->agregarDetalle();

        $this->resetValidation();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- TÍTULO --}}

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Salidas
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Registro de ventas y otras salidas de baterías.
        </p>
    </div>


    {{-- MENSAJE --}}

    @if (session('mensaje'))

        <div
            class="rounded-lg border border-green-200
                   bg-green-100 p-4 text-green-800"
        >
            {{ session('mensaje') }}
        </div>

    @endif


    {{-- FORMULARIO --}}

    @if ($this->puedeRegistrar)

        <div
            class="rounded-xl bg-white p-6 shadow
                   dark:bg-zinc-900"
        >

            <h2 class="mb-5 text-lg font-semibold">
                Nueva salida
            </h2>

            <form wire:submit="guardar">

                <div
                    class="grid grid-cols-1 gap-4
                           md:grid-cols-2 lg:grid-cols-3"
                >

                    {{-- FECHA --}}

                    <div>

                        <label class="mb-1 block text-sm font-medium">
                            Fecha
                        </label>

                        <input
                            type="date"
                            wire:model="fecha"
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   dark:bg-zinc-800"
                        >

                        @error('fecha')
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
                            wire:model.live="sede_id"
                            @disabled(! $this->puedeElegirSede)
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
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- TIPO SALIDA --}}

                    <div>

                        <label class="mb-1 block text-sm font-medium">
                            Tipo de salida
                        </label>

                        <select
                            wire:model.live="tipo_salida"
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   dark:bg-zinc-800"
                        >

                            <option value="Venta">
                                Venta
                            </option>

                            <option value="Garantía">
                                Garantía
                            </option>

                            <option value="Merma">
                                Merma
                            </option>

                            <option value="Uso interno">
                                Uso interno
                            </option>

                            <option value="Ajuste">
                                Ajuste
                            </option>

                        </select>

                    </div>


                    {{-- DOCUMENTO --}}

                    <div>

                        <label class="mb-1 block text-sm font-medium">
                            Número de documento
                        </label>

                        <input
                            type="text"
                            wire:model="numero_documento"
                            placeholder="B001-000001"
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   dark:bg-zinc-800"
                        >

                    </div>


                    {{-- CLIENTE --}}

                    <div class="md:col-span-2">

                        <label class="mb-1 block text-sm font-medium">
                            Cliente / Destino
                        </label>

                        <input
                            type="text"
                            wire:model="cliente_destino"
                            placeholder="Nombre del cliente o destino"
                            class="w-full rounded-lg
                                   border border-gray-300
                                   px-3 py-2
                                   dark:bg-zinc-800"
                        >

                    </div>

                </div>


                {{-- DETALLES --}}

                <div class="mt-8">

                    <div
                        class="mb-4 flex items-center
                               justify-between"
                    >

                        <h3 class="font-semibold">
                            Baterías de la salida
                        </h3>

                        <button
                            type="button"
                            wire:click="agregarDetalle"
                            class="rounded-lg border
                                   border-blue-500
                                   px-4 py-2 text-sm
                                   font-medium text-blue-500
                                   hover:bg-blue-500
                                   hover:text-white"
                        >
                            + Agregar batería
                        </button>

                    </div>


                    <div class="space-y-4">

                        @foreach ($detalles as $indice => $detalle)

                            <div
                                wire:key="salida-detalle-{{ $indice }}"
                                class="grid grid-cols-1 gap-3
                                       rounded-lg border
                                       border-zinc-700 p-4
                                       md:grid-cols-12"
                            >

                                {{-- BATERÍA --}}

                                <div class="md:col-span-5">

                                    <label class="mb-1 block text-xs">
                                        Batería
                                    </label>

                                    <select
                                        wire:model="detalles.{{ $indice }}.bateria_id"
                                        class="w-full rounded-lg
                                               border border-gray-300
                                               px-3 py-2
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

                                                | Stock:
                                                {{
                                                    $bateria
                                                        ->inventarios
                                                        ->first()
                                                        ?->stock_actual
                                                        ?? 0
                                                }}

                                            </option>

                                        @endforeach

                                    </select>

                                    @error("detalles.$indice.bateria_id")

                                        <span class="mt-1 block text-xs text-red-600">
                                            {{ $message }}
                                        </span>

                                    @enderror

                                </div>


                                {{-- CANTIDAD --}}

                                <div class="md:col-span-2">

                                    <label class="mb-1 block text-xs">
                                        Cantidad
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        wire:model.live="detalles.{{ $indice }}.cantidad"
                                        class="w-full rounded-lg
                                               border border-gray-300
                                               px-3 py-2
                                               dark:bg-zinc-800"
                                    >

                                    @error("detalles.$indice.cantidad")

                                        <span class="mt-1 block text-xs text-red-600">
                                            {{ $message }}
                                        </span>

                                    @enderror

                                </div>


                                {{-- PRECIO --}}

                                <div class="md:col-span-2">

                                    <label class="mb-1 block text-xs">
                                        Precio unitario
                                    </label>

                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        wire:model.live.debounce.300ms="detalles.{{ $indice }}.precio_unitario"
                                        placeholder="365.00"
                                        class="w-full rounded-lg
                                               border border-gray-300
                                               px-3 py-2
                                               dark:bg-zinc-800"
                                    >

                                    @error("detalles.$indice.precio_unitario")

                                        <span class="mt-1 block text-xs text-red-600">
                                            {{ $message }}
                                        </span>

                                    @enderror

                                </div>


                                {{-- SUBTOTAL --}}

                                <div class="md:col-span-2">

                                    <label class="mb-1 block text-xs">
                                        Subtotal
                                    </label>

                                    <div
                                        class="rounded-lg border
                                               border-gray-700
                                               px-3 py-2"
                                    >
                                        S/
                                        {{ number_format(
                                            (
                                                (float) ($detalle['cantidad'] ?? 0)
                                                *
                                                (float) ($detalle['precio_unitario'] ?? 0)
                                            ),
                                            2
                                        ) }}
                                    </div>

                                </div>


                                {{-- QUITAR --}}

                                <div class="flex items-end md:col-span-1">

                                    <button
                                        type="button"
                                        wire:click="quitarDetalle({{ $indice }})"
                                        class="w-full rounded-lg
                                               bg-red-600 px-3 py-2
                                               text-white
                                               hover:bg-red-700"
                                    >
                                        ×
                                    </button>

                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- OBSERVACIONES --}}

                <div class="mt-5">

                    <label class="mb-1 block text-sm font-medium">
                        Observaciones
                    </label>

                    <textarea
                        wire:model="observaciones"
                        rows="3"
                        placeholder="Información adicional de la salida..."
                        class="w-full rounded-lg
                               border border-gray-300
                               px-3 py-2
                               dark:bg-zinc-800"
                    ></textarea>

                </div>


                {{-- TOTAL --}}

                <div
                    class="mt-6 flex flex-col gap-4
                           border-t border-zinc-700 pt-5
                           md:flex-row
                           md:items-center
                           md:justify-between"
                >

                    <div>

                        <span class="text-sm text-gray-500">
                            Total de la salida
                        </span>

                        <div class="text-2xl font-bold">
                            S/
                            {{ number_format(
                                $this->totalFormulario,
                                2
                            ) }}
                        </div>

                    </div>


                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        class="rounded-lg bg-blue-600
                               px-6 py-3
                               font-semibold text-white
                               hover:bg-blue-700
                               disabled:opacity-50"
                    >
                        Registrar salida
                    </button>

                </div>

            </form>

        </div>

    @endif


    {{-- HISTORIAL --}}

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
                    Historial de salidas
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Últimas salidas registradas.
                </p>

            </div>


            <div class="flex flex-col gap-3 sm:flex-row">

                @if ($this->puedeElegirSede)

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


                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Buscar salida..."
                    class="rounded-lg border
                           border-gray-300
                           px-3 py-2
                           dark:bg-zinc-800"
                >

            </div>

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b">

                    <tr>

                        <th class="px-4 py-3">
                            N.º
                        </th>

                        <th class="px-4 py-3">
                            Fecha
                        </th>

                        <th class="px-4 py-3">
                            Sede
                        </th>

                        <th class="px-4 py-3">
                            Tipo
                        </th>

                        <th class="px-4 py-3">
                            Cliente / Documento
                        </th>

                        <th class="px-4 py-3">
                            Baterías
                        </th>

                        <th class="px-4 py-3">
                            Total
                        </th>

                        <th class="px-4 py-3">
                            Usuario
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($this->salidas as $salida)

                        <tr
                            wire:key="salida-{{ $salida->id }}"
                            class="border-b align-top"
                        >

                            <td class="px-4 py-3 font-medium">

                                #{{ str_pad(
                                    (string) $salida->id,
                                    6,
                                    '0',
                                    STR_PAD_LEFT
                                ) }}

                            </td>


                            <td class="px-4 py-3">

                                {{ $salida->fecha?->format('d/m/Y') }}

                            </td>


                            <td class="px-4 py-3">

                                {{ $salida->sede?->nombre }}

                            </td>


                            <td class="px-4 py-3">

                                {{ $salida->tipo_salida }}

                            </td>


                            <td class="px-4 py-3">

                                <div>
                                    {{ $salida->cliente_destino ?: '-' }}
                                </div>

                                @if ($salida->numero_documento)

                                    <div class="text-xs text-gray-500">
                                        {{ $salida->numero_documento }}
                                    </div>

                                @endif

                            </td>


                            <td class="px-4 py-3">

                                @foreach ($salida->detalles as $detalle)

                                    <div class="mb-1">

                                        <span class="font-medium">
                                            {{ $detalle->bateria?->codigo }}
                                        </span>

                                        <span class="text-gray-500">
                                            × {{ $detalle->cantidad }}
                                        </span>

                                    </div>

                                @endforeach

                            </td>


                            <td class="px-4 py-3 font-semibold">

                                S/
                                {{ number_format(
                                    (float) $salida->total,
                                    2
                                ) }}

                            </td>


                            <td class="px-4 py-3">

                                {{ $salida->usuario?->name }}

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="px-4 py-8
                                       text-center text-gray-500"
                            >
                                Todavía no existen salidas registradas.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>