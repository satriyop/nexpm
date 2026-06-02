<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    MapPin,
    RotateCcw,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ActivityTypeBadge from '@/components/ActivityTypeBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import * as Actions from '@/actions/App/Http/Controllers/Admin/MapController';
import * as SiteActions from '@/actions/App/Http/Controllers/Admin/SiteController';
import { dashboard } from '@/routes';

// --- Types ---
interface DropdownItem {
    id: number;
    name: string;
}

interface Assignment {
    id: number;
    activity_type: string;
    activity_label: string;
    status: string;
    status_label: string;
    status_color: string;
    subcontractor: DropdownItem | null;
}

interface Photo {
    label: string;
    url: string;
}

interface MapSite {
    id: number;
    site_code: string;
    location_name: string;
    address: string | null;
    city: string | null;
    province: string | null;
    latitude: number;
    longitude: number;
    project: DropdownItem | null;
    machine_type: DropdownItem | null;
    total_assignments: number;
    completed_count: number;
    completion_ratio: number;
    photos: Photo[];
    assignments: Assignment[];
}

interface Stats {
    total_sites: number;
    completed_sites: number;
    in_progress_sites: number;
    total_assignments: number;
}

interface Filters {
    project_id?: string;
    activity_type?: string;
    status?: string;
    subcontractor_id?: string;
    machine_type_id?: string;
    province?: string;
}

const props = defineProps<{
    sites: MapSite[];
    projects: DropdownItem[];
    subcontractors: DropdownItem[];
    machineTypes: DropdownItem[];
    provinces: string[];
    stats: Stats;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Dashboard Map', href: Actions.index().url },
        ],
    },
});

// --- State ---
const ALL = '__all__';
const mapContainer = ref<HTMLElement | null>(null);
const showLeftSidebar = ref(true);
const selectedSiteId = ref<number | null>(null);

const projectId = ref(props.filters.project_id?.toString() ?? ALL);
const activityType = ref(props.filters.activity_type ?? ALL);
const status = ref(props.filters.status ?? ALL);
const subcontractorId = ref(props.filters.subcontractor_id?.toString() ?? ALL);
const machineTypeId = ref(props.filters.machine_type_id?.toString() ?? ALL);
const province = ref(props.filters.province ?? ALL);

// Leaflet state — never put Leaflet instances in Vue refs (breaks internals)
let map: L.Map | null = null;
const markerMap = new Map<number, L.CircleMarker>();

// --- Computed ---
const selectedSite = computed(
    () => props.sites.find((s) => s.id === selectedSiteId.value) ?? null,
);

const hasActiveFilters = computed(
    () =>
        projectId.value !== ALL ||
        activityType.value !== ALL ||
        status.value !== ALL ||
        subcontractorId.value !== ALL ||
        machineTypeId.value !== ALL ||
        province.value !== ALL,
);

const visibleAssignments = computed(() => {
    if (!selectedSite.value) return [];
    return selectedSite.value.assignments.filter((a) => {
        if (activityType.value !== ALL && a.activity_type !== activityType.value) return false;
        if (subcontractorId.value !== ALL && a.subcontractor?.id.toString() !== subcontractorId.value) return false;
        return true;
    });
});

// --- Marker helpers ---
function getMarkerColor(site: MapSite): string {
    if (site.total_assignments === 0) return '#9ca3af';
    if (site.assignments.some((a) => a.status === 'REVISION')) return '#ef4444';
    if (site.completion_ratio >= 1) return '#22c55e';
    if (site.completion_ratio > 0) return '#f97316';
    return '#9ca3af';
}

// Safe same-origin storage URL (rejects anything that isn't a /storage/ relative path)
function safeStorageUrl(url: string): string | null {
    if (typeof url !== 'string') return null;
    return url.startsWith('/storage/') ? url : null;
}

