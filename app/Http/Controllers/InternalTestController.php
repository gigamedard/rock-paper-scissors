<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class InternalTestController extends Controller
{
    /**
     * Appelle le worker Node.js pour tester la communication interne.
     */
    public function testNodeCall(Request $request)
    {
        $secret = Config::get('app.internal_api_secret');
        $nodePort = 3000; // Port défini dans smart_contracts/config.js
        $nodeUrl = "http://localhost:{$nodePort}/sendPayment";

        // Scénario de succès : Appel avec le bon secret
        $responseSuccess = Http::withHeaders([
            'X-Internal-Secret' => $secret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($nodeUrl, [
            'wallet' => '0x1234567890123456789012345678901234567890',
            'amount' => '100'
        ]);

        // Scénario d'échec : Appel avec un mauvais secret
        $responseFailure = Http::withHeaders([
            'X-Internal-Secret' => 'mauvais-secret',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($nodeUrl, [
            'wallet' => '0x1234567890123456789012345678901234567890',
            'amount' => '100'
        ]);

        return response()->json([
            'description' => 'Résultats des tests de communication interne Laravel -> Node.js',
            'test_avec_bon_secret' => [
                'status_code' => $responseSuccess->status(),
                'body' => $responseSuccess->json(),
            ],
            'test_avec_mauvais_secret' => [
                'status_code' => $responseFailure->status(),
                'body' => $responseFailure->json(),
            ]
        ]);
    }
}
