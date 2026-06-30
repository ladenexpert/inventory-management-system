<div class="space-y-6">
    @foreach($roles as $roleValue => $roleLabel)
        <div class="rounded-xl border bg-card p-4 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-foreground">{{ $roleLabel }}</h3>
                    <p class="text-sm text-muted-foreground">Module and action permissions used by menus, routes, buttons, and backend checks.</p>
                </div>
                <x-primary-button type="button" wire:click="saveRole('{{ $roleValue }}')" class="w-full justify-center sm:w-auto">
                    Save {{ $roleLabel }}
                </x-primary-button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-[980px] divide-y divide-border text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold text-foreground">Module</th>
                            @foreach($actions as $actionKey => $actionLabel)
                                <th class="px-3 py-3 text-center font-semibold text-foreground">{{ $actionLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-background">
                        @foreach($modules as $moduleKey => $moduleLabel)
                            <tr>
                                <td class="px-3 py-3 font-medium text-foreground">{{ $moduleLabel }}</td>
                                @foreach($actions as $actionKey => $actionLabel)
                                    <td class="px-3 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            wire:model="permissions.{{ $roleValue }}.{{ $moduleKey }}.{{ $actionKey }}"
                                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
