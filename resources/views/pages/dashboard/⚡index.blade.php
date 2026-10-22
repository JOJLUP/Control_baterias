<?php

use App\Models\Bateria;
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
    /*
    |--------------------------------------------------------------------------
    | COMPROBAR SI ES ADMINISTRADOR
    |--------------------------------------------------------------------------
    */

    private function esAdministrador(): bool
    {
        $usuario = auth()->user();

        $usuario?->loadMissing('rol');

        return in_array(
            $usuario?->rol?->slug,
            [
                'super-administrador',
                'administrador',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SEDE DEL USUARIO
    |--------------------------------------------------------------------------
    |
    | Administrador = todas las sedes.
    | Otros usuarios = solamente su sede.
    |
    */

    private function sedeActual(): ?int
    {
        if ($this->esAdministrador()) {
            return null;
        }

        $usuario = auth()->user();

        return $usuario?->sede_id
            ? (int) $usuario->sede_id
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | RESUMEN DEL DASHBOARD
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function resumen(): array
    {
        $sedeId = $this->sedeActual();

        /*
        |--------------------------------------------------------------------------
        | STOCK TOTAL
        |--------------------------------------------------------------------------
        */

        $stockQuery = Inventario::query();

        if ($sedeId) {
            $stockQuery->where('sede_id', $sedeId);
        } elseif (! $this->esAdministrador()) {
            $stockQuery->whereRaw('1 = 0');
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
        } elseif (! $this->esAdministrador()) {
            $valorQuery->whereRaw('1 = 0');
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
        | VENTAS DEL MES
        |--------------------------------------------------------------------------
        */

        $ventasQuery = Salida::query()
            ->where('estado', true)
            ->where('tipo_salida', 'Venta')
            ->whereDate(
                'fecha',
                '>=',
                now()->startOfMonth()->format('Y-m-d')
            )
            ->whereDate(
                'fecha',
                '<=',
                now()->format('Y-m-d')
            );

        if ($sedeId) {
            $ventasQuery->where('sede_id', $sedeId);
        } elseif (! $this->esAdministrador()) {
            $ventasQuery->whereRaw('1 = 0');
        }

        $ventasMes = (float) $ventasQuery->sum('total');


        /*
        |--------------------------------------------------------------------------
        | UTILIDAD DEL MES
        |--------------------------------------------------------------------------
        */

        $utilidadQuery = SalidaDetalle::query()
            ->join(
                'salidas',
                'salidas.id',
                '=',
                'salida_detalles.salida_id'
            )
            ->where('salidas.estado', true)
            ->where('salidas.tipo_salida', 'Venta')
            ->whereDate(
                'salidas.fecha',
                '>=',
                now()->startOfMonth()->format('Y-m-d')
            )
            ->whereDate(
                'salidas.fecha',
                '<=',
                now()->format('Y-m-d')
            );

        if ($sedeId) {
            $utilidadQuery->where(
                'salidas.sede_id',
                $sedeId
            );
        } elseif (! $this->esAdministrador()) {
            $utilidadQuery->whereRaw('1 = 0');
        }

        $utilidad = (float) (
            $utilidadQuery
                ->selectRaw(
                    '
                    COALESCE(
                        SUM(
                            (
                                salida_detalles.precio_unitario
                                -
                                salida_detalles.costo_unitario
                            )
                            *
                            salida_detalles.cantidad
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
            $stockBajoQuery->where('sede_id', $sedeId);
        } elseif (! $this->esAdministrador()) {
            $stockBajoQuery->whereRaw('1 = 0');
        }

        $stockBajo = $stockBajoQuery->count();


        /*
        |--------------------------------------------------------------------------
        | SEDES
        |--------------------------------------------------------------------------
        */

        if ($this->esAdministrador()) {

            $sedesActivas = Sede::query()
                ->where('estado', true)
                ->count();

        } else {

            $sedesActivas = Sede::query()
                ->whereKey($sedeId)
                ->where('estado', true)
                ->count();
        }


        /*
        |--------------------------------------------------------------------------
        | BATERÍAS ACTIVAS
        |--------------------------------------------------------------------------
        */

        $bateriasActivas = Bateria::query()
            ->where('estado', true)
            ->count();


        return [
            'stock_total' => $stockTotal,
            'valor_inventario' => $valorInventario,
            'ventas_mes' => $ventasMes,
            'utilidad_mes' => $utilidad,
            'stock_bajo' => $stockBajo,
            'sedes_activas' => $sedesActivas,
            'baterias_activas' => $bateriasActivas,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ALERTAS DE STOCK
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function alertasStock()
    {
        $sedeId = $this->sedeActual();

        $query = Inventario::query()
            ->with([
                'sede',
                'bateria',
            ])
            ->whereColumn(
                'stock_actual',
                '<=',
                'stock_minimo'
            );

        if ($sedeId) {

            $query->where(
                'sede_id',
                $sedeId
            );

        } elseif (! $this->esAdministrador()) {

            $query->whereRaw('1 = 0');
        }

        return $query
            ->orderBy('stock_actual')
            ->limit(6)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVIDAD RECIENTE
    |--------------------------------------------------------------------------
    */

    #[Computed]
    public function actividadReciente()
    {
        $sedeId = $this->sedeActual();

        /*
        |--------------------------------------------------------------------------
        | INGRESOS
        |--------------------------------------------------------------------------
        */

        $ingresosQuery = Ingreso::query()
            ->with([
                'sede',
                'usuario',
            ])
            ->where('estado', true);

        if ($sedeId) {

            $ingresosQuery->where(
                'sede_id',
                $sedeId
            );

        } elseif (! $this->esAdministrador()) {

            $ingresosQuery->whereRaw('1 = 0');
        }

        $ingresos = $ingresosQuery
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($ingreso) {

                return [
                    'tipo' => 'Ingreso',

                    'titulo' =>
                        'Ingreso en '
                        . ($ingreso->sede?->nombre ?? 'Sede'),

                    'detalle' =>
                        'S/ '
                        . number_format(
                            (float) $ingreso->total,
                            2
                        ),

                    'fecha' =>
                        $ingreso->fecha
                            ? $ingreso->fecha->format('d/m/Y')
                            : '-',

                    'usuario' =>
                        $ingreso->usuario?->name ?? '-',

                    'orden' =>
                        $ingreso->created_at?->timestamp
                        ?? 0,
                ];
            });


        /*
        |--------------------------------------------------------------------------
        | SALIDAS
        |--------------------------------------------------------------------------
        */

        $salidasQuery = Salida::query()
            ->with([
                'sede',
                'usuario',
            ])
            ->where('estado', true);

        if ($sedeId) {

            $salidasQuery->where(
                'sede_id',
                $sedeId
            );

        } elseif (! $this->esAdministrador()) {

            $salidasQuery->whereRaw('1 = 0');
        }

        $salidas = $salidasQuery
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($salida) {

                return [
                    'tipo' =>
                        $salida->tipo_salida === 'Venta'
                            ? 'Venta'
                            : 'Salida',

                    'titulo' =>
                        ($salida->tipo_salida ?? 'Salida')
                        . ' en '
                        . ($salida->sede?->nombre ?? 'Sede'),

                    'detalle' =>
                        'S/ '
                        . number_format(
                            (float) $salida->total,
                            2
                        ),

                    'fecha' =>
                        $salida->fecha
                            ? $salida->fecha->format('d/m/Y')
                            : '-',

                    'usuario' =>
                        $salida->usuario?->name ?? '-',

                    'orden' =>
                        $salida->created_at?->timestamp
                        ?? 0,
                ];
            });


        /*
        |--------------------------------------------------------------------------
        | TRANSFERENCIAS
        |--------------------------------------------------------------------------
        */

        $transferenciasQuery = Transferencia::query()
            ->with([
                'sedeOrigen',
                'sedeDestino',
                'usuario',
                'detalles',
            ])
            ->where('estado', true);

        if ($sedeId) {

            $transferenciasQuery->where(
                function ($query) use ($sedeId) {

                    $query
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

        } elseif (! $this->esAdministrador()) {

            $transferenciasQuery->whereRaw('1 = 0');
        }

        $transferencias = $transferenciasQuery
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function ($transferencia) {

                $unidades =
                    $transferencia
                        ->detalles
                        ->sum('cantidad');

                return [
                    'tipo' => 'Transferencia',

                    'titulo' =>
                        ($transferencia->sedeOrigen?->nombre ?? '-')
                        . ' → '
                        . ($transferencia->sedeDestino?->nombre ?? '-'),

                    'detalle' =>
                        $unidades
                        . ' '
                        . (
                            $unidades === 1
                                ? 'unidad'
                                : 'unidades'
                        ),

                    'fecha' =>
                        $transferencia->fecha
                            ? $transferencia->fecha->format('d/m/Y')
                            : '-',

                    'usuario' =>
                        $transferencia->usuario?->name ?? '-',

                    'orden' =>
                        $transferencia->created_at?->timestamp
                        ?? 0,
                ];
            });


        /*
        |--------------------------------------------------------------------------
        | UNIR Y ORDENAR
        |--------------------------------------------------------------------------
        */

        return collect()
            ->merge($ingresos)
            ->merge($salidas)
            ->merge($transferencias)
            ->sortByDesc('orden')
            ->take(8)
            ->values();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- ============================================================= --}}
    {{-- CABECERA --}}
    {{-- ============================================================= --}}

    <div>
        <h1 class="text-2xl font-bold text-white">
            Dashboard
        </h1>

        <p class="mt-1 text-sm text-zinc-400">
            Resumen general del sistema de gestión de baterías.
        </p>
    </div>


    {{-- ============================================================= --}}
    {{-- TARJETAS PRINCIPALES --}}
    {{-- ============================================================= --}}

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">

        {{-- STOCK TOTAL --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">

            <div class="flex items-start justify-between">

                <div>
                    <p class="text-sm text-zinc-400">
                        Stock total
                    </p>

                    <p class="mt-2 text-3xl font-bold text-white">
                        {{ number_format(
                            $this->resumen['stock_total']
                        ) }}
                    </p>

                    <p class="mt-1 text-xs text-zinc-500">
                        Unidades disponibles
                    </p>
                </div>

                <div class="rounded-lg bg-blue-500/10 p-3 text-blue-400">
                    📦
                </div>

            </div>

        </div>


        {{-- VALOR INVENTARIO --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">

            <div class="flex items-start justify-between">

                <div>
                    <p class="text-sm text-zinc-400">
                        Valor del inventario
                    </p>

                    <p class="mt-2 text-3xl font-bold text-white">
                        S/
                        {{ number_format(
                            $this->resumen['valor_inventario'],
                            2
                        ) }}
                    </p>

                    <p class="mt-1 text-xs text-zinc-500">
                        Valorizado al costo promedio
                    </p>
                </div>

                <div class="rounded-lg bg-violet-500/10 p-3 text-violet-400">
                    💰
                </div>

            </div>

        </div>


        {{-- VENTAS DEL MES --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">

            <div class="flex items-start justify-between">

                <div>
                    <p class="text-sm text-zinc-400">
                        Ventas del mes
                    </p>

                    <p class="mt-2 text-3xl font-bold text-white">
                        S/
                        {{ number_format(
                            $this->resumen['ventas_mes'],
                            2
                        ) }}
                    </p>

                    <p class="mt-1 text-xs text-zinc-500">
                        Ventas registradas este mes
                    </p>
                </div>

                <div class="rounded-lg bg-green-500/10 p-3 text-green-400">
                    ↗
                </div>

            </div>

        </div>


        {{-- UTILIDAD --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">

            <div class="flex items-start justify-between">

                <div>
                    <p class="text-sm text-zinc-400">
                        Utilidad estimada
                    </p>

                    <p class="mt-2 text-3xl font-bold text-green-400">
                        S/
                        {{ number_format(
                            $this->resumen['utilidad_mes'],
                            2
                        ) }}
                    </p>

                    <p class="mt-1 text-xs text-zinc-500">
                        Venta menos costo del mes
                    </p>
                </div>

                <div class="rounded-lg bg-green-500/10 p-3 text-green-400">
                    S/
                </div>

            </div>

        </div>


        {{-- ALERTAS --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">

            <div class="flex items-start justify-between">

                <div>
                    <p class="text-sm text-zinc-400">
                        Alertas de stock
                    </p>

                    <p class="mt-2 text-3xl font-bold text-yellow-400">
                        {{ $this->resumen['stock_bajo'] }}
                    </p>

                    <p class="mt-1 text-xs text-zinc-500">
                        Productos en mínimo o debajo
                    </p>
                </div>

                <div class="rounded-lg bg-yellow-500/10 p-3 text-yellow-400">
                    !
                </div>

            </div>

        </div>


        {{-- SEDES --}}

        <div class="rounded-xl bg-zinc-900 p-5 shadow">

            <div class="flex items-start justify-between">

                <div>
                    <p class="text-sm text-zinc-400">
                        Sedes activas
                    </p>

                    <p class="mt-2 text-3xl font-bold text-white">
                        {{ $this->resumen['sedes_activas'] }}
                    </p>

                    <p class="mt-1 text-xs text-zinc-500">
                        {{ $this->resumen['baterias_activas'] }}
                        {{ $this->resumen['baterias_activas'] === 1
                            ? 'modelo de batería activo'
                            : 'modelos de batería activos' }}
                    </p>
                </div>

                <div class="rounded-lg bg-cyan-500/10 p-3 text-cyan-400">
                    ◉
                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- SEGUNDA FILA --}}
    {{-- ============================================================= --}}

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- ========================================================= --}}
        {{-- ACTIVIDAD RECIENTE --}}
        {{-- ========================================================= --}}

        <div class="rounded-xl bg-zinc-900 p-6 shadow xl:col-span-2">

            <div class="mb-5">

                <h2 class="text-lg font-semibold text-white">
                    Actividad reciente
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    Últimos movimientos registrados en el sistema.
                </p>

            </div>


            <div class="divide-y divide-zinc-800">

                @forelse ($this->actividadReciente as $actividad)

                    <div class="flex items-center justify-between gap-4 py-4">

                        <div class="min-w-0">

                            <div class="flex items-center gap-2">

                                @if ($actividad['tipo'] === 'Ingreso')

                                    <span class="rounded-full bg-blue-500/10 px-2 py-1 text-xs font-medium text-blue-400">
                                        Ingreso
                                    </span>

                                @elseif ($actividad['tipo'] === 'Venta')

                                    <span class="rounded-full bg-green-500/10 px-2 py-1 text-xs font-medium text-green-400">
                                        Venta
                                    </span>

                                @elseif ($actividad['tipo'] === 'Transferencia')

                                    <span class="rounded-full bg-violet-500/10 px-2 py-1 text-xs font-medium text-violet-400">
                                        Transferencia
                                    </span>

                                @else

                                    <span class="rounded-full bg-orange-500/10 px-2 py-1 text-xs font-medium text-orange-400">
                                        Salida
                                    </span>

                                @endif

                            </div>

                            <div class="mt-2 font-medium text-white">
                                {{ $actividad['titulo'] }}
                            </div>

                            <div class="mt-1 text-sm text-zinc-400">
                                {{ $actividad['detalle'] }}
                                ·
                                {{ $actividad['usuario'] }}
                            </div>

                        </div>

                        <div class="shrink-0 text-sm text-zinc-500">
                            {{ $actividad['fecha'] }}
                        </div>

                    </div>

                @empty

                    <div class="py-10 text-center text-zinc-500">
                        Todavía no existen movimientos registrados.
                    </div>

                @endforelse

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- ALERTAS --}}
        {{-- ========================================================= --}}

        <div class="rounded-xl bg-zinc-900 p-6 shadow">

            <div class="mb-5">

                <h2 class="text-lg font-semibold text-white">
                    Stock bajo
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
                    Productos que requieren atención.
                </p>

            </div>


            <div class="space-y-3">

                @forelse ($this->alertasStock as $inventario)

                    <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-4">

                        <div class="flex items-start justify-between gap-3">

                            <div>

                                <div class="font-medium text-white">
                                    {{ $inventario->bateria?->codigo }}
                                    -
                                    {{ $inventario->bateria?->marca }}
                                </div>

                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ $inventario->sede?->nombre }}
                                </div>

                            </div>


                            @if ((int) $inventario->stock_actual <= 0)

                                <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                    Sin stock
                                </span>

                            @else

                                <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700">
                                    Stock bajo
                                </span>

                            @endif

                        </div>


                        <div class="mt-3 flex items-center justify-between text-sm">

                            <span class="text-zinc-400">
                                Disponible
                            </span>

                            <span class="font-semibold text-white">
                                {{ $inventario->stock_actual }}
                            </span>

                        </div>


                        <div class="mt-1 flex items-center justify-between text-sm">

                            <span class="text-zinc-400">
                                Mínimo
                            </span>

                            <span class="text-white">
                                {{ $inventario->stock_minimo }}
                            </span>

                        </div>

                    </div>

                @empty

                    <div class="rounded-lg border border-zinc-700 p-6 text-center">

                        <div class="text-green-400">
                            ✓
                        </div>

                        <p class="mt-2 text-sm text-zinc-400">
                            No existen alertas de stock.
                        </p>

                    </div>

                @endforelse

            </div>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- ACCESOS RÁPIDOS --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-zinc-900 p-6 shadow">

        <h2 class="mb-5 text-lg font-semibold text-white">
            Accesos rápidos
        </h2>


        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">

            <a
                href="{{ route('baterias.index') }}"
                wire:navigate
                class="rounded-lg border border-zinc-700 p-4 text-center transition hover:bg-zinc-800"
            >
                <div class="font-medium text-white">
                    Baterías
                </div>
            </a>


            <a
                href="{{ route('inventario.index') }}"
                wire:navigate
                class="rounded-lg border border-zinc-700 p-4 text-center transition hover:bg-zinc-800"
            >
                <div class="font-medium text-white">
                    Inventario
                </div>
            </a>


            <a
                href="{{ route('ingresos.index') }}"
                wire:navigate
                class="rounded-lg border border-zinc-700 p-4 text-center transition hover:bg-zinc-800"
            >
                <div class="font-medium text-white">
                    Ingresos
                </div>
            </a>


            <a
                href="{{ route('salidas.index') }}"
                wire:navigate
                class="rounded-lg border border-zinc-700 p-4 text-center transition hover:bg-zinc-800"
            >
                <div class="font-medium text-white">
                    Salidas
                </div>
            </a>


            <a
                href="{{ route('transferencias.index') }}"
                wire:navigate
                class="rounded-lg border border-zinc-700 p-4 text-center transition hover:bg-zinc-800"
            >
                <div class="font-medium text-white">
                    Transferencias
                </div>
            </a>


            <a
                href="{{ route('reportes.index') }}"
                wire:navigate
                class="rounded-lg border border-zinc-700 p-4 text-center transition hover:bg-zinc-800"
            >
                <div class="font-medium text-white">
                    Reportes
                </div>
            </a>

        </div>

    </div>

</div>