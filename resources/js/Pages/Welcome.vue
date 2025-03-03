<script setup>
import axios from "axios";
import { ref, watch } from "vue";

const searchInput = ref("");
const searchSuggestions = ref([]);
const selectedLocations = ref([]);

// debounce, to handle call throttling
let timeout = null;

const handleLocationSelect = async (location) => {
    const daylightData = await getDaylightData(location.lat, location.lon);
    location = { ...location, daylightData };

    selectedLocations.push(location);
    console.log(location);
};
const getDaylightData = async (lat, lon) => {
    try {
        const response = await axios.get("/api/daylightdata", {
            params: {
                lat: lat,
                lon: lon,
            },
        });
        return response.data;
    } catch (error) {
        console.log("error getting daylightdata: ", error);
    }
};

watch(searchInput, (newInput) => {
    if (timeout) clearTimeout(timeout);

    // Clear suggestions if input is empty
    if (!newInput || newInput.length < 1) {
        searchSuggestions.value = [];
        return;
    }

    // Set a new timeout to delay the API call
    timeout = setTimeout(async () => {
        try {
            // Get suggestions and data from photon komoot api
            const response = await axios.get(`https://photon.komoot.io/api/`, {
                params: {
                    q: newInput,
                    limit: 5,
                    lang: "en",
                },
            });

            // Process the results
            if (response.data && response.data.features) {
                searchSuggestions.value = response.data.features.map(
                    (feature) => ({
                        name: feature.properties.name,
                        city:
                            feature.properties.city || feature.properties.name,
                        state: feature.properties.state,
                        country: feature.properties.country,
                        lat: feature.geometry.coordinates[1],
                        lon: feature.geometry.coordinates[0],
                        fullName: [
                            feature.properties.name,
                            feature.properties.state,
                            feature.properties.country,
                        ]
                            .filter(Boolean)
                            .join(", "),
                    })
                );
            }
        } catch (error) {
            console.error("Error fetching location suggestions:", error);
        }
    }, 300); // 300ms debounce
});
</script>

<template>
    <div class="from-gray-800 to-gray-700 w-dvw h-dvh p-4">
        <div class="my-auto max-w-md mx-auto">
            <!-- Search section -->
            <div class="relative">
                <input
                    type="text"
                    class="w-full rounded-md border-gray-300 p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    v-model="searchInput"
                    placeholder="Hae sijainti (englanniksi).."
                />

                <!-- Suggestions dropdown -->
                <div
                    v-if="searchSuggestions.length > 0"
                    class="absolute z-10 w-full mt-1 bg-white rounded-md shadow-lg max-h-60 overflow-y-auto"
                >
                    <ul>
                        <li
                            v-for="suggestion in searchSuggestions"
                            :key="`${suggestion.lat}-${suggestion.lon}`"
                            class="p-2 hover:bg-gray-100 cursor-pointer"
                            @click="
                                () => {
                                    // Clear search fields
                                    searchInput = '';
                                    searchSuggestions = [];
                                    // place location to selectedLocations array
                                    handleLocationSelect(suggestion);
                                }
                            "
                        >
                            <div class="font-medium">{{ suggestion.name }}</div>
                            <div class="text-sm text-gray-600">
                                {{ suggestion.fullName }}
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- Table for selected locations -->
            <div>
                <table class="table-auto mt-8 w-full">
                    <thead class="bg-gray-300">
                        <tr>
                            <th>Nimi</th>
                            <th>Maa</th>
                            <th>Kaupunki</th>

                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="location in selectedLocations"
                            :key="location.lat"
                        >
                            <th>
                                {{ location.name }}
                            </th>

                            <th>
                                {{ location.country }}
                            </th>
                            <th>{{ location.city }}</th>

                            <th>Poista</th>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- grap for daytime -->
        </div>
    </div>
</template>
