<x-app-layout title="Roles">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">Roles</h2>
            <p class="mt-1 text-sm text-muted-foreground">Current role model used by the RNI pilot.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-4">
                    @foreach(\App\Enums\UserRole::cases() as $role)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-sm font-semibold">{{ $role->label() }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Stored as <code>{{ $role->value }}</code> and managed from the Users page.</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
