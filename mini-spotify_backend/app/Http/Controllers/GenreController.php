<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GenreController extends Controller{

	public function index(){
		$genres = Genre::all();
		return response()->json($genres);
	}

	public function usedInSongs()
    {
        $genres = Genre::query()
            ->whereHas('songs')
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (?string $name) => filled($name))
            ->values();

        return response()->json($genres);
    }

	public function create(Request $request){
		$request->validate([
			'name' => 'required|string|max:255',
		]);

		$genre = Genre::create([
			'name' => $request->input('name'),
		]);
		return response()->json($genre, 201);
	}

	public function update(Request $request,$id){
		$genre = Genre::find($id);

		if(!$genre){
			return response()->json(['message' => 'Genre not found'], 404);
		}

		$request->validate([
			'name' => 'required|string|max:255',
		]);

		$genre->update([
			'name' => $request->input('name'),
		]);

		return response()->json($genre);
	}

	public function delete($id){
		$genre = Genre::find($id);
		if(!$genre){
			return response()->json(['message' => 'Genre not found'], 404);
		}
		$genre->delete();
		return response()->json(null, 204);
	}
	
	public function show($id){
		$genre = Genre::find($id);
		if(!$genre){
			return response()->json(['message' => 'Genre not found'], 404);
		}
		return response()->json($genre);
	}
	
}


