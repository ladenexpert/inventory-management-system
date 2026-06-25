<x-app-layout title="Roles">
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-foreground leading-tight">Roles</h2>
            <p class="mt-1 text-sm text-muted-foreground">Role and module permission matrix for the RNI pilot.</p>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto space-y-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <p class="text-sm text-muted-foreground">Stored roles remain enum-based on users, while permissions are now managed per role and module for view, create, update, delete, import, export, confirm, cancel, and restore.</p>
            </div>
            <livewire:roles.role-permission-matrix />
        </div>
    </div>
</x-app-layout>
