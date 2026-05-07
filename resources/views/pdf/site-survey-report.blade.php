<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
        color: #1a1a1a;
    }

    /* ── Header ─────────────────────────────────────────────── */
    .header {
        text-align: center;
        border-bottom: 2.5px solid #1a56db;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .header-title {
        font-size: 16pt;
        font-weight: bold;
        letter-spacing: 2px;
        color: #1a56db;
        margin-bottom: 6px;
    }
    .header-companies {
        width: 100%;
        font-size: 9pt;
        font-weight: bold;
        color: #333;
    }
    .header-companies td {
        width: 50%;
        padding: 2px 8px;
    }
    .header-companies .left  { text-align: left; }
    .header-companies .right { text-align: right; }
    .header-version {
        font-size: 7.5pt;
        color: #888;
        margin-top: 3px;
    }

    /* ── Section title ───────────────────────────────────────── */
    .section-title {
        background: #1a56db;
        color: #fff;
        font-size: 9pt;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 4px 8px;
        margin-bottom: 0;
    }

    /* ── Plant Information table ─────────────────────────────── */
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .info-table td {
        padding: 4px 8px;
        border: 1px solid #dde3ef;
        vertical-align: top;
        font-size: 9.5pt;
    }
    .info-table tr:nth-child(odd)  td { background: #f4f7fd; }
    .info-table tr:nth-child(even) td { background: #ffffff; }
    .info-table .label {
        width: 38%;
        font-weight: bold;
        color: #374151;
    }
    .info-table .value {
        width: 62%;
        color: #111;
        word-break: break-all;
    }
    .info-table .value a {
        color: #1a56db;
    }

    /* ── Documentation / Photos ──────────────────────────────── */
    .photos-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .photos-grid td {
        width: 50%;
        padding: 4px;
        vertical-align: top;
        text-align: center;
    }
    .photo-img {
        max-width: 100%;
        max-height: 180px;
        border: 1px solid #dde3ef;
    }
    .photo-missing {
        width: 100%;
        height: 120px;
        background: #f4f7fd;
        border: 1px dashed #b0bec5;
        color: #9aa8b8;
        font-size: 8pt;
        text-align: center;
        line-height: 120px;
    }
    .photo-label {
        font-size: 7.5pt;
        color: #666;
        margin-top: 3px;
    }

    /* ── BA Survey ───────────────────────────────────────────── */
    .ba-survey-box {
        border: 1px solid #dde3ef;
        padding: 10px 14px;
        margin-bottom: 14px;
        background: #f4f7fd;
        font-size: 9.5pt;
    }
    .ba-survey-box a  { color: #1a56db; }
    .ba-survey-box .empty { color: #9aa8b8; font-style: italic; }

    /* ── AS Plan Drawing ─────────────────────────────────────── */
    .plan-box {
        border: 1px solid #dde3ef;
        padding: 10px 14px;
        margin-bottom: 14px;
        background: #fff;
    }
    .plan-image {
        max-width: 100%;
        max-height: 240px;
        display: block;
        margin: 6px auto;
    }
    .plan-link { font-size: 9pt; color: #1a56db; }
    .plan-empty { color: #9aa8b8; font-size: 9pt; font-style: italic; }

    /* Legend */
    .legend-table { margin: 8px 0 10px 0; border-collapse: collapse; }
    .legend-table td { padding: 2px 8px 2px 4px; font-size: 8.5pt; vertical-align: middle; }
    .legend-dot {
        width: 12px; height: 12px;
        display: inline-block;
        border: 1px solid #ccc;
    }

    /* Signature table */
    .sig-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
    }
    .sig-table td {
        width: 33.33%;
        text-align: center;
        border: 1px solid #ccc;
        padding: 6px 4px;
        font-size: 8.5pt;
        color: #374151;
    }
    .sig-box {
        height: 55px;
        border-bottom: 1px solid #aaa;
        margin-bottom: 4px;
    }

    /* Footer meta */
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        font-size: 8pt;
        color: #666;
    }
    .meta-table td { padding: 2px 6px; }
    .meta-table .left  { text-align: left; }
    .meta-table .right { text-align: right; }

    /* page break utility */
    .page-break { page-break-after: always; }
</style>
</head>
<body>

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-title">SITE SURVEY REPORT</div>
        <table class="header-companies">
            <tr>
                <td class="left">{{ $site->project?->mainContractor?->name ?? 'VGREEN INDONESIA' }}</td>
                <td class="right">VAHANA GASTI TEKNIKA</td>
            </tr>
        </table>
        <div class="header-version">SSR V1.1</div>
    </div>

    {{-- ── Plant Information ────────────────────────────────────── --}}
    <div class="section-title">PLANT INFORMATION</div>
    <table class="info-table">
        <tr>
            <td class="label">Plant Name</td>
            <td class="value">{{ $site->location_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plant Address</td>
            <td class="value">{{ $site->address ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">GMaps Link</td>
            <td class="value">
                @if($site->google_map_url)
                    <a href="{{ $site->google_map_url }}">{{ $site->google_map_url }}</a>
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Charger Type</td>
            <td class="value">{{ $survey?->charger_type ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Survey Date</td>
            <td class="value">{{ $survey?->ss_schedule_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Surveyor Name</td>
            <td class="value">{{ $survey?->surveyor_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plant PIC</td>
            <td class="value">{{ $survey?->pic_location_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plant PIC Contact</td>
            <td class="value">{{ $survey?->pic_location_phone ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Charger Space Available</td>
            <td class="value">{{ $survey?->parking_slot ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Power Source</td>
            <td class="value">{{ $survey?->cable_pulling_type ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Power Grid Available</td>
            <td class="value">{{ $survey?->pln_network_type ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Machine SN (If Any)</td>
            <td class="value">—</td>
        </tr>
        <tr>
            <td class="label">Other Information</td>
            <td class="value">{{ $survey?->additional_info ?? '—' }}</td>
        </tr>
    </table>

    {{-- ── Documentation (Photos) ──────────────────────────────── --}}
    <div class="section-title">DOCUMENTATION</div>
    @php
        $photoLabels = [
            'Foto Tampak Keseluruhan Site',
            'Foto Lahan Parkir EVCS / Lokasi BSS',
            'Foto Lahan Sudut Pandang Lain',
            'Foto Jaringan PLN Terdekat',
            'Foto Satelit GMaps',
        ];
        $chunks = array_chunk(array_map(null, $photos, $photoLabels), 2);
    @endphp
    <table class="photos-grid" style="margin-top:4px;">
        @forelse($chunks as $pair)
            <tr>
                @foreach($pair as $item)
                    @php [$photoPath, $photoLabel] = $item ?? [null, null]; @endphp
                    <td>
                        @if($photoPath)
                            <img src="{{ $photoPath }}" class="photo-img" alt="{{ $photoLabel }}">
                        @else
                            <div class="photo-missing">Photo not available</div>
                        @endif
                        <div class="photo-label">{{ $photoLabel }}</div>
                    </td>
                @endforeach
                {{-- pad odd last row --}}
                @if(count($pair) === 1)
                    <td></td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align:center; padding:16px; color:#9aa8b8; font-style:italic;">
                    No photos uploaded
                </td>
            </tr>
        @endforelse
    </table>

    {{-- ── BA Survey ────────────────────────────────────────────── --}}
    <div class="section-title" style="margin-top:6px;">BA SURVEY</div>
    <div class="ba-survey-box">
        @if($baSurveyUrl)
            <a href="{{ $baSurveyUrl }}">{{ $baSurveyUrl }}</a>
        @else
            <span class="empty">BA Survey document not uploaded</span>
        @endif
    </div>

    {{-- ── AS Plan Drawing ──────────────────────────────────────── --}}
    <div class="section-title">IDEAL REPORT <span style="font-weight:normal; font-size:8pt;">for mockup and quotation</span></div>
    <div class="plan-box">
        <div style="text-align:center; font-weight:bold; font-size:10pt; margin-bottom:6px;">
            AS PLAN DRAWING
        </div>
        <div style="text-align:center; font-size:9pt; margin-bottom:6px;">
            {{ $site->location_name ?? $site->site_code }}
        </div>

        @if($mockupPath)
            <img src="{{ $mockupPath }}" class="plan-image" alt="Site Plan">
        @elseif($survey?->file_mockup_3d)
            <p class="plan-link" style="text-align:center;">
                Mock Up 3D: <a href="{{ \Illuminate\Support\Facades\Storage::url($survey->file_mockup_3d) }}">View File</a>
            </p>
        @else
            <p class="plan-empty" style="text-align:center;">Mockup / site plan not uploaded</p>
        @endif

        {{-- Legend --}}
        <table class="legend-table" style="margin:10px auto 6px auto;">
            <tr>
                <td><span class="legend-dot" style="background:#FFD700;"></span></td>
                <td>Sumber PLN</td>
                <td style="width:20px;"></td>
                <td><span class="legend-dot" style="background:#e53e3e;"></span></td>
                <td>Panel Listrik</td>
                <td style="width:20px;"></td>
                <td><span class="legend-dot" style="background:#2563eb;"></span></td>
                <td>BSS</td>
            </tr>
        </table>

        {{-- Signature blocks --}}
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-box"></div>
                    Manager
                </td>
                <td>
                    <div class="sig-box"></div>
                    Waspang
                </td>
                <td>
                    <div class="sig-box"></div>
                    Pelaksana
                </td>
            </tr>
        </table>

        {{-- Footer meta --}}
        <table class="meta-table">
            <tr>
                <td class="left">Location: {{ $site->location_name ?? '—' }}</td>
                <td class="right">Title: Site Plan</td>
            </tr>
            <tr>
                <td class="left">Site: {{ $site->site_code }}</td>
                <td class="right">PT Vahana Gasti Teknika</td>
            </tr>
        </table>
    </div>

</body>
</html>
