<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Mail\MailchimpTransport;
use Illuminate\Support\Facades\Mail;
use Internal\Common\Actions\SendCloud;
use Internal\Tools\Services\SendCloudMailTransport;

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
        Mail::extend('sendcloud', function (array $config = []) {
        return new SendCloudMailTransport(new SendCloud);
    });
    }
}
