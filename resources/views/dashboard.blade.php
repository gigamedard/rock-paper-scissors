<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Online Users Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg">Online Users</h3>
                        <ul>
                            @foreach($onlineUsers as $user)
                                <li>
                                    {{ $user->name }}
                                    <form method="POST" action="{{ route('challenge.send', $user->id) }}">
                                        @csrf
                                        <button type="submit" class="bg-blue-500 text-white px-2 py-1 rounded">Challenge</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Received Invitations Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg">Received Invitations</h3>
                        <ul>
                            @foreach($receivedInvitations as $invitation)
                                <li>
                                    {{ $invitation->sender->name }}
                                    <form method="POST" action="{{ route('challenge.accept', $invitation->id) }}">
                                        @csrf
                                        <button type="submit" class="bg-green-500 text-white px-2 py-1 rounded">Accept</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Sent Invitations Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg">Sent Invitations</h3>
                        <ul>
                            @foreach($sentInvitations as $invitation)
                                <li>
                                    {{ $invitation->receiver->name }}
                                    <span class="text-gray-500">Pending</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
