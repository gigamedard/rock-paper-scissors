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
                                    <input type="number" id="bet-amount-{{ $user->id }}" value="{{ $currentUser->userSetting->base_bet_amount }}" class="bet-amount-input" />
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
                            <li id="received-invitation-{{ $invitation->id }}" data-created-at="{{ $invitation->created_at->timestamp }}">
                                {{ $invitation->sender->name }} : {{ $invitation->base_bet_amount }}
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
                            <!-- New data display areas -->
                            <div id="gain-display" class="gain">Gain: </div>
                            <div id="verdict-display" class="verdict">Verdict: </div>
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
                        <button class="open-settings" onclick="showSettingsPopup()">Settings</button>
                    </div>
                </div>


                <!-- Settings Popup -->
                <div id="settings-popup">
                    <div class="settings-container">
                        <button class="close-popup" onclick="hideSettingsPopup()">Close</button>
                        <h3>User Settings</h3>
                        <form id="settings-form">
                            <label for="base-bet-amount">Base Bet Amount:</label>
                            <input type="number" id="base-bet-amount" name="base-bet-amount" required>
                            
                            <label for="same-bet-match">
                                <input type="checkbox" id="same-bet-match" name="same-bet-match" checked onclick="toggleMaxBetInput()">
                                Match only with users who want to bet the same amount
                            </label>

                            <div id="max-bet-amount-container" style="display: none;">
                                <label for="max-bet-amount">Max Bet Proposition Amount:</label>
                                <input type="number" id="max-bet-amount" name="max-bet-amount">
                            </div>

                            <button type="submit">Save</button>
                        </form>
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
            position: relative; /* To position the settings button */
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

        .open-settings {
            background-color: #f0f0f0;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 2;
        }

        /* Settings Popup */
        #settings-popup {
            display: none; /* Hide by default */
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8); /* Semi-transparent background */
            z-index: 1100; /* High z-index to ensure it overlays other elements */
        }

        .settings-container {
            width: 40%;
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 3px 5px 3px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        #settings-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        #settings-form input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        #settings-form button {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .bet-amount-input {
            width: 80px;
            margin-left: 10px;
            margin-right: 10px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
/* Initially hide the elements */
#gain-display, #verdict-display {
    display: none; /* Hidden by default */
    font-size: 24px; /* Set the font size to be clearly visible */
    color: white; /* White text to stand out against dark backgrounds */
    font-weight: bold; /* Make the text bold for emphasis */
    text-align: center; /* Center text horizontally within the container */
    margin-top: 10px; /* Add some space above for separation */
}

/* Position the gain-display element */
#gain-display {
    margin-top: 20px; /* Adjust margin for spacing from top */
}

/* Position the verdict-display element below the gain-display */
#verdict-display {
    margin-top: 40px; /* Space it further down from the gain-display */
}

/* Optional: Ensure the text is centered in its container */
#gamepad-popup .screen {
    display: flex;
    flex-direction: column;
    align-items: center; /* Center align horizontally */
    justify-content: center; /* Center align vertically */
}


    </style>

    <script>
let differenceClientServerTimestamp = 0;
let countdownInterval;
let selectedMove = '';
let fightId = null;
let fightCreatedAt = 0;
let CurrentRequestData = null;

let baseBetAmount = 0;
let maxBetAmount = 0;

function showSettingsPopup() {
    document.getElementById('settings-popup').style.display = 'flex';
}
function updateGamepadScreen(data) {
    // Update the content of the gain and verdict display
    document.getElementById('gain-display').textContent = 'Gain: ' + data.gain;
    document.getElementById('verdict-display').textContent = ': ' + data.verdict;
    
    // Show the elements by changing their display style
    document.getElementById('gain-display').style.display = 'block';
    document.getElementById('verdict-display').style.display = 'block';
}

function updateDifferenceClientServerTimestamp() {
    fetch('/get_server_time') // Replace with your server endpoint
        .then(response => response.json())
        .then(data => {
            // Assuming the server returns a JSON object with a 'timestamp' fieldme
            let jsTimestamp = new Date(data.timestamp * 1000).getTime();

            console.log(jsTimestamp);
            

            let currentTimestamp = new Date().getTime();
            console.log(currentTimestamp);

            differenceClientServerTimestamp = currentTimestamp - jsTimestamp; // Convert to JavaScript Date object

            console.log(differenceClientServerTimestamp);
        });
}

