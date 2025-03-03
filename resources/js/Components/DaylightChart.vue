<script setup>
import * as d3 from "d3";
import { ref, onMounted, watch, computed, onUnmounted } from "vue";
import TrashIcon from "../icons/TrashIcon.vue";
// Define emit for event communication with parent component
const emit = defineEmits(["removeLocation"]);

const props = defineProps({
    locations: {
        type: Array,
        required: true,
    },
});

// Create a refs for chart and tooltip
const chartRef = ref(null);
const tooltipRef = ref(null);

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
// Format time function for tooltip
const formatTime = (date) => {
    if (!date) return "24h daylight/darkness";
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
};

// Format date for tooltip header
const formatDate = (date) => {
    return date.toLocaleDateString([], { day: "numeric", month: "short" });
};
// Chart dimensions
let width = 0;
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

    // TOOLTIP

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

            // Collect data from all locations for this date
            const tooltipData = processedData.value
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

            if (tooltipData.length === 0) return;

            // Update tooltip line position
            const dateX = xScale(tooltipData[0].date);
            tooltipLine.attr("x1", dateX).attr("x2", dateX).style("opacity", 1);

            // Build tooltip HTML
            let tooltipHtml = `<div class="font-bold mb-2">${formatDate(
                tooltipData[0].date
            )}</div>`;

            tooltipData.forEach((data) => {
                tooltipHtml += `
                <div class="mb-1">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2" style="background-color: ${
                            data.color
                        }"></div>
                        <span class="font-semibold">${data.name}</span>
                    </div>
                    <div class="ml-5">Päivänvaloa: ${data.hours.toFixed(
                        1
                    )}h</div>
<div class="flex gap-2">
                        <div class="ml-5 flex items-center"><SunriseIcon />  ${formatTime(
                            data.sunrise
                        )}</div>
                    <div class="ml-5"><SunsetIcon/> ${formatTime(
                        data.sunset
                    )}</div></div>
                </div>
            `;
            });

            // Update tooltip content and position
            const tooltip = d3
                .select(tooltipRef.value)
                .html(tooltipHtml)
                .style("display", "block");

            // Position tooltip, ensuring it stays within chart boundaries
            const tooltipWidth = tooltipRef.value.offsetWidth;
            const tooltipHeight = tooltipRef.value.offsetHeight;
            const chartRect = chartRef.value.getBoundingClientRect();

            let left = event.clientX - chartRect.left + 10;
            if (left + tooltipWidth > chartRect.width) {
                left = event.clientX - chartRect.left - tooltipWidth - 10;
            }

            let top = event.clientY - chartRect.top - tooltipHeight / 2;
            if (top < 0) top = 5;
            if (top + tooltipHeight > chartRect.height) {
                top = chartRect.height - tooltipHeight - 5;
            }

            tooltip.style("left", `${left}px`).style("top", `${top}px`);
        })
        .on("mouseleave", function () {
            // Hide tooltip and line when mouse leaves chart
            d3.select(tooltipRef.value).style("display", "none");
            tooltipLine.style("opacity", 0);
        });
};

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
// Clean up the observer when component is unmounted
onUnmounted(() => {
    if (chartRef.value) {
        resizeObserver.unobserve(chartRef.value);
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
    <div class="border rounded-md p-4 relative">
        <div ref="chartRef" id="chart" class="w-full h-[400px]"></div>
        <!-- Tooltip Element -->
        <div
            ref="tooltipRef"
            class="tooltip absolute hidden p-2 bg-white border rounded shadow-lg z-10 pointer-events-none"
            style="min-width: 150px"
        />

        <!-- List of locations with delete button -->
        <div class="flex gap-4 flex-wrap mx-10">
            <div
                v-for="(location, index) in processedData"
                :key="index"
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

        <!-- Fallback content if chart doesn't render -->
        <div
            v-if="!processedData.length"
            class="flex w-52 items-center justify-center h-full absolute top-0 left-1/2 -translate-x-24"
        >
            <p class="text-gray-500">Ei valittuja sijainteja</p>
        </div>
    </div>
</template>

<style scoped>
.deleteLocationButton {
    @apply cursor-pointer opacity-0 absolute w-full h-full bg-gray-50 bg-opacity-80 hover:opacity-100 rounded-full  left-0 top-0 flex justify-center items-center;
}

.tooltip {
    font-size: 12px;
    background-color: rgba(255, 255, 255, 0.9);
    border: 1px solid #ccc;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    max-width: 250px;
}
</style>
