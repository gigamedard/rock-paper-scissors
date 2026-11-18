<?php

namespace App\Http\Controllers;

use App\Models\KycProfile;
use App\Services\KycService;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(private KycService $kyc)
    {
    }

    public function status(Request $request)
    {
        return response()->json($this->kyc->status($request->user()));
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'country' => 'required|string|max:64',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:120',
            'postal_code' => 'nullable|string|max:32',
            'id_type' => 'required|in:passport,national_id,driver_license',
            'id_number' => 'required|string|max:80',
            'source_of_funds' => 'nullable|string|max:1000',
            'occupation' => 'nullable|string|max:120',
            'consent' => 'accepted',
        ]);

        $profile = $this->kyc->submit($request->user(), $data);
        return response()->json(['profile' => $this->kyc->serialize($profile), 'status' => $request->user()->fresh()->kyc_status]);
    }

    public function adminIndex(Request $request)
    {
        $status = $request->query('status');
        $profiles = $this->kyc->adminList($status ? (string) $status : null)
            ->map(fn (KycProfile $p) => $this->kyc->serialize($p, true));
        return response()->json(['profiles' => $profiles]);
    }

    public function approve(Request $request, KycProfile $profile)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:1000']);
        $this->kyc->approve($profile, $request->user(), $data['notes'] ?? null);
        return response()->json(['profile' => $this->kyc->serialize($profile->fresh(), true)]);
    }

    public function reject(Request $request, KycProfile $profile)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $this->kyc->reject($profile, $request->user(), $data['reason'] ?? 'Rejetée');
        return response()->json(['profile' => $this->kyc->serialize($profile->fresh(), true)]);
    }
}
