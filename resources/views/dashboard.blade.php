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
                                <li id="user-{{ $user->id }}">
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
                                <li id="received-invitation-{{ $invitation->id }}">
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
                                <li id="sent-invitation-{{ $invitation->id }}">
                                    {{ $invitation->receiver->name }}
                                    <span class="text-gray-500">Pending</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Trigger Gamepad Popup Button -->
                <button onclick="showGamepadPopup()">Show Gamepad</button>

                <!-- Gamepad Popup -->
                <div id="gamepad-popup">
                    <div class="gmcontainer">
                        <button class="close-popup" onclick="hideGamepadPopup()">Close</button>
                        <div class="screen">
                            <video src="{{asset('videos/bg1.mp4')}}" autoplay loop muted></video>
                            <div id="timer-display" class="timer">15</div>
                            <div id="selection-display" class="selection"></div>
                        </div>
                        <div class="controls">
                            <div class="button rock" onclick="selectMove('rock')">
                                <i class="fas fa-hand-rock"></i>
                            </div>
                            <div class="button paper" onclick="selectMove('paper')">
                                <i class="fas fa-hand-paper"></i>
                            </div>
                            <div class="button scissors" onclick="selectMove('scissors')">
                                <i class="fas fa-hand-scissors"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #gamepad-popup {
            display: none; /* Hide by default */
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8); /* Semi-transparent background */
            z-index: 1000; /* High z-index to ensure it overlays other elements */
        }

        .gmcontainer {
            width: 65%;
            background: linear-gradient(to bottom right, #3a3748, #2e2b3b);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 3px 5px 3px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .screen {
            width: 100%;
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(to bottom right, #3a3748, #2e2b3b);
            box-shadow: 0px 3px 7px 2px rgb(249 241 241 / 23%);
            position: relative;
        }

        .screen::before {
            content: '';
            position: absolute;
            top: 0px;
            left: 0px;
            right: 0px;
            bottom: 0px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: inset 0px 3px 8px 3px rgb(236 229 229 / 80%);
            z-index: 1;
        }

        .screen video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .controls {
            display: flex;
            gap: 20px;
        }

        .button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #555;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: inset -5px 2px 3px 1px rgb(249 241 241 / 23%), inset 0 -3px 0 rgba(0, 0, 0, 0.3);
            cursor: pointer;
            font-size: 24px;
            color: white;
            transition: all 0.2s ease;
        }

        .button:active {
            box-shadow: inset 0 5px 10px rgba(0, 0, 0, 0.2), 0 3px 0 rgba(0, 0, 0, 0.3);
            transform: translateY(2px);
        }

        .button.rock {
            background: linear-gradient(to top, #ff6f31, #ff9a58);
        }

        .button.paper {
            background: linear-gradient(to top, #45c047, #6fdd6d);
        }

        .button.scissors {
            background: linear-gradient(to top, #1e90ff, #58a9ff);
        }

        .close-popup {
                background: #ff5f5f;
                color: white;
                padding: 5px 10px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                position: absolute;
                top: 20px;
                right: 20px;
                z-index: 2;
            }

        #timer-display {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 3rem;
            color: white;
            z-index: 2;
        }

        #selection-display {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 2rem;
            color: white;
            z-index: 2;
        }
    </style>

    <script>
let countdownInterval;
let selectedMove = '';
let fightId = null;
let CurrentRequestData = null;

function showGamepadPopup() {
    document.getElementById('gamepad-popup').style.display = 'flex';
    startCountdown();
}

function hideGamepadPopup() {
    document.getElementById('gamepad-popup').style.display = 'none';
    stopCountdown();
    clearSelection();
}

function startCountdown() {
    let timer = 15;
    document.getElementById('timer-display').textContent = timer;

    countdownInterval = setInterval(() => {
        timer--;
        document.getElementById('timer-display').textContent = timer;

        if (timer <= 0) {
            clearInterval(countdownInterval);
            // You can add any additional logic when the timer reaches zero
        }
    }, 1000);
}

function stopCountdown() {
    clearInterval(countdownInterval);
}

function selectMove(move) {
    selectedMove = move;
    document.getElementById('selection-display').textContent = selectedMove;
    console.log(`selectedMove: ${selectedMove}`),
    postRequest(`/fight/${fightId}/${selectedMove}`);
}

function clearSelection() {
    selectedMove = '';
    fightId = null;
    document.getElementById('selection-display').textContent = selectedMove;
}

function postRequest(url){
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => CurrentRequestData = data)
    .catch(error => console.error('Error:', error));
}

function sendChallenge(userId) {
    fetch(`/challenge/send/${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => removeUserAfterChallenged(data))
    .catch(error => console.error('Error:', error));
}

function removechallengerFromOnlineUserList(paramData, userId){
    const element = document.getElementById(`received-invitation-${userId}`);
    if (element) {
        element.remove();
    }
}

function removeUserAfterChallenged(data){
    if(data.status === 'ok'){
        const element = document.getElementById(`user-${data.challengerId}`);
        if (element) {
            element.remove();
        }
    }
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
    .then(data => challengeAccepted(data,invitationId))
    .catch(error => console.error('Error:', error));
}
function challengeAccepted(paramData,inv){
    fightId = paramData.fightId;
    console.log(`fightID: ${paramData.fightId}`)
    dropReceivedInvitationFromUI(inv);
    showGamepadPopup();
}
function dropReceivedInvitationFromUI(invId){
    const element = document.getElementById(`received-invitation-${invId}`);
    if (element) {
        element.remove();
    }
}

function dropSentInvitationFromUI(paramData){
    const element = document.getElementById(`sent-invitation-${paramData.invitationId}`);
    if (element) {
        element.remove();
    }
}

function displayGamePad(){
   ;
}

function updateSentInvitations(challenge) {
    const sentList = document.getElementById('sent-invitations-list');
    const newItem = document.createElement('li');
    newItem.id = `sent-invitation-${challenge.challenge.id}`;
    newItem.textContent = `${challenge.receiver} - Pending`;
    sentList.appendChild(newItem);
}

function updateReceivedInvitations(event) {
    const receivedList = document.getElementById('received-invitations-list');
    const newItem = document.createElement('li');
    newItem.id = `received-invitation-${event.challenge.id}`;
    const senderText = document.createTextNode(`${event.sender} `);
    const acceptButton = document.createElement('button');
    acceptButton.classList.add('bg-green-500', 'text-white', 'px-2', 'py-1', 'rounded');
    acceptButton.textContent = 'Accept';
    acceptButton.onclick = function () {
        acceptChallenge(event.challenge.id);
    };
    newItem.appendChild(senderText);
    newItem.appendChild(acceptButton);
    receivedList.appendChild(newItem);
}
    </script>
</x-app-layout>
