<script setup>
import * as d3 from "d3";
import { ref, onMounted, watch, computed, onUnmounted, reactive } from "vue";
import TrashIcon from "../icons/TrashIcon.vue";
import SunriseIcon from "../icons/SunriseIcon.vue";
import SunsetIcon from "../icons/SunsetIcon.vue";
// Define emit for event communication with parent component
const emit = defineEmits(["removeLocation"]);

const props = defineProps({
    locations: {
        type: Array,
        required: true,
    },
});

// Add reactive tooltip data
const tooltipData = reactive({
    visible: false,
    date: null,
    locations: [],
    position: { left: 0, top: 0 },
});

// Create a refs for chart and tooltip
const chartRef = ref(null);
const tooltipRef = ref(null);
const hoveredLocation = ref(null);

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

// Intersections value to keep track of and show to user
// returns an array of
const intersections = computed(() => {
    const result = [];

    // If there are fewer than 2 locations, no intersections are possible
    if (processedData.value.length < 2) {
        return result;
    }

    // Get all unique dates across all locations
    const dateMap = new Map();

    processedData.value.forEach((location) => {
        location.value.forEach((day) => {
            const dateKey = day.date.toISOString().split("T")[0];
            if (!dateMap.has(dateKey)) {
                dateMap.set(dateKey, []);
            }
            dateMap.get(dateKey).push({
                location: location.name,
                hours: day.hours,
                date: day.date,
            });
        });
    });

    // Check each date if all locations have data for that day
    for (const [dateKey, entries] of dateMap) {
        // Only process dates where all locations have data
        if (entries.length === processedData.value.length) {
            // Find min and max daylight hours for this date
            const hours = entries.map((e) => e.hours);
            const minHours = Math.min(...hours);
            const maxHours = Math.max(...hours);
            const diff = maxHours - minHours;

            // Determine score based on difference
            let score = 0;
            if (diff < 0.167) {
                // Less than 10 minutes (0.167 hours)
                score = 3;
            } else if (diff < 0.333) {
                // Less than 20 minutes (0.333 hours)
                score = 2;
            } else if (diff < 0.5) {
                // Less than 30 minutes (0.5 hours)
                score = 1;
            }

            // Only add to result if there's a score (meaning difference is under 30 min)
            if (score > 0) {
                result.push({
                    date: entries[0].date,
                    hours: (minHours + maxHours) / 2, // Average hours
                    locations: entries.map((e) => e.location),
                    difference: diff,
                    score: score,
                });
            }
        }
    }

    return result;
});

