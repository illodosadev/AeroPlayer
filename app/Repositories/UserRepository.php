<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRepository
{
    /**
     * The single owner account, or null before it is created.
     */
    public function findOwner(): ?User
    {
        return User::query()->oldest('id')->first();
    }

    /**
     * Create the owner account. The name is the local part of the email.
     */
    public function createOwner(string $email, string $password): User
    {
        return User::query()->create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Replace the owner's email and password, and end every other session of that account.
     */
    public function replaceOwner(User $owner, string $email, string $password): User
    {
        return DB::transaction(function () use ($owner, $email, $password) {
            $owner->forceFill([
                'email' => $email,
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $owner->getKey())->delete();

            return $owner->refresh();
        });
    }
}
