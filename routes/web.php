<?php

use Illuminate\Support\Facades\Route;

Route::get('/upload-seed-images', function () {
    $source = base_path('public/uploads');
    $files = glob($source . '/*.jpg');

    foreach ($files as $file) {
        $filename = basename($file);
        copy($file, public_path('uploads/' . $filename));
    }

    return 'Done! ' . count($files) . ' images copied.';
});
