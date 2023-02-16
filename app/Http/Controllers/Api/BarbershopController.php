<?php

namespace App\Http\Controllers\Api;
use App\Events\StoreBarbershopEvent;
use App\Http\Controllers\Controller;
use App\Models\Barbershop;
use App\Models\ImageProduct;
use App\Models\Location;
use App\Models\Product;
use App\Models\Schedule;
use App\Models\State;
use App\Models\TokenDevice;
use App\Services\BarbershopService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Validator;

class BarbershopController extends Controller
{
  use BarbershopService;


  public function getBarbershops()
  {
    try {
      $barbershops = $eis->serviceGetBarbershops();
      return response()->json($barbershops,200);
    }catch (\Exception $e) {
      \Log::debug($e->getMessage());
      return response()->json(['message' => $e->getMessage()], 400);
    }
  }

  public function getMyBarbershop()
  {
    try {
      $user = auth()->user();
      if(!$user->barbershop){
        return response()->json(['message'=>'Error, ud. no posee una barbería'],400);
      }else{
        $response = $user->barbershop->getData();
        $products = $user->barbershop->products;
        foreach($products as $product){
          $product['images'] = $product->images;
        }
        $response['products'] = $products;
        $response['schedules'] = $user->barbershop->getSchedules();
        return response()->json($response,200);
      }
    }catch (\Exception $e) {
      \Log::debug($e->getMessage());
      return response()->json(['message' => $e->getMessage()], 400);
    }
  }

  public function getBarbershop($barbershop_id)
  {
    try {
      $barbershop = Barbershop::find($barbershop_id);
      if(!$barbershop){
        return response()->json(['message'=>'Barbería no encontrada'],400);
      }else{
        $response = $barbershop->getData();
        $products = $barbershop->products;
        foreach($products as $product){
          $product['images'] = $product->images;
        }
        $response['products'] = $products;
        $response['schedules'] = $barbershop->getSchedules();
    
        return response()->json($response,200);
      }
    }catch (\Exception $e) {
      \Log::debug($e->getMessage());
      return response()->json(['message' => $e->getMessage()], 400);
    }

  }
  public function getProducts($barbershop_id)
  {
    try {
      $products = Product::where('barbershop_id', $barbershop_id)->get();
      if(!count($products)){
        return response()->json([],200);
      } else{
        foreach($products as $product){
          $product['images'] = $product->images;
        }
        return response()->json($products,200);
      }
    } catch (\Exception $e) {
      \Log::debug($e->getMessage());
      return response()->json(['message' => $e->getMessage()], 400);
    }
  }

