<script setup>
import { ref, computed, onMounted } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { BarChart3, LineChart, AreaChart, Calendar } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    chartData: {
        type: Object,
        required: true,
    }
});

const period = ref('weekly'); // weekly, monthly, yearly
const chartType = ref('area'); // area, bar, line

const currentData = computed(() => props.chartData[period.value] || []);

const series = computed(() => {
    return [
        {
            name: 'Total Penjualan (IDR)',
            data: currentData.value.map(d => Number(d.total || 0))
        },
        {
            name: 'Jumlah Pesanan',
            data: currentData.value.map(d => Number(d.count || 0))
        }
    ];
});

const chartOptions = computed(() => {
    return {
        chart: {
            id: 'sales-chart',
            toolbar: { show: false },
            fontFamily: 'Figtree, ui-sans-serif, system-ui',
        },
        colors: ['#db2777', '#f472b6'], // Pink 600, Pink 400
        stroke: {
            curve: 'smooth',
            width: chartType.value === 'line' ? 3 : 2
        },
        fill: {
            type: chartType.value === 'area' ? 'gradient' : 'solid',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [20, 100]
            }
        },
        xaxis: {
            categories: currentData.value.map(d => d.date),
            type: period.value === 'yearly' ? 'category' : 'datetime',
            labels: {
                style: { colors: '#9ca3af' },
                datetimeFormatter: {
                    year: 'yyyy',
                    month: 'MMM yyyy',
                    day: 'dd MMM',
                }
            }
        },
        yaxis: [
            {
                title: { text: 'Total Penjualan (IDR)', style: { color: '#db2777' } },
                labels: {
                    formatter: (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val),
                    style: { colors: '#db2777' }
                }
            },
            {
                opposite: true,
                title: { text: 'Jumlah Pesanan', style: { color: '#f472b6' } },
                labels: {
                    style: { colors: '#f472b6' }
                }
            }
        ],
        dataLabels: { enabled: false },
        tooltip: {
            theme: 'light',
            y: {
                formatter: (val, { seriesIndex }) => {
                    if (seriesIndex === 0) return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
                    return val + ' Pesanan';
                }
            }
        },
        grid: {
            borderColor: '#f3f4f6',
            strokeDashArray: 4,
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
        }
    };
});
</script>

<template>
    <div class="bg-white p-6 rounded-[2rem] border border-pink-100 shadow-sm space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold text-pink-950 flex items-center gap-2">
                    <BarChart3 class="w-5 h-5 text-pink-600" />
                    Statistik Penjualan
                </h3>
                <p class="text-sm text-muted-foreground mt-1">Pantau performa tokomu berdasarkan periode.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 bg-pink-50 p-1.5 rounded-2xl border border-pink-100/50">
                <BaseButton 
                    v-for="p in ['weekly', 'monthly', 'yearly']" 
                    :key="p"
                    :variant="period === p ? 'primary' : 'ghost'"
                    size="sm"
                    class="capitalize"
                    @click="period = p"
                >
                    {{ p === 'weekly' ? 'Mingguan' : p === 'monthly' ? 'Bulanan' : 'Tahunan' }}
                </BaseButton>
            </div>
        </div>

        <!-- Chart Container -->
        <div class="min-h-[350px] relative">
            <div class="absolute top-0 right-0 z-10 flex gap-1 bg-white/80 backdrop-blur-sm p-1 rounded-xl border border-pink-100/50">
                <BaseButton 
                    v-for="type in ['area', 'bar', 'line']" 
                    :key="type"
                    @click="chartType = type"
                    :variant="chartType === type ? 'primary' : 'ghost'"
                    size="icon"
                    class="h-9 w-9"
                    :title="`Ganti ke grafik ${type}`"
                >
                    <AreaChart v-if="type === 'area'" class="w-4 h-4" />
                    <BarChart3 v-else-if="type === 'bar'" class="w-4 h-4" />
                    <LineChart v-else-if="type === 'line'" class="w-4 h-4" />
                </BaseButton>
            </div>

            <VueApexCharts 
                height="350"
                :type="chartType"
                :options="chartOptions"
                :series="series"
            />
        </div>

        <div v-if="currentData.length === 0" class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
            <Calendar class="w-12 h-12 text-pink-100 mb-2" />
            <p>Belum ada data penjualan untuk periode ini.</p>
        </div>
    </div>
</template>
