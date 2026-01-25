<?php

namespace App\Http\Controllers;

use App\Services\IpfsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IpfsController extends Controller
{
    protected $ipfsService;

    public function __construct(IpfsService $ipfsService)
    {
        $this->ipfsService = $ipfsService;
    }

    public function upload(Request $request)
    {
        $request->validate([
            'data' => 'required', // Can be array or JSON string
        ]);

        $content = $request->input('data');
        
        Log::info("Received IPFS upload request from frontend user: " . $request->user()->id ?? 'guest');

        $cid = $this->ipfsService->uploadJson($content);

        if ($cid) {
            return response()->json([
                'success' => true,
                'IpfsHash' => $cid,
                'PinSize' => strlen(json_encode($content)), // Approximation
                'Timestamp' => now()->toIso8601String()
            ]);
        }

        return response()->json(['error' => 'Failed to upload to IPFS'], 500);
    }
}
