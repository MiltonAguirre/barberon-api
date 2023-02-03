<?php

namespace App\Listeners;

use App\Events\StoreBarbershopEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\TokenDevice;

class SendBarbershopNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\StoreBarbershopEvent  $event
     * @return void
     */
    public function handle(StoreBarbershopEvent $event)
    {
        $tokens_devices = TokenDevice::where('is_active',true)->pluck('token')->toArray();
        $fields = array(
            'registration_ids' => $tokens_devices,
            'notification' => array(
                "title"=>"Nueva barberia!",
                "body"=>"Hay una nueva barberia disponible!"
            )
        );
        sendNotify($fields);
    }
}
