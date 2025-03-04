<?php

namespace App\Services;

use App\Models\City;

class CityService
{

    public function search($query, $limit = 5)
    {
        // Return empty array if query is empty or under 2 characters
        if (empty($query) || strlen($query) < 2) return [];

        // Get cities where query matches parts of name or region, use the set limit
        $cities = City::where("name", "LIKE", $query . "%")
            ->orderByRaw("CASE WHEN country = 'FI' THEN 1 ELSE 2 END") // Prioritize FI
            ->orderBy('name') // Sort by name
            ->limit($limit) // limit to set limit count
            ->get();
        return $cities;
    }
}
