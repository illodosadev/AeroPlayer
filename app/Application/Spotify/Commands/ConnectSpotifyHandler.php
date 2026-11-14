<?php

namespace App\Application\Spotify\Commands;

use App\Application\Spotify\Exceptions\SpotifyConnectionFailed;
use App\Repositories\SpotifyConnectionRepository;
use App\Spotify\ConnectFailure;
use App\Spotify\SpotifyAccount;
use App\Spotify\SpotifyOAuth;

final readonly class ConnectSpotifyHandler
{
    public function __construct(
        private SpotifyOAuth $oauth,
        private SpotifyConnectionRepository $connections,
    ) {}

    /**
     * Exchange the code, read the account, accept only the owner's Premium account and store the connection.
     *
     * @throws SpotifyConnectionFailed
     */
    public function handle(ConnectSpotify $command): void
    {
        $tokens = $this->oauth->exchangeCode($command->code);

        if ($tokens instanceof ConnectFailure) {
            throw new SpotifyConnectionFailed($tokens);
        }

        $account = $this->oauth->fetchAccount($tokens->accessToken);

        if ($account instanceof ConnectFailure) {
            throw new SpotifyConnectionFailed($account);
        }

        $refusal = $this->refusal($account);

        if ($refusal !== null) {
            throw new SpotifyConnectionFailed($refusal);
        }

        $this->connections->storeForOwner($command->userId, $account, $tokens);
    }

    /**
     * Only the configured owner's account, and only with Premium when Spotify reports the product.
     */
    private function refusal(SpotifyAccount $account): ?ConnectFailure
    {
        if ($account->id !== $this->oauth->ownerId()) {
            return ConnectFailure::NotOwner;
        }

        if ($account->product !== null && $account->product !== 'premium') {
            return ConnectFailure::NotPremium;
        }

        return null;
    }
}
