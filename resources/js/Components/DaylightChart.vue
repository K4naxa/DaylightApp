<script setup>
import * as d3 from "d3";
import { ref, onMounted, watch, computed, onUnmounted, reactive } from "vue";
import TrashIcon from "../icons/TrashIcon.vue";
import SunriseIcon from "../icons/SunriseIcon.vue";
import SunsetIcon from "../icons/SunsetIcon.vue";

// Define component communication
const emit = defineEmits(["removeLocation"]);
const props = defineProps({
    locations: {
        type: Array,
        required: true,
    },
});

// UI state management
const chartRef = ref(null);
const tooltipRef = ref(null);
const hoveredLocation = ref(null);
const tooltipData = reactive({
    visible: false,
    date: null,
    locations: [],
    position: { left: 0, top: 0 },
});

// Chart dimensions
let width = 0;
const height = 400;
const margin = { top: 20, right: 20, bottom: 50, left: 50 };
const colorScale = d3.scaleOrdinal(d3.schemeCategory10);

/**
 * Processes location data for chart visualization
 * @returns {Array} Array of processed location data with daylight hours
 */
const processedData = computed(() => {
    if (!props.locations?.length) return [];

    return props.locations
        .map((location) => {
            if (!location.daylightData?.length) {
                return { name: location.name, value: [] };
            }

            return {
                name: location.name,
                value: location.daylightData.map((day) => {
                    const date =
                        day.date instanceof Date
                            ? day.date
                            : new Date(day.date);

                    // Calculate daylight hours
                    let daylightHours, sunrise, sunset;

                    if (day.sunrise === true && day.sunset === true) {
                        daylightHours = 24;
                        sunrise = null;
                        sunset = null;
                    } else {
                        sunrise = new Date(day.sunrise * 1000);
                        sunset = new Date(day.sunset * 1000);
                        daylightHours = (sunset - sunrise) / (1000 * 60 * 60);
                    }

                    return {
                        date,
                        hours: daylightHours,
                        sunrise,
                        sunset,
                        day,
                    };
                }),
            };
        })
        .filter((location) => location.value.length > 0);
});

/**
 * Calculates intersections between locations where daylight hours are similar
 * Gives scoring depending on the time difference of light hours
 * @returns {Array} Array of intersection points with date, score, and locations
 */
const intersections = computed(() => {
    if (processedData.value.length < 2) return [];

    // Get all unique dates with their daylight data
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

    // Find intersections where all locations have similar daylight hours
    const result = [];
    for (const [dateKey, entries] of dateMap) {
        if (entries.length === processedData.value.length) {
            const hours = entries.map((e) => e.hours);
            const minHours = Math.min(...hours);
            const maxHours = Math.max(...hours);
            const diff = maxHours - minHours;

            // Scoring based on difference in daylight hours
            let score = 0;
            if (diff < 0.167) score = 3; // < 10 minutes
            else if (diff < 0.333) score = 2; // < 20 minutes
            else if (diff < 0.5) score = 1; // < 30 minutes

            if (score > 0) {
                result.push({
                    date: entries[0].date,
                    hours: (minHours + maxHours) / 2,
                    locations: entries.map((e) => e.location),
                    difference: diff,
                    score,
                });
            }
        }
    }

    return result;
});

/**
 * Formats time for display in the tooltip
 * @param {Date} date - Date object to format
 * @returns {string} Formatted time string
 */
const formatTime = (date) => {
    if (!date) return null;
    return new Date(date).toLocaleTimeString("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
    });
};

/**
 * Creates or updates the chart visualization
 */
