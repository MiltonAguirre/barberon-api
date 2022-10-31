<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barbershop;
use App\Models\Location;
use App\Models\Product;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Validator;

class BarbershopController extends Controller
{
  public function __construct()
  {
      $this->middleware('auth:sanctum');
  }

  public function getMyBarbershop()
  {
    $user = auth('api')->user();
    if(!$user || !$user->isBarber())
    abort(401);
    if($user->barbershop){

        $response = $user->barbershop->getData();
        $products = $user->barbershop->products;
        foreach($products as $product){
          $product['images'] = $product->images;
        }
        $response['products'] = $products;
        $response['schedules'] = $user->barbershop->getSchedules();

        return response()->json($response,200);
    }else{

      return response()->json('',200);
    }
  }

  public function getBarbershop($barbershop_id)
  {
    $barbershop = Barbershop::find($barbershop_id);
    if(!$barbershop){
      return response()->json([
        'message'=>'Barbershop not found'
      ],400);
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

  }

  public function getBarbershops(Request $request)
  {
    $user = auth('api')->user();
    $country = $user->location->country;
    $zip = $user->location->zip;

    $barbershops = Barbershop::all();
    $response = [];
    foreach($barbershops as $barber){
      $res = [];
      $res['data'] = $barber->getData();
      $res['schedules'] = $barber->getSchedules();
      $response[] = $res;
    }

    return response()->json($response,200);
  }

  public function getProducts($barbershop_id)
  {
    $products = Product::where('barbershop_id', $barbershop_id)->get();
    if(count($products)){
      foreach($products as $product){
        $product['images'] = $product->images;
      }
      return response()->json($products,200);
    }
    else{
      return response()->json([],200);
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

    $user = auth('api')->user();
    if(!$user || !$user->isBarber() || !$user->barbershop) abort(401);
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
      abort(400);
    }

    $products = $user->barbershop->products;
    foreach($products as $product){
      $product['images'] = $product->images;
    }

    return response()->json([
      'products'=>$products,
      'message'=>'Se agregó un nuevo producto a su barbería'
    ],200);
  }
  public function destroyProducts($id)
  {
    $user = auth('api')->user();
    if(!$user || !$user->isBarber() || !$user->barbershop) abort(401);
    $product = $user->barbershop->products()->find($id);

    if(!$product) abort(400);

    $product->delete();

    return response()->json([
      'message'=>'El producto fue eliminado de su barbería'
    ],200);
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
    $user = auth('api')->user();
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
    $response['schedules'] = $user->barbershop->getSchedules();
    return response()->json($response,200);

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
    $user = auth('api')->user();

    if(!$user || !$user->isBarber() || !$user->barbershop)
    return response()->json([
        'message'=>'Usted no tiene los permisos para esta acción'
    ],400);
    $turns = $user->barbershop->turns->where('created_at', '>=', Carbon::now()->subYears(1));
    foreach($turns as $turn ){
        $turn["ending"] = $turn->getEnding();
        $product = $turn->product;
        $product['images'] =$product->images;
        $turn["product"] = $product;
        $turn["data_user"] = $turn->user->dataUser;
    }
    return response()->json($turns,200);
  }

  public function acceptTurn($turn_id)
  {
    $user = auth('api')->user();
    if(!$user || !$user->isBarber() || !$user->barbershop)
    return response()->json([
      'message'=>'Usted no tiene los permisos para esta acción'
    ],400);
    $turn = $user->barbershop->turns()->findOrFail($turn_id);
    if($user->barbershop->id !== $turn->barbershop->id)
    return response()->json([
      'message'=>'Usted no tiene los permisos para esta acción'
    ],400);
    if($turn->state===1){
      $turn->state=2;
      $turn->save();
    }else{
      return response()->json([
        'message'=>'El estado del turno no es correcto para esta acción'
      ],400);
    }
    $turns = $user->barbershop->turns->where('created_at', '>=', Carbon::now()->subYears(1));
    foreach($turns as $turn ){
        $turn["ending"] = $turn->getEnding();
        $product = $turn->product;
        $product['images'] =$product->images;
        $turn["product"] = $product;
        $turn["data_user"] = $turn->user->dataUser;
    }
    return response()->json($turns,200);
   }

  public function cancelTurn($turn_id)
  {
    $user = auth('api')->user();
    if(!$user || !$user->isBarber() || !$user->barbershop)
      return response()->json([
          'message'=>'Usted no tiene los permisos para esta acción'
      ],400);
    $turn = $user->barbershop->turns()->findOrFail($turn_id);
    if($user->barbershop->id !== $turn->barbershop->id)
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
    $turns = $user->barbershop->turns->where('created_at', '>=', Carbon::now()->subYears(1));
    foreach($turns as $turn ){
        $turn["ending"] = $turn->getEnding();
        $product = $turn->product;
        $product['images'] =$product->images;
        $turn["product"] = $product;
        $turn["data_user"] = $turn->user->dataUser;
    }
    return response()->json($turns,200);
  }

//************PENDING INTERFACE************
  public function update(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'phone' => 'required|min:5|max:20',
      'address' => 'required|string|min:3|max:255',
      'city' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      'state' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
      // 'image_path' => 'image'
    ]);
    if($validator->fails()){
      return response()->json(['errors' => $validator->errors()]);
    }
    $user = auth('api')->user();
    if(!$user || !$user->isBarber()) abort(401);

    $user->barbershop->location->address =$request->address;
    $user->barbershop->location->city = $request->city;
    $user->barbershop->location->state = $request->state;
    $user->barbershop->name = $request->name;
    $user->barbershop->phone = $request->phone;

    //Upload image
    // $image_path = $request->file('image_path');
    // if ($image_path) {
    //   //delete image for be replace
    //   if($user->barbershop->image){
    //     Storage::disk('barbershops')->delete($user->barbershop->image);
    //   }
    //   $image_path_name = time().$image_path->getClientOriginalName();
    //   Storage::disk('barbershops')->put($image_path_name, File::get($image_path));
    //   $user->barbershop->image = $image_path_name;
    // }
    $user->barbershop->location->save();
    $user->barbershop->save();

    return response()->json(['message'=>'Se ha actualizado su barbería correctamente'],200);

  }

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
    $user = auth('api')->user();
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
    $user = auth('api')->user();
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
