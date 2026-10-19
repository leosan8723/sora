<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking rules
    |--------------------------------------------------------------------------
    |
    | Times are stored in UTC (see ARCHITECTURE.md §10). Availability rules are
    | authored in the presentation timezone below, so slot generation converts
    | explicitly at the boundary.
    |
    */

    'timezone' => 'Asia/Tehran',

    // How far ahead a client may look at bookable slots (in days) and the
    // default window when the client does not specify a range.
    'max_range_days' => 31,
    'default_range_days' => 7,

    // A slot must start at least this many minutes in the future to be offered
    // or booked, so a client cannot book "the current minute".
    'minimum_lead_minutes' => 5,

    // Clients may cancel or reschedule an active appointment until this many
    // hours before its start time. Consultants may cancel at any time before
    // the start time (they still cannot touch terminal appointments).
    'cancellation_lead_hours' => 2,
    'reschedule_lead_hours' => 2,

    // Consultation modes a consultant may offer. The admin panel can narrow
    // this list at runtime through site settings; validation reads the
    // effective list so newly configured modes are honored without a deploy.
    'supported_modes' => ['online', 'in_person'],

];