function buildPopupEl(site: MapSite): HTMLElement {
    const root = document.createElement('div');
    Object.assign(root.style, {
        minWidth: '200px',
        maxWidth: '280px',
        fontFamily: 'system-ui',
        padding: '2px',
    });

    const title = document.createElement('div');
    Object.assign(title.style, { fontWeight: '600', fontSize: '13px', marginBottom: '2px' });
    title.textContent = site.site_code;
    root.appendChild(title);

    const sub = document.createElement('div');
    Object.assign(sub.style, { fontSize: '12px', color: '#6b7280', marginBottom: '8px' });
    sub.textContent = site.location_name + (site.city ? ` · ${site.city}` : '');
    root.appendChild(sub);

    const strip = document.createElement('div');
    Object.assign(strip.style, { display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '4px' });

    if (site.photos.length > 0) {
        for (const photo of site.photos) {
            const safeUrl = safeStorageUrl(photo.url);
            if (!safeUrl) continue;

            const wrap = document.createElement('div');
            wrap.style.flexShrink = '0';

            const lbl = document.createElement('div');
            Object.assign(lbl.style, { fontSize: '10px', color: '#6b7280', textAlign: 'center', marginBottom: '3px' });
            lbl.textContent = photo.label;

            const img = document.createElement('img');
            img.src = safeUrl;
            img.loading = 'lazy';
            Object.assign(img.style, { height: '80px', width: '80px', objectFit: 'cover', borderRadius: '6px', display: 'block' });

            wrap.appendChild(lbl);
            wrap.appendChild(img);
            strip.appendChild(wrap);
        }
    } else {
        const empty = document.createElement('span');
        Object.assign(empty.style, { fontSize: '12px', color: '#9ca3af' });
        empty.textContent = 'No photos';
        strip.appendChild(empty);
    }
    root.appendChild(strip);

    const footer = document.createElement('div');
    Object.assign(footer.style, { marginTop: '8px', fontSize: '11px', color: '#6b7280' });
    footer.textContent =
        site.total_assignments > 0
            ? `${site.completed_count}/${site.total_assignments} completed`
            : 'No assignments';
    root.appendChild(footer);

    return root;
}

// --- Map functions ---
function initMap(): void {
    if (!mapContainer.value) return;

    map = L.map(mapContainer.value, {
        zoomControl: true,
        preferCanvas: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:
            '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    drawMarkers();
}

function drawMarkers(): void {
    if (!map) return;

    markerMap.forEach((m) => m.remove());
    markerMap.clear();

    const validSites = props.sites.filter((s) => s.latitude != null && s.longitude != null);
    if (validSites.length === 0) return;

    const bounds: L.LatLngTuple[] = [];

    validSites.forEach((site) => {
        const color = getMarkerColor(site);
        const marker = L.circleMarker([site.latitude, site.longitude], {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9,
        });

        marker.bindPopup(buildPopupEl(site), { maxWidth: 300 });

        marker.on('click', () => {
            selectSite(site.id);
        });

        marker.addTo(map!);
        markerMap.set(site.id, marker);
        bounds.push([site.latitude, site.longitude]);
    });

    if (bounds.length > 0) {
        map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40], maxZoom: 14 });
    }
}