  public function storeProducts(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'description' => 'required|string|min:10|max:255|regex:/^[\pL\s]+$/u',
      'price' => 'required|numeric',
      'hours' => 'required|integer|min:0|max:24',
      'minutes' => 'required|integer|min:0|max:30',
      'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048'
    ]);

    if ($validator->fails()){
      return response()->json(['errors' => $validator->errors()],400);
    }
    try {
      DB::beginTransaction();
      $user = auth()->user();
      if(!$user->barbershop) {
        return response()->json("Error, you dont have a barbershop", 400);
      }
      $product = new Product;
      $product->name = $request->name;
      $product->description = $request->description;
      $product->price = $request->price;
      $product->hours = $request->hours;
      $product->minutes = $request->minutes;

      $product->barbershop()->associate($user->barbershop);
      $product->save();
      //Upload image
      $image_path = $request->file("photo");

      if ($image_path) {
        $image_path_name = "products/".time().$image_path->getClientOriginalName();
        Storage::disk('public')->put($image_path_name, file_get_contents($image_path));
        $image= new ImageProduct();
        $image->path = $image_path_name;
        $image->product()->associate($product);
        $image->save();
      }else{
        return response()->json("Error, you need add a picture", 400);
      }

      $products = $user->barbershop->products;
      foreach($products as $product){
        $product['images'] = $product->images;
      }
      DB::commit();

      return response()->json([
        'products'=>$products,
        'message'=>'Se agregó un nuevo producto a su barbería'
      ],200);
    } catch (\Exception $e) {
      DB::rollback();
      \Log::debug($e->getMessage());
      return response()->json(['message' =>'Error adding product'], 400);
    }
  }
  public function destroyProducts($id)
  {
    try {
      DB::beginTransaction();
      $user = auth()->user();
      if(!$user || !$user->isBarber() || !$user->barbershop) 
      return response()->json(['message' =>'Error deleting product'], 400);

      $product = $user->barbershop->products()->findOrFail($id);
    
      $product->delete();
      DB::commit();
      return response()->json([
        'message'=>'El producto fue eliminado de su barbería'
      ],200);
    } catch (\Exception $e) {
      DB::rollback();
      \Log::debug($e->getMessage());
      return response()->json(['message' =>'Error deleting product'], 400);
    }
  }
  public function store(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'phone' => 'required|min:5|max:20',
      'zip'=>'required|numeric|min:1',
      'country' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'address' => 'required|string|min:3|max:255',
      'city' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'state' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'days' => 'required|array',
      'opens' => 'required|array',
      'closes' => 'required|array',
      // 'image_path' => 'image'
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    }
    try {
      DB::beginTransaction();
      $user = auth()->user();
      if(!$user || !$user->isBarber() || $user->barbershop) abort(401);
      $location = Location::create([
        'zip' => $request->zip,
        'country' => $request->country,
        'address' => $request->address,
        'city' => $request->city,
        'state' => $request->state,
      ]);
      $barbershop = new Barbershop;
      $barbershop->name = $request->name;
      $barbershop->phone = $request->phone;
      $barbershop->days = implode(",",$request->days);
      $opens = $request->opens;
      $closes = $request->closes;
      $barbershop->location()->associate($location);
      $barbershop->user()->associate($user);
      $barbershop->save();
      foreach ($opens as $key => $value) {
          $schedule= new Schedule;
          $schedule->open = $value;
          $schedule->close = $closes[$key];
          $schedule->barbershop()->associate($barbershop);
          $schedule->save();
      }
      $response = $barbershop->getData();
      $products = $barbershop->products;
      foreach($products as $product){
        $product['images'] = $product->images;
      }
      $response['products'] = $products;
      $response['schedules'] = $barbershop->getSchedules();
      event(new StoreBarbershopEvent);
      DB::commit();
      return response()->json($response,200);

    } catch (\Exception $e) {
      DB::rollback();
      \Log::debug($e->getMessage());
      return response()->json(['message' =>'Error creating barbershop'], 400);
    }
    /*Upload image
     $image_path = $request->file('image_path');
     if ($image_path) {
       //delete image for be replace
       if($barbershop->image){
         Storage::disk('barbershops')->delete($barbershop->image);
       }
       $image_path_name = time().$image_path->getClientOriginalName();
       Storage::disk('barbershops')->put($image_path_name, File::get($image_path));
       $barbershop->image = $image_path_name;
     }*/

  }

  public function getTurns()
  {
    try {		
      $user = auth()->user();
      if(!$user->barbershop){
        return response()->json([
          'message'=>'Usted no tiene los permisos para esta acción'
        ],400);
      }
      $turns = $user->barbershop->turns()->join('products', 'turns.product_id', 'products.id')
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
      foreach ($turns as $turn) {
        $turn["state"] = $turn->lastState()->name();
      }
      return response()->json($turns,200);
    } catch (\Exception $e) {
      \Log::debug($e);
      return response()->json(['message' =>'Error, turns not found'], 400);
    }
  }

  public function acceptTurn(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'turn_id' => 'required|numeric|min:1',
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    } 
    try {
      DB::beginTransaction();
      $user = auth()->user();
      if(!$user->barbershop){
        return response()->json([
          'message'=>'Usted no tiene los permisos para esta acción'
        ],400);
      }
      $turn = $user->barbershop->turns()->findOrFail($request->turn_id);
      if($turn->lastState()->value!==1){
        return response()->json([
          'message'=>'El estado del turno no es correcto para esta acción'
        ],400);
      }
      $state = State::create(array_merge($request->all(), ['value'=>2]));
      DB::commit();
      return response()->json(["message"=>"Turno aceptado"],200);
    } catch (\Exception $e) {
      DB::rollback();
      \Log::debug($e);
      return response()->json(['message' =>'Error, turns not found'], 400);
    }
   }

  public function cancelTurn(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'turn_id' => 'required|numeric|min:1',
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    }
    try {
      DB::beginTransaction();
      $user = auth()->user();
      if(!$user->barbershop){
        return response()->json([
            'message'=>'Usted no tiene los permisos para esta acción'
        ],400);
      }    
      $turn = $user->barbershop->turns()->findOrFail($request->turn_id);
      if(!$turn->lastState()->value){
        return response()->json([
          'message'=>'El estado del turno no es correcto para esta acción'
        ],400);
      }
      $state = State::create(array_merge($request->all(), ['value'=>0]));
      DB::commit();
      return response()->json(["message"=>"Turno cancelado"],200);
    } catch (\Exception $e) {
      DB::rollback();
      \Log::debug($e);
      return response()->json(['message' =>'Error, turns not found'], 400);
    }
  }
  function showTurn($turn_id)
  {
      try {
        $turn = Turn::findOrFail($turn_id);
        $turn['barbershop'] = $turn->barbershop->getData();
        $turn['product'] = $turn->product;
        $turn['user'] = $turn->user->getData();
        return response()->json($response, 200);
      } catch (\Exception $e) {
        \Log::debug($e);
        return response()->json(['message' =>'Error, turn not found'], 400);
      }
  }

