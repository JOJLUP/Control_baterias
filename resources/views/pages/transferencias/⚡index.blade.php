<?php

use App\Models\Inventario;
use App\Models\Sede;
use App\Models\Transferencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $fecha = '';
    public string $sede_origen_id = '';
    public string $sede_destino_id = '';
    public string $observaciones = '';

    public array $detalles = [];

    public string $buscar = '';
    public string $filtroSede = '';

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');

        $usuario = auth()->user();

        $usuario?->loadMissing([
            'rol',
            'sede',
        ]);

        /*
        | Usuarios operativos:
        | la sede origen será siempre su propia sede.
        */
        if (! $this->puedeElegirOrigen()) {
            if ($usuario?->sede_id) {
                $this->sede_origen_id = (string) $usuario->sede_id;
                $this->filtroSede = (string) $usuario->sede_id;
            }
        }

        $this->agregarDetalle();
    }

    /*
    |--------------------------------------------------------------------------
    | AGREGAR DETALLE
    |--------------------------------------------------------------------------
    */

    public function agregarDetalle(): void
    {
        $this->detalles[] = [
            'bateria_id' => '',
            'cantidad' => '1',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUITAR DETALLE
    |--------------------------------------------------------------------------
    */

    public function quitarDetalle(int $indice): void
    {
        if (count($this->detalles) <= 1) {
            return;
        }

        unset($this->detalles[$indice]);

        $this->detalles = array_values($this->detalles);
    }

    /*
    |--------------------------------------------------------------------------
    | CAMBIO DE ORIGEN
    |--------------------------------------------------------------------------
    */

    public function updatedSedeOrigenId(): void
    {
        /*
        | Si destino y origen fueran iguales,
        | limpiamos destino.
        */
        if (
            $this->sede_destino_id !== ''
            &&
            $this->sede_destino_id === $this->sede_origen_id
        ) {
            $this->sede_destino_id = '';
        }

        /*
        | Limpiar baterías seleccionadas porque
        | pertenecían al inventario anterior.
        */
        $this->detalles = [];

        $this->agregarDetalle();

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR TRANSFERENCIA
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

            'sede_origen_id' => [
                'required',
                'integer',
                'exists:sedes,id',
            ],

            'sede_destino_id' => [
                'required',
                'integer',
                'different:sede_origen_id',
                'exists:sedes,id',
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
        ]);

        /*
        |--------------------------------------------------------------------------
        | AUTORIZAR SEDE ORIGEN
        |--------------------------------------------------------------------------
        */

        $this->autorizarOrigen(
            (int) $datos['sede_origen_id']
        );

        /*
        |--------------------------------------------------------------------------
        | COMPROBAR SEDES ACTIVAS
        |--------------------------------------------------------------------------
        */

        $origen = Sede::query()
            ->whereKey((int) $datos['sede_origen_id'])
            ->where('estado', true)
            ->first();

        $destino = Sede::query()
            ->whereKey((int) $datos['sede_destino_id'])
            ->where('estado', true)
            ->first();

        if (! $origen) {
            $this->addError(
                'sede_origen_id',
                'La sede de origen no está activa.'
            );

            return;
        }

        if (! $destino) {
            $this->addError(
                'sede_destino_id',
                'La sede de destino no está activa.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN COMPLETA
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($datos) {

            /*
            |--------------------------------------------------------------------------
            | CREAR CABECERA
            |--------------------------------------------------------------------------
            |
            | Primero usamos un número temporal único.
            | Después usamos el ID para formar TRF-000001.
            |
            */

            $transferencia = Transferencia::create([
                'sede_origen_id' =>
                    (int) $datos['sede_origen_id'],

                'sede_destino_id' =>
                    (int) $datos['sede_destino_id'],

                'user_id' =>
                    auth()->id(),

                'fecha' =>
                    $datos['fecha'],

                'numero' =>
                    'TMP-' . Str::uuid(),

                'observaciones' =>
                    $datos['observaciones'] ?: null,

                'estado' =>
                    true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | NÚMERO DEFINITIVO
            |--------------------------------------------------------------------------
            */

            $transferencia->numero =
                'TRF-' . str_pad(
                    (string) $transferencia->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $transferencia->save();

            /*
            |--------------------------------------------------------------------------
            | PROCESAR BATERÍAS
            |--------------------------------------------------------------------------
            */

            foreach ($datos['detalles'] as $indice => $detalle) {

                $bateriaId =
                    (int) $detalle['bateria_id'];

                $cantidad =
                    (int) $detalle['cantidad'];

                /*
                |--------------------------------------------------------------------------
                | INVENTARIO ORIGEN
                |--------------------------------------------------------------------------
                */

                $inventarioOrigen = Inventario::query()
                    ->where(
                        'sede_id',
                        (int) $datos['sede_origen_id']
                    )
                    ->where(
                        'bateria_id',
                        $bateriaId
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $inventarioOrigen) {
                    throw ValidationException::withMessages([
                        "detalles.$indice.bateria_id" =>
                            'La batería no pertenece al inventario de la sede de origen.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR STOCK
                |--------------------------------------------------------------------------
                */

                $stockOrigen =
                    (int) $inventarioOrigen->stock_actual;

                if ($cantidad > $stockOrigen) {
                    throw ValidationException::withMessages([
                        "detalles.$indice.cantidad" =>
                            'Stock insuficiente. Disponible: '
                            . $stockOrigen
                            . ' unidades. Solicitado: '
                            . $cantidad
                            . ' unidades.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | COSTO DE LAS UNIDADES TRANSFERIDAS
                |--------------------------------------------------------------------------
                */

                $costoOrigen =
                    round(
                        (float) $inventarioOrigen->costo_promedio,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | INVENTARIO DESTINO
                |--------------------------------------------------------------------------
                */

                $inventarioDestino = Inventario::query()
                    ->where(
                        'sede_id',
                        (int) $datos['sede_destino_id']
                    )
                    ->where(
                        'bateria_id',
                        $bateriaId
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                | Si la batería todavía no existe en el inventario
                | de destino, se crea automáticamente.
                */
                if (! $inventarioDestino) {

                    $inventarioDestino = new Inventario();

                    $inventarioDestino->sede_id =
                        (int) $datos['sede_destino_id'];

                    $inventarioDestino->bateria_id =
                        $bateriaId;

                    $inventarioDestino->stock_actual =
                        0;

                    $inventarioDestino->stock_minimo =
                        0;

                    $inventarioDestino->costo_promedio =
                        0;

                    $inventarioDestino->save();
                }

                /*
                |--------------------------------------------------------------------------
                | DATOS DESTINO ANTES DE TRANSFERIR
                |--------------------------------------------------------------------------
                */

                $stockDestinoAnterior =
                    (int) $inventarioDestino->stock_actual;

                $costoDestinoAnterior =
                    (float) $inventarioDestino->costo_promedio;

                /*
                |--------------------------------------------------------------------------
                | NUEVO STOCK DESTINO
                |--------------------------------------------------------------------------
                */

                $nuevoStockDestino =
                    $stockDestinoAnterior + $cantidad;

                /*
                |--------------------------------------------------------------------------
                | NUEVO COSTO PROMEDIO DESTINO
                |--------------------------------------------------------------------------
                |
                | Ejemplo:
                |
                | Destino:
                | 5 unidades × S/ 300 = 1500
                |
                | Transferencia:
                | 3 unidades × S/ 280 = 840
                |
                | Total:
                | 8 unidades
                |
                | Nuevo costo:
                | (1500 + 840) / 8 = S/ 292.50
                |
                */

                $valorDestinoAnterior =
                    $stockDestinoAnterior
                    *
                    $costoDestinoAnterior;

                $valorTransferido =
                    $cantidad
                    *
                    $costoOrigen;

                $nuevoCostoDestino =
                    $nuevoStockDestino > 0
                        ? (
                            $valorDestinoAnterior
                            +
                            $valorTransferido
                        ) / $nuevoStockDestino
                        : 0;

                /*
                |--------------------------------------------------------------------------
                | RESTAR EN ORIGEN
                |--------------------------------------------------------------------------
                */

                $inventarioOrigen->stock_actual =
                    $stockOrigen - $cantidad;

                /*
                | El costo promedio del origen no cambia
                | al retirar unidades.
                */

                $inventarioOrigen->save();

                /*
                |--------------------------------------------------------------------------
                | SUMAR EN DESTINO
                |--------------------------------------------------------------------------
                */

                $inventarioDestino->stock_actual =
                    $nuevoStockDestino;

                $inventarioDestino->costo_promedio =
                    round(
                        $nuevoCostoDestino,
                        2
                    );

                $inventarioDestino->save();

                /*
                |--------------------------------------------------------------------------
                | GUARDAR DETALLE
                |--------------------------------------------------------------------------
                */

                $transferencia->detalles()->create([
                    'bateria_id' =>
                        $bateriaId,

                    'cantidad' =>
                        $cantidad,

                    'costo_unitario' =>
                        $costoOrigen,
                ]);
            }
        });

        session()->flash(
            'mensaje',
            'Transferencia registrada correctamente. Los inventarios fueron actualizados.'
        );

        $this->limpiarFormulario();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTORIZACIÓN
    |--------------------------------------------------------------------------
    */

    private function autorizarRegistro(): void
    {
        if (! $this->puedeRegistrar()) {
            abort(
                403,
                'No tienes permiso para registrar transferencias.'
            );
        }
    }

    private function autorizarOrigen(int $sedeId): void
    {
        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        /*
        | Administradores pueden transferir
        | desde cualquier sede.
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
        | Usuarios operativos solamente
        | desde su sede.
        */
        if (
            ! $usuario?->sede_id
            ||
            (int) $usuario->sede_id !== $sedeId
        ) {
            abort(
                403,
                'No puedes transferir existencias desde esta sede.'
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

        $rol = $usuario?->rol?->slug;

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
    | PUEDE ELEGIR ORIGEN
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function puedeElegirOrigen(): bool
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
    | SEDES DE ORIGEN
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function sedesOrigen()
    {
        $usuario = auth()->user();

        if ($this->puedeElegirOrigen()) {
            return Sede::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get();
        }

        return Sede::query()
            ->whereKey($usuario?->sede_id)
            ->where('estado', true)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SEDES DE DESTINO
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function sedesDestino()
    {
        return Sede::query()
            ->where('estado', true)
            ->when(
                $this->sede_origen_id !== '',
                fn ($query) =>
                    $query->where(
                        'id',
                        '!=',
                        (int) $this->sede_origen_id
                    )
            )
            ->orderBy('nombre')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO DISPONIBLE DEL ORIGEN
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function inventariosOrigen()
    {
        if ($this->sede_origen_id === '') {
            return collect();
        }

        return Inventario::query()
            ->with('bateria')
            ->where(
                'sede_id',
                (int) $this->sede_origen_id
            )
            ->where(
                'stock_actual',
                '>',
                0
            )
            ->whereHas(
                'bateria',
                fn ($query) =>
                    $query->where('estado', true)
            )
            ->orderBy('bateria_id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL DE UNIDADES
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function totalUnidades(): int
    {
        $total = 0;

        foreach ($this->detalles as $detalle) {
            $total +=
                is_numeric($detalle['cantidad'] ?? null)
                    ? (int) $detalle['cantidad']
                    : 0;
        }

        return $total;
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORIAL
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function transferencias()
    {
        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        $query = Transferencia::query()
            ->with([
                'sedeOrigen',
                'sedeDestino',
                'usuario',
                'detalles.bateria',
            ]);

        /*
        | Usuarios operativos pueden ver transferencias
        | que salieron o llegaron a su sede.
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

                $query->where(function ($q) use ($usuario) {
                    $q
                        ->where(
                            'sede_origen_id',
                            $usuario->sede_id
                        )
                        ->orWhere(
                            'sede_destino_id',
                            $usuario->sede_id
                        );
                });

            } else {

                $query->whereRaw('1 = 0');
            }

        } elseif ($this->filtroSede !== '') {

            $sedeId =
                (int) $this->filtroSede;

            $query->where(function ($q) use ($sedeId) {
                $q
                    ->where(
                        'sede_origen_id',
                        $sedeId
                    )
                    ->orWhere(
                        'sede_destino_id',
                        $sedeId
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | BUSCADOR
        |--------------------------------------------------------------------------
        */

        if ($this->buscar !== '') {

            $buscar = $this->buscar;

            $query->where(function ($q) use ($buscar) {

                $q->where(
                    'numero',
                    'like',
                    '%' . $buscar . '%'
                )

                ->orWhereHas(
                    'sedeOrigen',
                    fn ($sede) =>
                        $sede->where(
                            'nombre',
                            'like',
                            '%' . $buscar . '%'
                        )
                )

                ->orWhereHas(
                    'sedeDestino',
                    fn ($sede) =>
                        $sede->where(
                            'nombre',
                            'like',
                            '%' . $buscar . '%'
                        )
                )

                ->orWhereHas(
                    'detalles.bateria',
                    function ($bateria) use ($buscar) {

                        $bateria
                            ->where(
                                'codigo',
                                'like',
                                '%' . $buscar . '%'
                            )
                            ->orWhere(
                                'marca',
                                'like',
                                '%' . $buscar . '%'
                            )
                            ->orWhere(
                                'modelo',
                                'like',
                                '%' . $buscar . '%'
                            );
                    }
                );
            });
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR
    |--------------------------------------------------------------------------
    */

    private function limpiarFormulario(): void
    {
        $usuario = auth()->user();

        $this->fecha =
            now()->format('Y-m-d');

        $this->sede_destino_id =
            '';

        $this->observaciones =
            '';

        if ($this->puedeElegirOrigen()) {
            $this->sede_origen_id = '';
        } else {
            $this->sede_origen_id =
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
            Transferencias
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Movimiento de baterías entre sedes.
        </p>
    </div>

    {{-- MENSAJE --}}

    @if (session('mensaje'))
        <div class="rounded-lg border border-green-200 bg-green-100 p-4 text-green-800">
            {{ session('mensaje') }}
        </div>
    @endif

    {{-- FORMULARIO --}}

    @if ($this->puedeRegistrar)

        <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

            <h2 class="mb-5 text-lg font-semibold">
                Nueva transferencia
            </h2>

            <form wire:submit="guardar">

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                    {{-- FECHA --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Fecha
                        </label>

                        <input
                            type="date"
                            wire:model="fecha"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('fecha')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- ORIGEN --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Sede de origen
                        </label>

                        <select
                            wire:model.live="sede_origen_id"
                            @disabled(! $this->puedeElegirOrigen)
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-60 dark:bg-zinc-800"
                        >
                            <option value="">
                                Seleccionar sede de origen
                            </option>

                            @foreach ($this->sedesOrigen as $sede)
                                <option value="{{ $sede->id }}">
                                    {{ $sede->codigo }} - {{ $sede->nombre }}
                                </option>
                            @endforeach
                        </select>

                        @error('sede_origen_id')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- DESTINO --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Sede de destino
                        </label>

                        <select
                            wire:model="sede_destino_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >
                            <option value="">
                                Seleccionar sede de destino
                            </option>

                            @foreach ($this->sedesDestino as $sede)
                                <option value="{{ $sede->id }}">
                                    {{ $sede->codigo }} - {{ $sede->nombre }}
                                </option>
                            @endforeach
                        </select>

                        @error('sede_destino_id')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>

                </div>

                {{-- DETALLES --}}

                <div class="mt-8">

                    <div class="mb-4 flex items-center justify-between">

                        <h3 class="font-semibold">
                            Baterías a transferir
                        </h3>

                        <button
                            type="button"
                            wire:click="agregarDetalle"
                            class="rounded-lg border border-blue-500 px-4 py-2 text-sm font-medium text-blue-500 hover:bg-blue-500 hover:text-white"
                        >
                            + Agregar batería
                        </button>

                    </div>

                    <div class="space-y-4">

                        @foreach ($detalles as $indice => $detalle)

                            <div
                                wire:key="transferencia-detalle-{{ $indice }}"
                                class="grid grid-cols-1 gap-3 rounded-lg border border-zinc-700 p-4 md:grid-cols-12"
                            >

                                <div class="md:col-span-8">

                                    <label class="mb-1 block text-xs">
                                        Batería
                                    </label>

                                    <select
                                        wire:model="detalles.{{ $indice }}.bateria_id"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                                    >
                                        <option value="">
                                            Seleccionar batería
                                        </option>

                                        @foreach ($this->inventariosOrigen as $inventario)

                                            <option value="{{ $inventario->bateria_id }}">
                                                {{ $inventario->bateria?->codigo }}
                                                -
                                                {{ $inventario->bateria?->marca }}
                                                {{ $inventario->bateria?->modelo }}
                                                | Stock: {{ $inventario->stock_actual }}
                                            </option>

                                        @endforeach
                                    </select>

                                    @error("detalles.$indice.bateria_id")
                                        <span class="mt-1 block text-xs text-red-600">
                                            {{ $message }}
                                        </span>
                                    @enderror

                                </div>

                                <div class="md:col-span-3">

                                    <label class="mb-1 block text-xs">
                                        Cantidad
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        wire:model.live="detalles.{{ $indice }}.cantidad"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                                    >

                                    @error("detalles.$indice.cantidad")
                                        <span class="mt-1 block text-xs text-red-600">
                                            {{ $message }}
                                        </span>
                                    @enderror

                                </div>

                                <div class="flex items-end md:col-span-1">

                                    <button
                                        type="button"
                                        wire:click="quitarDetalle({{ $indice }})"
                                        class="w-full rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700"
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
                        placeholder="Motivo o información adicional de la transferencia..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    ></textarea>

                </div>

                {{-- TOTAL --}}

                <div class="mt-6 flex flex-col gap-4 border-t border-zinc-700 pt-5 md:flex-row md:items-center md:justify-between">

                    <div>
                        <span class="text-sm text-gray-500">
                            Total de unidades
                        </span>

                        <div class="text-2xl font-bold">
                            {{ $this->totalUnidades }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="guardar"
                        class="rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        Registrar transferencia
                    </button>

                </div>

            </form>

        </div>

    @endif

    {{-- HISTORIAL --}}

    <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

            <div>
                <h2 class="text-lg font-semibold">
                    Historial de transferencias
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Últimos movimientos entre sedes.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">

                @if ($this->puedeElegirOrigen)

                    <select
                        wire:model.live="filtroSede"
                        class="rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                    >
                        <option value="">
                            Todas las sedes
                        </option>

                        @foreach ($this->sedesOrigen as $sede)
                            <option value="{{ $sede->id }}">
                                {{ $sede->nombre }}
                            </option>
                        @endforeach
                    </select>

                @endif

                <input
                    type="text"
                    wire:model.live.debounce.300ms="buscar"
                    placeholder="Buscar transferencia..."
                    class="rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
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
                            Origen
                        </th>

                        <th class="px-4 py-3">
                            Destino
                        </th>

                        <th class="px-4 py-3">
                            Baterías
                        </th>

                        <th class="px-4 py-3">
                            Unidades
                        </th>

                        <th class="px-4 py-3">
                            Usuario
                        </th>
                    </tr>

                </thead>

                <tbody>

                    @forelse ($this->transferencias as $transferencia)

                        <tr
                            wire:key="transferencia-{{ $transferencia->id }}"
                            class="border-b align-top"
                        >

                            <td class="px-4 py-3 font-medium">
                                {{ $transferencia->numero }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $transferencia->fecha?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $transferencia->sedeOrigen?->nombre }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $transferencia->sedeDestino?->nombre }}
                            </td>

                            <td class="px-4 py-3">

                                @foreach ($transferencia->detalles as $detalle)

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
                                {{ $transferencia->detalles->sum('cantidad') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $transferencia->usuario?->name }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Todavía no existen transferencias registradas.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>