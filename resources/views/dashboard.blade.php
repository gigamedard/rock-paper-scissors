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
                                    <button class="bg-blue-500 text-white px-2 py-1 rounded" onclick="sendChallenge({{ $user->id }})">Challenge</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Received Invitations Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg">Received Invitations</h3>
                        <ul id="received-invitations-list">
                            @foreach($receivedInvitations as $invitation)
                                <li>
                                    {{ $invitation->sender->name }}
                                    <button class="bg-green-500 text-white px-2 py-1 rounded" onclick="acceptChallenge({{ $invitation->id }})">Accept</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>


                <!-- Sent Invitations Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold text-lg">Sent Invitations</h3>
                        <ul id="sent-invitations-list">
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

    <script>
        function sendChallenge(userId) {
            fetch(`/challenge/send/${userId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => console.log(data))
            .catch(error => console.error('Error:', error));
        }

        function acceptChallenge(invitationId) {
            fetch(`/challenge/accept/${invitationId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => alert(data.status))
            .catch(error => console.error('Error:', error));
        }


        function updateSentInvitations(challenge) {
            // Update the sent invitations list dynamically
            const sentList = document.getElementById('sent-invitations-list');
            const newItem = document.createElement('li');
            newItem.textContent = `${challenge.receiver} - Pending`;
            sentList.appendChild(newItem);
            console.log('updateSentInvitations');


        }

        function updateReceivedInvitations(challenge) {
            const receivedList = document.querySelector('#received-invitations-list');
            const newItem = document.createElement('li');

            // Create the sender name text
            const senderText = document.createTextNode(`${challenge.sender.name} `);

            // Create the accept button
            const acceptButton = document.createElement('button');
            acceptButton.classList.add('bg-green-500', 'text-white', 'px-2', 'py-1', 'rounded');
            acceptButton.textContent = 'Accept';
            acceptButton.onclick = function () {
                acceptChallenge(challenge.id);
            };

            // Append sender name and button to the list item
            newItem.appendChild(senderText);
            newItem.appendChild(acceptButton);

            // Append the new list item to the received invitations list
            receivedList.appendChild(newItem);
        }

    </script>
</x-app-layout>
