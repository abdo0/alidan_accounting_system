<x-filament-panels::page>
    <x-filament::section>
        <table class="w-full text-sm">
            <tbody>
                @foreach ($this->catalogue() as $entry)
                    @php($d = $entry['definition'])
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $d->code }}</td>
                        <td class="px-3 py-2">
                            @if ($entry['url'])
                                <a href="{{ $entry['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $d->displayName() }}</a>
                            @else
                                <span class="text-gray-500">{{ $d->displayName() }}</span>
                            @endif
                            <div class="text-xs text-gray-500">{{ $d->content }}</div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if ($entry['url'] === null)
                                <x-filament::badge color="gray">{{ $d->phase === 'Phase 2' ? __('reports.phase_2') : __('reports.not_available') }}</x-filament::badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
