<?php

use Illuminate\Support\Facades\Schedule;

// Un snapshot toutes les 5 minutes. Le verrou empêche deux cycles de se chevaucher
// (il expire au bout de 10 minutes si un cycle plante sans le libérer).
Schedule::command('helixir:collect')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
