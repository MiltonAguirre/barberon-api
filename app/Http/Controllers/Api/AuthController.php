<?php

namespace App\Http\Controllers\Api;

use Validator;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DataUser;
use App\Models\Location;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|min:6|max:255|unique:users',
            'zip'=>'required|numeric|min:1',
            'country' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
            'first_name' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
            'last_name' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
            'role'=>'required|numeric|min:1|max:3',
            'password' => 'required|string|min:6|confirmed',
            //'username' => 'required|string|min:3|max:255|unique:users|alpha_dash',
            //'phone' => 'required|min:5|max:20',
            //'address' => 'required|string|min:3|max:255',
            //'city' => 'required|string|min:3|max:255|regex:/^[\pL\s]+$/u',
            //'state' => 'string|min:3|max:255|regex:/^[\pL\s]+$/u',
        ]);
        if ($validator->fails()){
            return response()->json(['errors' => $validator->errors()]);
        }
        try {
            DB::beginTransaction();
            $location = Location::create([
            'zip' => $request->get('zip'),
            'country' => $request->get('country'),
            ]);
            $dataUser = DataUser::create([
            'first_name' => $request->get('first_name'),
            'last_name' => $request->get('last_name'),
            ]);
            $role = Role::find($request->get('role'));
    
            $user = new User();
            $user->email = $request->get('email');
            $user->password = Hash::make($request->get('password'));
            $user->location()->associate($location);
            $user->dataUser()->associate($dataUser);
            $user->role()->associate($role);
            $user->last_conection = now();
            $user->save();
    
            $token = $user->createToken('Personal Access Token');
    
            DB::commit();
            return response()->json([
                'user_id' => $user->id,
                'role_id' => $user->role->id,
                'first_name' => $user->dataUser->first_name,
                'profile_img' => $user->dataUser->profile_img,
                'token_type'   => 'Bearer',
                'access_token' => $token->plainTextToken,
            ], 201);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollback();
            \Log::debug($th);
            return response()->json(['message' =>'Error creating user'], 400);
            
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'email'       => 'required|string',
            'password'    => 'required|string',
            'remember_me' => 'boolean',
        ]);
        if ($validator->fails()){
            return response()->json(['errors' => $validator->errors()]);
        }
        $user = User::where('email',$request->email)->first();
        if (!$user){
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!Hash::check($request->password,$user->password)){
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $token = $user->createToken('Personal Access Token');
        
        $user->last_conection = now();
        $user->save();
        return response()->json([
            'user_id'   => $user->id,
            'role_id'   => $user->role->id,
            'first_name'   => $user->dataUser->first_name,
            'profile_img'   => $user->dataUser->profile_img,
            'token_type'   => 'Bearer',
            'access_token' => $token->plainTextToken,
        ], 200);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        return response()->json(['message' =>'Successfully logged out']);
    }

    public function user()
    {
        return response()->json(auth()->user());
    }
}
