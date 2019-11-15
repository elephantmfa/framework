<?php

use Illuminate\Support\Carbon;

if (! function_exists('info')) {
    function info($logMessage)
    {
        echo '[' . Carbon::now() . "] $logMessage\n";
    }
}
