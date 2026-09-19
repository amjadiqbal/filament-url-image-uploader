<?php

return [

    'tabs' => [
        'upload' => [
            'label' => 'File Upload',
        ],
        'url' => [
            'label' => 'URL Upload',
        ],
    ],

    'url' => [
        'helper_text' => 'Enter a valid image URL',
        'invalid' => 'The url field must be a valid URL.',
    ],

    'actions' => [
        'fetch' => [
            'label' => 'Fetch Image',
        ],
    ],

    'notifications' => [
        'invalid_url' => [
            'title' => 'Invalid URL',
            'body' => 'Please enter a valid image URL.',
        ],
        'invalid_scheme' => [
            'title' => 'Invalid URL',
            'body' => 'Only http:// and https:// URLs are supported.',
        ],
        'fetch_success' => [
            'title' => 'Image fetched successfully',
        ],
        'fetch_failed' => [
            'title' => 'Failed to fetch image',
        ],
    ],

    'preview' => [
        'alt' => 'Image Preview',
        'hover' => 'Preview',
    ],

];
