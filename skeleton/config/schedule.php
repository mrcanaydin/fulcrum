<?php

declare(strict_types=1);

use Fulcrum\Schedule\Schedule;

return [
    Schedule::command('api-data:fetch --sort=new')->everyFiveMinutes(),
];
