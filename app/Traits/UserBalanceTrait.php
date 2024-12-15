<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Log;

trait UserBalanceTrait
{
   
   
   
    private const EMAIL_DOMAIN = '';
    private const MIN_USERNAME_LENGTH = 0;
    private const MAX_USERNAME_LENGTH = 0;

    public function __construct()
    {

        $this->EMAIL_DOMAIN = 'game.web3';
        $this->MIN_USERNAME_LENGTH = 9;
        $this->MAX_USERNAME_LENGTH = 20;
    }
   
   
   
    /**
     * Update existing user's balance.
     */
    protected function updateUserBalanceInDb(User $user, $balance)
    {
        $user->update(['balance' => $balance]);
    }

    /**
     * Create a new user with a generated name and email.
     */
    protected function createNewUser($walletAddress, $balance)
    {
        $username = $this->generateUniqueUsername($walletAddress);
        $email = $this->fromUsername($username);

        return User::create([
            'wallet_address' => strtolower($walletAddress),
            'name' => $username,
            'email' => $email,
            'password' => bcrypt(hash('sha256', $walletAddress)),
            'is_online' => true,
            'balance' => $balance,
        ]);
    }

    /**
     * Generate a unique username based on wallet address.
     */
    protected function generateUniqueUsername($walletAddress)
    {
        do {
            $username = $this->generateReadableName($walletAddress);
        } while (User::where('name', $username)->exists());

        return $username;
    }









    protected function generateReadableName(string $walletAddress,string $prefix = 'user'): string 
    {
        // Get the first 6 characters of the address (excluding '0x')
        $address = strtolower(trim(str_replace('0x', '', $walletAddress)));
        $shortHash = substr($address, 0, 6);
        
        return $prefix . '_' . $shortHash.= rand(100, 999);
    }


    protected function fromUsername(string $username,bool $includeRandomness = false): string
    {
        // Clean and validate username
        $cleanUsername = $this->sanitizeUsername($username);
        
        if ($includeRandomness) {
            $cleanUsername .= rand(100, 999);
        }

        return $this->generateEmail($cleanUsername);
    }


    protected function generateEmail(string $username): string
    {
        return sprintf('%s@%s', $username, $this->EMAIL_DOMAIN);
    }

    protected function sanitizeUsername(string $username): string
    {
        // Convert to lowercase and remove unwanted characters
        $clean = strtolower(trim($username));
        $clean = preg_replace('/[^a-z0-9.]/', '', $clean);
        
        // Validate length
        if (strlen($clean) < $this->MIN_USERNAME_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Username must be at least %d characters long', $this->MIN_USERNAME_LENGTH)
            );
        }

        // Truncate if too long
        if (strlen($clean) > $this->MAX_USERNAME_LENGTH) {
            $clean = substr($clean, 0, $this->MAX_USERNAME_LENGTH);
        }

        return $clean;
    }



































}
