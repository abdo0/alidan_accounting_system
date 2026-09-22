@php($figures = $this->figures())
<x-filament-panels::page>
    <x-filament::section>{{ $this->form }}</x-filament::section>

    @if ($figures !== null)
        <x-filament::section>
            <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                @foreach (['eligible' => 'eligible_revenue', 'excluded' => 'excluded_revenue', 'rate' => 'rate', 'due' => 'share_due', 'recognised' => 'share_recognised', 'to_recognise' => 'to_recognise'] as $key => $label)
                    <div>
                        <dt class="text-gray-500">{{ __('reports.columns.'.$label) }}</dt>
                        <dd class="font-mono text-lg">{{ $key === 'rate' ? $figures[$key] : \App\Support\Format\IqdFormatter::format($figures[$key]) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
