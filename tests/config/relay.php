<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Greeting Banner
    |--------------------------------------------------------------------------
    |
    | The banner to respond with when a connection is made.
    */
    'greeting_banner' => env('GREETING_BANNER', 'Greetings from ElephantMFA'),

    /*
    |--------------------------------------------------------------------------
    | Ports
    |--------------------------------------------------------------------------
    |
    | The ports to be listening on. This is in 127.0.0.1:25 format, however if
    | the IP isn't provided, it will default to 127.0.0.1. These can be
    | names, so that distinctions can be made with filters.
    */
    'ports' => [ ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout until the connection is closed automatically. This prevents
    | dangling connections which use up system resources. This is measured in
    | seconds.
    */
    'timeout' => (int) env('RELAY_TIMEOUT', 60 * 5),

    /*
    |--------------------------------------------------------------------------
    | Unfold MIME Headers
    |--------------------------------------------------------------------------
    |
    | If true, MIME headers will be unfolded when stored. Folding is the
    | process of taking a really long header and putting it on multiple lines.
    | The lines are usually then prefixed with spaces or tabs. Unfolding the
    | headers will remove those spaces/tabs and bring the header all on line.
    */
    'unfold_headers' => true,

    /*
    |--------------------------------------------------------------------------
    | Queue Processor
    |--------------------------------------------------------------------------
    |
    | This determines how queued mail should be handled. Mail can either be
    | handled with the event loop, or with queues. If handled with queues,
    | management of processing queued mail can be handled separately from
    | handling mail coming in. Alternatively, queuing of files can be disabled
    | altogether by setting this to none. This means the message will be held
    | in memory and be run through the queue pipeline while still in memory,
    | nefore sent to it's final destination.
    |
    | Options: none, event-loop, queue
    */
    'queue_processor' => 'event-loop',

];
