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
            if(!checkShiftAvailability($product, $date_start)){
                return response()->json(['message'=>'Error, el horario elegido no esta disponible'],400);
            }
            DB::beginTransaction();
            $user = auth()->user();
            $newTurn = new Turn();
            $newTurn->start = $date_start;
            $newTurn->user()->associate($user);
            $newTurn->product()->associate($product);
            $newTurn->barbershop()->associate($product->barbershop);
            $newTurn->save();
            $state = new State();
            $state->turn()->associate($newTurn);
            $state->save();
            $turns = $user->turns->where('created_at', '>=', Carbon::now()->subYears(1));
            foreach($turns as $turn ){
                $turn["ending"] = $turn->getEnd();
                $turn["barbershop"] = $turn->barbershop->getData();
            }
            DB::commit();
            event(new StoreTurn($newTurn));
            return response()->json($turns,200);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::debug($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    function getTurns()
    {
        try {
            $turns = auth()->user()->turns()
            ->join('products', 'turns.product_id', 'products.id')
            ->join('barbershops', 'products.barbershop_id', 'barbershops.id')
            ->join('users', 'turns.user_id', 'users.id')
            ->join('data_users', 'users.data_user_id', 'data_users.id')
            ->join('states', function($join) {
                $join->on('states.turn_id', '=', 'turns.id') 
                  ->on('states.id', '=', DB::raw("(select max(id) from states WHERE states.turn_id = turns.id)"));
              })
              ->where('turns.created_at', '>=', Carbon::now()->subYears(1))
              ->select('turns.*','states.value as turn_state', 'barbershops.name as barbershop_name', 'products.name as product_name', 'products.price as price', 
                DB::raw("CONCAT(data_users.first_name,' ', data_users.last_name) as user_name"),
            )->get();
            return response()->json($turns,200);
        } catch (\Exception $e) {
            \Log::debug($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
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
            $current_state = $turn->lastState()->value;
            if($current_state===1 || $current_state===2 ){
                return response()->json([
                    'message'=>'El estado del turno no es correcto para esta acción'
                ],400);
            } else {
                $state = new State();
                $state->value = 0;
                $state->turn()->associate($turn);
                $state->save();
            }
            DB::commit();
            return response()->json(['message'=>'Se cancelo el turno correctamente'],200);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::debug($e);
            return response()->json(['message' => $e->getMessage()], 400);
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
