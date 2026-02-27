<x-card title="Users">
    <x-badge #foreach="$users as $user" :color="$user->role">
        {{ $user->name }}
    </x-badge>
</x-card>
