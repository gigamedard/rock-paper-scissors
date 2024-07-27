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
        });
})


            
        </script>
    </body>
</html>
