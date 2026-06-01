<?php

$cloudinaryUrl = env('CLOUDINARY_URL', '');

preg_match('#cloudinary://([^:]+):([^@]+)@(.+)#', $cloudinaryUrl, $matches);

return [
    'cloud_url' => $cloudinaryUrl,
    'cloud'     => $matches[3] ?? env('CLOUDINARY_CLOUD_NAME'),
    'key'       => $matches[1] ?? env('CLOUDINARY_API_KEY'),
    'secret'    => $matches[2] ?? env('CLOUDINARY_API_SECRET'),
    'secure'    => true,
];