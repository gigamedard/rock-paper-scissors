<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <style>
        @font-face {
            font-family: 'Orbitron';
            font-style: normal;
            font-weight: 700;
            src: url(https://fonts.gstatic.com/s/orbitron/v31/yMJMMIlzdpvBhQQL_SC3X9yhF25-T1ny_CmBoWg2.ttf) format('truetype');
        }

        * {
            position: relative;
            box-sizing: border-box;
            font-family: sans-serif;
        }

        /* New CSS for popup */
        .popup-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .popup-content {
            position: relative;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            cursor: pointer;
        }

        /* Existing CSS for PSP gamepad */
        #psp {
            font-family: sans-serif;
            display: block;
            position: relative;
            margin: 20px auto;
            max-width: 600px;
            padding: 40px 20px;
            box-shadow: 0px 10px 25px rgba(0, 0, 0, 0.2);
            background: white;
            border-radius: 25px;
        }

        .interaction-area {
            position: relative;
            z-index: 5;
            width: 150px;
            height: 150px;
        }

        /* Rest of your existing CSS */
    </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
        <script>
//alert('my js is activated!');

const userId = {{ Auth::user()->id }};

const channelName = `App.Models.User.${userId}`;

window.addEventListener("DOMContentLoaded",function(){
    window.Echo.private(channelName)
    .listen("testevent",(event)=>{alert(event.message);})
    .listen('ChallengeSent', (chall) => {
            // Assume there's a function to update the UI
            updateSentInvitations(chall);
            //alert(chall.challenge.status);
        })
    .listen('ReceivedInvitationEvent', (invitation) => {
        // Assume there's a function to update the UI
        //alert("badaboom");
        updateReceivedInvitations(invitation);
    })
    .listen('ChallengeAccepted', (invitationData) => {
            // Assume there's a function to update the UI
            alert(invitationData.invitationId);
            //updateReceivedInvitations(invitation);
            dropSentInvitationFromUI(invitationData);
        });
})

//-------------------------------------------------------------popup-------------------------------------------
document.getElementById('openPopup').addEventListener('click', function() {
            document.getElementById('popupContainer').style.display = 'flex';
        });

        document.getElementById('closePopup').addEventListener('click', function() {
            document.getElementById('popupContainer').style.display = 'none';
        });

        // Close popup when clicking outside of it
        document.getElementById('popupContainer').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
            
        </script>
    </body>
</html>
