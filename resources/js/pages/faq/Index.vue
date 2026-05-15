<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    Bot,
    CheckSquare,
    ChevronRight,
    ClipboardList,
    FolderKanban,
    HelpCircle,
    Layers,
    Lock,
    MessageCircle,
    RefreshCw,
    Users,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, nextTick, ref } from 'vue';
import { dashboard } from '@/routes';

type UserRole = 'super_admin' | 'admin' | 'subcontractor';
type FaqRole = UserRole | 'all';

interface FaqStep {
    icon?: string;
    text: string;
    note?: string;
}

interface FaqQuestion {
    id: string;
    question: string;
    answer: string;
    steps?: FaqStep[];
    diagram?: 'status_survey' | 'status_construction' | 'status_pln' | 'status_bast' | 'workflow_subcontractor' | 'workflow_admin';
    tips?: string[];
    warning?: string;
}

interface FaqCategory {
    id: string;
    label: string;
    icon: Component;
    roles: FaqRole[];
    questions: FaqQuestion[];
}

interface ChatMessage {
    id: string;
    type: 'question' | 'answer';
    questionText?: string;
    question?: FaqQuestion;
}

interface StatusStep {
    step: string;
    label: string;
    color: keyof typeof statusColorMap;
}

const props = defineProps<{
    userRole: UserRole;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Pusat Bantuan', href: '/faq' },
        ],
    },
});

// Status color classes — full strings so Tailwind scanner includes them
const statusColorMap = {
    amber: 'rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400',
    blue: 'rounded-lg border border-blue-200 bg-blue-50 px-2 py-1.5 text-[11px] font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-400',
    cyan: 'rounded-lg border border-cyan-200 bg-cyan-50 px-2 py-1.5 text-[11px] font-semibold text-cyan-700 dark:border-cyan-800 dark:bg-cyan-950/30 dark:text-cyan-400',
    emerald: 'rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-[11px] font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400',
    violet: 'rounded-lg border border-violet-200 bg-violet-50 px-2 py-1.5 text-[11px] font-semibold text-violet-700 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-400',
    orange: 'rounded-lg border border-orange-200 bg-orange-50 px-2 py-1.5 text-[11px] font-semibold text-orange-700 dark:border-orange-800 dark:bg-orange-950/30 dark:text-orange-400',
    teal: 'rounded-lg border border-teal-200 bg-teal-50 px-2 py-1.5 text-[11px] font-semibold text-teal-700 dark:border-teal-800 dark:bg-teal-950/30 dark:text-teal-400',
    indigo: 'rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1.5 text-[11px] font-semibold text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-400',
    red: 'rounded-lg border border-red-200 bg-red-50 px-2 py-1.5 text-[11px] font-semibold text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-400',
} as const;

// Activity-specific status step data (matches AssignmentStatus enum exactly)
const surveyStatusSteps: StatusStep[] = [
    { step: 'PENDING', label: 'Baru dibuat', color: 'amber' },
    { step: 'SURVEY', label: 'Jadwal\nsurvey diisi', color: 'blue' },
    { step: 'DOCUMENT', label: 'Semua field\nlengkap', color: 'cyan' },
    { step: 'VERIFIED', label: 'Diverifikasi\nadmin', color: 'emerald' },
    { step: 'REPORTED', label: 'Masuk\nlaporan SSR', color: 'violet' },
];

const constructionStatusSteps: StatusStep[] = [
    { step: 'PENDING', label: 'Menunggu\nWO dari admin', color: 'amber' },
    { step: 'CONSTRUCTION', label: 'Tgl mulai\ndiisi', color: 'orange' },
    { step: 'MACHINE\nONSITE', label: 'SN mesin\n+ foto diisi', color: 'blue' },
    { step: 'DONE', label: 'Tgl selesai\ndiisi', color: 'teal' },
    { step: 'LIVE', label: 'Go-live PLN\n(admin isi)', color: 'indigo' },
    { step: 'VERIFIED', label: 'Diverifikasi\nadmin', color: 'emerald' },
    { step: 'REPORTED', label: 'Masuk\nlaporan', color: 'violet' },
];

const plnStatusSteps: StatusStep[] = [
    { step: 'PENDING', label: 'Baru dibuat', color: 'amber' },
    { step: 'REGISTRATION', label: 'SLO/NIDI/REG\ndiupload', color: 'orange' },
    { step: 'BILLING', label: 'Email BPUJL\ndikirim', color: 'blue' },
    { step: 'CONNECTION', label: 'BPUJL\ndiperoleh', color: 'cyan' },
    { step: 'KWH_DONE', label: 'ID Pelanggan\n+ kWh selesai', color: 'teal' },
    { step: 'VERIFIED', label: 'Diverifikasi\nadmin', color: 'emerald' },
    { step: 'REPORTED', label: 'Masuk\nlaporan', color: 'violet' },
];

const bastStatusSteps: StatusStep[] = [
    { step: 'PENDING', label: 'Baru dibuat\n/ pasca revisi', color: 'amber' },
    { step: 'SUBMITTED', label: 'Subkon\nklik Submit', color: 'blue' },
    { step: 'VERIFIED', label: 'Diverifikasi\nadmin', color: 'emerald' },
    { step: 'REPORTED', label: 'Masuk\nlaporan BAST', color: 'violet' },
];

// Workflow step data for flowcharts
const adminWorkflowSteps = [
    { step: 1, label: 'Buat\nProject' },
    { step: 2, label: 'Tambah\nSite' },
    { step: 3, label: 'Buat\nAssignment' },
    { step: 4, label: 'Pantau\nStatus' },
    { step: 5, label: 'Verifikasi' },
    { step: 6, label: 'Generate\nLaporan' },
];

