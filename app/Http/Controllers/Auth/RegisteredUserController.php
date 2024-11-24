<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Web3p\EthereumUtil\Util;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'autoplay_active' => $request->input('autoplay_active', false),
            'status' => $request->input('status', 'available'),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }


    public function verifySignature(Request $request)
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
            'signature' => 'required|string',
        ]);

        $nonce = session('nonce');
        if (!$nonce) {
            return response()->json(['message' => 'Invalid session'], 400);
        }

        $message = "Sign this message to verify your wallet: $nonce";

        // Recover the Ethereum address
        $util = new Util();
        $recoveredAddress = $util->personalEcRecover($message, $validated['signature']);

        if (strtolower($recoveredAddress) !== strtolower($validated['wallet_address'])) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Register or authenticate the user
        $user = User::firstOrCreate(
            ['wallet_address' => $validated['wallet_address']],
            ['name' => $validated['wallet_address']]
        );

        auth()->login($user);

        return response()->json(['message' => 'Authenticated successfully']);
    }

}
