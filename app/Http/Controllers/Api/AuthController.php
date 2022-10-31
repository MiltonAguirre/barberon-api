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
use Illuminate\Support\Facades\Storage;
use Hash;

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
        $user->password = bcrypt($request->get('password'));
        $user->location()->associate($location);
        $user->dataUser()->associate($dataUser);
        $user->role()->associate($role);

        $tokenResult = $user->createToken('Personal Access Token');
        $user->last_conection = now();
        $user->save();
        $token = $tokenResult->token;
        if ($request->remember_me) {
            $token->expires_at = Carbon::now()->addWeeks(1);
        }
        $token->save();
        return response()->json([
            'user_id' => $user->id,
            'role_id' => $user->role->id,
            'first_name' => $user->dataUser->first_name,
            'profile_img' => $user->dataUser->profile_img,
            'message' => 'Successfully created user!',
            'token_type'   => 'Bearer',
            'access_token' => $tokenResult->accessToken,
            'expires_at'   => Carbon::parse($tokenResult->token->expires_at)
                                    ->toDateTimeString()
        ], 201);
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

          // $credentials = request(['email', 'password']);
          // if (!\Auth::guard('api')->check($credentials)) {
          //     return response()->json([
          //         'message' => 'Unauthorized'], 401);
          // }
          $user = User::where('email',$request->email)->first();
          if (!$user){
              return response()->json([
                  'message' => 'Unauthorized'], 401);
          }
          //BUG ! ! ! !
          // BUST BE Hash::check("password",bcrypt("password"))

          if (!Hash::check($request->password,$user->password)){
              return response()->json([
                  'message' => 'Unauthorized'], 401);
          }
          $tokenResult = $user->createToken('Personal Access Token');
          $user->last_conection = now();
          $user->save();
          $token = $tokenResult->token;
          if ($request->remember_me) {
              $token->expires_at = Carbon::now()->addWeeks(1);
          }
          $token->save();
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type'   => 'Bearer',
            'role_id'   => $user->role->id,
            'user_id'   => $user->id,
            'first_name'   => $user->dataUser->first_name,
            'profile_img'   => $user->dataUser->profile_img,
            'expires_at'   => Carbon::parse(
                $tokenResult->token->expires_at)
                    ->toDateTimeString(),
        ]);
    }

    public function logout(Request $request)
    {

        $request->user()->token()->revoke();
        return response()->json(['message' =>
            'Successfully logged out']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
