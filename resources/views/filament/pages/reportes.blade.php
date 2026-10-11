<x-filament-panels::page>
    <form wire:submit="descargar">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-x-3">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-arrow-down-tray">
                Bericht herunterladen
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
