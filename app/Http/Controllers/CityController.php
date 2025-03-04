<?php

namespace App\Http\Controllers;

use App\Services\CityService;
use Illuminate\Http\Request;

class CityController extends Controller
{
    protected $cityService;

    public function __construct(CityService $cityService)
    {
        $this->cityService = $cityService;
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $limit = $request->input('limit', 5);

        $results = $this->cityService->search($query, $limit);

        return response()->json($results);
    }
}
