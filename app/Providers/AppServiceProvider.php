<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // تأكدي إن القيم دي موجودة في الـ Variables بتاعة Railway
        Config::set('cloudinary.cloud_name', env('CLOUDINARY_CLOUD_NAME'));
        Config::set('cloudinary.api_key', env('CLOUDINARY_API_KEY'));
        Config::set('cloudinary.api_secret', env('CLOUDINARY_API_SECRET'));

        // أو لو بتستخدمي الـ URL الموحد
        Config::set('cloudinary.cloudinary_url', env('CLOUDINARY_URL'));
    }
}