function selectSite(siteId: number): void {
    selectedSiteId.value = siteId;
    const marker = markerMap.get(siteId);
    if (marker && map) {
        map.panTo(marker.getLatLng(), { animate: true });
        marker.openPopup();
    }
    nextTick(() => {
        document
            .getElementById(`site-item-${siteId}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

function closeSitePanel(): void {
    selectedSiteId.value = null;
}

// --- Filters ---
const statusOptions = [
    { value: 'PENDING', label: 'Pending' },
    { value: 'SURVEY', label: 'Survey' },
    { value: 'DOCUMENT', label: 'Document' },
    { value: 'CONSTRUCTION', label: 'Construction' },
    { value: 'MACHINE_ONSITE', label: 'Machine Onsite' },
    { value: 'DONE', label: 'Done' },
    { value: 'LIVE', label: 'Live' },
    { value: 'REGISTRATION', label: 'Registration' },
    { value: 'BILLING', label: 'Billing' },
    { value: 'CONNECTION', label: 'Connection' },
    { value: 'KWH_DONE', label: 'KWH Done' },
    { value: 'SUBMITTED', label: 'Submitted' },
    { value: 'REVISION', label: 'Revision' },
    { value: 'VERIFIED', label: 'Verified' },
    { value: 'REPORTED', label: 'Reported' },
    { value: 'DROP', label: 'Drop' },
];

const activityTypeOptions = [
    { value: 'SURVEY', label: 'Survey' },
    { value: 'PLN_CONNECTION', label: 'PLN Connection' },
    { value: 'CONSTRUCTION', label: 'Construction' },
    { value: 'BAST', label: 'BAST' },
];

function applyFilters(): void {
    const query: Record<string, string> = {};
    if (projectId.value !== ALL) query.project_id = projectId.value;
    if (activityType.value !== ALL) query.activity_type = activityType.value;
    if (status.value !== ALL) query.status = status.value;
    if (subcontractorId.value !== ALL)
        query.subcontractor_id = subcontractorId.value;
    if (machineTypeId.value !== ALL)
        query.machine_type_id = machineTypeId.value;
    if (province.value !== ALL) query.province = province.value;

    selectedSiteId.value = null;
    router.get(Actions.index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters(): void {
    projectId.value = ALL;
    activityType.value = ALL;
    status.value = ALL;
    subcontractorId.value = ALL;
    machineTypeId.value = ALL;
    province.value = ALL;
    applyFilters();
}

// --- Lifecycle ---
onMounted(() => {
    initMap();
    // Leaflet reads the container size at init time. The SidebarProvider uses
    // min-h-svh (not height), so flex-1 children may have 0 computed height
    // when Leaflet initializes. invalidateSize() forces a recalculation once
    // the browser has finished layout.
    nextTick(() => map?.invalidateSize());
});

onUnmounted(() => {
    map?.remove();
    map = null;
});

watch(
    () => props.sites,
    () => {
        drawMarkers();
    },
);

watch(showLeftSidebar, () => {
    nextTick(() => map?.invalidateSize());
});

watch(
    () => props.filters,
    (next) => {
        projectId.value = next?.project_id?.toString() ?? ALL;
        activityType.value = next?.activity_type ?? ALL;
        status.value = next?.status ?? ALL;
        subcontractorId.value = next?.subcontractor_id?.toString() ?? ALL;
        machineTypeId.value = next?.machine_type_id?.toString() ?? ALL;
        province.value = next?.province ?? ALL;
    },
    { deep: true },
);

// Completion label helper
function completionLabel(site: MapSite): string {
    if (site.total_assignments === 0) return '–';
    return `${site.completed_count}/${site.total_assignments}`;
}
</script>

<template>
    <Head title="Dashboard Map" />

    <!-- Full-height flex layout after the AppSidebarHeader (h-16) -->
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- Stats bar -->
        <div
            class="flex shrink-0 items-center gap-6 border-b border-sidebar-border/70 bg-background px-4 py-2"
        >
            <div class="flex items-center gap-2 text-sm">
                <MapPin class="size-4 text-muted-foreground" />
                <span class="font-medium">{{ stats.total_sites }}</span>
                <span class="text-muted-foreground">Sites</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span
                    class="inline-block size-2.5 rounded-full bg-green-500"
                ></span>
                <span class="font-medium">{{ stats.completed_sites }}</span>
                <span class="text-muted-foreground">Completed</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span
                    class="inline-block size-2.5 rounded-full bg-orange-500"
                ></span>
                <span class="font-medium">{{ stats.in_progress_sites }}</span>
                <span class="text-muted-foreground">In Progress</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="font-medium">{{ stats.total_assignments }}</span>
                <span class="text-muted-foreground">Assignments</span>
            </div>
        </div>

        <!-- Three-panel layout -->
        <div class="flex flex-1 overflow-hidden" style="min-height: 0">
            <!-- Left sidebar toggle button (visible when sidebar hidden) -->
            <button
                v-if="!showLeftSidebar"
                class="absolute left-0 top-1/2 z-[1000] flex -translate-y-1/2 items-center justify-center rounded-r-lg border border-l-0 bg-background p-2 shadow-md transition-colors hover:bg-accent"
                @click="showLeftSidebar = true"
            >
                <ChevronRight class="size-4" />
            </button>

            <!-- Left sidebar -->
            <div
                v-if="showLeftSidebar"
                class="flex w-80 shrink-0 flex-col overflow-hidden border-r border-sidebar-border/70 bg-background"
            >
                <!-- Sidebar header -->
                <div
                    class="flex shrink-0 items-center justify-between border-b border-sidebar-border/70 px-4 py-3"
                >
                    <span class="text-sm font-semibold">Filters</span>
                    <button
                        class="flex items-center justify-center rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        @click="showLeftSidebar = false"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>

                <!-- Filters -->
                <div
                    class="flex shrink-0 flex-col gap-2 border-b border-sidebar-border/70 p-3"
                >
                    <Select v-model="projectId" @update:model-value="applyFilters">
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="All Projects" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All Projects</SelectItem>
                            <SelectItem
                                v-for="p in projects"
                                :key="p.id"
                                :value="p.id.toString()"
                            >
                                {{ p.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        v-model="activityType"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="All Activity Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All Activity Types</SelectItem>
                            <SelectItem
                                v-for="a in activityTypeOptions"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ a.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="status" @update:model-value="applyFilters">
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All Statuses</SelectItem>
                            <SelectItem
                                v-for="s in statusOptions"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        v-model="subcontractorId"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="All Subcontractors" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All Subcontractors</SelectItem>
                            <SelectItem
                                v-for="s in subcontractors"
                                :key="s.id"
                                :value="s.id.toString()"
                            >
                                {{ s.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        v-model="machineTypeId"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="All Machine Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All Machine Types</SelectItem>
                            <SelectItem
                                v-for="m in machineTypes"
                                :key="m.id"
                                :value="m.id.toString()"
                            >
                                {{ m.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="province" @update:model-value="applyFilters">
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="All Provinces" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All Provinces</SelectItem>
                            <SelectItem
                                v-for="prov in provinces"
                                :key="prov"
                                :value="prov"
                            >
                                {{ prov }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="h-7 w-full gap-1.5 text-xs text-muted-foreground"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3" />
                        Reset Filters
                    </Button>
                </div>

                <!-- Site list -->
                <div class="flex-1 overflow-y-auto" style="min-height: 0">
                    <div
                        v-if="sites.length === 0"
                        class="flex flex-col items-center justify-center gap-2 p-8 text-center text-sm text-muted-foreground"
                    >
                        <MapPin class="size-8 opacity-30" />
                        <p>No sites with coordinates found.</p>
                    </div>

                    <button
                        v-for="site in sites"
                        :id="`site-item-${site.id}`"
                        :key="site.id"
                        class="flex w-full items-start gap-3 border-b border-sidebar-border/50 px-4 py-3 text-left transition-colors hover:bg-accent/50"
                        :class="{
                            'bg-accent': selectedSiteId === site.id,
                        }"
                        @click="selectSite(site.id)"
                    >
                        <!-- Completion dot -->
                        <span
                            class="mt-0.5 size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: getMarkerColor(site) }"
                        ></span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium">{{
                                    site.site_code
                                }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground">
                                    {{ completionLabel(site) }}
                                </span>
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ site.location_name }}
                                <template v-if="site.city">
                                    · {{ site.city }}</template
                                >
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Map container -->
            <div class="relative flex-1 overflow-hidden" style="min-height: 0">
                <div ref="mapContainer" class="h-full w-full"></div>

                <!-- Legend -->
                <div
                    class="absolute bottom-4 right-4 z-[999] rounded-lg border bg-background/95 px-3 py-2 text-xs shadow-md backdrop-blur-sm"
                >
                    <div class="mb-1.5 font-semibold text-foreground">Legend</div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-green-500"></span>
                            <span class="text-muted-foreground">Complete</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-orange-500"></span>
                            <span class="text-muted-foreground">In Progress</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-red-500"></span>
                            <span class="text-muted-foreground">Needs Revision</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-gray-400"></span>
                            <span class="text-muted-foreground">Not Started</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right panel: site detail -->
            <div
                v-if="selectedSite"
                class="flex w-80 shrink-0 flex-col overflow-hidden border-l border-sidebar-border/70 bg-background"
            >
                <!-- Panel header -->
                <div
                    class="flex shrink-0 items-start justify-between border-b border-sidebar-border/70 px-4 py-3"
                >
                    <div class="min-w-0 flex-1 pr-2">
                        <div class="text-sm font-semibold leading-tight">
                            {{ selectedSite.site_code }}
                        </div>
                        <div
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{ selectedSite.location_name }}
                        </div>
                    </div>
                    <button
                        class="shrink-0 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        @click="closeSitePanel"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Panel scrollable content -->
                <div class="flex-1 overflow-y-auto p-4" style="min-height: 0">
                    <!-- Site details -->
                    <div
                        class="mb-4 rounded-xl border border-sidebar-border/70 bg-muted/30 p-3"
                    >
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Site Details
                        </div>
                        <dl class="space-y-1.5 text-sm">
                            <div v-if="selectedSite.project" class="flex gap-2">
                                <dt class="w-24 shrink-0 text-muted-foreground">
                                    Project
                                </dt>
                                <dd class="min-w-0 font-medium">
                                    <Link
                                        :href="`/admin/projects/${selectedSite.project.id}`"
                                        class="truncate hover:underline"
                                    >
                                        {{ selectedSite.project.name }}
                                    </Link>
                                </dd>
                            </div>
                            <div
                                v-if="selectedSite.machine_type"
                                class="flex gap-2"
                            >
                                <dt class="w-24 shrink-0 text-muted-foreground">
                                    Machine
                                </dt>
                                <dd class="font-medium">
                                    {{ selectedSite.machine_type.name }}
                                </dd>
                            </div>
                            <div
                                v-if="selectedSite.city || selectedSite.province"
                                class="flex gap-2"
                            >
                                <dt class="w-24 shrink-0 text-muted-foreground">
                                    Location
                                </dt>
                                <dd class="text-muted-foreground">
                                    {{ [selectedSite.city, selectedSite.province].filter(Boolean).join(', ') }}
                                </dd>
                            </div>
                            <div v-if="selectedSite.address" class="flex gap-2">
                                <dt class="w-24 shrink-0 text-muted-foreground">
                                    Address
                                </dt>
                                <dd class="text-xs text-muted-foreground">
                                    {{ selectedSite.address }}
                                </dd>
                            </div>
                        </dl>

                        <!-- Completion bar -->
                        <div
                            v-if="selectedSite.total_assignments > 0"
                            class="mt-3"
                        >
                            <div
                                class="mb-1 flex justify-between text-xs text-muted-foreground"
                            >
                                <span>Completion</span>
                                <span
                                    >{{ selectedSite.completed_count }}/{{
                                        selectedSite.total_assignments
                                    }}</span
                                >
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-green-500 transition-all"
                                    :style="{
                                        width: `${selectedSite.completion_ratio * 100}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity cards -->
                    <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Activities
                        <span
                            v-if="activityType !== ALL"
                            class="ml-1 font-normal normal-case text-foreground"
                        >
                            (filtered)
                        </span>
                    </div>

                    <div
                        v-if="visibleAssignments.length === 0"
                        class="rounded-xl border border-dashed border-sidebar-border/70 px-4 py-6 text-center text-sm text-muted-foreground"
                    >
                        No assignments match the current filter.
                    </div>

                    <div class="flex flex-col gap-2">
                        <div
                            v-for="assignment in visibleAssignments"
                            :key="assignment.id"
                            class="rounded-xl border border-sidebar-border/70 bg-background p-3"
                        >
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <ActivityTypeBadge
                                    :activity-type="assignment.activity_type"
                                />
                                <StatusBadge :status="assignment.status" />
                            </div>
                            <div
                                v-if="assignment.subcontractor"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Building2 class="size-3 shrink-0" />
                                <span class="truncate">{{
                                    assignment.subcontractor.name
                                }}</span>
                            </div>
                            <div
                                v-else
                                class="text-xs text-muted-foreground/50"
                            >
                                No subcontractor assigned
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel footer -->
                <div
                    class="shrink-0 border-t border-sidebar-border/70 p-3"
                >
                    <Button as-child variant="default" size="sm" class="w-full">
                        <Link :href="SiteActions.edit({ site: selectedSite.id }).url">
                            Edit Site
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