const allCategories: FaqCategory[] = [
    // ── Login & Akses ──────────────────────────────────────────────────────────
    {
        id: 'login',
        label: 'Login & Akses',
        icon: Lock,
        roles: ['all'],
        questions: [
            {
                id: 'how-to-login',
                question: 'Bagaimana cara login ke NexPM?',
                answer: 'Untuk masuk ke NexPM, ikuti langkah-langkah berikut:',
                steps: [
                    { text: 'Buka browser dan akses URL aplikasi NexPM yang diberikan admin.' },
                    { text: 'Masukkan alamat email dan password akun Anda.' },
                    { text: 'Klik tombol "Sign In".' },
                    {
                        text: 'Anda diarahkan ke halaman sesuai role akun Anda.',
                        note: 'Admin/Super Admin → Dashboard. Subkontraktor → My Assignments.',
                    },
                ],
                tips: [
                    'Perhatikan huruf besar/kecil saat mengetik password',
                    'Semua role (Super Admin, Admin, Subkontraktor) menggunakan halaman login yang sama',
                ],
            },
            {
                id: 'forgot-password',
                question: 'Bagaimana jika saya lupa password?',
                answer: 'NexPM menyediakan fitur reset password melalui email:',
                steps: [
                    { text: 'Di halaman login, klik "Forgot your password?" di bawah form.' },
                    { text: 'Masukkan alamat email akun Anda, lalu klik "Email Password Reset Link".' },
                    { text: 'Buka inbox email dan klik tautan reset password yang dikirimkan.' },
                    { text: 'Masukkan dan konfirmasi password baru.', note: 'Tautan berlaku selama 60 menit.' },
                ],
                tips: ['Periksa folder Spam/Junk jika email tidak ada di inbox utama'],
            },
            {
                id: 'different-view',
                question: 'Mengapa tampilan menu saya berbeda dari rekan kerja?',
                answer:
                    'NexPM menggunakan sistem role-based access. Setiap role melihat menu yang berbeda sesuai fungsinya:\n\n• Super Admin — Akses penuh ke semua fitur: manajemen sistem, semua proyek, AI Assistant\n• Admin — Akses ke manajemen proyek, site, assignment, verifikasi, dan laporan (terbatas pada Main Contractor-nya sendiri)\n• Subkontraktor — Hanya melihat dan mengerjakan assignment yang ditugaskan kepada perusahaan mereka',
                tips: ['Jika akses tidak sesuai kebutuhan, hubungi Super Admin untuk penyesuaian role'],
            },
        ],
    },

    // ── Struktur Aplikasi ──────────────────────────────────────────────────────
    {
        id: 'structure',
        label: 'Struktur Aplikasi',
        icon: Layers,
        roles: ['all'],
        questions: [
            {
                id: 'hierarchy',
                question: 'Apa itu Project, Site, dan Assignment di NexPM?',
                answer: 'NexPM menggunakan hierarki data berikut dari atas ke bawah:',
                steps: [
                    {
                        icon: '🏢',
                        text: 'Project — Kumpulan site yang dikerjakan untuk satu klien. Contoh: "vGreen EVCS Batch 1 - Jakarta".',
                    },
                    {
                        icon: '📍',
                        text: 'Site — Satu lokasi fisik pemasangan charging station atau BSS. Setiap site memiliki Site Code unik.',
                    },
                    {
                        icon: '📋',
                        text: 'Assignment — Penugasan satu tipe aktivitas pada satu site kepada satu subkontraktor. Satu site dapat memiliki hingga 4 assignment — satu per tipe aktivitas.',
                        note: 'Assignment adalah unit kerja terkecil. Inilah yang diisi, diverifikasi, dan dilaporkan.',
                    },
                ],
                tips: [
                    'Satu site bisa memiliki beberapa assignment sekaligus — masing-masing untuk aktivitas berbeda dan bisa ditangani subkontraktor yang berbeda',
                    'Site Code adalah kode unik setiap site dan digunakan sebagai referensi utama dalam import CSV',
                ],
            },
            {
                id: 'activity-types',
                question: 'Apa saja tipe aktivitas (Activity Type) yang ada?',
                answer:
                    'Ada 4 tipe aktivitas dalam NexPM. Setiap assignment memiliki SATU tipe aktivitas yang tetap — tidak berubah setelah dibuat. Masing-masing memiliki form, status, dan alur kerja yang berbeda:',
                steps: [
                    {
                        icon: '🔍',
                        text: 'SURVEY — Survei lokasi fisik site. Subkontraktor mengisi data teknis, koordinat, jenis jaringan PLN, foto-foto site, Mockup 3D, dan dokumen BA Survey. Status naik otomatis saat field tersimpan.',
                    },
                    {
                        icon: '⚡',
                        text: 'PLN CONNECTION — Proses koneksi daya PLN. Subkontraktor mengurus perizinan (SLO, NIDI, REG), email BPUJL, dan instalasi kWh meter. Status naik otomatis per tahap proses.',
                    },
                    {
                        icon: '🏗️',
                        text: 'CONSTRUCTION — Pemasangan fisik charger/BSS. Terkunci hingga admin mengisi Nomor WO terlebih dahulu. Status naik otomatis saat field terisi.',
                    },
                    {
                        icon: '📋',
                        text: 'BAST — Berita Acara Serah Terima. Subkontraktor mengisi data commissioning dan foto checkpoint. SATU-SATUNYA tipe yang memerlukan klik "Submit for Review" secara manual — tidak otomatis.',
                    },
                ],
                warning:
                    'Survey, PLN Connection, dan Construction TIDAK memiliki tombol "Submit for Review". Status naik otomatis saat field tersimpan. Hanya BAST yang memerlukan Submit manual.',
            },
        ],
    },

    // ── Status per Aktivitas ───────────────────────────────────────────────────
    {
        id: 'status',
        label: 'Status per Aktivitas',
        icon: Activity,
        roles: ['all'],
        questions: [
            {
                id: 'status-survey',
                question: 'Apa saja status pada aktivitas Survey?',
                answer:
                    'Status Survey berjalan otomatis — tidak ada tombol Submit. Sistem memantau field yang tersimpan dan menaikkan status secara otomatis:',
                diagram: 'status_survey',
                steps: [
                    { icon: '⏳', text: 'PENDING — Assignment baru dibuat, belum ada data diisi.' },
                    { icon: '📅', text: 'SURVEY — Tanggal jadwal survey (ss_schedule_date) sudah tersimpan.' },
                    { icon: '📄', text: 'DOCUMENT — Semua field wajib terisi dan 5 foto sudah diupload. Siap diverifikasi admin.' },
                    { icon: '✅', text: 'VERIFIED — Admin telah memverifikasi seluruh data survey.' },
                    { icon: '📊', text: 'REPORTED — Assignment dimasukkan ke laporan SSR (Site Survey Report, format PDF).' },
                ],
                tips: ['Admin menerima notifikasi saat status mencapai DOCUMENT', 'Tidak ada REVISION untuk Survey — jika data perlu dikoreksi, admin dapat langsung mengedit di halaman detail'],
            },
            {
                id: 'status-construction',
                question: 'Apa saja status pada aktivitas Konstruksi?',
                answer:
                    'Status Konstruksi juga naik otomatis, tetapi ada prasyarat penting: Nomor WO harus diisi oleh Admin terlebih dahulu sebelum subkontraktor bisa melakukan apapun:',
                diagram: 'status_construction',
                steps: [
                    { icon: '🔒', text: 'PENDING — Assignment terkunci. Subkontraktor tidak bisa mengisi apapun sampai admin mengisi Nomor WO.' },
                    { icon: '🏗️', text: 'CONSTRUCTION — Tanggal mulai aktual (cons_actual_start_date) diisi subkontraktor.' },
                    { icon: '🔧', text: 'MACHINE_ONSITE — Serial number mesin dan foto mesin diisi oleh subkontraktor.' },
                    { icon: '✔️', text: 'DONE — Tanggal selesai aktual (cons_actual_done_date) diisi subkontraktor.' },
                    { icon: '⚡', text: 'LIVE — Tanggal go-live PLN diisi oleh Admin (bukan subkontraktor).' },
                    { icon: '✅', text: 'VERIFIED — Admin memverifikasi seluruh data konstruksi.' },
                    { icon: '📊', text: 'REPORTED — Assignment masuk ke laporan.' },
                ],
                warning:
                    'Assignment Konstruksi terkunci sepenuhnya sampai Admin mengisi Nomor WO via fitur "Construction Prerequisite". Subkontraktor yang melihat tanda terkunci harus meminta admin untuk mengisi WO Number terlebih dahulu.',
            },
            {
                id: 'status-pln',
                question: 'Apa saja status pada aktivitas PLN Connection?',
                answer:
                    'Status PLN Connection mengikuti tahapan proses perizinan PLN dan naik otomatis saat dokumen/data yang relevan tersimpan:',
                diagram: 'status_pln',
                steps: [
                    { icon: '⏳', text: 'PENDING — Assignment baru dibuat.' },
                    { icon: '📁', text: 'REGISTRATION — File SLO, NIDI, dan REG sudah diupload.' },
                    { icon: '📧', text: 'BILLING — Tanggal email ke BPUJL dikirim sudah diisi.' },
                    { icon: '🔌', text: 'CONNECTION — Tanggal BPUJL diperoleh sudah diisi.' },
                    { icon: '⚡', text: 'KWH_DONE — ID Pelanggan PLN, Type Rate, tanggal instalasi kWh meter, dan foto kWh meter sudah lengkap. Siap diverifikasi admin.' },
                    { icon: '✅', text: 'VERIFIED — Admin memverifikasi.' },
                    { icon: '📊', text: 'REPORTED — Masuk laporan.' },
                ],
                tips: ['Tidak ada REVISION untuk PLN Connection — admin dapat langsung mengedit data jika perlu koreksi'],
            },
            {
                id: 'status-bast',
                question: 'Apa saja status pada aktivitas BAST?',
                answer:
                    'BAST adalah satu-satunya aktivitas yang tidak naik status secara otomatis. Subkontraktor harus secara aktif menekan tombol "Submit for Review" untuk mengajukan ke admin. BAST juga satu-satunya yang memiliki alur REVISION:',
                diagram: 'status_bast',
                steps: [
                    { icon: '⏳', text: 'PENDING — Baru dibuat, atau setelah admin mengirim permintaan revisi.' },
                    { icon: '📤', text: 'SUBMITTED — Subkontraktor menekan tombol "Submit for Review". Admin dapat memverifikasi atau mengirim revisi.' },
                    { icon: '🔄', text: 'REVISION — (Opsional) Admin menemukan ketidaksesuaian dan mengirim catatan revisi. Status kembali ke PENDING, subkontraktor memperbaiki lalu submit ulang.' },
                    { icon: '✅', text: 'VERIFIED — Admin memverifikasi seluruh data dan foto checkpoint BAST.' },
                    { icon: '📊', text: 'REPORTED — Assignment masuk laporan BAST (format XLSX).' },
                ],
                warning:
                    'REVISION hanya ada di BAST. Survey, PLN Connection, dan Construction tidak memiliki alur REVISION — admin mengedit data langsung jika perlu koreksi.',
                tips: ['Admin juga bisa menekan "Submit for Review" atas nama subkontraktor dari halaman admin detail assignment'],
            },
        ],
    },

    // ── Alur Kerja Subkontraktor ───────────────────────────────────────────────
    {
        id: 'subcontractor-workflow',
        label: 'Alur Kerja Saya',
        icon: ClipboardList,
        roles: ['subcontractor'],
        questions: [
            {
                id: 'identify-activity',
                question: 'Bagaimana cara mengetahui aktivitas apa yang perlu saya kerjakan?',
                answer:
                    'Setiap assignment yang ditugaskan kepada Anda memiliki SATU tipe aktivitas yang tetap dan tidak berubah. Tipe ini menentukan form apa yang harus Anda isi:',
                steps: [
                    { text: 'Login → klik "My Assignments" di sidebar.' },
                    { text: 'Setiap assignment menampilkan badge tipe aktivitas: Survey / PLN Connection / Construction / BAST.' },
                    { text: 'Klik assignment untuk membuka halaman detail dan melihat form yang perlu diisi.' },
                    {
                        text: 'Status terkini ditampilkan di bagian atas halaman detail.',
                        note: 'Status PENDING berarti belum ada data yang diisi, atau data sudah dikembalikan untuk revisi (khusus BAST).',
                    },
                ],
                tips: [
                    'Anda mungkin memiliki beberapa assignment — masing-masing adalah satu tipe aktivitas di satu site yang berbeda',
                    'Satu site bisa memiliki beberapa assignment berbeda yang ditangani perusahaan berbeda',
                ],
            },
            {
                id: 'fill-survey',
                question: 'Bagaimana cara mengisi assignment Survey?',
                answer: 'Survey adalah aktivitas pengumpulan data teknis lokasi site. Status naik otomatis — tidak ada tombol Submit:',
                steps: [
                    { text: 'Buka assignment bertipe Survey.' },
                    { text: 'Isi field wajib: nama surveyor, nama & no. HP PIC lokasi, jenis charger, tanggal jadwal survey, jenis kabel, tipe jaringan PLN, dan jumlah parking slot.' },
                    {
                        text: 'Upload 5 foto wajib: tampak keseluruhan site, lahan parkir, sudut pandang lain, jaringan PLN terdekat, foto satelit GMaps.',
                        note: 'Setiap foto maksimal 10 MB.',
                    },
                    { text: 'Upload dokumen: Mockup 3D, Site Plan, dan BA Survey.' },
                    { text: 'Klik Simpan — status otomatis berubah ke SURVEY saat jadwal diisi, lalu ke DOCUMENT saat semua field & foto lengkap.' },
                ],
                tips: [
                    'Tidak ada tombol Submit untuk Survey — admin menerima notifikasi secara otomatis saat status mencapai DOCUMENT',
                    'Anda bisa menyimpan sebagian data dan melanjutkan nanti',
                ],
            },
            {
                id: 'fill-pln',
                question: 'Bagaimana cara mengisi assignment PLN Connection?',
                answer: 'PLN Connection adalah aktivitas pengurusan perizinan dan koneksi daya PLN. Status naik per tahap secara otomatis:',
                steps: [
                    { text: 'Buka assignment bertipe PLN Connection.' },
                    {
                        text: 'Upload File SLO, File NIDI, dan File REG → status berubah ke REGISTRATION.',
                        note: 'Ketiga file ini harus diupload sekaligus untuk status naik.',
                    },
                    { text: 'Isi tanggal pengiriman email ke BPUJL → status berubah ke BILLING.' },
                    { text: 'Isi tanggal BPUJL diperoleh → status berubah ke CONNECTION.' },
                    { text: 'Isi ID Pelanggan PLN, Type Rate, tanggal instalasi kWh meter, dan upload foto kWh meter → status berubah ke KWH_DONE.' },
                    { text: 'Isi catatan progres untuk mencatat perkembangan terkini.' },
                ],
                tips: ['Tidak ada tombol Submit — status naik otomatis setiap kali data tersimpan', 'Lengkapi setiap tahap secara berurutan untuk status naik dengan benar'],
            },
            {
                id: 'fill-construction',
                question: 'Bagaimana cara mengisi assignment Konstruksi?',
                answer:
                    'Construction adalah aktivitas pemasangan fisik. Penting: assignment ini TERKUNCI hingga Admin mengisi Nomor WO. Jika Anda melihat tanda gembok, hubungi admin Anda:',
                steps: [
                    {
                        text: 'Tunggu Admin mengisi Nomor WO (Construction Prerequisite). Nomor WO akan tampil di halaman assignment setelah diisi admin.',
                        note: 'Selama Nomor WO belum diisi admin, seluruh form tidak bisa diedit.',
                    },
                    { text: 'Setelah tidak terkunci, isi tanggal mulai aktual konstruksi → status berubah ke CONSTRUCTION.' },
                    { text: 'Isi serial number mesin dan upload foto mesin (foto SN) → status berubah ke MACHINE_ONSITE.' },
                    { text: 'Isi tanggal selesai aktual → status berubah ke DONE.' },
                    { text: 'Upload minimal 1 foto progres konstruksi (maksimal 2 foto).' },
                    { text: 'Isi catatan progres.' },
                ],
                tips: [
                    'Tanggal go-live PLN (LIVE) diisi oleh Admin, bukan subkontraktor — Anda tidak perlu mengisi bagian itu',
                    'Tidak ada tombol Submit — status naik otomatis',
                ],
                warning: 'Jika assignment Konstruksi Anda masih terkunci (ada ikon gembok), artinya Admin belum mengisi Nomor WO. Hubungi Admin Anda untuk segera mengisi prerequisite ini.',
            },
            {
                id: 'fill-bast',
                question: 'Bagaimana cara mengisi dan mengirim assignment BAST?',
                answer:
                    'BAST (Berita Acara Serah Terima) adalah satu-satunya aktivitas yang memerlukan klik "Submit for Review" secara manual. Data tidak otomatis terkirim ke admin:',
                steps: [
                    { text: 'Buka assignment bertipe BAST.' },
                    { text: 'Isi data BAST: nomor SIM card, nama provider, dan tanggal commissioning.' },
                    {
                        text: 'Upload foto untuk setiap checkpoint yang tersedia di halaman. Setiap checkpoint memerlukan foto sebagai bukti.',
                        note: 'Jumlah dan jenis checkpoint tergantung tipe site (EVCS atau BSS).',
                    },
                    { text: 'Setelah semua checkpoint difoto, cari tombol "Submit for Review" di halaman.' },
                    { text: 'Klik tombol tersebut dan konfirmasi → status berubah ke SUBMITTED, admin menerima notifikasi.' },
                ],
                warning:
                    'BAST TIDAK otomatis terkirim. Anda harus aktif menekan tombol "Submit for Review". Tanpa klik Submit, admin tidak akan melihat pekerjaan Anda selesai.',
                tips: ['Tombol Submit muncul hanya saat status PENDING atau REVISION (setelah revisi)'],
            },
            {
                id: 'bast-revision',
                question: 'Apa arti status "Revision" pada assignment BAST dan apa yang harus dilakukan?',
                answer:
                    'Jika BAST berstatus REVISION, Admin menemukan ketidaksesuaian atau kekurangan setelah Anda submit dan mengembalikan assignment disertai catatan:',
                steps: [
                    { text: 'Buka assignment BAST dengan status REVISION.' },
                    { text: 'Baca catatan revisi dari admin yang tampil di bagian atas halaman — catatan ini menjelaskan apa yang perlu diperbaiki.' },
                    { text: 'Perbaiki data atau foto checkpoint sesuai catatan admin.' },
                    {
                        text: 'Klik kembali tombol "Submit for Review" untuk mengirim ulang ke admin.',
                        note: 'Prosesnya sama persis seperti submit pertama kali.',
                    },
                ],
                tips: [
                    'Baca catatan revisi dengan teliti sebelum melakukan perubahan',
                    'Jika catatan tidak jelas, hubungi admin untuk klarifikasi sebelum mengubah data',
                    'REVISION hanya ada untuk aktivitas BAST — Survey, PLN, dan Construction tidak memiliki alur revisi',
                ],
            },
        ],
    },

    // ── Manajemen Proyek ──────────────────────────────────────────────────────
    {
        id: 'project-management',
        label: 'Manajemen Proyek',
        icon: FolderKanban,
        roles: ['admin', 'super_admin'],
        questions: [
            {
                id: 'overview-admin-workflow',
                question: 'Apa alur kerja lengkap seorang Admin di NexPM?',
                answer: 'Sebagai Admin, berikut alur kerja dari setup proyek hingga pelaporan:',
                diagram: 'workflow_admin',
                steps: [
                    { icon: '1️⃣', text: 'Buat Project — Masukkan nama proyek, klien, dan main contractor.' },
                    { icon: '2️⃣', text: 'Tambah Site — Daftarkan lokasi site satu per satu atau import massal via CSV.' },
                    { icon: '3️⃣', text: 'Buat Assignment — Tugaskan subkontraktor ke site dengan menentukan tipe aktivitas.' },
                    { icon: '4️⃣', text: 'Pantau Status — Monitor progress assignment melalui halaman Assignments.' },
                    { icon: '5️⃣', text: 'Verifikasi — Review dan verifikasi data dari subkontraktor saat assignment mencapai status verifiable (DOCUMENT / SUBMITTED / LIVE / KWH_DONE).' },
                    { icon: '6️⃣', text: 'Generate Laporan — Buat laporan SSR, BAST, atau Daily Monitoring sesuai kebutuhan.' },
                ],
            },
            {
                id: 'create-project',
                question: 'Bagaimana cara membuat project baru?',
                answer: 'Project adalah wadah utama yang mengelompokkan semua site pekerjaan:',
                steps: [
                    { text: 'Di sidebar, klik menu "Projects".' },
                    { text: 'Klik tombol "Add Project".' },
                    { text: 'Isi nama project, pilih client, dan pilih main contractor.' },
                    { text: 'Klik "Simpan". Project siap ditambahkan site.', note: 'Project dapat memiliki tanggal mulai dan selesai untuk tracking deadline.' },
                ],
            },
            {
                id: 'add-sites',
                question: 'Bagaimana cara menambahkan site ke project?',
                answer: 'Site adalah satu lokasi fisik pemasangan. Setiap site di sistem memiliki Site Code unik:',
                steps: [
                    { text: 'Buka detail project yang ingin ditambahkan site-nya.' },
                    { text: 'Klik tab "Sites".' },
                    { text: 'Untuk satu site: klik "Add Site", isi Site Code (wajib unik), nama lokasi, dan data lainnya.' },
                    {
                        text: 'Untuk banyak site: klik "Import CSV", unduh template, isi data di template, lalu upload.',
                        note: 'CSV import menggunakan Site Code sebagai kunci upsert — site yang sudah ada akan diupdate, yang baru akan dibuat.',
                    },
                    { text: 'Setelah site ditambahkan, Anda dapat membuat assignment untuk setiap site.' },
                ],
                tips: [
                    'Import CSV sangat direkomendasikan untuk project dengan banyak site',
                    'Site Code harus unik di seluruh sistem — bukan hanya per project',
                ],
            },
            {
                id: 'create-assignment',
                question: 'Bagaimana cara membuat assignment untuk sebuah site?',
                answer: 'Assignment dibuat per site per tipe aktivitas. Satu site bisa memiliki hingga 4 assignment:',
                steps: [
                    { text: 'Buka detail project → tab "Sites".' },
                    { text: 'Klik pada site yang ingin dibuat assignmentnya.' },
                    { text: 'Di halaman site, klik "Assign Subcontractor" atau "Add Assignment".' },
                    { text: 'Pilih tipe aktivitas (Survey, PLN Connection, Construction, BAST) dan pilih subkontraktor.' },
                    {
                        text: 'Klik konfirmasi untuk membuat assignment.',
                        note: 'Hanya boleh ada 1 assignment per tipe aktivitas per site. Sistem akan menolak duplikat.',
                    },
                    {
                        text: 'Untuk assignment Konstruksi: setelah dibuat, segera isi Nomor WO via fitur "Construction Prerequisite" agar subkontraktor bisa mulai bekerja.',
                        note: 'Tanpa Nomor WO, subkontraktor Construction tidak bisa mengisi data apapun.',
                    },
                ],
                tips: [
                    'Subkontraktor yang berbeda bisa menangani aktivitas berbeda di site yang sama',
                    'Pastikan subkontraktor sudah terdaftar di menu Subcontractors sebelum membuat assignment',
                ],
            },
            {
                id: 'construction-prerequisite',
                question: 'Bagaimana cara mengisi Nomor WO (Construction Prerequisite)?',
                answer:
                    'Nomor WO adalah prasyarat wajib untuk aktivitas Construction. Tanpa ini, subkontraktor tidak dapat mengisi data apapun pada assignment tersebut:',
                steps: [
                    { text: 'Buka halaman detail assignment bertipe Construction.' },
                    { text: 'Cari tombol atau bagian "Construction Prerequisite" di halaman.' },
                    { text: 'Isi Nomor WO (field wajib). Isi juga Project Status dan Setup Approval Date jika tersedia (opsional).' },
                    { text: 'Klik Simpan.' },
                    {
                        text: 'Assignment Konstruksi kini terbuka — subkontraktor bisa langsung mulai mengisi data.',
                        note: 'Subkontraktor akan melihat Nomor WO tampil di halaman mereka sebagai konfirmasi assignment sudah dibuka.',
                    },
                ],
                warning: 'Jangan lupa mengisi Construction Prerequisite segera setelah membuat assignment Construction. Subkontraktor tidak bisa melakukan apapun sampai ini diisi.',
            },
        ],
    },

    // ── Verifikasi & Laporan ──────────────────────────────────────────────────
    {
        id: 'verification',
        label: 'Verifikasi & Laporan',
        icon: CheckSquare,
        roles: ['admin', 'super_admin'],
        questions: [
            {
                id: 'verify-assignment',
                question: 'Bagaimana cara memverifikasi assignment?',
                answer:
                    'Setiap tipe aktivitas mencapai status verifiable yang berbeda. Verifikasi tersedia saat assignment berada di status berikut:',
                steps: [
                    { icon: '🔍', text: 'Survey → verifiable saat status DOCUMENT (semua field & foto lengkap).' },
                    { icon: '📋', text: 'BAST → verifiable saat status SUBMITTED (subkontraktor sudah klik Submit).' },
                    { icon: '🏗️', text: 'Construction → verifiable saat status LIVE (tanggal go-live PLN sudah diisi admin).' },
                    { icon: '⚡', text: 'PLN Connection → verifiable saat status KWH_DONE (ID Pelanggan & kWh meter lengkap).' },
                ],
                tips: ['Setelah menemukan assignment di status verifiable, buka halaman detailnya, review semua data, lalu klik tombol "Verify" dan konfirmasi'],
                warning: 'Verifikasi tidak bisa dibatalkan. Pastikan review data sudah lengkap sebelum menekan Verify.',
            },
            {
                id: 'request-revision',
                question: 'Bagaimana cara mengirim permintaan revisi ke subkontraktor?',
                answer:
                    'Permintaan revisi HANYA tersedia untuk aktivitas BAST dalam status SUBMITTED. Untuk Survey, PLN Connection, dan Construction, Admin dapat langsung mengedit data via halaman detail tanpa perlu mengirim revisi:',
                steps: [
                    { text: 'Buka assignment BAST yang berstatus SUBMITTED.' },
                    { text: 'Klik tombol "Request Revision".' },
                    {
                        text: 'Tulis catatan revisi yang spesifik — sebutkan checkpoint mana atau data apa yang perlu diperbaiki.',
                        note: 'Catatan ini langsung terlihat oleh subkontraktor.',
                    },
                    { text: 'Klik Kirim → status berubah ke REVISION dan subkontraktor mendapat notifikasi.' },
                ],
                tips: [
                    'Berikan catatan yang spesifik — sebutkan nama checkpoint atau field yang bermasalah',
                    'Untuk Survey/PLN/Construction: gunakan fitur edit langsung di halaman admin — tidak perlu mengirim revisi ke subkontraktor',
                ],
                warning:
                    'Tombol "Request Revision" hanya muncul untuk BAST berstatus SUBMITTED. Jika tidak terlihat, berarti tipe aktivitas atau status tidak memenuhi syarat.',
            },
            {
                id: 'report-ssr',
                question: 'Bagaimana cara membuat Laporan SSR (Site Survey Report)?',
                answer: 'Laporan SSR adalah laporan PDF yang merangkum hasil survei site. Hanya untuk assignment Survey berstatus VERIFIED:',
                steps: [
                    { text: 'Pastikan ada assignment Survey berstatus VERIFIED yang belum dilaporkan.' },
                    { text: 'Klik menu "Reports" di sidebar.' },
                    { text: 'Pilih tipe "SSR".' },
                    { text: 'Pilih assignment Survey VERIFIED yang ingin dimasukkan ke laporan.' },
                    {
                        text: 'Klik "Generate". Sistem menghasilkan PDF dan assignment masuk ke tab History.',
                        note: 'Assignment Survey yang masuk laporan SSR berubah status menjadi REPORTED.',
                    },
                    { text: 'Download PDF dari halaman History.' },
                ],
            },
            {
                id: 'report-bast',
                question: 'Bagaimana cara membuat Laporan BAST?',
                answer: 'Laporan BAST adalah file XLSX dokumentasi serah terima per site. Hanya untuk assignment BAST berstatus VERIFIED:',
                steps: [
                    { text: 'Pastikan ada assignment BAST berstatus VERIFIED yang belum dilaporkan.' },
                    { text: 'Klik menu "Reports" → pilih tipe "BAST".' },
                    { text: 'Pilih assignment BAST VERIFIED yang ingin dimasukkan.' },
                    {
                        text: 'Klik "Generate". Jika satu assignment → file XLSX tunggal. Jika lebih dari satu → file ZIP berisi semua XLSX.',
                        note: 'Assignment BAST yang masuk laporan berubah status menjadi REPORTED.',
                    },
                    { text: 'Download file dari halaman History.' },
                ],
            },
            {
                id: 'report-daily',
                question: 'Bagaimana cara membuat Daily Monitoring Report?',
                answer:
                    'Daily Monitoring adalah laporan XLSX ringkasan harian yang mencakup SEMUA assignment aktif tanpa filter status — berbeda dari SSR dan BAST yang hanya mengambil assignment VERIFIED:',
                steps: [
                    { text: 'Klik menu "Reports" → pilih tipe "Daily Monitoring".' },
                    { text: 'Klik "Generate Report". Sistem otomatis mengumpulkan SEMUA assignment yang tidak berstatus DROP.' },
                    {
                        text: 'Laporan berisi 2 sheet: satu untuk EVCS, satu untuk BSS — mencakup semua kolom site, status per aktivitas, dan milestone pembayaran.',
                        note: 'Daily Monitoring tidak mengubah status assignment.',
                    },
                    { text: 'Download XLSX dari halaman History.' },
                ],
                tips: [
                    'Daily Monitoring tidak memerlukan assignment dalam status tertentu — semua assignment aktif masuk',
                    'Generate setiap hari atau sesuai kebutuhan pelaporan kepada klien',
                ],
            },
        ],
    },

    // ── Manajemen Pengguna ────────────────────────────────────────────────────
    {
        id: 'user-management',
        label: 'Manajemen Pengguna',
        icon: Users,
        roles: ['super_admin'],
        questions: [
            {
                id: 'add-user',
                question: 'Bagaimana cara menambahkan user baru?',
                answer: 'Hanya Super Admin yang dapat membuat akun pengguna baru:',
                steps: [
                    { text: 'Di sidebar, klik menu "Users".' },
                    { text: 'Klik tombol "Add User".' },
                    { text: 'Isi nama lengkap, email, pilih role, dan buat password awal.' },
                    {
                        text: 'Untuk role Subkontraktor: pilih perusahaan subcontractor yang sudah terdaftar di sistem.',
                        note: 'Satu perusahaan subcontractor hanya boleh punya satu akun login.',
                    },
                    { text: 'Klik "Simpan". User bisa langsung login.' },
                ],
                tips: [
                    'Pastikan subcontractor company sudah terdaftar di menu Subcontractors sebelum membuat akun subkontraktor',
                    'Admin bisa membuat akun subkontraktor jika diizinkan oleh Super Admin',
                ],
            },
            {
                id: 'edit-user',
                question: 'Bagaimana cara mengubah data atau role user?',
                answer: 'Super Admin dapat mengubah informasi dan role pengguna kapan saja:',
                steps: [
                    { text: 'Buka menu "Users".' },
                    { text: 'Cari user yang ingin diubah.' },
                    { text: 'Klik ikon edit (pensil) di baris user tersebut.' },
                    { text: 'Ubah data: nama, email, role, atau subcontractor company.' },
                    { text: 'Klik "Simpan".' },
                ],
                warning: 'Mengubah role user akan segera mengubah hak akses mereka ke seluruh sistem. Perubahan berlaku instan.',
            },
            {
                id: 'role-differences',
                question: 'Apa perbedaan role Admin dan Super Admin?',
                answer:
                    'Super Admin memiliki semua akses Admin, ditambah kemampuan eksklusif:\n\n✦ Manajemen User — Tambah dan kelola semua akun pengguna\n✦ Cross-tenant — Bisa melihat dan mengelola data semua Main Contractor\n✦ AI Assistant — Akses asisten AI untuk analisis data proyek\n✦ App Settings — Konfigurasi pengaturan aplikasi (Company Settings)\n✦ Import Manual — Import data proyek secara massal\n✦ Legacy Reports — Upload laporan lama ke assignment\n\nAdmin dapat:\n✦ Membuat dan mengelola project, site, dan assignment di Main Contractor-nya\n✦ Mengisi Construction Prerequisite (Nomor WO)\n✦ Memverifikasi pekerjaan subkontraktor\n✦ Mengirim permintaan revisi (BAST)\n✦ Generate laporan (SSR, BAST, Daily Monitoring)\n✦ Edit data subkontraktor langsung dari halaman admin',
            },
        ],
    },

    // ── Asisten AI ───────────────────────────────────────────────────────────
    {
        id: 'ai-assistant',
        label: 'Asisten AI',
        icon: Bot,
        roles: ['super_admin'],
        questions: [
            {
                id: 'how-to-use-ai',
                question: 'Bagaimana cara menggunakan AI Assistant?',
                answer: 'AI Assistant NexPM membantu Super Admin menganalisis kondisi proyek secara cepat:',
                steps: [
                    { text: 'Klik ikon sparkle (✨) di header aplikasi, pojok kanan atas.' },
                    { text: 'Panel AI Assistant muncul dari sisi kanan layar.' },
                    { text: 'Ketikkan pertanyaan dalam Bahasa Indonesia atau Inggris.' },
                    {
                        text: 'Tekan Enter. AI menganalisis data real-time dari sistem dan menjawab.',
                        note: 'AI hanya membaca data — tidak bisa mengubah, membuat, atau menghapus data apapun.',
                    },
                ],
                tips: [
                    'Gunakan quick prompts yang tersedia untuk pertanyaan umum',
                    'AI menjawab dalam bahasa yang sama dengan pertanyaan Anda',
                    'Tersedia maksimal 20 pertanyaan per menit',
                ],
            },
            {
                id: 'ai-questions',
                question: 'Pertanyaan apa saja yang bisa ditanyakan ke AI?',
                answer: 'AI Assistant dirancang untuk analisis dan monitoring proyek. Contoh pertanyaan efektif:',
                steps: [
                    { icon: '📊', text: '"Briefing proyek hari ini" — Ringkasan kondisi dan statistik semua proyek aktif.' },
                    { icon: '⚠️', text: '"Apa risiko terbesar saat ini?" — Identifikasi assignment yang berisiko tinggi.' },
                    { icon: '🚧', text: '"Assignment mana yang terblokir?" — Temukan assignment yang stuck atau sudah terlambat.' },
                    { icon: '📋', text: '"Assignment mana yang siap dilaporkan?" — Daftar assignment VERIFIED yang belum masuk laporan.' },
                    { icon: '🏗️', text: '"Subkontraktor mana yang paling banyak masalah?" — Analisis berdasarkan blocker per subkontraktor.' },
                    { icon: '🔍', text: '"Ada workflow gap apa?" — Deteksi inkonsistensi data dan masalah alur kerja di seluruh sistem.' },
                ],
                tips: ['Buka halaman assignment atau site tertentu, lalu tanya AI untuk analisis yang lebih terfokus pada konteks halaman tersebut'],
            },
        ],
    },
];

