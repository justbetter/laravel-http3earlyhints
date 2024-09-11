<?php

return [
    /**
     * This works by building a cache of links after the first request, how long should we keep them for in seconds?
     * Each request updates the cache and resets the duration, on busier and larger sites this can be lowered.
     */
    'cache_duration' => env('EARLY_HINTS_CACHE_DURATION', 864000 /* 24h */),
    'cache_driver' => env('EARLY_HINTS_CACHE_DRIVER', null),
    /** Wether to send the 103. This means we do not need an external party to handle early hints but is currently only supported by FrankenPHP */
    'send_103' => env('EARLY_HINTS_SEND_103', false) && \function_exists('headers_send'),
    /** Size limit in bytes */
    'size_limit' => env('EARLY_HINTS_SIZE_LIMIT', '6000'),
    'base_path' => env('EARLY_HINTS_BASE_PATH', '/'),
    /** List of file extensions to return and generate early hints for */
    'extensions' => array_merge(
        explode(',', env('EARLY_HINTS_EXTENSIONS', '')),
        [
            'php',
            'html',
            '',
        ]
    ),
    /** Keywords that if they exist in the url will exclude link header */
    'exclude_keywords' => array_merge(
        explode(',', env('EARLY_HINTS_EXCLUDE_KEYWORDS', '')),
        []
    ),
    'default_headers' => array_merge(
        explode(',', env('EARLY_HINTS_DEFAULT_HEADERS', '')),
        [
            // '</styles/style.css>; rel=preload; as=style',
        ]
    ),
];