// Function to toggle the visibility of the Max Bet Amount field
/*
function toggleMaxBetInput() {
    const maxBetContainer = document.getElementById('max-bet-amount-container');
    const matchSameBet = document.getElementById('same-bet-match').checked;
    
    // Show or hide the Max Bet Amount input based on the checkbox status
    if (matchSameBet) {
        maxBetContainer.style.display = 'none';
    } else {
        maxBetContainer.style.display = 'block';
    }
}*/

// Make sure to trigger the toggle function on page load to apply the correct initial state
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');


       // Fetch user settings when the popup is opened
       function loadUserSettings() {
        fetch('/user/settings')
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                // Persist the settings in localStorage
                localStorage.setItem('base_bet_amount', data.base_bet_amount);
                localStorage.setItem('same_bet_match', data.same_bet_match);
                localStorage.setItem('max_bet_amount', data.max_bet_amount);

                baseBetAmount =parseFloat(localStorage.getItem('base_bet_amount'));
                maxBetAmount = parseFloat(localStorage.getItem('max_bet_amount'));

                // Populate the form fields with the user's settings
                document.getElementById('base-bet-amount').value = data.base_bet_amount;
                document.getElementById('same-bet-match').checked = data.same_bet_match;
                if (data.same_bet_match) {
                    document.getElementById('max-bet-amount-container').style.display = 'none';
                    document.getElementById('max-bet-amount').value = data.max_bet_amount;
                } else {
                    document.getElementById('max-bet-amount-container').style.display = 'block';
                }
            })
            .catch(function(error) {
                console.error('Error fetching settings:', error);
            });
        }

    // Show or hide the max bet input based on the checkbox
    document.getElementById('same-bet-match').addEventListener('change', function() {
        toggleMaxBetInput();
    });

   function toggleMaxBetInput() {
        const isChecked = document.getElementById('same-bet-match').checked;
        document.getElementById('max-bet-amount-container').style.display = isChecked ? 'none' : 'block';
    }

    // Handle form submission for saving settings
    document.getElementById('settings-form').addEventListener('submit', function(event) {
        event.preventDefault();

        // Gather form data
        const formData = {
            base_bet_amount: document.getElementById('base-bet-amount').value,
            same_bet_match: document.getElementById('same-bet-match').checked ? 1 : 0,
            max_bet_amount: document.getElementById('max-bet-amount').value
        };

        // Persist the settings in localStorage
        localStorage.setItem('base_bet_amount', formData.base_bet_amount);
        localStorage.setItem('same_bet_match', formData.same_bet_match);
        localStorage.setItem('max_bet_amount', formData.max_bet_amount);

        // Send a POST request to save the settings to the server
        fetch('/user/settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken // Include CSRF token in the request headers                
            },
            body: JSON.stringify(formData)
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            alert(data.status);
            hideSettingsPopup();  // Close the popup after saving
        })
        .catch(function(error) {
            console.error('Error saving settings:', error);
        });
    });

    // Load user settings when the popup is shown
    loadUserSettings();
});


function hideSettingsPopup() {
    document.getElementById('settings-popup').style.display = 'none';
}
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
    updateDifferenceClientServerTimestamp();




    const serverTime = new Date(fightCreatedAt * 1000).getTime(); // Convert timestamp to JS Date
    const clientTime = new Date().getTime(); // Client's current time

    // Calculate elapsed time in seconds
    const elapsedTime = Math.floor((clientTime - serverTime) / 1000);

    // Calculate starting time for countdown
    let timer = Math.max(0, 15 - elapsedTime + differenceClientServerTimestamp/1000); // Ensures timer is not negative

    console.log(`elapsedTime :${elapsedTime} differenceClientServerTimestamp: ${differenceClientServerTimestamp} timer:${timer}`)

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


function deleteOldChallenges() {
    fetch('/challenges/cleanup', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => console.log(data))
    .catch(error => console.error('Error:', error));
}