const createChart = () => {
    if (!chartRef.value) {
        return;
    }

    // Clear previous chart
    d3.select(chartRef.value).selectAll("*").remove();

    // Create SVG container
    const svg = d3
        .select(chartRef.value)
        .append("svg")
        .attr("width", "100%")
        .attr("height", height);

    // Extract data for scales
    const allDates = processedData.value.flatMap((loc) =>
        loc.value.map((d) => d.date)
    );
    const dateExtent = d3.extent(allDates);
    const maxHours =
        d3.max(processedData.value, (loc) =>
            d3.max(loc.value, (d) => d.hours)
        ) || 24;

    // Create scales
    const xScale = d3
        .scaleTime()
        .domain(dateExtent)
        .range([margin.left, width - margin.right]);

    const yScale = d3
        .scaleLinear()
        .domain([0, Math.min(24, maxHours)])
        .nice()
        .range([height - margin.bottom, margin.top]);

    // Draw chart elements
    drawGridLines(svg, xScale, yScale);
    drawAxes(svg, xScale, yScale);
    drawLines(svg, xScale, yScale);
    drawIntersections(svg, xScale, yScale, dateExtent);
    drawCurrentDateLine(svg, xScale, dateExtent);
    setupTooltip(svg, xScale, yScale);

    /**
     * Draws grid lines for the chart
     */
    function drawGridLines(svg, xScale, yScale) {
        const yGridLines = d3
            .axisLeft(yScale)
            .tickSize(-width + margin.left + margin.right)
            .tickFormat("")
            .ticks(5);

        svg.append("g")
            .attr("class", "grid-lines")
            .attr("transform", `translate(${margin.left}, 0)`)
            .call(yGridLines)
            .call((g) => g.select(".domain").remove())
            .call((g) =>
                g
                    .selectAll(".tick line")
                    .attr("stroke", "#e0e0e0")
                    .attr("stroke-opacity", 0.7)
                    .attr("shape-rendering", "crispEdges")
            );
    }

    /**
     * Draws axes for the chart
     */
    function drawAxes(svg, xScale, yScale) {
        // X axis with month labels
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
                return width < 500
                    ? months[d.getMonth()].charAt(0)
                    : months[d.getMonth()];
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

        // Y axis with hour labels
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

        // Y-axis label
        svg.append("text")
            .attr("transform", "rotate(-90)")
            .attr("x", -height / 2)
            .attr("y", margin.left - 40)
            .attr("text-anchor", "middle")
            .attr("fill", "#666")
            .attr("font-size", "14px")
            .text("Valoisaa");
    }

    /**
     * Draws data lines for each location
     */
    function drawLines(svg, xScale, yScale) {
        // Add shadow filter for hover effect
        svg.append("defs")
            .append("filter")
            .attr("id", "line-shadow")
            .append("feDropShadow")
            .attr("dx", 0)
            .attr("dy", 1)
            .attr("stdDeviation", 2)
            .attr("flood-color", "rgba(0,0,0,0.3)")
            .attr("flood-opacity", 0.5);

        // Line generator
        const line = d3
            .line()
            .x((d) => xScale(d.date))
            .y((d) => yScale(d.hours));

        // Draw lines for each location
        processedData.value.forEach((location, index) => {
            const isHovered = hoveredLocation.value === index;
            const otherIsHovered = hoveredLocation.value !== null && !isHovered;

            svg.append("path")
                .datum(location.value)
                .attr("fill", "none")
                .attr("stroke", colorScale(index))
                .attr("stroke-width", isHovered ? 3.5 : 2)
                .attr("opacity", otherIsHovered ? 0.3 : 1)
                .attr("filter", isHovered ? "url(#line-shadow)" : null)
                .attr("d", line)
                .raise();
        });
    }

    /**
     * Draws intersection indicators
     */
    function drawIntersections(svg, xScale, yScale, dateExtent) {
        // Group intersections by date
        const intersectionDates = {};
        intersections.value.forEach((point) => {
            const dateKey = point.date.toISOString().split("T")[0];
            if (!intersectionDates[dateKey]) {
                intersectionDates[dateKey] = [];
            }
            intersectionDates[dateKey].push(point);
        });

        // Add vertical bands for intersection days
        svg.selectAll(".intersection-band")
            .data(Object.keys(intersectionDates))
            .join("rect")
            .attr("class", "intersection-band")
            .attr("x", (dateKey) => xScale(new Date(dateKey)) - 5)
            .attr("y", margin.top)
            .attr("width", 10)
            .lower()
            .attr("height", height - margin.top - margin.bottom)
            .attr("fill", (dateKey) => {
                const maxScore = Math.max(
                    ...intersectionDates[dateKey].map((d) => d.score)
                );
                return `rgba(255, 230, 230, ${maxScore * 0.1})`;
            })
            .attr("stroke", "none");

        // Create heatmap for intersections
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
            if (
                !heatmapData[dateKey] ||
                point.score > heatmapData[dateKey].score
            ) {
                heatmapData[dateKey] = {
                    date: point.date,
                    score: point.score,
                    locations: point.locations,
                    difference: point.difference,
                };
            }
        });

        // Generate all dates for the year
        const allDaysOfYear = [];
        for (
            let d = new Date(dateExtent[0]);
            d <= dateExtent[1];
            d.setDate(d.getDate() + 1)
        ) {
            allDaysOfYear.push(new Date(d));
        }

        // Draw heatmap cells
        heatmapSvg
            .selectAll(".heatmap-cell")
            .data(allDaysOfYear)
            .join("rect")
            .attr("class", "heatmap-cell")
            .attr("x", (d) => xScale(d) - 1.5)
            .attr("y", (d) => {
                const dateKey = d.toISOString().split("T")[0];
                const data = heatmapData[dateKey];
                const score = data ? data.score : 0;
                const barHeight = score > 0 ? score * 5 : 2;
                return heatmapHeight - barHeight;
            })
            .attr("width", 3)
            .attr("height", (d) => {
                const dateKey = d.toISOString().split("T")[0];
                const data = heatmapData[dateKey];
                const score = data ? data.score : 0;
                return score > 0 ? score * 5 : 2;
            })
            .attr("fill", (d) => {
                const dateKey = d.toISOString().split("T")[0];
                const data = heatmapData[dateKey];
                const score = data ? data.score : 0;
                return score > 0 ? d3.interpolateReds(score / 3) : "#fdfefe";
            })
            .attr("rx", 1)
            .attr("ry", 1);
    }

    /**
     * Draws a line indicating the current date
     */
    function drawCurrentDateLine(svg, xScale, dateExtent) {
        const currentDate = new Date();
        if (currentDate >= dateExtent[0] && currentDate <= dateExtent[1]) {
            const currentX = xScale(currentDate);

            // Vertical line
            svg.append("line")
                .attr("class", "current-date-line")
                .attr("x1", currentX)
                .attr("x2", currentX)
                .attr("y1", margin.top)
                .attr("y2", height - margin.bottom)
                .attr("stroke", "#666")
                .attr("opacity", 0.55)
                .attr("stroke-width", 1.3);

            // "Today" label
            svg.append("text")
                .attr("class", "current-date-label")
                .attr("x", currentX)
                .attr("y", margin.top - 10)
                .attr("text-anchor", "middle")
                .attr("fill", "#666")
                .attr("font-size", "12px")
                .text("Tänään");
        }
    }

    /**
     * Sets up tooltip interaction
     */
    function setupTooltip(svg, xScale, yScale) {
        // Tooltip line (vertical)
        const tooltipLine = svg
            .append("line")
            .attr("class", "tooltip-line")
            .attr("y1", margin.top)
            .attr("y2", height - margin.bottom)
            .attr("stroke", "#ccc")
            .attr("stroke-width", 1)
            .attr("stroke-dasharray", "5,5")
            .style("opacity", 0);

        // Invisible overlay for mouse events
        svg.append("rect")
            .attr("width", width - margin.left - margin.right)
            .attr("height", height - margin.top - margin.bottom)
            .attr("x", margin.left)
            .attr("y", margin.top)
            .attr("fill", "none")
            .attr("pointer-events", "all")
            .on("mousemove", handleMouseMove)
            .on("mouseleave", () => {
                tooltipData.visible = false;
                tooltipLine.style("opacity", 0);
            });

        /**
         * Handles mouse movement to update tooltip
         */
        function handleMouseMove(event) {
            const [mouseX] = d3.pointer(event);
            const hoveredDate = xScale.invert(mouseX);
            const bisectDate = d3.bisector((d) => d.date).left;

            // Collect data for all locations at this date
            const locations = processedData.value
                .map((location, index) => {
                    const i = bisectDate(location.value, hoveredDate);
                    const dataPoint = location.value[i];
                    if (!dataPoint) return null;

                    return {
                        name: location.name,
                        hours: dataPoint.hours,
                        sunrise: dataPoint.sunrise,
                        sunset: dataPoint.sunset,
                        date: dataPoint.date,
                        color: colorScale(index),
                    };
                })
                .filter((d) => d !== null);

            if (locations.length === 0) return;

            // Update tooltip line
            const dateX = xScale(locations[0].date);
            tooltipLine.attr("x1", dateX).attr("x2", dateX).style("opacity", 1);

            // Update tooltip data
            tooltipData.visible = true;
            tooltipData.date = locations[0].date;
            tooltipData.locations = locations;

            // Position tooltip
            positionTooltip(event);
        }

        /**
         * Positions tooltip based on mouse position
         */
        function positionTooltip(event) {
            const chartRect = chartRef.value.getBoundingClientRect();
            const tooltipRect = tooltipRef.value.getBoundingClientRect();

            // Calculate horizontal position
            let left = event.clientX - chartRect.left + 30;
            if (left + tooltipRect.width > chartRect.width) {
                left = event.clientX - chartRect.left - tooltipRect.width;
            }

            // Calculate vertical position
            let top = event.clientY - chartRect.top - tooltipRect.height / 2;
            if (top < 0) top = 5;
            if (top + tooltipRect.height > chartRect.height) {
                top = chartRect.height - tooltipRect.height - 5;
            }

            tooltipData.position = { left, top };
        }
    }
};