//************PENDING INTERFACE************
  // public function update(Request $request)
  // {
  //   $validator = Validator::make($request->all(), [
  //     'name' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
  //     'phone' => 'required|min:5|max:20',
  //     'address' => 'required|string|min:3|max:255',
  //     'city' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
  //     'state' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
  //     // 'image_path' => 'image'
  //   ]);
  //   if($validator->fails()){
  //     return response()->json(['errors' => $validator->errors()]);
  //   }
  //   $user = auth()->user();
  //   if(!$user || !$user->isBarber()) abort(401);

  //   $user->barbershop->location->address =$request->address;
  //   $user->barbershop->location->city = $request->city;
  //   $user->barbershop->location->state = $request->state;
  //   $user->barbershop->name = $request->name;
  //   $user->barbershop->phone = $request->phone;

  //   //Upload image
  //   // $image_path = $request->file('image_path');
  //   // if ($image_path) {
  //   //   //delete image for be replace
  //   //   if($user->barbershop->image){
  //   //     Storage::disk('barbershops')->delete($user->barbershop->image);
  //   //   }
  //   //   $image_path_name = time().$image_path->getClientOriginalName();
  //   //   Storage::disk('barbershops')->put($image_path_name, File::get($image_path));
  //   //   $user->barbershop->image = $image_path_name;
  //   // }
  //   $user->barbershop->location->save();
  //   $user->barbershop->save();

  //   return response()->json(['message'=>'Se ha actualizado su barbería correctamente'],200);

  // }

/*
************IN CONSTRUCTION************
  public function storeSchedule(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'days' => 'required|array',
      'hours' => 'required|array',
      'minutes' => 'required|array',
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    }
    $user = auth()->user();
    if( !$user || !$user->isBarber() || !$user->barbershop ) abort(401);

    $user->barbershop->days =   json_encode($request->days);
    $user->barbershop->hours =   json_encode($request->hours);
    $user->barbershop->minutes =   json_encode($request->minutes);

    $user->barbershop->save();

    return response()->json($schedule,200);

  }
  public function uploadSchedule(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'days' => 'required|array',
      'open' => 'required|array',
      'close' => 'required|array',
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    }
    $user = auth()->user();
    if( !$user || !$user->isBarber() || !$user->barbershop ) abort(401);
    $days = json_encode($request->days);
    $open = json_encode($request->open);
    $close = json_encode($request->close);

    $schedule = $user->barbershop->schedule;
    $schedule->days = $days;
    $schedule->open = $open;
    $schedule->close = $close;

    $schedule->save();

    return response()->json($schedule,200);

  }

  public function getSchedules()
  {
    $validator = Validator::make($request->all(), [
      'barbershop_id' => 'required|numeric',
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    }
    $barbershop = Barbershop::find($request->barbershop_id);
    $schedules = $barbershop->schedule ? $barbershop->schedule : [];
    return response()->json($schedules, 200);

  }

  public function getImage($filename)
  {
    $file = Storage::disk('barbershops')->get($filename);
    return new Response($file,200);
  }
************************************
  */
}