function challengeAccepted(paramData,inv){
    fightId = paramData.fightId;
    fightCreatedAt = paramData.createdAt;
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

    let createdAt = new Date(challenge.challenge.created_at).getTime();
    console.log(`challenge.challenge.created_at : ${challenge.challenge.created_at}`);
    console.log(new Date('2024-08-22T22:55:31.000000Z').getTime()); // Should output a valid timestamp

    newItem.setAttribute('data-created-at', createdAt); // Assuming event.challenge.created_at holds the timestamp

    newItem.id = `sent-invitation-${challenge.challenge.id}`;
    newItem.textContent = `${challenge.receiver} - Pending`;
    sentList.appendChild(newItem);
}
/*
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
}*/



function updateReceivedInvitations(event) {
    const receivedList = document.getElementById('received-invitations-list');

    // Create a new list item
    const newItem = document.createElement('li');

    // Set the ID and data-created-at attributes
    newItem.id = `received-invitation-${event.challenge.id}`;
    let createdAt = new Date(event.challenge.created_at).getTime()
    newItem.setAttribute('data-created-at', createdAt); // Assuming event.challenge.created_at holds the timestamp

    // Create text node with the sender's name
    const senderText = document.createTextNode(`${event.sender} : ${event.challenge.base_bet_amount} ` );

    // Create the Accept button
    const acceptButton = document.createElement('button');
    acceptButton.classList.add('bg-green-500', 'text-white', 'px-2', 'py-1', 'rounded');
    acceptButton.textContent = 'Accept';
    acceptButton.onclick = function () {
        acceptChallenge(event.challenge.id);
    };

    // Append the sender text and the Accept button to the list item
    newItem.appendChild(senderText);
    newItem.appendChild(acceptButton);

    // Append the new list item to the received invitations list
    receivedList.appendChild(newItem);
}






















function sendChallenge(userId) {
        const betAmount = document.getElementById(`bet-amount-${userId}`).value;


        
        fetch(`/challenge/send/${userId}/${baseBetAmount}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                betAmount: betAmount
            })
        })
        .then(response => response.json())
        .then(data => removeUserAfterChallenged(data))
        .catch(error => console.error('Error:', error));
}


function checkAndRemoveExpiredInvitations() {
    const invitations = document.querySelectorAll('#received-invitations-list li');
    const currentTime = new Date().getTime()/1000; // Current time in seconds

    invitations.forEach(invitation => {
        const createdAt = parseFloat(invitation.getAttribute('data-created-at')) / 1000;
        console.log(`currentTime ${currentTime} createdAt :${createdAt}`);
        if (currentTime - createdAt > 45) {
            // Remove the expired invitation from the UI
            invitation.remove();

            console.log(`currentTime ${currentTime} createdAt :${createdAt} diff = ${currentTime - createdAt}`);
        }
    });
}




function checkAndRemoveExpiredInvitationsSent() {
    const invitations = document.querySelectorAll('#sent-invitations-list li'); // Select all <li> elements within the container
    const currentTime = new Date().getTime() / 1000; // Current time in seconds

    invitations.forEach(invitation => {
        let createdAt = invitation.getAttribute('data-created-at');

        console.log(`Original createdAt attribute: ${createdAt}`);
        
        if (!createdAt) {
            console.error('No data-created-at attribute found for this invitation:', invitation);
            return; // Skip processing this element
        }

        createdAt = parseFloat(createdAt) / 1000; // Convert milliseconds to seconds
        console.log(`Parsed createdAt in seconds: ${createdAt}`);
        console.log(`Current Time in seconds: ${currentTime}`);

        if (currentTime - createdAt > 45) { // If more than 45 seconds have passed
            // Remove the expired invitation from the UI
            invitation.remove();
            console.log(`Removed invitation due to expiration. Time difference: ${currentTime - createdAt} seconds.`);
        }
    });
}






function routin(){
    checkAndRemoveExpiredInvitations();
    checkAndRemoveExpiredInvitationsSent();
    deleteOldChallenges();

}


// Run the check every second
setInterval(routin, 4000);

    </script>

</x-app-layout>
