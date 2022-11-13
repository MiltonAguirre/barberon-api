<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Product;
use App\Models\State;
use App\Models\Turn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use DateInterval;
use DateTime;
use Validator;

class UserController extends Controller
{
    /**
     * HELPERS
     */
    function isWorking($barbershop, $date, $hours)
    {
        /**
         * CHECK WORK SCHEDULES
         */
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
    function checkAvailability($product, $date)
    {
        /**
         * CHECK ALL TURNS FOR COINCIDENS
         */
        $available = true;
        $start = date('Y-m-d', strtotime($date));
        $newStart = new DateTime($date);
        $newEnd = new DateTime($date);
        
        $newInterval = new DateInterval('PT' . (int)$product->hours . 'H' . $product->minutes . 'M');
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
        $newEnd->add($newInterval);
        foreach($turnsActive as $turnActive){
            $turnActiveStart = new DateTime($turnActive->start);
            $turnActiveEnd = $turnActive->getEnding();
            // Check availability
            if(($newStart >= $turnActiveStart && $newStart < $turnActiveEnd)
                    || ($newEnd > $turnActiveStart && $newEnd <= $turnActiveEnd))
                    $available = false;
        }
        return $available;
    }
    /**
     * END OF HELPERS
     */
    function show()
    {
        $user = auth()->user();
        if(!$user)  abort(401);
        return response()->json($user->getData());
    }

    function storeTurn(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start' => 'required|date_format:Y-m-d H:i|after:now',
                'product_id' => 'required|integer|min:1',
            ]);
            if($validator->fails()){
                return response()->json(['errors' => $validator->errors()]);
            }

            DB::beginTransaction();        
            $product = Product::findOrFail($request->product_id);
            $date_start = $request->start;

            $in_work = $this->isWorking($product->barbershop, $date_start, $product->hours);
            if(!$in_work){
                return response()->json(['message'=>'Error, turno fuera del horario de atencion'],400);
            }
            $available = $this->checkAvailability($product, $date_start);
            if(!$available){
                return response()->json(['message'=>'Error, el horario elegido no esta disponible'],400);
            }
            $user = auth()->user();
            $new_turn = new Turn();
            $new_turn->start = $date_start;
            $new_turn->user()->associate($user);
            $new_turn->product()->associate($product);
            $new_turn->barbershop()->associate($product->barbershop);
            $new_turn->save();
            $state = new State();
            $state->turn()->associate($new_turn);
            $state->save();
            $turns = $user->turns->where('created_at', '>=', Carbon::now()->subYears(1));
            foreach($turns as $turn ){
                $turn["ending"] = $turn->getEnding();
                $turn["barbershop"] = $turn->barbershop->getData();
            }
            DB::commit();
            return response()->json($turns,200);
        } catch (\Throwable $th) {
            DB::rollback();
            \Log::debug($th);
            abort(400);
        }
    }

    function getTurns()
    {
        try {
            $turns = auth()->user()->turns->where('created_at', '>=', Carbon::now()->subYears(1));
            foreach($turns as $turn ){
                    $turn["ending"] = $turn->getEnding();
                    $barbershop = $turn->barbershop;
                    $barbershop['location'] = $barbershop->location;
                    $turn["barbershop"] = $barbershop;
            }
            return response()->json($turns,200);
        } catch (\Throwable $th) {
            \Log::debug($th);
            abort(400);
        }
     }

    function cancelTurn($turn_id)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();            
            $turn = Turn::findOrFail($turn_id);

            if($user->id !== $turn->user->id){
                return response()->json([
                    'message'=>'Usted no tiene los permisos para esta acción'
                ],400);
            }
            if($turn->state===1 || $turn->state===2 ){
                $turn->state=0;
                $turn->save();
            }else{
                return response()->json([
                    'message'=>'El estado del turno no es correcto para esta acción'
                ],400);
            }
            
            $turns = $user->turns->where('created_at', '>=', Carbon::now()->subYears(1));
            foreach($turns as $turn ){
                    $turn["ending"] = $turn->getEnding();
                    $barbershop = $turn->barbershop;
                    $barbershop['location'] = $barbershop->location;
                    $turn["barbershop"] = $barbershop;
            }
            DB::commit();
            return response()->json($turns,200);
        } catch (\Throwable $th) {
            DB::rollback();
            \Log::debug($th);
            abort(400);
        } 
    }
    function showTurn($turn_id)
    {
        $turn = Turn::findOrFail($turn_id);
        $response = $turn;
        $response['barbershop'] = $turn->barbershop->getData();
        $response['product'] = $turn->product;
        $response['user'] = $turn->user->getData();
        return response()->json($response, 200);
    }
}
