<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryController extends Controller{

	public function index(){
		$countries = Country::all();
		return response()->json($countries);
	}
	
	public function create(Request $request){
		$request->validate([
			'name' => 'required|string|max:30',
		]);
		
		$country = Country::create([
			'name' => $request->input('name'),
		]);

		return response()->json($country, 201);
	}

	public function update(Request $request, $id){
		$country = Country::find($id);
		
		if(!$country){
			return response()->json(['message' => 'Country not found'], 404);
		}

		$request->validate([
			'name' => 'required|string|max:30',
		]);
		
		$country->update([
			'name' => $request->input('name'),
		]);
		
		return response()->json($country);
	}

	public function show($id){
		$country = Country::find($id);
		if(!$country){
			return response()->json(['message' => 'Country not found'], 404);
		}
		return response()->json($country);
	}

	public function delete($id){
		$country = Country::find($id);
		if(!$country){
			return response()->json(['message' => 'Country not found'], 404);
		}
		$country->delete();
		return response()->json(null, 204);
	}
}


