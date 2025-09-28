<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\ApiToken;

class ApiTokenGuard implements Guard
{
    protected $user;

    public function __construct($provider = null)
    {
        $this->provider = $provider;
    }

    public function check()
    {
        return !is_null($this->user());
    }

    public function guest()
    {
        return !$this->check();
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        $token = request()->bearerToken();

        if (!$token) {
            return null;
        }

        $apiToken = ApiToken::where('token', $token)->first();

        if ($apiToken) {
            return $this->user = $apiToken->user;
        }

        return null;
    }

    public function id()
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }

    public function validate(array $credentials = [])
    {
        return false;
    }

    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }

    public function hasUser() // ✅ Added for Laravel 11
    {
        return !is_null($this->user);
    }
}
