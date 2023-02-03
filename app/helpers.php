<?php
use App\Color;
use App\ForeignExchange;
use App\Models\Turn;
use Illuminate\Support\Facades\DB;

function imBarber(){
    return auth()->user()->role->name=="barber";
}
function imClient(){
    return auth()->user()->role->name == "client";
}
function barbershopIsOpen($barbershop, $date, $hours)
{
    /*** CHECK WORK SCHEDULES    */
    $in_work = false;
    $days = explode(",",$barbershop->days);
    $start = getDate(strtotime($date));
    $end = $start['hours']+$hours;
    if($days[$start['wday']]){
        foreach($barbershop->schedules as $schedule){
            if($schedule->open <= $start['hours'] && $schedule->close > $end)
                $in_work = true;
        }
    }
    return $in_work;
}
function checkShiftAvailability($product, $date)
{
    $start = date('Y-m-d', strtotime($date));
    //Get turns active for this date &
    //Check availability for this product        
    $turnsActive = Turn::join('states', function($join) {
                        $join->on('states.turn_id', '=', 'turns.id')
                            ->on('states.id', '=', DB::raw("(select max(id) from states WHERE states.turn_id = turns.id)"));
                    })
                    ->where('turns.barbershop_id', $product->barbershop->id)
                    ->where('states.value', '>=', 1)
                    ->where('turns.start', '>=', $start)
                    ->where('turns.start','<', date('Y-m-d H:i:s', strtotime($start . ' + ' . $product->hours. 'hours + '. $product->minutes . ' minutes')))
                    ->select('turns.*','states.value as turn_state')
                    ->get();
    if(!count($turnsActive)){
        return true;
    }
    return false;
}

function sendNotify($fields)
{
   try {
    \Log::info("Send notify: ");
    $url = 'https://fcm.googleapis.com/fcm/send';
    $server_key = env('FCM_SERVER_KEY');

    $headers = array(
        'Authorization: key=' . $server_key,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

    $result = curl_exec($ch);
    curl_close($ch);

    \Log::debug('NOTI RES::::\n\n\n');
    \Log::debug($result);
    \Log::debug('NOTI RES::::\n\n\n');
   } catch (\Exception $e) {
    \Log::error($e->getMessage());
    dd($e);
   }
}