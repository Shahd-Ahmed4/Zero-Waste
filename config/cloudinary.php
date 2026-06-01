<?php

return [
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],
    // إضافة الـ URL إذا كنتِ تفضلين استخدامه كبديل
    'cloudinary_url' => env('CLOUDINARY_URL'),
];