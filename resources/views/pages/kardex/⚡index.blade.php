<?php

use App\Models\Bateria;
use App\Models\IngresoDetalle;
use App\Models\Inventario;
use App\Models\SalidaDetalle;
use App\Models\Sede;
use App\Models\TransferenciaDetalle;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $sede_id = '';
    public string $bateria_id = '';

    public string $fecha_desde = '';
    public string $fecha_hasta = '';

    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->fecha_desde = now()
            ->startOfMonth()
            ->format('Y-m-d');

        $this->fecha_hasta = now()
            ->format('Y-m-d');

        $usuario = auth()->user();

        $usuario?->loadMissing([
            'rol',
            'sede',
        ]);

        /*
        | Usuarios que no son administradores
        | quedan limitados a su propia sede.
        */
        if (! $this->puedeElegirSede()) {
            if ($usuario?->sede_id) {
                $this->sede_id = (string) $usuario->sede_id;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISOS
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
    | SEDE EFECTIVA
    |--------------------------------------------------------------------------
    */

    public function sedeEfectiva(): ?int
    {
        if (! $this->puedeElegirSede()) {
            return auth()->user()?->sede_id
                ? (int) auth()->user()->sede_id
                : null;
        }

        return $this->sede_id !== ''
            ? (int) $this->sede_id
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | SEDES
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function sedes()
    {
        if ($this->puedeElegirSede()) {
            return Sede::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get();
        }

        return Sede::query()
            ->whereKey(auth()->user()?->sede_id)
            ->where('estado', true)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | BATERÍAS
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function baterias()
    {
        $sedeId = $this->sedeEfectiva();

        /*
        | Si hay sede seleccionada, mostramos baterías
        | que hayan sido asignadas al inventario de esa sede.
        */
        if ($sedeId) {
            return Bateria::query()
                ->where('estado', true)
                ->whereHas(
                    'inventarios',
                    fn ($query) =>
                        $query->where('sede_id', $sedeId)
                )
                ->orderBy('codigo')
                ->get();
        }

        return Bateria::query()
            ->where('estado', true)
            ->orderBy('codigo')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | CAMBIAR SEDE
    |--------------------------------------------------------------------------
    */

    public function updatedSedeId(): void
    {
        $this->bateria_id = '';
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO ACTUAL
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function inventarioActual(): ?Inventario
    {
        $sedeId = $this->sedeEfectiva();

        if (
            ! $sedeId
            ||
            $this->bateria_id === ''
        ) {
            return null;
        }

        return Inventario::query()
            ->with([
                'sede',
                'bateria',
            ])
            ->where(
                'sede_id',
                $sedeId
            )
            ->where(
                'bateria_id',
                (int) $this->bateria_id
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | TODOS LOS MOVIMIENTOS
    |--------------------------------------------------------------------------
    |
    | Primero obtenemos TODOS los movimientos hasta fecha_hasta.
    | Esto permite calcular correctamente el saldo acumulado,
    | aunque el usuario filtre desde una fecha posterior.
    |
    */

    #[Computed]
    public function movimientos()
    {
        $sedeId = $this->sedeEfectiva();

        if (
            ! $sedeId
            ||
            $this->bateria_id === ''
        ) {
            return collect();
        }

        $bateriaId = (int) $this->bateria_id;

        $movimientos = collect();

        /*
        |--------------------------------------------------------------------------
        | INGRESOS
        |--------------------------------------------------------------------------
        */

        $ingresos = IngresoDetalle::query()
            ->with([
                'ingreso',
                'bateria',
            ])
            ->where(
                'bateria_id',
                $bateriaId
            )
            ->whereHas(
                'ingreso',
                function ($query) use ($sedeId) {
                    $query
                        ->where(
                            'sede_id',
                            $sedeId
                        )
                        ->where(
                            'estado',
                            true
                        );

                    if ($this->fecha_hasta !== '') {
                        $query->whereDate(
                            'fecha',
                            '<=',
                            $this->fecha_hasta
                        );
                    }
                }
            )
            ->get();

        foreach ($ingresos as $detalle) {
            $ingreso = $detalle->ingreso;

            if (! $ingreso) {
                continue;
            }

            $movimientos->push([
                'tipo' => 'Ingreso',

                'fecha' =>
                    Carbon::parse($ingreso->fecha),

                'fecha_texto' =>
                    Carbon::parse($ingreso->fecha)
                        ->format('d/m/Y'),

                'entrada' =>
                    (int) $detalle->cantidad,

                'salida' =>
                    0,

                'costo_unitario' =>
                    (float) $detalle->costo_unitario,

                'referencia' =>
                    'ING-' . str_pad(
                        (string) $ingreso->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),

                'detalle' =>
                    'Ingreso de mercadería',

                'created_at' =>
                    $ingreso->created_at,

                'orden_id' =>
                    (int) $ingreso->id,

                'prioridad' =>
                    1,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SALIDAS
        |--------------------------------------------------------------------------
        */

        $salidas = SalidaDetalle::query()
            ->with([
                'salida',
                'bateria',
            ])
            ->where(
                'bateria_id',
                $bateriaId
            )
            ->whereHas(
                'salida',
                function ($query) use ($sedeId) {
                    $query
                        ->where(
                            'sede_id',
                            $sedeId
                        )
                        ->where(
                            'estado',
                            true
                        );

                    if ($this->fecha_hasta !== '') {
                        $query->whereDate(
                            'fecha',
                            '<=',
                            $this->fecha_hasta
                        );
                    }
                }
            )
            ->get();

        foreach ($salidas as $detalle) {
            $salida = $detalle->salida;

            if (! $salida) {
                continue;
            }

            $tipoSalida =
                $salida->tipo_salida
                ?: 'Salida';

            $movimientos->push([
                'tipo' =>
                    $tipoSalida === 'Venta'
                        ? 'Venta'
                        : 'Salida',

                'fecha' =>
                    Carbon::parse($salida->fecha),

                'fecha_texto' =>
                    Carbon::parse($salida->fecha)
                        ->format('d/m/Y'),

                'entrada' =>
                    0,

                'salida' =>
                    (int) $detalle->cantidad,

                'costo_unitario' =>
                    (float) $detalle->costo_unitario,

                'referencia' =>
                    'SAL-' . str_pad(
                        (string) $salida->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),

                'detalle' =>
                    $tipoSalida,

                'created_at' =>
                    $salida->created_at,

                'orden_id' =>
                    (int) $salida->id,

                'prioridad' =>
                    2,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSFERENCIAS
        |--------------------------------------------------------------------------
        |
        | Una transferencia puede ser:
        |
        | - SALIDA para la sede de origen
        | - ENTRADA para la sede de destino
        |
        */

        $transferencias = TransferenciaDetalle::query()
            ->with([
                'transferencia.sedeOrigen',
                'transferencia.sedeDestino',
                'bateria',
            ])
            ->where(
                'bateria_id',
                $bateriaId
            )
            ->whereHas(
                'transferencia',
                function ($query) use ($sedeId) {
                    $query
                        ->where(
                            'estado',
                            true
                        )
                        ->where(
                            function ($q) use ($sedeId) {
                                $q
                                    ->where(
                                        'sede_origen_id',
                                        $sedeId
                                    )
                                    ->orWhere(
                                        'sede_destino_id',
                                        $sedeId
                                    );
                            }
                        );

                    if ($this->fecha_hasta !== '') {
                        $query->whereDate(
                            'fecha',
                            '<=',
                            $this->fecha_hasta
                        );
                    }
                }
            )
            ->get();

        foreach ($transferencias as $detalle) {
            $transferencia =
                $detalle->transferencia;

            if (! $transferencia) {
                continue;
            }

            /*
            | Sale de esta sede.
            */
            if (
                (int) $transferencia->sede_origen_id
                ===
                $sedeId
            ) {
                $movimientos->push([
                    'tipo' =>
                        'Transferencia salida',

                    'fecha' =>
                        Carbon::parse(
                            $transferencia->fecha
                        ),

                    'fecha_texto' =>
                        Carbon::parse(
                            $transferencia->fecha
                        )->format('d/m/Y'),

                    'entrada' =>
                        0,

                    'salida' =>
                        (int) $detalle->cantidad,

                    'costo_unitario' =>
                        (float) $detalle->costo_unitario,

                    'referencia' =>
                        $transferencia->numero,

                    'detalle' =>
                        'Hacia '
                        .
                        (
                            $transferencia
                                ->sedeDestino
                                ?->nombre
                            ??
                            'otra sede'
                        ),

                    'created_at' =>
                        $transferencia->created_at,

                    'orden_id' =>
                        (int) $transferencia->id,

                    'prioridad' =>
                        3,
                ]);
            }

            /*
            | Entra a esta sede.
            */
            if (
                (int) $transferencia->sede_destino_id
                ===
                $sedeId
            ) {
                $movimientos->push([
                    'tipo' =>
                        'Transferencia entrada',

                    'fecha' =>
                        Carbon::parse(
                            $transferencia->fecha
                        ),

                    'fecha_texto' =>
                        Carbon::parse(
                            $transferencia->fecha
                        )->format('d/m/Y'),

                    'entrada' =>
                        (int) $detalle->cantidad,

                    'salida' =>
                        0,

                    'costo_unitario' =>
                        (float) $detalle->costo_unitario,

                    'referencia' =>
                        $transferencia->numero,

                    'detalle' =>
                        'Desde '
                        .
                        (
                            $transferencia
                                ->sedeOrigen
                                ?->nombre
                            ??
                            'otra sede'
                        ),

                    'created_at' =>
                        $transferencia->created_at,

                    'orden_id' =>
                        (int) $transferencia->id,

                    'prioridad' =>
                        3,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ORDEN CRONOLÓGICO
        |--------------------------------------------------------------------------
        */

        $movimientos = $movimientos
            ->sortBy(function ($movimiento) {

                $fecha =
                    $movimiento['fecha']
                        ->format('Y-m-d');

                $creado =
                    $movimiento['created_at']
                        ? Carbon::parse(
                            $movimiento['created_at']
                        )->format('H:i:s.u')
                        : '00:00:00.000000';

                return
                    $fecha
                    . ' '
                    . $creado
                    . '-'
                    . str_pad(
                        (string) $movimiento['prioridad'],
                        2,
                        '0',
                        STR_PAD_LEFT
                    )
                    . '-'
                    . str_pad(
                        (string) $movimiento['orden_id'],
                        10,
                        '0',
                        STR_PAD_LEFT
                    );
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | CALCULAR SALDO ACUMULADO
        |--------------------------------------------------------------------------
        */

        $saldo = 0;

        $movimientos = $movimientos
            ->map(function ($movimiento) use (&$saldo) {

                $movimiento['saldo_anterior'] =
                    $saldo;

                $saldo +=
                    (int) $movimiento['entrada'];

                $saldo -=
                    (int) $movimiento['salida'];

                $movimiento['saldo'] =
                    $saldo;

                return $movimiento;
            });

        /*
        |--------------------------------------------------------------------------
        | FILTRAR DESDE
        |--------------------------------------------------------------------------
        |
        | El saldo ya se calculó antes de filtrar.
        | Así mantenemos el saldo histórico correcto.
        |
        */

        if ($this->fecha_desde !== '') {
            $desde = Carbon::parse(
                $this->fecha_desde
            )->startOfDay();

            $movimientos = $movimientos
                ->filter(
                    fn ($movimiento) =>
                        $movimiento['fecha']
                            ->startOfDay()
                            ->greaterThanOrEqualTo($desde)
                )
                ->values();
        }

        return $movimientos;
    }

    /*
    |--------------------------------------------------------------------------
    | RESUMEN DEL PERÍODO
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function resumen(): array
    {
        $movimientos = $this->movimientos;

        $entradas = (int) $movimientos
            ->sum('entrada');

        $salidas = (int) $movimientos
            ->sum('salida');

        $saldoInicial = 0;

        if ($movimientos->isNotEmpty()) {
            $saldoInicial =
                (int) $movimientos
                    ->first()['saldo_anterior'];
        }

        $saldoFinal =
            $saldoInicial
            +
            $entradas
            -
            $salidas;

        return [
            'saldo_inicial' =>
                $saldoInicial,

            'entradas' =>
                $entradas,

            'salidas' =>
                $salidas,

            'saldo_final' =>
                $saldoFinal,
        ];
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- ============================================================= --}}
    {{-- TÍTULO --}}
    {{-- ============================================================= --}}

    <div>
        <h1 class="text-2xl font-bold text-white">
            Kardex
        </h1>

        <p class="mt-1 text-sm text-zinc-400">
            Historial de entradas, salidas y transferencias por batería y sede.
        </p>
    </div>


    {{-- ============================================================= --}}
    {{-- FILTROS --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <h2 class="mb-5 text-lg font-semibold text-white">
            Consultar movimientos
        </h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

            {{-- SEDE --}}

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Sede
                </label>

                <select
                    wire:model.live="sede_id"
                    @disabled(! $this->puedeElegirSede)
                    class="w-full rounded-lg border border-zinc-600
                           bg-zinc-800 px-3 py-2
                           disabled:opacity-60"
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
            </div>


            {{-- BATERÍA --}}

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Batería
                </label>

                <select
                    wire:model.live="bateria_id"
                    class="w-full rounded-lg border border-zinc-600
                           bg-zinc-800 px-3 py-2"
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
            </div>


            {{-- DESDE --}}

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Desde
                </label>

                <input
                    type="date"
                    wire:model.live="fecha_desde"
                    class="w-full rounded-lg border border-zinc-600
                           bg-zinc-800 px-3 py-2"
                >
            </div>


            {{-- HASTA --}}

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Hasta
                </label>

                <input
                    type="date"
                    wire:model.live="fecha_hasta"
                    class="w-full rounded-lg border border-zinc-600
                           bg-zinc-800 px-3 py-2"
                >
            </div>

        </div>
    </div>


    {{-- ============================================================= --}}
    {{-- SI YA SELECCIONÓ SEDE Y BATERÍA --}}
    {{-- ============================================================= --}}

    @if (
        $this->sedeEfectiva()
        &&
        $bateria_id !== ''
    )

        {{-- INFORMACIÓN DEL PRODUCTO --}}

        @if ($this->inventarioActual)

            <div class="rounded-xl bg-zinc-900 p-6 shadow">

                <div class="grid grid-cols-1 gap-5 md:grid-cols-4">

                    <div>
                        <div class="text-sm text-zinc-500">
                            Batería
                        </div>

                        <div class="mt-1 font-semibold text-white">
                            {{ $this->inventarioActual->bateria?->codigo }}
                            -
                            {{ $this->inventarioActual->bateria?->marca }}
                            {{ $this->inventarioActual->bateria?->modelo }}
                        </div>
                    </div>


                    <div>
                        <div class="text-sm text-zinc-500">
                            Sede
                        </div>

                        <div class="mt-1 font-semibold text-white">
                            {{ $this->inventarioActual->sede?->nombre }}
                        </div>
                    </div>


                    <div>
                        <div class="text-sm text-zinc-500">
                            Stock actual
                        </div>

                        <div class="mt-1 text-2xl font-bold text-white">
                            {{ $this->inventarioActual->stock_actual }}
                        </div>
                    </div>


                    <div>
                        <div class="text-sm text-zinc-500">
                            Costo promedio
                        </div>

                        <div class="mt-1 text-2xl font-bold text-white">
                            S/
                            {{ number_format(
                                (float) $this->inventarioActual->costo_promedio,
                                2
                            ) }}
                        </div>
                    </div>

                </div>

            </div>

        @endif


        {{-- ========================================================= --}}
        {{-- RESUMEN --}}
        {{-- ========================================================= --}}

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <div class="rounded-xl bg-zinc-900 p-5 shadow">

                <div class="text-sm text-zinc-400">
                    Saldo inicial
                </div>

                <div class="mt-2 text-3xl font-bold text-white">
                    {{ $this->resumen['saldo_inicial'] }}
                </div>

            </div>


            <div class="rounded-xl bg-zinc-900 p-5 shadow">

                <div class="text-sm text-zinc-400">
                    Entradas
                </div>

                <div class="mt-2 text-3xl font-bold text-green-400">
                    +{{ $this->resumen['entradas'] }}
                </div>

            </div>


            <div class="rounded-xl bg-zinc-900 p-5 shadow">

                <div class="text-sm text-zinc-400">
                    Salidas
                </div>

                <div class="mt-2 text-3xl font-bold text-red-400">
                    -{{ $this->resumen['salidas'] }}
                </div>

            </div>


            <div class="rounded-xl bg-zinc-900 p-5 shadow">

                <div class="text-sm text-zinc-400">
                    Saldo final
                </div>

                <div class="mt-2 text-3xl font-bold text-blue-400">
                    {{ $this->resumen['saldo_final'] }}
                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- TABLA KARDEX --}}
        {{-- ========================================================= --}}

        <div class="rounded-xl bg-zinc-900 p-6 shadow">

            <div class="mb-5">

                <h2 class="text-lg font-semibold text-white">
                    Movimientos
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    Historial cronológico de existencias.
                </p>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full text-left text-sm">

                    <thead class="border-b border-zinc-600">

                        <tr>
                            <th class="px-4 py-3">
                                Fecha
                            </th>

                            <th class="px-4 py-3">
                                Movimiento
                            </th>

                            <th class="px-4 py-3">
                                Referencia
                            </th>

                            <th class="px-4 py-3">
                                Detalle
                            </th>

                            <th class="px-4 py-3 text-center">
                                Entrada
                            </th>

                            <th class="px-4 py-3 text-center">
                                Salida
                            </th>

                            <th class="px-4 py-3 text-center">
                                Saldo
                            </th>

                            <th class="px-4 py-3 text-right">
                                Costo
                            </th>
                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($this->movimientos as $indice => $movimiento)

                            <tr
                                wire:key="kardex-{{ $indice }}"
                                class="border-b border-zinc-700"
                            >

                                {{-- FECHA --}}

                                <td class="px-4 py-3">
                                    {{ $movimiento['fecha_texto'] }}
                                </td>


                                {{-- TIPO --}}

                                <td class="px-4 py-3">

                                    @if ($movimiento['tipo'] === 'Ingreso')

                                        <span class="rounded-full bg-green-500/10 px-3 py-1 text-xs font-medium text-green-400">
                                            Ingreso
                                        </span>

                                    @elseif ($movimiento['tipo'] === 'Venta')

                                        <span class="rounded-full bg-red-500/10 px-3 py-1 text-xs font-medium text-red-400">
                                            Venta
                                        </span>

                                    @elseif ($movimiento['tipo'] === 'Transferencia entrada')

                                        <span class="rounded-full bg-blue-500/10 px-3 py-1 text-xs font-medium text-blue-400">
                                            Transferencia +
                                        </span>

                                    @elseif ($movimiento['tipo'] === 'Transferencia salida')

                                        <span class="rounded-full bg-violet-500/10 px-3 py-1 text-xs font-medium text-violet-400">
                                            Transferencia -
                                        </span>

                                    @else

                                        <span class="rounded-full bg-orange-500/10 px-3 py-1 text-xs font-medium text-orange-400">
                                            Salida
                                        </span>

                                    @endif

                                </td>


                                {{-- REFERENCIA --}}

                                <td class="px-4 py-3 font-medium text-white">
                                    {{ $movimiento['referencia'] }}
                                </td>


                                {{-- DETALLE --}}

                                <td class="px-4 py-3 text-zinc-400">
                                    {{ $movimiento['detalle'] }}
                                </td>


                                {{-- ENTRADA --}}

                                <td class="px-4 py-3 text-center">

                                    @if ($movimiento['entrada'] > 0)

                                        <span class="font-semibold text-green-400">
                                            +{{ $movimiento['entrada'] }}
                                        </span>

                                    @else

                                        <span class="text-zinc-600">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- SALIDA --}}

                                <td class="px-4 py-3 text-center">

                                    @if ($movimiento['salida'] > 0)

                                        <span class="font-semibold text-red-400">
                                            -{{ $movimiento['salida'] }}
                                        </span>

                                    @else

                                        <span class="text-zinc-600">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- SALDO --}}

                                <td class="px-4 py-3 text-center text-lg font-bold text-white">
                                    {{ $movimiento['saldo'] }}
                                </td>


                                {{-- COSTO --}}

                                <td class="px-4 py-3 text-right">
                                    S/
                                    {{ number_format(
                                        $movimiento['costo_unitario'],
                                        2
                                    ) }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="8"
                                    class="px-4 py-10 text-center text-zinc-500"
                                >
                                    No existen movimientos para los filtros seleccionados.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    @else

        {{-- ========================================================= --}}
        {{-- SIN SELECCIÓN --}}
        {{-- ========================================================= --}}

        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-10 text-center">

            <div class="text-lg font-semibold text-white">
                Selecciona una sede y una batería
            </div>

            <p class="mt-2 text-sm text-zinc-500">
                El Kardex mostrará cronológicamente todas sus entradas,
                salidas y transferencias.
            </p>

        </div>

    @endif

</div>