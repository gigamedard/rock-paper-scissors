<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KycService
{
    public function status(User $user): array
    {
        $profile = KycProfile::where('user_id', $user->id)->first();
        return [
            'status' => $user->kyc_status ?: 'unverified',
            'submitted_at' => $user->kyc_submitted_at?->toIso8601String(),
            'reviewed_at' => $user->kyc_reviewed_at?->toIso8601String(),
            'can_submit' => in_array($user->kyc_status, [null, '', 'unverified', 'rejected'], true),
            'profile' => $profile ? $this->serialize($profile) : null,
        ];
    }

    public function submit(User $user, array $data): KycProfile
    {
        if (in_array($user->kyc_status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['kyc' => 'Une vérification est déjà en cours ou approuvée.']);
        }

        return DB::transaction(function () use ($user, $data) {
            $profile = KycProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $data['full_name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'country' => $data['country'],
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'postal_code' => $data['postal_code'] ?? null,
                    'id_type' => $data['id_type'],
                    'id_number' => $data['id_number'],
                    'source_of_funds' => $data['source_of_funds'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'wallet_address' => (string) $user->wallet_address,
                    'status' => KycProfile::STATUS_PENDING,
                    'reviewer_id' => null,
                    'notes' => null,
                    'consent_at' => now(),
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                ]
            );

            $user->kyc_status = 'pending';
            $user->kyc_submitted_at = now();
            $user->kyc_reviewed_at = null;
            $user->save();

            return $profile;
        });
    }

    public function approve(KycProfile $profile, User $admin, ?string $notes = null): KycProfile
    {
        return DB::transaction(function () use ($profile, $admin, $notes) {
            $profile->update([
                'status' => KycProfile::STATUS_APPROVED,
                'reviewer_id' => $admin->id,
                'notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $profile->user->kyc_status = 'approved';
            $profile->user->kyc_reviewed_at = now();
            $profile->user->save();
            return $profile;
        });
    }

    public function reject(KycProfile $profile, User $admin, ?string $reason = null): KycProfile
    {
        return DB::transaction(function () use ($profile, $admin, $reason) {
            $profile->update([
                'status' => KycProfile::STATUS_REJECTED,
                'reviewer_id' => $admin->id,
                'notes' => $reason,
                'reviewed_at' => now(),
            ]);
            $profile->user->kyc_status = 'rejected';
            $profile->user->kyc_reviewed_at = now();
            $profile->user->save();
            return $profile;
        });
    }

    public function adminList(?string $status = null, int $limit = 100)
    {
        $q = KycProfile::with('user')->latest('submitted_at');
        if ($status) $q->where('status', $status);
        return $q->limit($limit)->get();
    }

    public function serialize(KycProfile $profile, bool $admin = false): array
    {
        $out = [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'full_name' => $profile->full_name,
            'date_of_birth' => $profile->date_of_birth?->format('Y-m-d'),
            'country' => $profile->country,
            'address' => $profile->address,
            'city' => $profile->city,
            'postal_code' => $profile->postal_code,
            'id_type' => $profile->id_type,
            'id_number_last4' => $profile->idLast4(),
            'source_of_funds' => $profile->source_of_funds,
            'occupation' => $profile->occupation,
            'wallet_address' => $profile->wallet_address,
            'status' => $profile->status,
            'risk_level' => $profile->risk_level,
            'notes' => $profile->notes,
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
            'reviewed_at' => $profile->reviewed_at?->toIso8601String(),
        ];
        if ($admin) {
            $out['wallet'] = $profile->user?->wallet_address;
            $out['email'] = $profile->user?->email;
            $out['is_admin'] = (bool) $profile->user?->is_admin;
            $out['onramp_allowed'] = (bool) $profile->user?->onramp_allowed;
        }
        return $out;
    }
}
