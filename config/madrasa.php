<?php

return [

    /*
    | Locales the UI and API can be served in. The first one is the default
    | when neither the user, the request nor the school specifies one.
    */
    'locales' => ['ar', 'en'],

    'rtl_locales' => ['ar'],

    /*
    | How dates are shown when a school does not override it:
    | "gregorian", "hijri" or "both".
    */
    'date_display' => env('MADRASA_DATE_DISPLAY', 'both'),

    /*
    | Request header API clients use to pick the active school
    | (school id or slug).
    */
    'school_header' => 'X-School',

];
