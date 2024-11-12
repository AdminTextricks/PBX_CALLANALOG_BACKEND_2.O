<?php

namespace App\Providers;

use App\Models\BlockNumber;
use App\Models\Conference;
use App\Models\Extension;
use App\Models\Ivr;
use App\Models\MainPrice;
use App\Models\OneGoUser;
use App\Models\Queue;
use App\Models\ResellerPrice;
use App\Models\RingGroup;
use App\Models\Tfn;
use App\Models\TimeCondition;
use App\Models\Trunk;
use App\Models\VoiceMail;
use App\Observers\DeletionObserver;
use Illuminate\Support\ServiceProvider;

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
        Extension::observe(DeletionObserver::class);
        Ivr::observe(DeletionObserver::class);
        Trunk::observe(DeletionObserver::class);
        Tfn::observe(DeletionObserver::class);
        BlockNumber::observe(DeletionObserver::class);
        RingGroup::observe(DeletionObserver::class);
        Queue::observe(DeletionObserver::class);
        Conference::observe(DeletionObserver::class);
        OneGoUser::observe(DeletionObserver::class);
        VoiceMail::observe(DeletionObserver::class);
        TimeCondition::observe(DeletionObserver::class);
        
    }
}
