<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Barber;
use App\Models\Turn;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use DateInterval;
use DateTime;
use Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    function show()
    {
        $user = auth('api')->user();
        if(!$user)  abort(401);
        return response()->json($user->getData());
    }

    function getUser($user_id)
    {
        $user = User::find($user_id);
        if(!$user){
          abort(401);
        }else{
          return response()->json($user->getData(),200);
        }
    }

    function storeTurn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start' => 'required|date_format:Y-m-d H:i|after:now',
            'product_id' => 'required|integer|min:1',
        ]);

        if($validator->fails())     return response()->json(['errors' => $validator->errors()]);
        $user = auth('api')->user();
        if(!$user) abort(401);

        $product = Product::findOrFail($request->product_id);
        $barbershop = $product->barbershop;

        /**
         * CHECK WORK SCHEDULES
         */
        $days = explode(",",$barbershop->days);
        $start = getDate(strtotime($request->start));
        $end = $start['hours']+$product->hours;
        $in_work = false;
        if($days[$start['wday']]){
            foreach($barbershop->schedules as $schedule){
                if($schedule->open <= $start['hours'] && $schedule->close > $end)
                    $in_work = true;
            }
        }
        if(!$in_work)   return response()->json(['message'=>'Error, turno fuera del horario de atencion'],400);

        /**
         * CHECK ALL TURNS FOR COINCIDENS
         */
        $start = date('Y-m-d', strtotime($request->start));
        $turnsActive = Turn::join('barbershops', 'turns.barbershop_id', 'barbershops.id')
                        ->where('barbershops.id', $barbershop->id)
                        ->where('turns.state', 1)
                        ->whereDate('turns.start',$start)
                        ->get();

        $newStart = new DateTime($request->start);
        $newEnd = new DateTime($request->start);
        $newInterval = new DateInterval('PT' . (int)$product->hours . 'H' . $product->minutes . 'M');
        $newEnd->add($newInterval);

        foreach($turnsActive as $turnActive){
            $turnActiveStart = new DateTime($turnActive->start);
            $turnActiveEnd = $turnActive->getEnding();
            // Check availability
            if(($newStart >= $turnActiveStart && $newStart < $turnActiveEnd)
                    || ($newEnd > $turnActiveStart && $newEnd <= $turnActiveEnd))
                    return response()->json(['message'=>'Error, el horario elegido no esta disponible'],400);
        }

        $new_turn = new Turn;
        $new_turn->start = $request->start;
        $new_turn->user()->associate($user);
        $new_turn->product()->associate($product);
        $new_turn->barbershop()->associate($product->barbershop);
        $new_turn->save();

        $turns = $user->turns->where('created_at', '>=', Carbon::now()->subYears(1));
        foreach($turns as $turn ){
              $turn["ending"] = $turn->getEnding();
              $product = $turn->product;
              $product['images'] =$product->images;
              $turn["product"] = $product;
              $barbershop = $turn->barbershop;
              $barbershop['location'] = $barbershop->location;
              $turn["barbershop"] = $barbershop;
        }
        return response()->json($turns,200);
    }

    function getTurns()
    {
          $user = auth('api')->user();
          if(!$user) abort(401);
          $turns = $user->turns->where('created_at', '>=', Carbon::now()->subYears(1));
          foreach($turns as $turn ){
                $turn["ending"] = $turn->getEnding();
                $product = $turn->product;
                $product['images'] =$product->images;
                $turn["product"] = $product;
                $barbershop = $turn->barbershop;
                $barbershop['location'] = $barbershop->location;
                $turn["barbershop"] = $barbershop;
          }
          return response()->json($turns,200);
     }

    function cancelTurn($turn_id)
    {
          $user = auth('api')->user();
          if(!$user) abort(401);

          $turn = Turn::findOrFail($turn_id);
          if($user->id !== $turn->user->id)
            return response()->json([
                'message'=>'Usted no tiene los permisos para esta acción'
            ],400);

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
                $product = $turn->product;
                $product['images'] =$product->images;
                $turn["product"] = $product;
                $barbershop = $turn->barbershop;
                $barbershop['location'] = $barbershop->location;
                $turn["barbershop"] = $barbershop;
          }
          return response()->json($turns,200);
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
