<?php

use App\Models\Bateria;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $bateriaId = null;

    public string $codigo = '';
    public string $marca = '';
    public string $modelo = '';
    public string $tipo_vehiculo = '';
    public string $voltaje = '';
    public string $capacidad_ah = '';
    public string $cca = '';
    public string $tecnologia = '';
    public string $polaridad = '';
    public string $garantia_meses = '';
    public string $costo_referencial = '';
    public string $precio_venta = '';
    public string $observaciones = '';

    public string $buscar = '';

    /*
    |--------------------------------------------------------------------------
    | GUARDAR / ACTUALIZAR
    |--------------------------------------------------------------------------
    */
    public function guardar(): void
    {
        $this->autorizarGestion();

        $datos = $this->validate([
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('baterias', 'codigo')
                    ->ignore($this->bateriaId),
            ],

            'marca' => [
                'required',
                'string',
                'max:100',
            ],

            'modelo' => [
                'required',
                'string',
                'max:100',
            ],

            'tipo_vehiculo' => [
                'required',
                'string',
                'max:50',
            ],

            'voltaje' => [
                'required',
                'numeric',
                'min:0',
            ],

            'capacidad_ah' => [
                'required',
                'numeric',
                'min:0',
            ],

            'cca' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'tecnologia' => [
                'nullable',
                'string',
                'max:50',
            ],

            'polaridad' => [
                'nullable',
                'string',
                'max:50',
            ],

            'garantia_meses' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'costo_referencial' => [
                'required',
                'numeric',
                'min:0',
            ],

            'precio_venta' => [
                'required',
                'numeric',
                'min:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $datos['cca'] = $this->cca !== ''
            ? (int) $this->cca
            : null;

        $datos['garantia_meses'] = $this->garantia_meses !== ''
            ? (int) $this->garantia_meses
            : null;

        $datos['costo_referencial'] = (float) $this->costo_referencial;
        $datos['precio_venta'] = (float) $this->precio_venta;
        $datos['voltaje'] = (float) $this->voltaje;
        $datos['capacidad_ah'] = (float) $this->capacidad_ah;

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        if ($this->bateriaId) {

            $bateria = Bateria::findOrFail($this->bateriaId);

            $bateria->update($datos);

            session()->flash(
                'mensaje',
                'Batería actualizada correctamente.'
            );

        /*
        |--------------------------------------------------------------------------
        | CREAR
        |--------------------------------------------------------------------------
        */

        } else {

            $datos['estado'] = true;

            Bateria::create($datos);

            session()->flash(
                'mensaje',
                'Batería registrada correctamente.'
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
        $this->autorizarGestion();

        $bateria = Bateria::findOrFail($id);

        $this->bateriaId = $bateria->id;
        $this->codigo = $bateria->codigo;
        $this->marca = $bateria->marca;
        $this->modelo = $bateria->modelo;
        $this->tipo_vehiculo = $bateria->tipo_vehiculo;
        $this->voltaje = (string) $bateria->voltaje;
        $this->capacidad_ah = (string) $bateria->capacidad_ah;
        $this->cca = $bateria->cca !== null
            ? (string) $bateria->cca
            : '';
        $this->tecnologia = $bateria->tecnologia ?? '';
        $this->polaridad = $bateria->polaridad ?? '';
        $this->garantia_meses = $bateria->garantia_meses !== null
            ? (string) $bateria->garantia_meses
            : '';
        $this->costo_referencial = (string) $bateria->costo_referencial;
        $this->precio_venta = (string) $bateria->precio_venta;
        $this->observaciones = $bateria->observaciones ?? '';

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
        $this->autorizarGestion();

        $bateria = Bateria::findOrFail($id);

        $bateria->estado = ! $bateria->estado;
        $bateria->save();

        session()->flash(
            'mensaje',
            $bateria->estado
                ? 'Batería activada correctamente.'
                : 'Batería desactivada correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISOS DE GESTIÓN
    |--------------------------------------------------------------------------
    */
    private function autorizarGestion(): void
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
                'No tienes permiso para modificar el catálogo de baterías.'
            );
        }
    }

    #[Computed]
    public function puedeGestionar(): bool
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
    | LISTADO
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function baterias()
    {
        return Bateria::query()
            ->when($this->buscar, function ($query) {

                $query->where(function ($q) {

                    $q->where(
                        'codigo',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )
                    ->orWhere(
                        'marca',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )
                    ->orWhere(
                        'modelo',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )
                    ->orWhere(
                        'tipo_vehiculo',
                        'ilike',
                        '%' . $this->buscar . '%'
                    )
                    ->orWhere(
                        'tecnologia',
                        'ilike',
                        '%' . $this->buscar . '%'
                    );
                });
            })
            ->orderBy('marca')
            ->orderBy('modelo')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | LIMPIAR
    |--------------------------------------------------------------------------
    */
    private function limpiarFormulario(): void
    {
        $this->reset([
            'bateriaId',
            'codigo',
            'marca',
            'modelo',
            'tipo_vehiculo',
            'voltaje',
            'capacidad_ah',
            'cca',
            'tecnologia',
            'polaridad',
            'garantia_meses',
            'costo_referencial',
            'precio_venta',
            'observaciones',
        ]);

        $this->resetValidation();
    }
};

?>

<div class="mx-auto max-w-7xl space-y-6 p-6">

    {{-- TÍTULO --}}

    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Baterías
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Administración del catálogo general de baterías.
        </p>
    </div>


    {{-- MENSAJES --}}

    @if (session('mensaje'))
        <div class="rounded-lg border border-green-200 bg-green-100 p-4 text-green-800">
            {{ session('mensaje') }}
        </div>
    @endif


    {{-- ============================================================= --}}
    {{-- FORMULARIO --}}
    {{-- ============================================================= --}}

    @if ($this->puedeGestionar)

        <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

            <h2 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $bateriaId ? 'Editar batería' : 'Nueva batería' }}
            </h2>

            <form wire:submit="guardar">

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">


                    {{-- CÓDIGO --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Código
                        </label>

                        <input
                            type="text"
                            wire:model="codigo"
                            placeholder="BAT-001"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('codigo')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- MARCA --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Marca
                        </label>

                        <input
                            type="text"
                            wire:model="marca"
                            placeholder="ETNA"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('marca')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- MODELO --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Modelo
                        </label>

                        <input
                            type="text"
                            wire:model="modelo"
                            placeholder="Platinum"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('modelo')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- TIPO VEHÍCULO --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Tipo de vehículo
                        </label>

                        <select
                            wire:model="tipo_vehiculo"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >
                            <option value="">Seleccionar tipo</option>
                            <option value="Carro">Carro</option>
                            <option value="Moto">Moto</option>
                            <option value="Camioneta">Camioneta</option>
                            <option value="Camión">Camión</option>
                            <option value="Bus">Bus</option>
                            <option value="Maquinaria">Maquinaria</option>
                            <option value="Otro">Otro</option>
                        </select>

                        @error('tipo_vehiculo')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- VOLTAJE --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Voltaje
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="voltaje"
                            placeholder="12"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('voltaje')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- CAPACIDAD --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Capacidad (Ah)
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="capacidad_ah"
                            placeholder="60"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('capacidad_ah')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- CCA --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            CCA
                        </label>

                        <input
                            type="number"
                            min="0"
                            wire:model="cca"
                            placeholder="550"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('cca')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- TECNOLOGÍA --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Tecnología
                        </label>

                        <select
                            wire:model="tecnologia"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >
                            <option value="">Seleccionar tecnología</option>
                            <option value="Plomo-ácido">Plomo-ácido</option>
                            <option value="AGM">AGM</option>
                            <option value="EFB">EFB</option>
                            <option value="Gel">Gel</option>
                            <option value="Litio">Litio</option>
                            <option value="Otro">Otro</option>
                        </select>

                        @error('tecnologia')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- POLARIDAD --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Polaridad
                        </label>

                        <select
                            wire:model="polaridad"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >
                            <option value="">Seleccionar polaridad</option>
                            <option value="Positivo derecho">
                                Positivo derecho
                            </option>
                            <option value="Positivo izquierdo">
                                Positivo izquierdo
                            </option>
                            <option value="Positivo central">
                                Positivo central
                            </option>
                            <option value="Otro">
                                Otro
                            </option>
                        </select>

                        @error('polaridad')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- GARANTÍA --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Garantía (meses)
                        </label>

                        <input
                            type="number"
                            min="0"
                            wire:model="garantia_meses"
                            placeholder="18"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('garantia_meses')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- COSTO --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Costo referencial (S/)
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="costo_referencial"
                            placeholder="280.00"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('costo_referencial')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- PRECIO --}}

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Precio de venta (S/)
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="precio_venta"
                            placeholder="350.00"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        >

                        @error('precio_venta')
                            <span class="mt-1 block text-sm text-red-600">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>


                    {{-- OBSERVACIONES --}}

                    <div class="md:col-span-2 lg:col-span-3">

                        <label class="mb-1 block text-sm font-medium">
                            Observaciones
                        </label>

                        <textarea
                            wire:model="observaciones"
                            rows="3"
                            placeholder="Información adicional de la batería..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:bg-zinc-800"
                        ></textarea>

                        @error('observaciones')
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
                        {{ $bateriaId ? 'Actualizar batería' : 'Guardar batería' }}
                    </button>

                    @if ($bateriaId)

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

    @endif


    {{-- ============================================================= --}}
    {{-- LISTADO --}}
    {{-- ============================================================= --}}

    <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

        <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

            <h2 class="text-lg font-semibold">
                Baterías registradas
            </h2>

            <input
                type="text"
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar batería..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 md:max-w-sm dark:bg-zinc-800"
            >

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Batería</th>
                        <th class="px-4 py-3">Aplicación</th>
                        <th class="px-4 py-3">Especificaciones</th>
                        <th class="px-4 py-3">Precio</th>
                        <th class="px-4 py-3">Estado</th>

                        @if ($this->puedeGestionar)
                            <th class="px-4 py-3">Acciones</th>
                        @endif
                    </tr>
                </thead>


                <tbody>

                    @forelse ($this->baterias as $bateria)

                        <tr
                            wire:key="bateria-{{ $bateria->id }}"
                            class="border-b"
                        >

                            <td class="px-4 py-3 font-medium">
                                {{ $bateria->codigo }}
                            </td>


                            <td class="px-4 py-3">

                                <div class="font-medium">
                                    {{ $bateria->marca }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $bateria->modelo }}
                                </div>

                            </td>


                            <td class="px-4 py-3">
                                {{ $bateria->tipo_vehiculo }}
                            </td>


                            <td class="px-4 py-3">

                                <div>
                                    {{ $bateria->voltaje }} V
                                    /
                                    {{ $bateria->capacidad_ah }} Ah
                                </div>

                                @if ($bateria->cca)
                                    <div class="text-xs text-gray-500">
                                        CCA: {{ $bateria->cca }}
                                    </div>
                                @endif

                                @if ($bateria->tecnologia)
                                    <div class="text-xs text-gray-500">
                                        {{ $bateria->tecnologia }}
                                    </div>
                                @endif

                            </td>


                            <td class="px-4 py-3">

                                <div class="font-medium">
                                    S/ {{ number_format((float) $bateria->precio_venta, 2) }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    Costo:
                                    S/ {{ number_format((float) $bateria->costo_referencial, 2) }}
                                </div>

                            </td>


                            <td class="px-4 py-3">

                                @if ($bateria->estado)

                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">
                                        Activa
                                    </span>

                                @else

                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
                                        Inactiva
                                    </span>

                                @endif

                            </td>


                            @if ($this->puedeGestionar)

                                <td class="px-4 py-3">

                                    <div class="flex flex-wrap gap-2">

                                        <button
                                            type="button"
                                            wire:click="editar({{ $bateria->id }})"
                                            class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600"
                                        >
                                            Editar
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="cambiarEstado({{ $bateria->id }})"
                                            wire:confirm="¿Deseas cambiar el estado de esta batería?"
                                            class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
                                        >
                                            {{ $bateria->estado ? 'Desactivar' : 'Activar' }}
                                        </button>

                                    </div>

                                </td>

                            @endif

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="{{ $this->puedeGestionar ? 7 : 6 }}"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Todavía no existen baterías registradas.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>