// Handle component lifecycle
let resizeObserver;

onMounted(() => {
    // Initialize chart
    if (chartRef.value) {
        width = chartRef.value.clientWidth;
        if (props.locations.length > 0) {
            createChart();
        }
    }

    // Make chart responsive
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

onUnmounted(() => {
    if (resizeObserver && chartRef.value) {
        resizeObserver.unobserve(chartRef.value);
    }
});

// Redraw chart when data changes
watch(
    [processedData, hoveredLocation],
    () => {
        if (processedData.value.length > 0) {
            createChart();
        }
    },
    { deep: true }
);
</script>

<template>
    <div class="lg:p-4 relative">
        <!-- Chart container -->
        <div ref="chartRef" id="chart" class="w-full h-[400px]"></div>

        <!-- Tooltip -->
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
            <!-- Date header -->
            <div v-if="tooltipData.date" class="mb-2">
                {{
                    new Date(tooltipData.date).toLocaleDateString("fi-FI", {
                        day: "numeric",
                        month: "long",
                    })
                }}
            </div>

            <!-- Location data -->
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

                <!-- Sunrise/sunset times -->
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

        <!-- Location legend -->
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
    @apply cursor-pointer opacity-0 absolute w-full h-full bg-gray-50 bg-opacity-80 hover:opacity-100 rounded-full left-0 top-0 flex justify-center items-center;
}

.tooltip {
    font-size: 12px;
    background-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(16px);
    border: 1px solid #ececec;
    max-width: 250px;
    transition: opacity 0.15s ease, transform 0.15s ease;
    opacity: 0;
    transform: translateY(10px);
}

.tooltip-visible {
    opacity: 1;
    transform: translateY(0);
}
</style>
