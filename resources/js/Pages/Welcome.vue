<script setup>
import axios from "axios";
import { ref, watch } from "vue";
import DaylightChart from "../Components/DaylightChart.vue";

const searchInput = ref("");
const searchSuggestions = ref([]);
const selectedLocations = ref([]);
const locationInputError = ref("");

// debounce, to handle call throttling
let timeout = null;

// Checks if location is new
// Fetches daylightdata for new location
// pushes the new location with daylightdata to selectedlocations array
const handleLocationSelect = async (location) => {
    locationInputError.value = "";

    // check for already matching location
    if (
        selectedLocations.value.some(
            (oldLoc) =>
                oldLoc.latitude === location.latitude &&
                oldLoc.longitude === location.longitude
        )
    ) {
        locationInputError.value = location.name + " on jo valittuna";
        return;
    }

    console.log("location lat, long: ", location.latitude, location.longitude);
    // get daylightdata
    const daylightData = await getDaylightData(
        location.latitude,
        location.longitude
    );

    location = { ...location, daylightData };

    selectedLocations.value.push(location);
    console.log(location);
};

const handleLocationRemove = (index) => {
    selectedLocations.value.splice(index, 1);
};

// get daylight data from backend
// returns only the received data
const getDaylightData = async (lat, lon) => {
    console.log(lat, lon);
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

// remove the error message after 5 seconds
watch(locationInputError, (newInput) => {
    setTimeout(() => {
        locationInputError.value = "";
    }, 5000);
});

// watcher for handling new suggestion queries
// watcher has 300ms debounce to throttle queries when user is typing fast and only makes query when user stops
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
            // Get suggestions and data from backend
            const response = await axios.get(`/api/search`, {
                params: {
                    query: newInput,
                },
            });

            // Process the results
            if (response.data) {
                console.log(response.data);
                searchSuggestions.value = response.data;
            }
        } catch (error) {
            console.error("Error fetching location suggestions:", error);
        }
        console.log("searchSuggestions: ", searchSuggestions.value);
    }, 300); // 300ms debounce
});
</script>

<template>
    <div
        class="bg-gradient-to-b from-white to-gray-50 w-dvw h-dvh p-4 flex flex-col justify-center"
    >
        <div
            class="max-w-5xl mx-auto w-full rounded-lg shadow-md border p-8 flex flex-grow-0 flex-col gap-8"
        >
            <header class="text-center text-2xl">Vertaa päivänvaloa</header>
            <!-- Search section -->
            <div class="relative flex justify-center">
                <div class="relative w-full flex justify-center">
                    <input
                        type="text"
                        class="w-full max-w-md rounded-md border-gray-300 p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        v-model="searchInput"
                        placeholder="Hae kaupunki..."
                    />
                    <span class="text-sm px-2 text-red-500 absolute">{{
                        locationInputError
                    }}</span>
                </div>

                <!-- Suggestions dropdown -->
                <div
                    v-if="searchSuggestions.length > 0"
                    class="absolute z-10 w-full max-w-md mt-12 bg-white rounded-md shadow-lg max-h-60 overflow-y-auto"
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
                            <div class="font-medium flex items-center gap-2">
                                {{ suggestion.name }}
                                <span class="text-xs text-gray-600">
                                    {{ suggestion.country }}
                                    {{ suggestion.region }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- grap for daytime -->

            <DaylightChart
                v-if="selectedLocations.length > 0"
                :locations="selectedLocations"
                @removeLocation="handleLocationRemove"
            />
        </div>
    </div>
</template>