// Format time function for tooltip
const formatTime = (date) => {
    if (!date) return null;
    return new Date(date).toLocaleTimeString("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Format date for tooltip header
const formatDate = (date) => {
    return date.toLocaleDateString([], { day: "numeric", month: "short" });
};
// Chart dimensions
let width = 0;
const height = 400;
const margin = { top: 20, right: 20, bottom: 50, left: 50 };

// Color scale setup for multiple locations
const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

// Function to create or update the chart
const createChart = () => {
    // Throw error if chart is not found
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
        .attr("width", "100%")
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

    // Create grid lines
    const yGridLines = d3
        .axisLeft(yScale)
        .tickSize(-width + margin.left + margin.right)
        .tickFormat("")
        .ticks(5);

    svg.append("g")
        .attr("class", "grid-lines")
        .attr("transform", `translate(${margin.left}, 0)`)
        .call(yGridLines)
        .call((g) => g.select(".domain").remove()) // Remove axis line
        .call((g) =>
            g
                .selectAll(".tick line")
                .attr("stroke", "#e0e0e0")
                .attr("stroke-opacity", 0.7)
                .attr("shape-rendering", "crispEdges")
        );

    const xAxis = d3
        .axisBottom(xScale)
        .tickFormat((d) => {
            const months = [
                "Tammi",
                "Helmi",
                "Maalis",
                "Huhti",
                "Touko",
                "Kesä",
                "Heinä",
                "Elo",
                "Syys",
                "Loka",
                "Marras",
                "Joulu",
            ];
            // For small screens, only show first letter
            if (width < 500) {
                return months[d.getMonth()].charAt(0);
            }
            return months[d.getMonth()];
        })
        .tickSize(5)
        .tickPadding(10);

    svg.append("g")
        .attr("class", "x-axis")
        .attr("transform", `translate(0, ${height - margin.bottom})`)
        .call(xAxis)
        .call((g) => g.select(".domain").attr("stroke", "#ccc"))
        .call((g) => g.selectAll(".tick line").attr("stroke", "#ccc"))
        .call((g) =>
            g
                .selectAll(".tick text")
                .attr("fill", "#666")
                .attr("font-size", "12px")
        );

    //  y-axis
    const yAxis = d3
        .axisLeft(yScale)
        .tickFormat((d) => `${d}h`)
        .tickSize(5)
        .tickPadding(10);

    svg.append("g")
        .attr("class", "y-axis")
        .attr("transform", `translate(${margin.left}, 0)`)
        .call(yAxis)
        .call((g) => g.select(".domain").attr("stroke", "#ccc"))
        .call((g) => g.selectAll(".tick line").attr("stroke", "#ccc"))
        .call((g) =>
            g
                .selectAll(".tick text")
                .attr("fill", "#666")
                .attr("font-size", "12px")
        );

    // line generator
    const line = d3
        .line()
        .x((d) => xScale(d.date))
        .y((d) => yScale(d.hours));

    // create line paths
    processedData.value.forEach((location, index) => {
        // draw lines normally if no location is hovered
        if (
            hoveredLocation.value === null ||
            hoveredLocation.value === undefined
        )
            svg.append("path")
                .datum(location.value)
                .attr("fill", "none")
                .attr("stroke", colorScale(index))
                .attr("stroke-width", 2)
                .attr("opacity", 1)
                .attr("d", line)
                .raise();
        // draw line bigger and fully opaque if location is hovered
        else if (hoveredLocation.value === index)
            svg.append("path")
                .datum(location.value)
                .attr("fill", "none")
                .attr("stroke", colorScale(index))
                .attr("filter", "url(#line-shadow)") // Apply shadow
                .attr("stroke-width", 3.5)
                .attr("opacity", 1)
                .attr("d", line)
                .raise();
        // make other lines have lesser opacity if other location is hovered
        else
            svg.append("path")
                .datum(location.value)
                .attr("fill", "none")
                .attr("stroke", colorScale(index))
                .attr("stroke-width", 2)
                .attr("opacity", 0.3)
                .attr("d", line)
                .raise();
    });

    // Shadow for hovered location
    svg.append("defs")
        .append("filter")
        .attr("id", "line-shadow")
        .append("feDropShadow")
        .attr("dx", 0)
        .attr("dy", 1)
        .attr("stdDeviation", 2)
        .attr("flood-color", "rgba(0,0,0,0.3)")
        .attr("flood-opacity", 0.5);

    svg.append("text")
        .attr("transform", "rotate(-90)")
        .attr("x", -height / 2)
        .attr("y", margin.left - 40)
        .attr("text-anchor", "middle")
        .attr("fill", "#666")
        .attr("font-size", "14px")
        .text("Valoisaa");

    // INTERESECTIONS -------------------------------------------
    // Group intersections by date
    const intersectionDates = {};
    intersections.value.forEach((point) => {
        const dateKey = point.date.toISOString().split("T")[0];
        if (!intersectionDates[dateKey]) {
            intersectionDates[dateKey] = [];
        }
        intersectionDates[dateKey].push(point);
    });

    // Add vertical bands for days with intersections
    //  colors based on the score of intersection
    svg.selectAll(".intersection-band")
        .data(Object.keys(intersectionDates))
        .join("rect")
        .attr("class", "intersection-band")
        .attr("x", (dateKey) => xScale(new Date(dateKey)) - 5)
        .attr("y", margin.top)
        .attr("width", 10)
        .attr("height", height - margin.top - margin.bottom)
        .attr("fill", (dateKey) => {
            // Get highest score for this date
            const maxScore = Math.max(
                ...intersectionDates[dateKey].map((d) => d.score)
            );
            // Color based on score (1-3)
            return `rgba(255, 230, 230, ${maxScore * 0.1})`;
        })
        .attr("stroke", "none");

    const heatmapHeight = 15;
    const heatmapSvg = svg
        .append("g")
        .attr(
            "transform",
            `translate(0, ${height - margin.bottom - heatmapHeight})`
        )
        .attr("opacity", 1)
        .lower();

    // Process intersection data for heatmap
    const heatmapData = {};
    intersections.value.forEach((point) => {
        const dateKey = point.date.toISOString().split("T")[0];
        if (!heatmapData[dateKey]) {
            heatmapData[dateKey] = {
                date: point.date,
                score: point.score,
                locations: point.locations,
                difference: point.difference,
            };
        } else if (point.score > heatmapData[dateKey].score) {
            // Keep the intersection with the highest score
            heatmapData[dateKey] = {
                date: point.date,
                score: point.score,
                locations: point.locations,
                difference: point.difference,
            };
        }
    });

    // Get all dates for the full year
    const allDaysOfYear = [];
    const startDate = new Date(dateExtent[0]);
    const endDate = new Date(dateExtent[1]);
    for (
        let d = new Date(startDate);
        d <= endDate;
        d.setDate(d.getDate() + 1)
    ) {
        allDaysOfYear.push(new Date(d));
    }

    // Create heat map cells with enhanced tooltips
    const heatmapCells = heatmapSvg
        .selectAll(".heatmap-cell")
        .data(allDaysOfYear)
        .join("rect")
        .attr("class", "heatmap-cell")
        .attr("x", (d) => xScale(d) - 1.5)
        .attr("y", (d) => {
            const dateKey = d.toISOString().split("T")[0];
            const data = heatmapData[dateKey];
            const score = data ? data.score : 0;
            // Use score directly for height calculation
            const barHeight = score > 0 ? score * 5 : 2;
            return heatmapHeight - barHeight;
        })
        .attr("width", 3)
        .attr("height", (d) => {
            const dateKey = d.toISOString().split("T")[0];
            const data = heatmapData[dateKey];
            const score = data ? data.score : 0;
            // Use score directly for height
            return score > 0 ? score * 5 : 2;
        })
        .attr("fill", (d) => {
            const dateKey = d.toISOString().split("T")[0];
            const data = heatmapData[dateKey];
            const score = data ? data.score : 0;
            // Color intensity based on score
            return score > 0 ? d3.interpolateReds(score / 3) : "#fdfefe"; // White for no intersections
        })
        .attr("rx", 1) // Rounded corners for aesthetics
        .attr("ry", 1);

    // CURRENTDATE LINE --------------------------------------------------

    // Add current date line
    const currentDate = new Date();
    if (currentDate >= dateExtent[0] && currentDate <= dateExtent[1]) {
        const currentX = xScale(currentDate);

        // Add vertical line for current date
        svg.append("line")
            .attr("class", "current-date-line")
            .attr("x1", currentX)
            .attr("x2", currentX)
            .attr("y1", margin.top)
            .attr("y2", height - margin.bottom)
            .attr("stroke", "#666")
            .attr("opacity", 0.55)
            .attr("stroke-width", 1.3);

        svg.append("text")
            .attr("class", "current-date-label")
            .attr("x", currentX)
            .attr("y", margin.top - 10)
            .attr("text-anchor", "middle")
            .attr("fill", "#666")
            .attr("font-size", "12px")
            .text("Tänään");
    }
    // TOOLTIP ------------------------------------------------------

    // Add invisible overlay for tooltip
    const overlay = svg
        .append("rect")
        .attr("width", width - margin.left - margin.right)
        .attr("height", height - margin.top - margin.bottom)
        .attr("x", margin.left)
        .attr("y", margin.top)
        .attr("fill", "none")
        .attr("pointer-events", "all");

    // Create vertical line for tooltip
    const tooltipLine = svg
        .append("line")
        .attr("class", "tooltip-line")
        .attr("y1", margin.top)
        .attr("y2", height - margin.bottom)
        .attr("stroke", "#ccc")
        .attr("stroke-width", 1)
        .attr("stroke-dasharray", "5,5")
        .style("opacity", 0);

    // Add tooltip interaction
    overlay
        .on("mousemove", function (event) {
            const [mouseX] = d3.pointer(event);

            // Get date at mouse position
            const hoveredDate = xScale.invert(mouseX);

            // Find closest date in data
            const bisectDate = d3.bisector((d) => d.date).left;

            // Collect data for tooltip
            const locations = processedData.value
                .map((location) => {
                    const index = bisectDate(location.value, hoveredDate);
                    const dataPoint = location.value[index];

                    if (!dataPoint) return null;

                    return {
                        name: location.name,
                        hours: dataPoint.hours,
                        sunrise: dataPoint.sunrise,
                        sunset: dataPoint.sunset,
                        date: dataPoint.date,
                        color: colorScale(
                            processedData.value.indexOf(location)
                        ),
                    };
                })
                .filter((d) => d !== null);

            if (locations.length === 0) return;

            // Update tooltip line position
            const dateX = xScale(locations[0].date);
            tooltipLine.attr("x1", dateX).attr("x2", dateX).style("opacity", 1);

            // Update reactive data for Vue component
            tooltipData.visible = true;
            tooltipData.date = locations[0].date;
            tooltipData.locations = locations;

            // Calculate position
            const chartRect = chartRef.value.getBoundingClientRect();
            const tooltipRect = tooltipRef.value.getBoundingClientRect();

            // Compare event location to chart cotnainers left wall
            let left = event.clientX - chartRect.left + 30;
            if (left + tooltipRect.width > chartRect.width) {
                left = event.clientX - chartRect.left - tooltipRect.width;
            }
            // Compare event location height to chart container top wall and divide to center
            let top = event.clientY - chartRect.top - tooltipRect.height / 2;
            if (top < 0) top = 5;
            if (top + tooltipRect.height > chartRect.height) {
                top = chartRect.height - tooltipRect.height - 5;
            }

            tooltipData.position = { left, top };
        })
        .on("mouseleave", function () {
            tooltipData.visible = false;
            tooltipLine.style("opacity", 0);
        });
};

// Define resizeObserver at component level
let resizeObserver;

// Create chart when component is mounted
onMounted(() => {
    // get initial width for container
    if (chartRef.value) {
        width = chartRef.value.clientWidth;
        if (props.locations.length > 0) {
            createChart();
        }
    }

    // Make the chart responsive
    resizeObserver = new ResizeObserver((entries) => {
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
// Clean up the observer when component is unmounted
onUnmounted(() => {
    if (chartRef.value && resizeObserver) {
        resizeObserver.unobserve(chartRef.value);
    }
});

// Watch for changes in processed data
watch(
    [processedData, hoveredLocation],
    (newData) => {
        if (newData.length > 0) {
            createChart();
        }
    },
    { deep: true }
);
</script>

<template>
    <div class="lg:p-4 relative">
        <div ref="chartRef" id="chart" class="w-full h-[400px]"></div>
        <!-- Tooltip Element -->
        <div
            ref="tooltipRef"
            class="tooltip absolute p-2 bg-white border rounded shadow-lg z-10 pointer-events-none"
            :class="{ 'tooltip-visible': tooltipData.visible }"
            :style="{
                left: tooltipData.position.left + 'px',
                top: tooltipData.position.top + 'px',
                minWidth: '150px',
            }"
        >
            <div v-if="tooltipData.date" class="mb-2">
                {{
                    new Date(tooltipData.date).toLocaleDateString("fi-FI", {
                        day: "numeric",
                        month: "long",
                    })
                }}
            </div>

            <div
                v-for="(data, idx) in tooltipData.locations"
                :key="idx"
                class="mb-1"
            >
                <div class="flex items-center">
                    <div
                        class="w-3 h-3 rounded-full mr-2"
                        :style="{ backgroundColor: data.color }"
                    ></div>
                    <span class="font-semibold">{{ data.name }}</span>
                </div>
                <div class="ml-5">Valoisaa: {{ data.hours.toFixed(1) }}h</div>

                <!-- show sunset/sunrise only if sunsets and rises  -->
                <div
                    v-if="data.hours !== 24 && data.hours !== 0"
                    class="flex gap-2"
                >
                    <div class="flex items-center">
                        <SunriseIcon class="w-4 h-4 mr-1" />
                        {{ formatTime(data.sunrise) }}
                    </div>
                    <div class="flex items-center">
                        <SunsetIcon class="w-4 h-4 mr-1" />
                        {{ formatTime(data.sunset) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- List of locations with delete button -->
        <div class="flex gap-4 flex-wrap mx-10">
            <div
                v-for="(location, index) in processedData"
                :key="index"
                @mouseover="hoveredLocation = index"
                @mouseleave="hoveredLocation = null"
                class="rounded-full border py-1 px-4 select-none flex relative"
                :style="{ borderColor: colorScale(index) }"
            >
                {{ location.name }}

                <div
                    @click.prevent="$emit('removeLocation', index)"
                    class="deleteLocationButton"
                >
                    <TrashIcon />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.deleteLocationButton {
    @apply cursor-pointer opacity-0 absolute w-full h-full bg-gray-50 bg-opacity-80 hover:opacity-100 rounded-full  left-0 top-0 flex justify-center items-center;
}

.tooltip {
    font-size: 12px;
    background-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(16px);
    border: 1px solid #ececec;
    max-width: 250px;

    /* Add these for smooth transitions */
    transition: opacity 0.15s ease, transform 0.15s ease;
    opacity: 0;
    transform: translateY(10px);
}

.tooltip-visible {
    opacity: 1;
    transform: translateY(0);
}
</style>
