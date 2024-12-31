<?php

namespace App\Http\Controllers\API;

use App\Actions\Fortify\PasswordValidationRules;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use PasswordValidationRules;

    public function login(Request $request){
        try {
            // Validasi input
            $request->validate([
                'email' => 'email|required',
                'password' => 'required'
            ]);

            // Cek kredensial
            $credential = request(['email', 'password']);

            if(!Auth::attempt($credential)){
                return ResponseFormatter::error([
                    'message' => 'Unauthorized'
                ], 'Authentication failed', 500);
            }

            // check hash
            $user = User::where('email', $request->email)->first();
            if(!Hash::check($request->password, $user->password, [])){
                throw new Exception('Invalid credential');
            }

            // jika berhasil maka login
            $token_result = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $token_result,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Authenticated');

        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $err
            ], 'Authentication failed',500);
        }
    }

    public function register(Request $request){
        try {
            // Validasi input
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'email|string|unique:users|max:255|required',
                'password' => $this->passwordRules()
            ]);

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'address' => $request->address,
                'houseNumber' => $request->houseNumber,
                'phoneNumber' => $request->phoneNumber,
                'city' => $request->city,
                'password' => Hash::make($request->password),
            ]);

            // check hash
            $user = User::where('email', $request->email)->first();
            if(!Hash::check($request->password, $user->password, [])){
                throw new Exception('Invalid credential');
            }

            // jika berhasil maka login
            $token_result = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $token_result,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Authenticated');

        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $err
            ], 'Authentication failed',500);
        }
    }

    public function logout(Request $request){
        $token = $request->user()->currentAccessToken()->delete();

        return ResponseFormatter::success($token, 'Token revoked');
    }

    public function fetch(Request $request){
        return ResponseFormatter::success($request->user(), 'Fetch user successfully');
    }

    public function updateProfile(Request $request){
        $data = $request->all();

        $user = Auth::user();
        $user->update($data);

        return ResponseFormatter::success($user, 'Profile updated');
    }

    public function updatePhoto(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|max:2048'
        ]);

        if(!$validator){
            return ResponseFormatter::error([
                'error' => $validator->errors()
            ], 'Update photo fails', 401);
        }

        if($request->file('file')){
            $file = $request->file->store('assets/user', 'public');

            // simpan foto ke database
            $user = Auth::user();

            $user->profile_photo_path = $file;
            $user->update();

            return ResponseFormatter::success($file, 'Successfully uploaded');
        }
    }
}
