<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Morning round time
    |--------------------------------------------------------------------------
    |
    | The time of day, in Rome, when `reminders:run` expires the cards, syncs the
    | reminders and sends the notices and the morning email.
    |
    */

    'time' => env('REMINDERS_TIME', '06:00'),

];
