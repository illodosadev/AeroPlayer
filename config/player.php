<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue sync
    |--------------------------------------------------------------------------
    |
    | How often, in seconds, a visible tab reads the playback queue again, so a change made on another device shows up
    | (playback-queue, design D6 and D8). Browser tests shorten it; the hosting allows no websockets (T-04).
    |
    */

    'queue_poll_seconds' => (int) env('PLAYER_QUEUE_POLL_SECONDS', 30),

];
