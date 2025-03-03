<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MainController extends Controller
{
    public function daylightdata(Request $request)
    {


        // validate inputted data
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180'
        ]);


        $lat = $validated['lat'];
        $lon = $validated['lon'];



        // prep daylight info
        $daylightInfo = [];
        $daylightInfo = array_fill(0, 365, null);

        // Date used to loop through every day of current year
        $currentYear = date('Y');
        $startOfYear = mktime(0, 0, 0, 1, 1, $currentYear);

        for ($i = 0; $i < 365; $i++) {

            $timestamp = strtotime("+$i days", $startOfYear);
            $daylightInfo[$i] = date_sun_info($timestamp, $lat, $lon);

            $data = date_sun_info(
                $timestamp,
                $lat,
                $lon,
            );

            $daylightInfo = [...$daylightInfo, $data];
        }





        return response()->json($daylightInfo, 201);
    }
}
