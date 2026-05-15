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
    diagram?: 'status_flow' | 'workflow_subcontractor' | 'workflow_admin';
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

const subcontractorWorkflowSteps = [
    { step: 1, label: 'Login' },
    { step: 2, label: 'Lihat\nAssignment' },
    { step: 3, label: 'Buka\nAssignment' },
    { step: 4, label: 'Isi\nSurvey' },
    { step: 5, label: 'Isi\nKonstruksi' },
    { step: 6, label: 'Isi\nBAST' },
    { step: 7, label: 'Submit' },
    { step: 8, label: 'Tunggu\nVerifikasi' },
];

const adminWorkflowSteps = [
    { step: 1, label: 'Buat\nProject' },
    { step: 2, label: 'Tambah\nSite' },
    { step: 3, label: 'Buat\nAssignment' },
    { step: 4, label: 'Pantau\nStatus' },
    { step: 5, label: 'Verifikasi' },
    { step: 6, label: 'Generate\nLaporan' },
];

const allCategories: FaqCategory[] = [
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
                    { text: 'Buka browser dan akses URL aplikasi NexPM yang diberikan oleh admin.' },
                    { text: 'Masukkan alamat email dan password akun Anda di halaman login.' },
                    { text: 'Klik tombol "Sign In" untuk masuk.' },
                    {
                        text: 'Anda akan diarahkan ke halaman sesuai role akun Anda.',
                        note: 'Admin diarahkan ke Dashboard, Subkontraktor ke My Assignments.',
                    },
                ],
                tips: [
                    'Perhatikan huruf besar/kecil saat mengetik password',
                    'Jika tidak bisa login, hubungi Super Admin untuk memeriksa status akun Anda',
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
                    {
                        text: 'Masukkan dan konfirmasi password baru Anda.',
                        note: 'Tautan reset berlaku selama 60 menit.',
                    },
                ],
                tips: ['Periksa folder Spam/Junk jika email tidak ada di inbox utama'],
            },
            {
                id: 'different-view',
                question: 'Mengapa tampilan menu saya berbeda dari rekan kerja?',
                answer:
                    'NexPM menggunakan sistem role-based access. Setiap role memiliki tampilan dan akses berbeda sesuai fungsinya:\n\n• Super Admin — Akses penuh ke semua fitur termasuk manajemen sistem dan AI Assistant\n• Admin — Manajemen proyek, assignment, verifikasi, dan laporan harian\n• Subkontraktor — Hanya melihat dan mengerjakan assignment yang ditugaskan',
                tips: ['Jika akses tidak sesuai kebutuhan pekerjaan, hubungi Super Admin untuk penyesuaian role'],
            },
        ],
    },
    {
        id: 'status',
        label: 'Status Assignment',
        icon: Activity,
        roles: ['all'],
        questions: [
            {
                id: 'status-meanings',
                question: 'Apa arti setiap status assignment?',
                answer: 'Setiap assignment memiliki status yang menggambarkan tahap pengerjaannya saat ini:',
                diagram: 'status_flow',
                steps: [
                    { icon: '⏳', text: 'PENDING — Assignment baru dibuat, menunggu dikerjakan oleh subkontraktor.' },
                    { icon: '📄', text: 'DOCUMENT — Subkontraktor telah submit semua data, menunggu verifikasi admin.' },
                    { icon: '🔄', text: 'REVISION — Admin meminta perbaikan. Subkontraktor harus memperbaiki lalu submit ulang.' },
                    { icon: '✅', text: 'VERIFIED — Admin telah memverifikasi semua data. Siap dimasukkan ke laporan.' },
                    { icon: '📊', text: 'REPORTED — Assignment sudah masuk ke laporan resmi.' },
                ],
            },
        ],
    },
    {
        id: 'subcontractor-workflow',
        label: 'Alur Kerja Saya',
        icon: ClipboardList,
        roles: ['subcontractor'],
        questions: [
            {
                id: 'overview-workflow',
                question: 'Apa saja tahapan pekerjaan yang perlu saya selesaikan?',
                answer: 'Sebagai subkontraktor, ada tiga tahap pengisian data untuk setiap assignment:',
                diagram: 'workflow_subcontractor',
                steps: [
                    { icon: '1️⃣', text: 'Survey — Mengisi data teknis lokasi site (koordinat, infrastruktur, PLN, dll).' },
                    { icon: '2️⃣', text: 'Konstruksi — Mengisi data pekerjaan konstruksi termasuk nomor WO dan commissioning.' },
                    { icon: '3️⃣', text: 'BAST — Mengisi Berita Acara Serah Terima dengan nomor SIM, foto, dan dokumen.' },
                ],
                tips: [
                    'Selesaikan ketiga tahap sebelum melakukan Submit',
                    'Data tersimpan secara berkala, tetapi Anda tetap perlu klik tombol Submit untuk mengirimkan ke admin',
                ],
            },
            {
                id: 'view-assignments',
                question: 'Bagaimana cara melihat daftar assignment saya?',
                answer: 'Semua assignment yang ditugaskan kepada Anda tersedia di halaman My Assignments:',
                steps: [
                    { text: 'Setelah login, klik menu "My Assignments" di sidebar kiri.' },
                    { text: 'Daftar semua assignment beserta informasi site, project, dan status terkini akan ditampilkan.' },
                    { text: 'Gunakan filter Status untuk memfilter berdasarkan tahap pekerjaan.' },
                    {
                        text: 'Klik pada assignment manapun untuk membuka detail dan mulai mengisi data.',
                        note: 'Assignment dengan status PENDING berarti belum pernah dikerjakan.',
                    },
                ],
            },
            {
                id: 'fill-survey',
                question: 'Bagaimana cara mengisi data Survey?',
                answer: 'Data survey berisi informasi teknis tentang kondisi fisik lokasi site:',
                steps: [
                    { text: 'Buka halaman detail assignment dengan mengkliknya dari daftar.' },
                    { text: 'Navigasi ke bagian atau tab "Survey".' },
                    { text: 'Isi semua field yang tersedia: koordinat GPS, kapasitas daya PLN, kondisi infrastruktur existing, dll.' },
                    {
                        text: 'Klik tombol "Simpan" untuk menyimpan progress.',
                        note: 'Anda bisa menyimpan sebagian data dan melanjutkan nanti.',
                    },
                    { text: 'Pastikan semua field wajib terisi sebelum melanjutkan ke tahap Konstruksi.' },
                ],
                tips: ['Data survey yang lengkap sangat penting agar proses verifikasi admin berjalan lancar'],
            },
            {
                id: 'fill-construction',
                question: 'Bagaimana cara mengisi data Konstruksi?',
                answer: 'Data konstruksi mencatat detail pekerjaan fisik yang dilakukan di lapangan:',
                steps: [
                    { text: 'Di halaman detail assignment, navigasi ke tab "Konstruksi".' },
                    {
                        text: 'Isi nomor Work Order (WO).',
                        note: 'WO Number adalah field kritis dan wajib diisi sebelum submission.',
                    },
                    { text: 'Isi tanggal commissioning dan data teknis konstruksi lainnya.' },
                    { text: 'Upload foto dokumentasi pekerjaan jika diperlukan.' },
                    { text: 'Klik "Simpan" untuk menyimpan data.' },
                ],
                warning: 'WO Number wajib diisi. Assignment tidak bisa disubmit jika WO Number masih kosong.',
            },
            {
                id: 'fill-bast',
                question: 'Bagaimana cara mengisi data BAST?',
                answer: 'BAST (Berita Acara Serah Terima) adalah dokumen formal penyelesaian pekerjaan di site:',
                steps: [
                    { text: 'Di halaman detail assignment, navigasi ke tab "BAST".' },
                    { text: 'Isi nomor SIM card yang terpasang di site dan nama provider-nya.' },
                    { text: 'Isi tanggal commissioning BAST.' },
                    { text: 'Upload foto-foto sebagai bukti dokumentasi penyelesaian pekerjaan.' },
                    { text: 'Upload dokumen pendukung jika ada.' },
                    { text: 'Klik "Simpan" untuk menyimpan data BAST.' },
                ],
                tips: [
                    'Pastikan foto yang diupload jelas dan relevan sebagai bukti penyelesaian',
                    'Foto adalah bagian krusial dari proses verifikasi admin',
                ],
            },
            {
                id: 'submit-work',
                question: 'Bagaimana cara submit pekerjaan untuk diverifikasi?',
                answer: 'Setelah semua data terisi, submit assignment agar admin dapat memverifikasi:',
                steps: [
                    { text: 'Pastikan semua tab (Survey, Konstruksi, BAST) sudah terisi lengkap.' },
                    { text: 'Pastikan WO Number sudah diisi di tab Konstruksi.', note: 'Ini syarat wajib untuk bisa submit.' },
                    { text: 'Cari dan klik tombol "Submit for Review" di halaman assignment.' },
                    { text: 'Konfirmasi di dialog yang muncul.' },
                    {
                        text: 'Status assignment berubah menjadi DOCUMENT dan admin akan menerima notifikasi.',
                        note: 'Setelah submit, data tidak bisa diubah kecuali admin meminta revisi.',
                    },
                ],
            },
            {
                id: 'revision-status',
                question: 'Apa arti status "Revision" dan apa yang harus dilakukan?',
                answer: 'Status REVISION berarti admin menemukan ketidaksesuaian atau kekurangan dalam data yang Anda submit:',
                steps: [
                    { text: 'Buka assignment yang berstatus REVISION.' },
                    { text: 'Cari dan baca catatan revisi dari admin yang menjelaskan apa yang perlu diperbaiki.' },
                    { text: 'Perbaiki data sesuai catatan admin (bisa di tab Survey, Konstruksi, atau BAST).' },
                    {
                        text: 'Submit ulang menggunakan tombol "Submit for Review".',
                        note: 'Prosesnya sama seperti submit pertama kali.',
                    },
                ],
                tips: [
                    'Baca catatan revisi dengan teliti sebelum melakukan perubahan apapun',
                    'Jika catatan revisi tidak jelas atau membingungkan, hubungi admin untuk klarifikasi',
                ],
            },
        ],
    },
    {
        id: 'project-management',
        label: 'Manajemen Proyek',
        icon: FolderKanban,
        roles: ['admin', 'super_admin'],
        questions: [
            {
                id: 'overview-admin-workflow',
                question: 'Apa alur kerja lengkap seorang Admin di NexPM?',
                answer: 'Sebagai Admin, berikut adalah alur kerja lengkap dari setup proyek hingga pelaporan:',
                diagram: 'workflow_admin',
                steps: [
                    { icon: '1️⃣', text: 'Buat Project — Masukkan data proyek beserta client dan main contractor terkait.' },
                    { icon: '2️⃣', text: 'Tambah Site — Daftarkan semua lokasi site (satu per satu atau import massal via CSV).' },
                    { icon: '3️⃣', text: 'Buat Assignment — Tugaskan subkontraktor ke masing-masing site yang perlu dikerjakan.' },
                    { icon: '4️⃣', text: 'Pantau Status — Monitor progress pengerjaan semua assignment melalui halaman Assignments.' },
                    { icon: '5️⃣', text: 'Verifikasi — Review dan verifikasi data Survey, Konstruksi, BAST dari subkontraktor.' },
                    { icon: '6️⃣', text: 'Generate Laporan — Buat laporan harian dari semua assignment yang sudah berstatus VERIFIED.' },
                ],
            },
            {
                id: 'create-project',
                question: 'Bagaimana cara membuat project baru?',
                answer: 'Project adalah wadah utama untuk mengelompokkan site-site pekerjaan:',
                steps: [
                    { text: 'Di sidebar, klik menu "Projects".' },
                    { text: 'Klik tombol "Add Project" di kanan atas halaman.' },
                    { text: 'Isi nama project, pilih client, dan pilih main contractor.' },
                    {
                        text: 'Klik "Simpan" untuk membuat project.',
                        note: 'Project siap ditambahkan site segera setelah dibuat.',
                    },
                ],
            },
            {
                id: 'add-sites',
                question: 'Bagaimana cara menambahkan site ke project?',
                answer: 'Site adalah lokasi fisik pekerjaan yang menjadi bagian dari suatu project:',
                steps: [
                    { text: 'Buka halaman detail project yang ingin ditambahkan site-nya.' },
                    { text: 'Klik tab "Sites".' },
                    { text: 'Untuk satu site: klik "Add Site", isi kode site, nama lokasi, dan data lainnya, lalu simpan.' },
                    {
                        text: 'Untuk banyak site sekaligus: klik "Import CSV", unduh template Excel, isi data di template, lalu upload kembali.',
                        note: 'Import CSV sangat disarankan untuk project dengan puluhan site.',
                    },
                ],
                tips: [
                    'Gunakan fitur Import CSV untuk efisiensi jika memiliki banyak site',
                    'Kode site (site code) harus unik dalam satu project',
                ],
            },
            {
                id: 'create-assignment',
                question: 'Bagaimana cara membuat assignment dan menugaskan subkontraktor?',
                answer: 'Assignment adalah penugasan pekerjaan pada site tertentu kepada subkontraktor:',
                steps: [
                    { text: 'Buka halaman detail project → tab "Sites".' },
                    { text: 'Klik pada site yang ingin dibuat assignmentnya.' },
                    { text: 'Di halaman site tersebut, klik "Assign Subcontractor" atau "Add Assignment".' },
                    { text: 'Pilih subkontraktor dari daftar yang tersedia dan tentukan tipe aktivitas (Survey, Construction, BAST, PLN).' },
                    {
                        text: 'Klik "Konfirmasi" untuk membuat assignment.',
                        note: 'Subkontraktor akan langsung melihat assignment ini di dashboard mereka.',
                    },
                ],
                tips: [
                    'Satu site bisa memiliki beberapa assignment dengan tipe aktivitas berbeda',
                    'Pastikan subkontraktor sudah terdaftar di menu Subcontractors sebelum membuat assignment',
                ],
            },
        ],
    },
    {
        id: 'verification',
        label: 'Verifikasi & Laporan',
        icon: CheckSquare,
        roles: ['admin', 'super_admin'],
        questions: [
            {
                id: 'verify-assignment',
                question: 'Bagaimana cara memverifikasi pekerjaan subkontraktor?',
                answer: 'Verifikasi dilakukan setelah subkontraktor mengirimkan semua data (assignment berstatus DOCUMENT):',
                steps: [
                    { text: 'Buka menu "Assignments" di sidebar.' },
                    { text: 'Gunakan filter status "DOCUMENT" untuk menemukan assignment yang perlu diverifikasi.' },
                    { text: 'Klik assignment yang ingin diverifikasi untuk membuka halaman detailnya.' },
                    { text: 'Review semua data: Survey (data teknis site), Konstruksi (WO, commissioning, foto), dan BAST (SIM, foto, dokumen).' },
                    { text: 'Jika semua data sesuai dan lengkap, klik tombol "Verify" di bagian atas halaman.' },
                    {
                        text: 'Konfirmasi di dialog yang muncul. Status berubah menjadi VERIFIED.',
                        note: 'Verifikasi tidak bisa dibatalkan — pastikan review sudah lengkap.',
                    },
                ],
                tips: [
                    'Periksa semua tab data sebelum menekan Verify',
                    'Jika ada ketidaksesuaian data, gunakan "Request Revision" daripada "Verify"',
                ],
            },
            {
                id: 'request-revision',
                question: 'Bagaimana cara mengembalikan assignment untuk revisi?',
                answer: 'Jika data dari subkontraktor tidak lengkap atau tidak sesuai standar, Anda bisa meminta revisi:',
                steps: [
                    { text: 'Buka assignment yang berstatus DOCUMENT.' },
                    { text: 'Klik tombol "Request Revision" atau "Revisi".' },
                    {
                        text: 'Tulis catatan yang jelas dan spesifik tentang apa yang perlu diperbaiki.',
                        note: 'Catatan ini akan dilihat langsung oleh subkontraktor.',
                    },
                    { text: 'Klik "Kirim". Status berubah menjadi REVISION dan subkontraktor mendapat notifikasi.' },
                ],
                tips: [
                    'Berikan catatan revisi yang spesifik — sebutkan tab mana dan field apa yang bermasalah',
                    'Catatan yang jelas akan mempersingkat waktu bolak-balik revisi',
                ],
            },
            {
                id: 'generate-report',
                question: 'Bagaimana cara generate Daily Report?',
                answer: 'Daily Report merangkum semua assignment VERIFIED yang belum dilaporkan:',
                steps: [
                    { text: 'Klik menu "Reports" di sidebar.' },
                    { text: 'Pastikan tersedia assignment berstatus VERIFIED yang belum masuk laporan.' },
                    { text: 'Klik tombol "Generate Report" atau "Buat Laporan".' },
                    { text: 'Sistem secara otomatis mengumpulkan semua assignment VERIFIED yang belum dilaporkan.' },
                    {
                        text: 'Laporan muncul di tab History. Klik ikon Download untuk mengunduh file laporan.',
                        note: 'Assignment yang masuk laporan berubah status menjadi REPORTED.',
                    },
                ],
                tips: [
                    'Generate laporan secara rutin setiap hari setelah selesai memverifikasi semua pekerjaan',
                    'Assignment berstatus REPORTED tidak bisa diubah lagi',
                ],
            },
        ],
    },
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
                    { text: 'Klik tombol "Add User" atau ikon "+" di kanan atas.' },
                    { text: 'Isi nama lengkap, alamat email, pilih role, dan buat password awal.' },
                    { text: 'Untuk role Subkontraktor, pilih perusahaan subcontractor yang sudah terdaftar di sistem.' },
                    {
                        text: 'Klik "Simpan" untuk membuat akun.',
                        note: 'User bisa langsung login menggunakan email dan password yang dibuat.',
                    },
                ],
                tips: [
                    'Gunakan password yang aman dan minta user menggantinya setelah login pertama',
                    'Pastikan subcontractor company sudah terdaftar di menu Subcontractors sebelum membuat akun subkontraktor',
                ],
            },
            {
                id: 'edit-user',
                question: 'Bagaimana cara mengubah data atau role user?',
                answer: 'Super Admin dapat mengubah informasi dan role pengguna kapan saja:',
                steps: [
                    { text: 'Buka menu "Users" di sidebar.' },
                    { text: 'Cari user yang ingin diubah menggunakan fitur pencarian.' },
                    { text: 'Klik ikon edit (pensil) di baris user tersebut.' },
                    { text: 'Ubah data yang diperlukan: nama, email, role, atau subcontractor company.' },
                    { text: 'Klik "Simpan" untuk menyimpan perubahan.' },
                ],
                warning: 'Mengubah role user akan segera mengubah hak akses mereka ke sistem. Lakukan dengan hati-hati.',
            },
            {
                id: 'role-differences',
                question: 'Apa perbedaan role Admin dan Super Admin?',
                answer:
                    'Super Admin memiliki semua akses Admin, ditambah kemampuan eksklusif berikut:\n\n✦ Manajemen User — Tambah, edit, dan kelola semua akun pengguna\n✦ AI Assistant — Akses ke asisten AI untuk analisis data proyek\n✦ App Settings — Konfigurasi pengaturan aplikasi (Company Settings, logo, dll)\n✦ Import Manual — Import data proyek secara massal ke sistem\n\nAdmin dapat:\n✦ Membuat dan mengelola project, site, dan assignment\n✦ Memverifikasi pekerjaan subkontraktor\n✦ Generate laporan harian (Daily Report)\n✦ Mengelola data client, main contractor, dan subcontractor',
            },
        ],
    },
    {
        id: 'ai-assistant',
        label: 'Asisten AI',
        icon: Bot,
        roles: ['super_admin'],
        questions: [
            {
                id: 'how-to-use-ai',
                question: 'Bagaimana cara menggunakan AI Assistant?',
                answer: 'AI Assistant NexPM membantu Super Admin menganalisis data proyek secara cepat dan terstruktur:',
                steps: [
                    { text: 'Klik ikon sparkle (✨) di header aplikasi, pojok kanan atas layar.' },
                    { text: 'Panel AI Assistant akan muncul dari sisi kanan.' },
                    { text: 'Ketikkan pertanyaan Anda dalam Bahasa Indonesia atau Inggris.' },
                    { text: 'Tekan Enter atau klik tombol kirim.' },
                    {
                        text: 'AI menganalisis data real-time dari sistem dan memberikan jawaban terstruktur.',
                        note: 'AI hanya membaca data — tidak bisa mengubah, menghapus, atau mengirim apapun.',
                    },
                ],
                tips: [
                    'Gunakan quick prompts yang tersedia di panel AI untuk pertanyaan umum',
                    'AI bisa menjawab dalam Bahasa Indonesia maupun Inggris sesuai bahasa pertanyaan Anda',
                    'Tersedia maksimal 20 pertanyaan per menit',
                ],
            },
            {
                id: 'ai-questions',
                question: 'Pertanyaan apa saja yang bisa ditanyakan ke AI?',
                answer: 'AI Assistant dirancang khusus untuk analisis dan monitoring proyek. Contoh pertanyaan efektif:',
                steps: [
                    { icon: '📊', text: '"Briefing proyek hari ini" — Ringkasan kondisi dan statistik semua proyek aktif.' },
                    { icon: '⚠️', text: '"Apa risiko terbesar saat ini?" — Identifikasi assignment yang berisiko tinggi.' },
                    { icon: '🚧', text: '"Assignment mana yang terblokir?" — Temukan assignment yang stuck atau sudah terlambat.' },
                    { icon: '📋', text: '"Assignment mana yang siap dilaporkan?" — Daftar assignment VERIFIED yang belum masuk laporan.' },
                    { icon: '🏗️', text: '"Subkontraktor mana yang paling banyak masalah?" — Analisis berdasarkan blocker per subkontraktor.' },
                    { icon: '🔍', text: '"Ada workflow gap apa?" — Deteksi inkonsistensi data dan masalah alur kerja di seluruh sistem.' },
                ],
                tips: [
                    'Buka halaman assignment atau site terlebih dahulu, lalu tanya AI untuk analisis yang lebih terfokus pada konteks tersebut',
                ],
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
        const cat = allCategories.find((c) => c.id === 'subcontractor-workflow');
        return cat ? cat.questions.slice(0, 3).map((q) => ({ question: q, category: cat })) : [];
    }
    if (props.userRole === 'admin') {
        const pm = allCategories.find((c) => c.id === 'project-management');
        const ver = allCategories.find((c) => c.id === 'verification');
        const items: { question: FaqQuestion; category: FaqCategory }[] = [];
        if (pm) items.push({ question: pm.questions[0], category: pm });
        if (ver) items.push({ question: ver.questions[0], category: ver });
        return items;
    }
    const login = allCategories[0];
    const ai = allCategories.find((c) => c.id === 'ai-assistant');
    const status = allCategories.find((c) => c.id === 'status');
    const items: { question: FaqQuestion; category: FaqCategory }[] = [{ question: login.questions[0], category: login }];
    if (status) items.push({ question: status.questions[0], category: status });
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

            <!-- Question list for selected category -->
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
            <!-- Chat header -->
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
                        Pilih topik di panel kiri, lalu klik pertanyaan yang ingin Anda ketahui. Jawaban akan muncul di sini.
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

                                <!-- ── Status Flow Diagram ── -->
                                <div v-if="msg.question.diagram === 'status_flow'" class="rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Status Assignment
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
                                        >
                                            ⏳ PENDING
                                        </span>
                                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span
                                            class="flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-400"
                                        >
                                            📄 DOCUMENT
                                        </span>
                                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span
                                            class="flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400"
                                        >
                                            ✅ VERIFIED
                                        </span>
                                        <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span
                                            class="flex items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2.5 py-1.5 text-xs font-semibold text-violet-700 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-400"
                                        >
                                            📊 REPORTED
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-center gap-2.5">
                                        <div class="ml-[144px] h-px w-16 border-t-2 border-dashed border-muted-foreground/30"></div>
                                        <span
                                            class="flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-400"
                                        >
                                            🔄 REVISION
                                        </span>
                                        <span class="text-xs italic text-muted-foreground">↩ kembali ke DOCUMENT</span>
                                    </div>
                                </div>

                                <!-- ── Subcontractor Workflow Diagram ── -->
                                <div v-else-if="msg.question.diagram === 'workflow_subcontractor'" class="overflow-x-auto rounded-lg border bg-background p-4">
                                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Alur Lengkap Subkontraktor
                                    </p>
                                    <div class="flex min-w-max items-start gap-1.5">
                                        <template v-for="(s, i) in subcontractorWorkflowSteps" :key="s.step">
                                            <div class="flex w-[68px] flex-col items-center gap-2">
                                                <div
                                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                                                >
                                                    {{ s.step }}
                                                </div>
                                                <p class="whitespace-pre-line text-center text-[11px] leading-tight text-muted-foreground">
                                                    {{ s.label }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="i < subcontractorWorkflowSteps.length - 1"
                                                class="mt-[18px] h-px w-4 shrink-0 bg-muted-foreground/30"
                                            ></div>
                                        </template>
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
                                                <div
                                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                                                >
                                                    {{ s.step }}
                                                </div>
                                                <p class="whitespace-pre-line text-center text-[11px] leading-tight text-muted-foreground">
                                                    {{ s.label }}
                                                </p>
                                            </div>
                                            <div
                                                v-if="i < adminWorkflowSteps.length - 1"
                                                class="mt-[18px] h-px w-4 shrink-0 bg-muted-foreground/30"
                                            ></div>
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
                                <div
                                    v-if="msg.question.warning"
                                    class="flex items-start gap-2.5 rounded-lg bg-destructive/10 px-3.5 py-3"
                                >
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
