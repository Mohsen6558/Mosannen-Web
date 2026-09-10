<script setup>
import { computed } from 'vue';
import { use } from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import { useTheme } from '@/Composables/useTheme';
import { chrome } from '@/Support/chartTheme';

use([BarChart, LineChart, GridComponent, TooltipComponent, LegendComponent, CanvasRenderer]);

/**
 * Thin ECharts wrapper that applies the app's chrome and RTL conventions.
 *
 * Callers pass only the parts that differ — series, categories, formatters.
 * Everything shared (recessive grid, tabular Persian ticks, right-hand value
 * axis, time flowing right to left) is set here so charts cannot drift apart.
 */
const props = defineProps({
    option: { type: Object, required: true },
    height: { type: String, default: '18rem' },
});

const { theme } = useTheme();

const isDark = computed(() => {
    if (theme.value === 'dark') return true;
    if (theme.value === 'light') return false;

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
});

const merged = computed(() => {
    const c = chrome(isDark.value);
    const base = {
        animation: false,
        textStyle: { fontFamily: 'Vazirmatn, system-ui, sans-serif', color: c.ink },
        grid: {
            top: 24,
            right: 52,
            bottom: 30,
            left: 12,
            containLabel: false,
            // ECharts 6 shrinks the plot to keep axis labels inside the
            // canvas, by up to 25% per side. With wide Jalali date labels
            // that silently halves the plot. The padding above already
            // reserves what the labels need, so opt out.
            outerBoundsMode: 'none',
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow', shadowStyle: { color: isDark.value ? 'rgba(255,255,255,.04)' : 'rgba(23,22,20,.04)' } },
            backgroundColor: c.tooltipBg,
            borderColor: c.tooltipBorder,
            borderWidth: 1,
            padding: [8, 12],
            textStyle: { color: c.ink, fontSize: 12, fontFamily: 'Vazirmatn, system-ui, sans-serif' },
            extraCssText: 'direction:rtl;border-radius:8px;box-shadow:0 8px 24px -8px rgba(23,22,20,.18);',
        },
        legend: {
            top: 0,
            right: 0,
            icon: 'roundRect',
            itemWidth: 10,
            itemHeight: 10,
            itemGap: 16,
            textStyle: { color: c.muted, fontSize: 11, fontFamily: 'Vazirmatn, system-ui, sans-serif' },
        },
    };

    const axisDefaults = {
        axisLine: { lineStyle: { color: c.axis } },
        axisTick: { show: false },
        axisLabel: {
            color: c.muted,
            fontSize: 11,
            fontFamily: 'Vazirmatn, system-ui, sans-serif',
            hideOverlap: true,
        },
        splitLine: { lineStyle: { color: c.grid, type: 'solid' } },
    };

    const applyAxis = (axis, isValue) => ({
        ...axisDefaults,
        // A category axis carries no gridlines of its own; the value axis does.
        splitLine: isValue ? axisDefaults.splitLine : { show: false },
        axisLine: isValue ? { show: false } : axisDefaults.axisLine,
        ...axis,
        axisLabel: { ...axisDefaults.axisLabel, ...(axis?.axisLabel ?? {}) },
    });

    const option = { ...base, ...props.option };

    if (option.xAxis) {
        option.xAxis = applyAxis(option.xAxis, option.xAxis.type === 'value');
    }

    if (option.yAxis) {
        // Value axis on the right, matching the reading direction.
        option.yAxis = applyAxis(
            { position: option.yAxis.type === 'category' ? 'right' : 'right', ...option.yAxis },
            option.yAxis.type !== 'category',
        );
    }

    option.tooltip = { ...base.tooltip, ...(props.option.tooltip ?? {}) };
    option.grid = { ...base.grid, ...(props.option.grid ?? {}) };
    // One series needs no legend: the card title names it. Leaving the legend
    // on also makes ECharts drop an unnamed series entirely.
    option.legend = {
        ...base.legend,
        show: (option.series?.length ?? 0) > 1,
        ...(props.option.legend ?? {}),
    };

    return option;
});
</script>

<template>
    <VChart
        :option="merged"
        :style="{ height }"
        autoresize
        :init-options="{ renderer: 'canvas' }"
    />
</template>
