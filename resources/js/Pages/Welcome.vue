<script setup>
import axios from "axios";
import { ref, watch, computed } from "vue";
import DaylightChart from "../Components/DaylightChart.vue";

/**
 * Location Search and Daylight Comparison Component
 *
 * Allows users to search for locations and compare daylight hours
 * throughout the year using the DaylightChart component.
 *
 * Daylight information is received from laravel backend using cities500 database (cities that have greater population than 500)
 */

// UI state management
const searchInput = ref("");
const searchSuggestions = ref([]);
const selectedLocations = ref([]);
const locationInputError = ref("");
const showSuggestions = ref(false);
const isLoading = ref(false);

// Debounce control
let debounceTimeout = null;
const DEBOUNCE_DELAY = 300;
const ERROR_DISPLAY_TIME = 5000;

/**
 * Adds a new location to the comparison chart
 *
 * @param {Object} location - Location object with name, latitude, and longitude
 */
const handleLocationSelect = async (location) => {
    // Reset error state
    locationInputError.value = "";

    // Check if location is already selected
    if (isLocationAlreadySelected(location)) {
        locationInputError.value = `${location.name} on jo valittuna`;
        return;
    }

    try {
        isLoading.value = true;

        // Fetch daylight data for the selected location
        const daylightData = await getDaylightData(
            location.latitude,
            location.longitude
        );

        // Add location with daylight data to selected locations
        selectedLocations.value.push({
            ...location,
            daylightData,
        });

        // Clear search input and suggestions
        searchInput.value = "";
        searchSuggestions.value = [];
    } catch (error) {
        console.error("Error adding location:", error);
        locationInputError.value = "Virhe lisättäessä sijaintia";
    } finally {
        isLoading.value = false;
    }
};

/**
 * Checks if a location is already in the selected locations list
 *
 * @param {Object} location - Location to check
 * @returns {boolean} True if location is already selected
 */
const isLocationAlreadySelected = (location) => {
    return selectedLocations.value.some(
        (existingLocation) =>
            existingLocation.latitude === location.latitude &&
            existingLocation.longitude === location.longitude
    );
};

/**
 * Removes a location from the comparison chart
 *
 * @param {number} index - Index of the location to remove
 */
const handleLocationRemove = (index) => {
    selectedLocations.value.splice(index, 1);
};

/**
 * Fetches daylight data for a specific location
 * Gets the data from Laravels own date_sun_info function
 *
 * @param {number} lat - Latitude
 * @param {number} lon - Longitude
 * @returns {Promise<Array>} Daylight data for the location
 */
const getDaylightData = async (lat, lon) => {
    try {
        const response = await axios.get("/api/daylightdata", {
            params: { lat, lon },
        });
        return response.data;
    } catch (error) {
        console.error("Error fetching daylight data:", error);
        throw new Error("Failed to fetch daylight data");
    }
};

/**
 * Searches for location suggestions based on user input
 *
 * @param {string} query - Search query
 */
const searchLocations = async (query) => {
    if (!query || query.length < 1) {
        searchSuggestions.value = [];
        return;
    }

    try {
        isLoading.value = true;
        const response = await axios.get("/api/search", {
            params: { query },
        });

        if (response.data) {
            searchSuggestions.value = response.data;
        }
    } catch (error) {
        console.error("Error fetching location suggestions:", error);
        searchSuggestions.value = [];
    } finally {
        isLoading.value = false;
    }
};

// Clear error message after timeout
watch(locationInputError, (newError) => {
    if (newError) {
        setTimeout(() => {
            locationInputError.value = "";
        }, ERROR_DISPLAY_TIME);
    }
});

// Debounced search for location suggestions
watch(searchInput, (newInput) => {
    // Clear any existing timeout
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }

    // Start new debounce timeout
    debounceTimeout = setTimeout(() => {
        searchLocations(newInput);
    }, DEBOUNCE_DELAY);
});

// Computed properties for UI state
const hasLocations = computed(() => selectedLocations.value.length > 0);
const showSuggestionsDropdown = computed(
    () => searchSuggestions.value.length > 0 && showSuggestions.value
);
</script>

<template>
    <div
        class="bg-gradient-to-b from-gray-400 to-gray-500 min-h-screen min-w-screen lg:p-4 flex"
    >
        <div
            class="max-w-5xl mx-auto my-auto w-full rounded-lg lg:shadow-md lg:border p-4 lg:p-8 flex flex-col gap-8 bg-gray-50 bg-opacity-80 drop-shadow-lg"
        >
            <!-- Header -->
            <header class="text-center text-xl">
                Päivien pituuksien vertailu
            </header>

            <!-- Search Section -->
            <div class="relative flex justify-center">
                <div class="relative w-full flex justify-center">
                    <!-- Search Input -->
                    <input
                        type="text"
                        v-model="searchInput"
                        @focus="showSuggestions = true"
                        @blur="showSuggestions = false"
                        @keydown.enter.prevent="
                            if (searchSuggestions.length > 0) {
                                handleLocationSelect(searchSuggestions[0]);
                            }
                        "
                        class="w-full max-w-md rounded-md border-gray-300 bg-gray-50 p-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Hae kaupunki..."
                    />

                    <!-- Loading Indicator -->
                    <div
                        v-if="isLoading"
                        class="absolute right-4 top-1/2 transform -translate-y-1/2"
                    >
                        <div
                            class="animate-spin h-4 w-4 border-2 border-blue-500 rounded-full border-t-transparent"
                        ></div>
                    </div>

                    <!-- Error Message -->
                    <span
                        v-if="locationInputError"
                        class="text-sm px-2 text-red-500 absolute"
                    >
                        {{ locationInputError }}
                    </span>
                </div>

                <!-- Suggestions Dropdown -->
                <div
                    v-if="showSuggestionsDropdown"
                    class="absolute z-10 w-full max-w-md mt-12 bg-white bg-opacity-90 backdrop-blur-md rounded-md shadow-lg max-h-60 overflow-y-auto"
                >
                    <ul>
                        <li
                            v-for="suggestion in searchSuggestions"
                            :key="`${suggestion.latitude}-${suggestion.longitude}`"
                            class="p-2 hover:bg-gray-100 cursor-pointer"
                            @mousedown="() => handleLocationSelect(suggestion)"
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

            <!-- Daylight Chart -->
            <DaylightChart
                v-if="hasLocations"
                :locations="selectedLocations"
                @removeLocation="handleLocationRemove"
            />

            <!-- Empty State -->
            <div v-else class="text-center text-gray-500 py-8">
                Etsi kaupunkeja vertaillaksesi päivien pituuksia
            </div>
        </div>
    </div>
</template>
