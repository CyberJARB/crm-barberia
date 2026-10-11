<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-x-3">
            <x-filament::button type="submit" size="lg">
                Einstellungen speichern
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
