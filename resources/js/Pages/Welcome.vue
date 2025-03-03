<script setup>
import axios from "axios";
import { ref, watch } from "vue";
import DaylightChart from "../Components/DaylightChart.vue";

const searchInput = ref("");
const searchSuggestions = ref([]);
const selectedLocations = ref([]);

// debounce, to handle call throttling
let timeout = null;

const handleLocationSelect = async (location) => {
    const daylightData = await getDaylightData(location.lat, location.lon);
    location = { ...location, daylightData };

    selectedLocations.value.push(location);
    console.log(location);
};

const handleLocationRemove = (index) => {
    selectedLocations.value.splice(index, 1);
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
    <div
        class="bg-gradient-to-b from-white to-gray-50 w-dvw h-dvh p-4 flex flex-col justify-center"
    >
        <div
            class="max-w-5xl mx-auto w-full rounded-lg shadow-md border p-8 flex flex-grow-0 flex-col"
        >
            <!-- Search section -->
            <div class="relative flex justify-center">
                <input
                    type="text"
                    class="w-full max-w-md rounded-md border-gray-300 p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    v-model="searchInput"
                    placeholder="Hae sijainti (englanniksi).."
                />

                <!-- Suggestions dropdown -->
                <div
                    v-if="searchSuggestions.length > 0"
                    class="absolute z-10 w-full max-w-md mt-1 bg-white rounded-md shadow-lg max-h-60 overflow-y-auto"
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
            <!-- grap for daytime -->

            <DaylightChart
                :locations="selectedLocations"
                @removeLocation="handleLocationRemove"
            />
        </div>
    </div>
</template>
