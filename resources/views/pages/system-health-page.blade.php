<x-filament-panels::page>
    {{ $this->diagnosticsHeroInfolist }}
    {{ $this->diagnosticsDetailInfolist }}
    @if ($this->showsRuntime)
        {{ $this->diagnosticsRuntimeInfolist }}
    @endif
    {{ $this->table }}
</x-filament-panels::page>
