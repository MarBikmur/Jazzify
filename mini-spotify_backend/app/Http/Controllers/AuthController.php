<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller{

	public function register(Request $request){
		$request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|email|max:255|unique:users,email',
			'password' => 'required|string|min:8',
		]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
	}

    public function login(Request $request){
        $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);
    
        $user = User::where('email', $request->input('email'))->first();

        if(!$user || !Hash::check($request->input('password'), $user->password)){
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ]);
    }

    public function logOutEverywhere(Request $request){
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $user->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function logOutCurrent(Request $request){
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if (!$user->currentAccessToken()){
            return response()->json(['message' => 'No active current token'], 401);
        }

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function updateProfile(Request $request){
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->uid, 'uid')],
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg|max:10240',
            'remove_avatar' => 'nullable|boolean',
        ]);

        $user->fill($request->only(['name', 'email']));
        
        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return response()->json($user);
    }

    public function changePassword(Request $request){
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|different:current_password',
        ]);

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }
}
