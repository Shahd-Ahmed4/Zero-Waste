<?php

use Illuminate\Support\Facades\Route;

Route::get('/upload-form', function () {
    return '
    <form method="POST" action="/upload-seed-images" enctype="multipart/form-data">
        ' . csrf_field() . '
        <input type="file" name="images[]" multiple accept="image/*">
        <button type="submit">Upload</button>
    </form>';
});

Route::post('/upload-seed-images', function (Illuminate\Http\Request $request) {
    $count = 0;
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $file) {
            $file->move(public_path('uploads'), $file->getClientOriginalName());
            $count++;
        }
    }
    return 'Done! ' . $count . ' images uploaded.';
});
Route::get('/list-uploads', function () {
    $files = glob(public_path('uploads') . '/*');
    return implode('<br>', array_map('basename', $files));
});
Route::get('/delete-uploads', function () {
    $files = glob(public_path('uploads') . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    return 'Done! All files deleted.';
});