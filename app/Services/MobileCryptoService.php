<?php

namespace App\Services;

use App\Models\MobileCryptoUser;
use Illuminate\Validation\ValidationException;

class MobileCryptoService
{
    public function touch(string $chatId, ?string $username = null): MobileCryptoUser
    {
        $user = MobileCryptoUser::firstOrCreate(
            ['telegram_chat_id' => (string) $chatId],
            ['telegram_username' => $username, 'last_seen_at' => now()]
        );
        $dirty = [];
        if ($username && $user->telegram_username !== $username) $dirty['telegram_username'] = $username;
        $dirty['last_seen_at'] = now();
        $user->forceFill($dirty)->save();
        return $user;
    }

    public function register(MobileCryptoUser $user, string $fullName, string $phone, string $country = 'CI'): MobileCryptoUser
    {
        $fullName = trim($fullName);
        $phone = preg_replace('/\s+/', '', trim($phone));
        if (mb_strlen($fullName) < 2) throw ValidationException::withMessages(['name' => 'Nom complet requis.']);
        if (! preg_match('/^0[0-9]{9}$/', $phone)) throw ValidationException::withMessages(['phone' => 'Numéro invalide (10 chiffres, ex: 0758431653).']);
        $user->update([
            'full_name' => $fullName,
            'phone' => $phone,
            'country' => strtoupper(substr($country ?: 'CI', 0, 2)),
            'kyc_status' => MobileCryptoUser::KYC_PENDING,
            'allowlist' => false,
        ]);
        return $user->refresh();
    }

    public function setPayoutAddress(MobileCryptoUser $user, string $address): MobileCryptoUser
    {
        $address = trim($address);
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) throw ValidationException::withMessages(['wallet' => 'Adresse Polygon invalide.']);
        $user->update(['payout_address' => $address]);
        return $user->refresh();
    }

    public function acceptTerms(MobileCryptoUser $user): MobileCryptoUser
    {
        $user->update(['terms_accepted_at' => now()]);
        return $user->refresh();
    }

    public function approveKyc(MobileCryptoUser $user, ?string $note = null, bool $allow = true): MobileCryptoUser
    {
        $user->update([
            'kyc_status' => MobileCryptoUser::KYC_APPROVED,
            'allowlist' => $allow,
            'admin_note' => $note,
        ]);
        return $user->refresh();
    }

    public function rejectKyc(MobileCryptoUser $user, ?string $note = null): MobileCryptoUser
    {
        $user->update([
            'kyc_status' => MobileCryptoUser::KYC_REJECTED,
            'allowlist' => false,
            'admin_note' => $note,
        ]);
        return $user->refresh();
    }

    public function setAllowlist(MobileCryptoUser $user, bool $allow): MobileCryptoUser
    {
        $user->update(['allowlist' => $allow]);
        return $user->refresh();
    }
}
