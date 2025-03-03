<script setup>
import * as d3 from "d3";
import { ref, onMounted, watch, computed } from "vue";
// Define emit for event communication with parent component
const emit = defineEmits(["removeLocation"]);

const props = defineProps({
    locations: {
        type: Array,
        required: true,
    },
});

// Create a ref for the chart container
const chartRef = ref(null);

// Process the data for visualization
const processedData = computed(() => {
    if (!props.locations || props.locations.length === 0) return [];

    return (
        props.locations
            .map((location) => {
                if (!location.daylightData || !location.daylightData.length)
                    return {
                        name: location.name,
                        value: [],
                    };

                // reformat the data for chart to read
                return {
                    name: location.name,
                    value: location.daylightData.map((day) => {
                        // make sure date is correctly formatted
                        const date =
                            day.date instanceof Date
                                ? day.date
                                : new Date(day.date);

                        let daylightHours;

                        // Calculate daylight hours: sunset - sunrise (in hours)
                        if (day.sunrise === true && day.sunset === true) {
                            daylightHours = 24;
                        } else {
                            const sunriseTime = new Date(day.sunrise * 1000);
                            const sunsetTime = new Date(day.sunset * 1000);
                            const daylightMilliseconds =
                                sunsetTime - sunriseTime;
                            daylightHours =
                                daylightMilliseconds / (1000 * 60 * 60);
                        }

                        return {
                            date: date,
                            hours: daylightHours,
                            sunrise:
                                day.sunrise === true
                                    ? null
                                    : new Date(day.sunrise * 1000),
                            sunset:
                                day.sunset === true
                                    ? null
                                    : new Date(day.sunset * 1000),
                            day: day,
                        };
                    }),
                };
            })
            //filter away empty locations, if these appear
            .filter((location) => location.value.length > 0)
    );
});

// Chart dimensions
let width = 800;
const height = 400;
const margin = { top: 50, right: 50, bottom: 50, left: 50 };

// Color scale setup for multiple locations
const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

// Function to create or update the chart
const createChart = () => {
    if (!chartRef.value) {
        console.error("Chart container reference is null");
        return;
    }

    // Clear previous chart if it exists
    d3.select(chartRef.value).selectAll("*").remove();

    // Create SVG
    const svg = d3
        .select(chartRef.value)
        .append("svg")
        .attr("width", width)
        .attr("height", height);

    // Get dates from all locations ( Should be indendtical with 365 dates)
    const allDates = processedData.value.flatMap((location) =>
        location.value.map((d) => d.date)
    );

    //Get date range
    const dateExtent = d3.extent(allDates);

    //get max hours across all locations
    const maxHours = d3.max(processedData.value, (location) =>
        d3.max(location.value, (d) => d.hours)
    );

    // Create scales
    const xScale = d3
        .scaleTime()
        .domain(dateExtent)
        .range([margin.left, width - margin.right]);

    const yScale = d3
        .scaleLinear()
        .domain([0, Math.min(24, maxHours || 24)])
        .nice()
        .range([height - margin.bottom, margin.top]);

    // Create axes (generates all ticks labes and lines)
    const xAxis = d3.axisBottom(xScale).tickFormat(d3.timeFormat("%b")); // Show month abbreviations

    svg.append("g")
        .attr("transform", `translate(0, ${height - 50})`)
        .call(xAxis);

    // Create and add y-axis
    const yAxis = d3.axisLeft(yScale).tickFormat((d) => `${d}h`);

    svg.append("g").attr("transform", "translate(50, 0)").call(yAxis);

    // Create line generator
    const line = d3
        .line()
        .x((d) => xScale(d.date))
        .y((d) => yScale(d.hours));

    // Add the line path
    processedData.value.forEach((location, index) => {
        svg.append("path")
            .datum(location.value)
            .attr("fill", "none")
            .attr("stroke", colorScale(index))
            .attr("stroke-width", 2)
            .attr("d", line);
    });

    // Legend for differienting the locations
    const legend = svg
        .append("g")
        .attr("class", "legend")
        .attr(
            "transform",
            `translate(${width - margin.right - 150}, ${margin.top})`
        );

    processedData.value.forEach((location, index) => {
        const legendRow = legend
            .append("g")
            .attr("transform", `translate(0, ${index * 20})`);

        legendRow
            .append("rect")
            .attr("width", 10)
            .attr("height", 10)
            .attr("fill", colorScale(index));

        legendRow
            .append("text")
            .attr("x", 15)
            .attr("y", 10)
            .text(location.name)
            .style("font-size", "12px");

        // Add delete button
        legendRow
            .append("text")
            .attr("x", 120)
            .attr("y", 10)
            .attr("class", "delete-btn")
            .text("×")
            .style("font-size", "16px")
            .style("cursor", "pointer")
            .style("font-weight", "bold")
            .style("fill", "red")
            .on("click", () => {
                // Find the index in the original locations array
                const locationIndex = props.locations.findIndex(
                    (loc) => loc.name === location.name
                );
                if (locationIndex !== -1) {
                    // Emit an event to parent component to update locations
                    emit("removeLocation", locationIndex);
                }
            });
    });

    // Add title
    svg.append("text")
        .attr("x", width / 2)
        .attr("y", margin.top / 2)
        .attr("text-anchor", "middle")
        .style("font-size", "16px")
        .text("Päivänvalon pituuksien vertaus");

    // Add axis labels
    svg.append("text")
        .attr("x", width / 2)
        .attr("y", height - 10)
        .attr("text-anchor", "middle")
        .text("Kuukausi");

    svg.append("text")
        .attr("transform", "rotate(-90)")
        .attr("x", -height / 2)
        .attr("y", margin.left / 3)
        .attr("text-anchor", "middle")
        .text("Tuntia päivänvaloa");
};

// Create chart when component is mounted
onMounted(() => {
    if (props.locations.length > 0) {
        createChart();
    }

    // Make the chart responsive
    const resizeObserver = new ResizeObserver((entries) => {
        if (entries[0]) {
            width = entries[0].contentRect.width;
            if (processedData.value.length > 0) {
                createChart();
            }
        }
    });

    if (chartRef.value) {
        resizeObserver.observe(chartRef.value);
    }
});

// Watch for changes in locations prop
watch(
    () => props.locations,
    (newLocations) => {
        console.log("Locations updated:", newLocations);
        if (newLocations.length > 0) {
            createChart();
        }
    },
    { deep: true }
);

// Watch for changes in processed data
watch(
    processedData,
    (newData) => {
        if (newData.length > 0) {
            createChart();
        }
    },
    { deep: true }
);
</script>

<template>
    <div
        ref="chartRef"
        id="chart"
        class="w-full h-[400px] mt-12 border rounded-md"
    >
        <!-- Fallback content if chart doesn't render -->
        <div
            v-if="!processedData.length"
            class="flex items-center justify-center h-full"
        >
            <p class="text-gray-500">Ei valittuja sijainteja</p>
        </div>
    </div>
</template>

<style scoped></style>
