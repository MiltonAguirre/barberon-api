<?php

namespace App\Listeners;

use App\Events\StoreTurn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendTurnNotification
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
     * @param  \App\Events\StoreTurn  $event
     * @return void
     */
    public function handle(StoreTurn $event)
    {
        try {
            $turn = $event->turn;
            $users_id = [$turn->user->id, $turn->barbershop->user->id];
            $tokens_devices = TokenDevice::whereIn('user_id', $users_id)->where('is_active',true)->pluck('token')->toArray();
            $data = [
                "foreground" => false, // BOOLEAN: If the notification was received in foreground or not
                "userInteraction" => true, // BOOLEAN: If the notification was opened by the user from the notification area or not
                "message" => 'My Notification Message', // STRING: The notification message
                "data" => [
                    "registration_ids" => $tokens_devices,
                    "notification"=>[
                        "title"=>"Portugal vs. Denmark",
                        "body"=>"great match!"
                    ]
                ], // OBJECT: The push data or the defined userInfo in local notifications
            ];
            sendNotify($data);
        } catch (\Exception $e) {
            \Log::error($e->getMessage(), $e);
        }
    }
}
