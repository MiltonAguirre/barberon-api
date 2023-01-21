<?php
use App\Color;
use App\ForeignExchange;
use App\Models\Turn;
use Illuminate\Support\Facades\DB;
use DateInterval;
use DateTime;

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
    /*** CHECK ALL TURNS FOR COINCIDENS   */
    $available = true;
    $start = date('Y-m-d', strtotime($date));
    $newStart = new DateTime($date);
    $newEnd = new DateTime($date);
    $newInterval = new DateInterval('PT' . (int)$product->hours . 'H' . $product->minutes . 'M');
    $newEnd->add($newInterval);
    
    //Get turns active for this date
    $turnsActive = Turn::join('barbershops', 'turns.barbershop_id', 'barbershops.id')
                    ->join('states', function($join) {
                        $join->on('states.turn_id', '=', 'turns.id')
                            ->on('states.id', '=', DB::raw("(select max(id) from states WHERE states.turn_id = turns.id)"));
                    })
                    ->where('barbershops.id', $product->barbershop->id)
                    ->where('states.value', '>=', 1)
                    ->whereDate('turns.start',$start)
                    ->select('turns.*','states.value as turn_state')
                    ->get();
    //Check availability for this product        
    foreach($turnsActive as $turnActive){
        $turnActiveStart = new DateTime($turnActive->start);
        $turnActiveEnd = $turnActive->getEnd();
        if(($newStart >= $turnActiveStart && $newStart < $turnActiveEnd)
                || ($newEnd > $turnActiveStart && $newEnd <= $turnActiveEnd))
                $available = false;
    }
    return $available;
}