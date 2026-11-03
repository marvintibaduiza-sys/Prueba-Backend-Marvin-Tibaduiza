<?php

use Illuminate\Support\Facades\Schedule;

// Delete expired tokens daily so the table doesn't keep growing.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
