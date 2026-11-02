<?php

use Illuminate\Support\Facades\Schedule;

// Expired tokens are already rejected; this keeps the tokens table from growing forever.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