const selectedCategoryId = ref<string | null>(null);
const chatMessages = ref<ChatMessage[]>([]);
const chatContainer = ref<HTMLElement | null>(null);
const answeredQuestionIds = ref<Set<string>>(new Set());

const categories = computed(() =>
    allCategories.filter((cat) => cat.roles.includes('all') || cat.roles.includes(props.userRole as FaqRole)),
);

const selectedCategory = computed(() => categories.value.find((c) => c.id === selectedCategoryId.value) ?? categories.value[0] ?? null);

if (categories.value.length > 0) {
    selectedCategoryId.value = categories.value[0].id;
}

function selectCategory(categoryId: string) {
    selectedCategoryId.value = categoryId;
}

async function clickQuestion(question: FaqQuestion, category: FaqCategory) {
    const msgId = `${category.id}-${question.id}-${Date.now()}`;
    chatMessages.value.push({ id: `q-${msgId}`, type: 'question', questionText: question.question });
    chatMessages.value.push({ id: `a-${msgId}`, type: 'answer', question });
    answeredQuestionIds.value.add(question.id);
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

function resetChat() {
    chatMessages.value = [];
    answeredQuestionIds.value = new Set();
}

const quickStartQuestions = computed(() => {
    if (props.userRole === 'subcontractor') {
        const struct = allCategories.find((c) => c.id === 'structure');
        const wf = allCategories.find((c) => c.id === 'subcontractor-workflow');
        const items: { question: FaqQuestion; category: FaqCategory }[] = [];
        if (struct) items.push({ question: struct.questions[0], category: struct });
        if (wf) items.push({ question: wf.questions[0], category: wf });
        if (wf) items.push({ question: wf.questions[4], category: wf }); // BAST question
        return items;
    }
    if (props.userRole === 'admin') {
        const pm = allCategories.find((c) => c.id === 'project-management');
        const ver = allCategories.find((c) => c.id === 'verification');
        const items: { question: FaqQuestion; category: FaqCategory }[] = [];
        if (pm) items.push({ question: pm.questions[0], category: pm });
        if (ver) items.push({ question: ver.questions[0], category: ver });
        return items;
    }
    // super admin
    const struct = allCategories.find((c) => c.id === 'structure');
    const status = allCategories.find((c) => c.id === 'status');
    const ai = allCategories.find((c) => c.id === 'ai-assistant');
    const items: { question: FaqQuestion; category: FaqCategory }[] = [];
    if (struct) items.push({ question: struct.questions[1], category: struct }); // activity types
    if (status) items.push({ question: status.questions[3], category: status }); // BAST status
    if (ai) items.push({ question: ai.questions[0], category: ai });
    return items;
});
</script>

<template>
    <Head title="Pusat Bantuan" />

    <div class="flex h-[calc(100vh-8.5rem)] gap-4 p-6 pt-2">
        <!-- ── Left Panel: Categories + Questions ── -->
        <div class="flex w-72 shrink-0 flex-col overflow-hidden rounded-xl border bg-card">
            <!-- Category list -->
            <div class="shrink-0 border-b p-3">
                <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Topik</p>
                <nav class="space-y-0.5">
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                        :class="
                            selectedCategory?.id === cat.id
                                ? 'bg-primary text-primary-foreground font-medium'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="selectCategory(cat.id)"
                    >
                        <component :is="cat.icon" class="h-4 w-4 shrink-0" />
                        <span>{{ cat.label }}</span>
                    </button>
                </nav>
            </div>

            <!-- Question list -->
            <div class="flex-1 overflow-y-auto p-3">
                <p class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Pertanyaan</p>
                <div v-if="selectedCategory" class="space-y-0.5">
                    <button
                        v-for="question in selectedCategory.questions"
                        :key="question.id"
                        class="flex w-full items-start gap-2 rounded-lg px-3 py-2.5 text-left text-sm transition-colors"
                        :class="
                            answeredQuestionIds.has(question.id)
                                ? 'bg-primary/10 text-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="clickQuestion(question, selectedCategory)"
                    >
                        <MessageCircle
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                            :class="answeredQuestionIds.has(question.id) ? 'text-primary' : ''"
                        />
                        <span class="leading-snug">{{ question.question }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Right Panel: Chat Area ── -->
        <div class="flex flex-1 flex-col overflow-hidden rounded-xl border bg-card">
            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between border-b px-5 py-3.5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <HelpCircle class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Pusat Bantuan NexPM</p>
                        <p class="text-xs text-muted-foreground">Klik pertanyaan di panel kiri untuk membaca jawaban</p>
                    </div>
                </div>
                <button
                    v-if="chatMessages.length > 0"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    @click="resetChat"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    Reset
                </button>
            </div>

            <!-- Chat body -->
            <div ref="chatContainer" class="flex-1 overflow-y-auto p-5">
                <!-- ── Welcome state ── -->
                <div v-if="chatMessages.length === 0" class="flex h-full flex-col items-center justify-center px-4 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
                        <HelpCircle class="h-8 w-8 text-primary" />
                    </div>
                    <h3 class="mb-1.5 text-base font-semibold">Selamat datang di Pusat Bantuan</h3>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Pilih topik di panel kiri, lalu klik pertanyaan yang ingin Anda ketahui.
                    </p>
                    <div class="mt-5 flex flex-wrap justify-center gap-2">
                        <button
                            v-for="item in quickStartQuestions"
                            :key="item.question.id"
                            class="rounded-full border px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="clickQuestion(item.question, item.category)"
                        >
                            {{ item.question.question }}
                        </button>
                    </div>
                </div>

                <!-- ── Chat messages ── -->
                <div v-else class="space-y-5">
                    <template v-for="msg in chatMessages" :key="msg.id">
                        <!-- User question bubble -->
                        <div v-if="msg.type === 'question'" class="flex justify-end">
                            <div class="max-w-sm rounded-2xl rounded-tr-sm bg-primary px-4 py-2.5 text-sm text-primary-foreground">
                                {{ msg.questionText }}
                            </div>
                        </div>

                        <!-- Answer bubble -->
                        <div v-else-if="msg.type === 'answer' && msg.question" class="flex justify-start">
                            <div class="max-w-2xl space-y-3.5 rounded-2xl rounded-tl-sm bg-muted px-5 py-4 text-sm">
                                <!-- Answer prose -->
                                <p class="whitespace-pre-wrap leading-relaxed text-foreground">{{ msg.question.answer }}</p>

                                <!-- ── Survey Status Diagram ── -->
                                <div v-if="msg.question.diagram === 'status_survey'" class="overflow-x-auto rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Status — Aktivitas Survey (otomatis)
                                    </p>
                                    <div class="flex min-w-max flex-wrap items-start gap-1.5">
                                        <template v-for="(s, i) in surveyStatusSteps" :key="s.step">
                                            <div class="flex flex-col items-center gap-1.5 w-[72px]">
                                                <span :class="statusColorMap[s.color]" class="text-center">{{ s.step }}</span>
                                                <p class="whitespace-pre-line text-center text-[10px] leading-tight text-muted-foreground">{{ s.label }}</p>
                                            </div>
                                            <div v-if="i < surveyStatusSteps.length - 1" class="mt-[11px] flex shrink-0 items-center">
                                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ── Construction Status Diagram ── -->
                                <div v-else-if="msg.question.diagram === 'status_construction'" class="overflow-x-auto rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Status — Aktivitas Konstruksi (otomatis, setelah WO diisi)
                                    </p>
                                    <div class="flex min-w-max items-start gap-1.5">
                                        <template v-for="(s, i) in constructionStatusSteps" :key="s.step">
                                            <div class="flex flex-col items-center gap-1.5 w-[72px]">
                                                <span :class="statusColorMap[s.color]" class="whitespace-pre-line text-center">{{ s.step }}</span>
                                                <p class="whitespace-pre-line text-center text-[10px] leading-tight text-muted-foreground">{{ s.label }}</p>
                                            </div>
                                            <div v-if="i < constructionStatusSteps.length - 1" class="mt-[11px] flex shrink-0 items-center">
                                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ── PLN Status Diagram ── -->
                                <div v-else-if="msg.question.diagram === 'status_pln'" class="overflow-x-auto rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Status — Aktivitas PLN Connection (otomatis)
                                    </p>
                                    <div class="flex min-w-max items-start gap-1.5">
                                        <template v-for="(s, i) in plnStatusSteps" :key="s.step">
                                            <div class="flex flex-col items-center gap-1.5 w-[72px]">
                                                <span :class="statusColorMap[s.color]" class="text-center">{{ s.step }}</span>
                                                <p class="whitespace-pre-line text-center text-[10px] leading-tight text-muted-foreground">{{ s.label }}</p>
                                            </div>
                                            <div v-if="i < plnStatusSteps.length - 1" class="mt-[11px] flex shrink-0 items-center">
                                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ── BAST Status Diagram ── -->
                                <div v-else-if="msg.question.diagram === 'status_bast'" class="rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Status — Aktivitas BAST (manual Submit diperlukan)
                                    </p>
                                    <!-- Main flow -->
                                    <div class="flex flex-wrap items-start gap-1.5">
                                        <template v-for="(s, i) in bastStatusSteps" :key="s.step">
                                            <div class="flex flex-col items-center gap-1.5 w-[72px]">
                                                <span :class="statusColorMap[s.color]" class="text-center">{{ s.step }}</span>
                                                <p class="whitespace-pre-line text-center text-[10px] leading-tight text-muted-foreground">{{ s.label }}</p>
                                            </div>
                                            <div v-if="i < bastStatusSteps.length - 1" class="mt-[11px] flex shrink-0 items-center">
                                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                            </div>
                                        </template>
                                    </div>
                                    <!-- REVISION branch -->
                                    <div class="mt-3 flex items-center gap-2.5 border-t pt-3">
                                        <span class="text-xs text-muted-foreground">Opsional:</span>
                                        <span :class="statusColorMap['blue']">SUBMITTED</span>
                                        <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                        <span :class="statusColorMap['red']">REVISION</span>
                                        <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                        <span :class="statusColorMap['amber']">PENDING</span>
                                        <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/50" />
                                        <span class="text-xs italic text-muted-foreground">subkon submit ulang → SUBMITTED lagi</span>
                                    </div>
                                </div>

                                <!-- ── Admin Workflow Diagram ── -->
                                <div v-else-if="msg.question.diagram === 'workflow_admin'" class="overflow-x-auto rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Lengkap Admin
                                    </p>
                                    <div class="flex min-w-max items-start gap-1.5">
                                        <template v-for="(s, i) in adminWorkflowSteps" :key="s.step">
                                            <div class="flex w-[68px] flex-col items-center gap-2">
                                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground shadow-sm">
                                                    {{ s.step }}
                                                </div>
                                                <p class="whitespace-pre-line text-center text-[11px] leading-tight text-muted-foreground">
                                                    {{ s.label }}
                                                </p>
                                            </div>
                                            <div v-if="i < adminWorkflowSteps.length - 1" class="mt-[18px] h-px w-4 shrink-0 bg-muted-foreground/30"></div>
                                        </template>
                                    </div>
                                </div>

                                <!-- ── Steps / numbered list ── -->
                                <ol v-if="msg.question.steps" class="space-y-2.5">
                                    <li v-for="(step, idx) in msg.question.steps" :key="idx" class="flex gap-3">
                                        <span v-if="step.icon" class="mt-0.5 shrink-0 text-base leading-none">{{ step.icon }}</span>
                                        <span
                                            v-else
                                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[11px] font-bold text-primary"
                                        >
                                            {{ idx + 1 }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <span class="text-foreground">{{ step.text }}</span>
                                            <p v-if="step.note" class="mt-0.5 text-xs italic text-muted-foreground">
                                                {{ step.note }}
                                            </p>
                                        </div>
                                    </li>
                                </ol>

                                <!-- ── Warning box ── -->
                                <div v-if="msg.question.warning" class="flex items-start gap-2.5 rounded-lg bg-destructive/10 px-3.5 py-3">
                                    <span class="shrink-0 text-base">⚠️</span>
                                    <p class="text-sm text-destructive">{{ msg.question.warning }}</p>
                                </div>

                                <!-- ── Tips box ── -->
                                <div
                                    v-if="msg.question.tips?.length"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-3 dark:border-blue-800 dark:bg-blue-950/20"
                                >
                                    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-400">
                                        <span>💡</span> Tips
                                    </p>
                                    <ul class="space-y-1">
                                        <li
                                            v-for="(tip, idx) in msg.question.tips"
                                            :key="idx"
                                            class="flex items-start gap-1.5 text-xs text-blue-700 dark:text-blue-300"
                                        >
                                            <span class="mt-0.5 shrink-0">•</span>
                                            <span>{{ tip }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
