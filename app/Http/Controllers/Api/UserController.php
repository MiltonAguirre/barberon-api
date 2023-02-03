<?php

namespace App\Http\Controllers\Api;

use App\Events\StoreTurn;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Product;
use App\Models\State;
use App\Models\Turn;
use App\Models\TokenDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Validator;

class UserController extends Controller
{
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
            $product = Product::findOrFail($request->product_id);
            $date_start = $request->start;
            if(!barbershopIsOpen($product->barbershop, $date_start, $product->hours)){
                return response()->json(['message'=>'Error, turno fuera del horario de atencion'],400);
            }
            $available = checkShiftAvailability($product, $date_start);
            if(!$available){
                return response()->json(['message'=>'Error, el horario elegido no esta disponible'],400);
            }
            DB::beginTransaction();
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
                $turn["ending"] = $turn->getEnd();
                $turn["barbershop"] = $turn->barbershop->getData();
            }
            DB::commit();
            event(new StoreTurn);
            return response()->json($turns,200);
        } catch (\Throwable $th) {
            DB::rollback();
            \Log::debug($th);
            return response()->json(['message' => $th->getMessage()], 400);
        }
    }

    function getTurns()
    {
        try {
            $turns = auth()->user()->turns->where('created_at', '>=', Carbon::now()->subYears(1));
            foreach($turns as $turn ){
                    $turn["ending"] = $turn->getEnd();
                    $barbershop = $turn->barbershop;
                    $barbershop['location'] = $barbershop->location;
                    $turn["barbershop"] = $barbershop;
            }
            return response()->json($turns,200);
        } catch (\Throwable $th) {
            \Log::debug($th);
            return response()->json(['message' => $th->getMessage()], 400);
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
                    $turn["ending"] = $turn->getEnd();
                    $barbershop = $turn->barbershop;
                    $barbershop['location'] = $barbershop->location;
                    $turn["barbershop"] = $barbershop;
            }
            DB::commit();
            return response()->json($turns,200);
        } catch (\Throwable $th) {
            DB::rollback();
            \Log::debug($th);
            return response()->json(['message' => $th->getMessage()], 400);
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
    function storeTokenDevice(Request $request)
    {
        \Log::debug("Store Token Device:  ".$request->fcm_token);

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|min:20|max:200'
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        try {
            DB::beginTransaction();
            $alreadyRegistered = TokenDevice::where('user_id', auth()->user()->id)->where('token', $request->fcm_token)->count();
            if($alreadyRegistered){
                return response()->json(['message'=>'Device is already register'],400);
            }else{
                TokenDevice::create([
                    'token' =>$request->fcm_token,
                    'user_id' => auth()->user()->id,
                ]);
                DB::commit();
                return response()->json(['message'=>'Successfully device register'],200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("Error fmc_token device ".$e->getMessage()." in file ".$e->getFile()."@".$e->getLine());
            return response()->json(['message'=>'Error, something went wrong'],400);
        }
    }
}
