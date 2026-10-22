<?php

use App\Models\Ingreso;
use App\Models\Inventario;
use App\Models\Salida;
use App\Models\SalidaDetalle;
use App\Models\Sede;
use App\Models\Transferencia;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $fecha_desde = '';
    public string $fecha_hasta = '';
    public string $sede_id = '';
    public string $buscar = '';

    public function mount(): void
    {
        /*
        |--------------------------------------------------------------------------
        | PERÍODO INICIAL
        |--------------------------------------------------------------------------
        | Desde el primer día del mes hasta hoy.
        */

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
        | solamente podrán ver su sede.
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

    private function sedeEfectiva(): ?int
    {
        $usuario = auth()->user();

        if (! $this->puedeElegirSede()) {
            return $usuario?->sede_id
                ? (int) $usuario->sede_id
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

        $usuario = auth()->user();

        return Sede::query()
            ->whereKey($usuario?->sede_id)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | RESUMEN GENERAL
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function resumen(): array
    {
        $sedeId = $this->sedeEfectiva();

        /*
        |--------------------------------------------------------------------------
        | STOCK TOTAL
        |--------------------------------------------------------------------------
        */

        $stockQuery = Inventario::query();

        if ($sedeId) {
            $stockQuery->where('sede_id', $sedeId);
        }

        $stockTotal = (int) $stockQuery->sum('stock_actual');

        /*
        |--------------------------------------------------------------------------
        | VALOR DEL INVENTARIO
        |--------------------------------------------------------------------------
        */

        $valorQuery = Inventario::query();

        if ($sedeId) {
            $valorQuery->where('sede_id', $sedeId);
        }

        $valorInventario = (float) (
            $valorQuery
                ->selectRaw(
                    'COALESCE(SUM(stock_actual * costo_promedio), 0) AS total'
                )
                ->value('total')
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | TOTAL INGRESOS / COMPRAS
        |--------------------------------------------------------------------------
        */

        $ingresoQuery = Ingreso::query()
            ->where('estado', true);

        if ($sedeId) {
            $ingresoQuery->where('sede_id', $sedeId);
        }

        if ($this->fecha_desde !== '') {
            $ingresoQuery->whereDate(
                'fecha',
                '>=',
                $this->fecha_desde
            );
        }

        if ($this->fecha_hasta !== '') {
            $ingresoQuery->whereDate(
                'fecha',
                '<=',
                $this->fecha_hasta
            );
        }

        $totalIngresos = (float) $ingresoQuery->sum('total');

        /*
        |--------------------------------------------------------------------------
        | TOTAL VENTAS
        |--------------------------------------------------------------------------
        */

        $ventaQuery = Salida::query()
            ->where('estado', true)
            ->where('tipo_salida', 'Venta');

        if ($sedeId) {
            $ventaQuery->where('sede_id', $sedeId);
        }

        if ($this->fecha_desde !== '') {
            $ventaQuery->whereDate(
                'fecha',
                '>=',
                $this->fecha_desde
            );
        }

        if ($this->fecha_hasta !== '') {
            $ventaQuery->whereDate(
                'fecha',
                '<=',
                $this->fecha_hasta
            );
        }

        $totalVentas = (float) $ventaQuery->sum('total');

        /*
        |--------------------------------------------------------------------------
        | UTILIDAD ESTIMADA
        |--------------------------------------------------------------------------
        |
        | (precio - costo) × cantidad
        |
        */

        $utilidadQuery = SalidaDetalle::query()
            ->join(
                'salidas',
                'salidas.id',
                '=',
                'salida_detalles.salida_id'
            )
            ->where('salidas.estado', true)
            ->where('salidas.tipo_salida', 'Venta');

        if ($sedeId) {
            $utilidadQuery->where(
                'salidas.sede_id',
                $sedeId
            );
        }

        if ($this->fecha_desde !== '') {
            $utilidadQuery->whereDate(
                'salidas.fecha',
                '>=',
                $this->fecha_desde
            );
        }

        if ($this->fecha_hasta !== '') {
            $utilidadQuery->whereDate(
                'salidas.fecha',
                '<=',
                $this->fecha_hasta
            );
        }

        $utilidad = (float) (
            $utilidadQuery
                ->selectRaw(
                    '
                    COALESCE(
                        SUM(
                            (salida_detalles.precio_unitario
                            - salida_detalles.costo_unitario)
                            * salida_detalles.cantidad
                        ),
                        0
                    ) AS total
                    '
                )
                ->value('total')
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | STOCK BAJO
        |--------------------------------------------------------------------------
        */

        $stockBajoQuery = Inventario::query()
            ->whereColumn(
                'stock_actual',
                '<=',
                'stock_minimo'
            );

        if ($sedeId) {
            $stockBajoQuery->where(
                'sede_id',
                $sedeId
            );
        }

        $stockBajo = $stockBajoQuery->count();

        return [
            'stock_total' => $stockTotal,
            'valor_inventario' => $valorInventario,
            'ingresos' => $totalIngresos,
            'ventas' => $totalVentas,
            'utilidad' => $utilidad,
            'stock_bajo' => $stockBajo,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function inventarios()
    {
        $sedeId = $this->sedeEfectiva();

        $query = Inventario::query()
            ->with([
                'sede',
                'bateria',
            ]);

        if ($sedeId) {
            $query->where(
                'sede_id',
                $sedeId
            );
        }

        if ($this->buscar !== '') {
            $buscar = $this->buscar;

            $query->whereHas(
                'bateria',
                function ($q) use ($buscar) {
                    $q
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

        return $query
            ->orderBy('sede_id')
            ->orderBy('bateria_id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ÚLTIMOS INGRESOS
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function ingresos()
    {
        $sedeId = $this->sedeEfectiva();

        $query = Ingreso::query()
            ->with([
                'sede',
                'usuario',
            ])
            ->where('estado', true);

        if ($sedeId) {
            $query->where(
                'sede_id',
                $sedeId
            );
        }

        if ($this->fecha_desde !== '') {
            $query->whereDate(
                'fecha',
                '>=',
                $this->fecha_desde
            );
        }

        if ($this->fecha_hasta !== '') {
            $query->whereDate(
                'fecha',
                '<=',
                $this->fecha_hasta
            );
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ÚLTIMAS SALIDAS
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function salidas()
    {
        $sedeId = $this->sedeEfectiva();

        $query = Salida::query()
            ->with([
                'sede',
                'usuario',
            ])
            ->where('estado', true);

        if ($sedeId) {
            $query->where(
                'sede_id',
                $sedeId
            );
        }

        if ($this->fecha_desde !== '') {
            $query->whereDate(
                'fecha',
                '>=',
                $this->fecha_desde
            );
        }

        if ($this->fecha_hasta !== '') {
            $query->whereDate(
                'fecha',
                '<=',
                $this->fecha_hasta
            );
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSFERENCIAS
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function transferencias()
    {
        $sedeId = $this->sedeEfectiva();

        $query = Transferencia::query()
            ->with([
                'sedeOrigen',
                'sedeDestino',
                'usuario',
                'detalles',
            ])
            ->where('estado', true);

        if ($sedeId) {
            $query->where(
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
        }

        if ($this->fecha_desde !== '') {
            $query->whereDate(
                'fecha',
                '>=',
                $this->fecha_desde
            );
        }

        if ($this->fecha_hasta !== '') {
            $query->whereDate(
                'fecha',
                '<=',
                $this->fecha_hasta
            );
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(20)
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
            Reportes
        </h1>

        <p class="mt-1 text-sm text-zinc-400">
            Resumen general de inventario, ingresos, ventas y movimientos.
        </p>
    </div>


    {{-- ============================================================= --}}
    {{-- FILTROS --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <h2 class="mb-4 text-lg font-semibold text-white">
            Filtros
        </h2>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

            {{-- FECHA DESDE --}}

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


            {{-- FECHA HASTA --}}

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

                    @if ($this->puedeElegirSede)
                        <option value="">
                            Todas las sedes
                        </option>
                    @endif

                    @foreach ($this->sedes as $sede)
                        <option value="{{ $sede->id }}">
                            {{ $sede->codigo }} - {{ $sede->nombre }}
                        </option>
                    @endforeach

                </select>

            </div>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- TARJETAS --}}
    {{-- ============================================================= --}}

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">

        {{-- STOCK --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">
            <div class="text-sm text-zinc-400">
                Stock total
            </div>

            <div class="mt-2 text-3xl font-bold text-white">
                {{ number_format($this->resumen['stock_total']) }}
            </div>

            <div class="mt-1 text-xs text-zinc-500">
                Unidades disponibles
            </div>
        </div>


        {{-- VALOR INVENTARIO --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">
            <div class="text-sm text-zinc-400">
                Valor del inventario
            </div>

            <div class="mt-2 text-3xl font-bold text-white">
                S/
                {{ number_format(
                    $this->resumen['valor_inventario'],
                    2
                ) }}
            </div>

            <div class="mt-1 text-xs text-zinc-500">
                Valorizado al costo promedio
            </div>
        </div>


        {{-- INGRESOS --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">
            <div class="text-sm text-zinc-400">
                Compras / Ingresos
            </div>

            <div class="mt-2 text-3xl font-bold text-white">
                S/
                {{ number_format(
                    $this->resumen['ingresos'],
                    2
                ) }}
            </div>

            <div class="mt-1 text-xs text-zinc-500">
                En el período seleccionado
            </div>
        </div>


        {{-- VENTAS --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">
            <div class="text-sm text-zinc-400">
                Ventas
            </div>

            <div class="mt-2 text-3xl font-bold text-white">
                S/
                {{ number_format(
                    $this->resumen['ventas'],
                    2
                ) }}
            </div>

            <div class="mt-1 text-xs text-zinc-500">
                Salidas de tipo venta
            </div>
        </div>


        {{-- UTILIDAD --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">
            <div class="text-sm text-zinc-400">
                Utilidad estimada
            </div>

            <div class="mt-2 text-3xl font-bold text-green-400">
                S/
                {{ number_format(
                    $this->resumen['utilidad'],
                    2
                ) }}
            </div>

            <div class="mt-1 text-xs text-zinc-500">
                Venta menos costo
            </div>
        </div>


        {{-- STOCK BAJO --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">
            <div class="text-sm text-zinc-400">
                Alertas de stock
            </div>

            <div class="mt-2 text-3xl font-bold text-yellow-400">
                {{ $this->resumen['stock_bajo'] }}
            </div>

            <div class="mt-1 text-xs text-zinc-500">
                Productos en mínimo o debajo
            </div>
        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- INVENTARIO --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <div
            class="mb-5 flex flex-col gap-4
                   md:flex-row md:items-center
                   md:justify-between"
        >

            <div>
                <h2 class="text-lg font-semibold text-white">
                    Inventario por sede
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    Existencias actuales y valorización.
                </p>
            </div>

            <input
                type="text"
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar batería..."
                class="rounded-lg border border-zinc-600
                       bg-zinc-800 px-3 py-2"
            >

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-zinc-600">

                    <tr>
                        <th class="px-4 py-3">Sede</th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Batería</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Mínimo</th>
                        <th class="px-4 py-3">Costo promedio</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Situación</th>
                    </tr>

                </thead>


                <tbody>

                    @forelse ($this->inventarios as $inventario)

                        @php
                            $stock = (int) $inventario->stock_actual;
                            $minimo = (int) $inventario->stock_minimo;

                            $valor =
                                $stock
                                *
                                (float) $inventario->costo_promedio;
                        @endphp

                        <tr
                            wire:key="reporte-inventario-{{ $inventario->id }}"
                            class="border-b border-zinc-700"
                        >

                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $inventario->sede?->nombre }}
                                </div>

                                <div class="text-xs text-zinc-500">
                                    {{ $inventario->sede?->codigo }}
                                </div>
                            </td>


                            <td class="px-4 py-3 font-medium">
                                {{ $inventario->bateria?->codigo }}
                            </td>


                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $inventario->bateria?->marca }}
                                    {{ $inventario->bateria?->modelo }}
                                </div>

                                <div class="text-xs text-zinc-500">
                                    {{ number_format(
                                        (float) $inventario->bateria?->voltaje,
                                        2
                                    ) }} V
                                    /
                                    {{ number_format(
                                        (float) $inventario->bateria?->capacidad_ah,
                                        2
                                    ) }} Ah
                                </div>
                            </td>


                            <td class="px-4 py-3 text-lg font-bold">
                                {{ $stock }}
                            </td>


                            <td class="px-4 py-3">
                                {{ $minimo }}
                            </td>


                            <td class="px-4 py-3">
                                S/
                                {{ number_format(
                                    (float) $inventario->costo_promedio,
                                    2
                                ) }}
                            </td>


                            <td class="px-4 py-3 font-semibold">
                                S/
                                {{ number_format($valor, 2) }}
                            </td>


                            <td class="px-4 py-3">

                                @if ($stock <= 0)

                                    <span
                                        class="rounded-full bg-red-100
                                               px-3 py-1 text-xs
                                               font-medium text-red-700"
                                    >
                                        Sin stock
                                    </span>

                                @elseif ($stock <= $minimo)

                                    <span
                                        class="rounded-full bg-yellow-100
                                               px-3 py-1 text-xs
                                               font-medium text-yellow-700"
                                    >
                                        Stock bajo
                                    </span>

                                @else

                                    <span
                                        class="rounded-full bg-green-100
                                               px-3 py-1 text-xs
                                               font-medium text-green-700"
                                    >
                                        Normal
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="8"
                                class="px-4 py-8 text-center text-zinc-500"
                            >
                                No existen registros de inventario.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- INGRESOS --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <h2 class="mb-5 text-lg font-semibold text-white">
            Últimos ingresos
        </h2>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-zinc-600">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Sede</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Registrado por</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($this->ingresos as $ingreso)

                        <tr
                            wire:key="reporte-ingreso-{{ $ingreso->id }}"
                            class="border-b border-zinc-700"
                        >

                            <td class="px-4 py-3">
                                {{ $ingreso->fecha?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $ingreso->sede?->nombre }}
                            </td>

                            <td class="px-4 py-3 font-semibold">
                                S/
                                {{ number_format(
                                    (float) $ingreso->total,
                                    2
                                ) }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $ingreso->usuario?->name }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-zinc-500"
                            >
                                No existen ingresos en este período.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- SALIDAS --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <h2 class="mb-5 text-lg font-semibold text-white">
            Últimas salidas
        </h2>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-zinc-600">

                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Sede</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Registrado por</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse ($this->salidas as $salida)

                        <tr
                            wire:key="reporte-salida-{{ $salida->id }}"
                            class="border-b border-zinc-700"
                        >

                            <td class="px-4 py-3">
                                {{ $salida->fecha?->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $salida->sede?->nombre }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $salida->tipo_salida }}
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
                                colspan="5"
                                class="px-4 py-8 text-center text-zinc-500"
                            >
                                No existen salidas en este período.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- TRANSFERENCIAS --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <h2 class="mb-5 text-lg font-semibold text-white">
            Últimas transferencias
        </h2>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-zinc-600">

                    <tr>
                        <th class="px-4 py-3">Número</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Origen</th>
                        <th class="px-4 py-3">Destino</th>
                        <th class="px-4 py-3">Unidades</th>
                        <th class="px-4 py-3">Usuario</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse ($this->transferencias as $transferencia)

                        <tr
                            wire:key="reporte-transferencia-{{ $transferencia->id }}"
                            class="border-b border-zinc-700"
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
                                colspan="6"
                                class="px-4 py-8 text-center text-zinc-500"
                            >
                                No existen transferencias en este período.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>