<?php

namespace App\YouTubeMusic;

/**
 * Google's answer to a device code request. Only the user code and the verification address reach the browser.
 */
final readonly class DeviceCode
{
    public function __construct(
        #[\SensitiveParameter] public string $deviceCode,
        public string $userCode,
        public string $verificationUrl,
        public int $interval,
        public int $expiresIn,
    ) {}
}
