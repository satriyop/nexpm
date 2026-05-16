<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: {{ $theme['font_family'] }}; font-size: 10pt; color: #1a1a1a; }

    table { border-collapse: collapse; }

    /* ── Section wrapper ─────────────────────────────── */
    .section {
        width: 100%;
        border: 1.5px solid {{ $theme['border_color'] }};
        margin-bottom: 14px;
    }

    /* ── Section header row ──────────────────────────── */
    .section-head {
        background: {{ $theme['section_head_bg'] }};
        color: {{ $theme['section_head_fg'] }};
        font-weight: bold;
        font-size: 10pt;
        padding: 7px 10px;
        border-bottom: 1px solid {{ $theme['border_color'] }};
    }
    .section-head-accent {
        background: {{ $theme['accent_color'] }};
        color: #fff;
        font-weight: bold;
        font-size: 10pt;
        padding: 7px 10px;
        border-bottom: 1px solid {{ $theme['accent_color'] }};
    }

    /* ── Info rows ───────────────────────────────────── */
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table td {
        padding: 5px 10px;
        border: 1px solid {{ $theme['cell_border'] }};
        vertical-align: top;
        font-size: 9.5pt;
    }
    .info-table .lbl {
        width: 38%;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 8.5pt;
        background: {{ $theme['label_bg'] }};
        color: {{ $theme['label_fg'] }};
        border: 1px solid {{ $theme['label_border'] }};
    }
    .info-table .val { color: #111; }
    .info-table .val a { color: {{ $theme['accent_color'] }}; }

    /* ── Photos ──────────────────────────────────────── */
    .photo-cell { width: 50%; padding: 6px; text-align: center; vertical-align: top; }
    .photo-img  { max-width: 100%; max-height: 190px; border: 1px solid {{ $theme['cell_border'] }}; }
    .photo-placeholder {
        width: 100%;
        height: 150px;
        background: #f5f5f5;
        border: 1px dashed #aaa;
        color: #aaa;
        font-size: 8pt;
        text-align: center;
        vertical-align: middle;
    }
    .photo-lbl { font-size: 7.5pt; color: #555; padding-top: 3px; }

    /* ── Signature ───────────────────────────────────── */
    .sig-table { width: 100%; border-collapse: collapse; }
    .sig-table td {
        width: 33.33%;
        text-align: center;
        border: 1px solid {{ $theme['cell_border'] }};
        padding: 5px 4px;
        font-size: 8.5pt;
        color: #333;
    }
    .sig-space { height: 52px; border-bottom: 1px solid #aaa; }

    /* ── Legend ──────────────────────────────────────── */
    .legend-table { border-collapse: collapse; margin: 8px auto; }
    .legend-table td { padding: 2px 8px 2px 3px; font-size: 8.5pt; vertical-align: middle; }
    .dot { width: 12px; height: 12px; display: inline-block; border: 1px solid #bbb; }

    /* ── Footer meta ─────────────────────────────────── */
    .meta-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 7.5pt; color: #555; }
    .meta-table td { padding: 1px 4px; }
</style>
</head>
<body>

{{-- ── HEADER ──────────────────────────────────────────────── --}}
<table class="section" style="margin-bottom:14px;">
    {{-- Title row --}}
    <tr>
        <td colspan="2" style="text-align:center; font-size:14pt; font-weight:bold; letter-spacing:1.5px; padding:10px 8px; border-bottom:1px solid {{ $theme['cell_border'] }}; background:{{ $theme['accent_color'] }}; color:#fff;">
            SITE SURVEY REPORT
        </td>
    </tr>
    {{-- Logo / company name rows --}}
    <tr>
        <td style="width:50%; text-align:center; padding:10px 8px; border-right:1px solid {{ $theme['cell_border'] }}; border-bottom:1px solid {{ $theme['cell_border'] }}; font-size:10pt; font-weight:bold; color:#333;">
            @if($clientLogoAbs)
                <img src="{{ $clientLogoAbs }}" style="max-height:60px; max-width:160px; display:block; margin:0 auto 4px auto;" alt="Client logo">
            @endif
            {{ $clientName }}
        </td>
        <td style="width:50%; text-align:center; padding:10px 8px; border-bottom:1px solid {{ $theme['cell_border'] }}; font-size:10pt; font-weight:bold; color:#333;">
            @if($contractorLogoAbs)
                <img src="{{ $contractorLogoAbs }}" style="max-height:60px; max-width:160px; display:block; margin:0 auto 4px auto;" alt="Contractor logo">
            @endif
            {{ $contractorName }}
        </td>
    </tr>
    {{-- Version --}}
    <tr>
        <td colspan="2" style="padding:5px 10px; font-weight:bold; font-size:9pt; background:{{ $theme['section_head_bg'] }}; color:{{ $theme['section_head_fg'] }};">
            SSR V1.1
        </td>
    </tr>
</table>

{{-- ── PLANT INFORMATION ────────────────────────────────────── --}}
<table class="section">
    <tr>
        <td colspan="2" class="section-head">PLANT INFORMATION</td>
    </tr>
    <tr><td colspan="2" style="height:6px;"></td></tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">WO NUMBER</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->ss_wo_number ?? $site->ss_wo_number ?? '' }}</td>
    </tr>
    <tr>
        <td class="info-table lbl" style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; width:38%; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">PLANT NAME</td>
        <td class="info-table val" style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $site->location_name ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">PLANT ADDRESS</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $site->address ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">GMAPS LINK</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">
            @if($site->google_map_url)
                <a href="{{ $site->google_map_url }}" style="color:{{ $theme['accent_color'] }};">{{ $site->google_map_url }}</a>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">CHARGER TYPE</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->charger_type ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">SURVEY DATE</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">
            {{ $survey?->ss_schedule_date?->format('d/m/Y') ?? '' }}
        </td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">SURVEYOR NAME</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->surveyor_name ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">PLANT PIC</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->pic_location_name ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">PLANT PIC CONTACT</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->pic_location_phone ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">CHARGER SPACE AVAILABLE</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->parking_slot ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">POWER SOURCE</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->cable_pulling_type ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">POWER GRID AVAILABLE</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->pln_network_type ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">MACHINE SN (IF ANY)</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;"></td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid {{ $theme['label_border'] }}; font-weight:bold; font-size:8.5pt; text-transform:uppercase; background:{{ $theme['label_bg'] }}; color:{{ $theme['label_fg'] }};">OTHER INFORMATION</td>
        <td style="padding:5px 10px; border:1px solid {{ $theme['cell_border'] }}; font-size:9.5pt;">{{ $survey?->additional_info ?? '' }}</td>
    </tr>
</table>

{{-- ── DOCUMENTATION (Photos) ───────────────────────────────── --}}
@php
    $photoLabels = [
        'Foto Tampak Keseluruhan Site',
        'Foto Lahan Parkir EVCS / Lokasi BSS',
        'Jalan Akses Menuju Lokasi',
        'Foto Jaringan PLN Terdekat',
        'Foto Satelit GMaps',
    ];
    $photoPairs = array_chunk(
        array_map(null, array_values($photos), $photoLabels),
        2
    );
@endphp
<table class="section">
    <tr>
        <td colspan="2" class="section-head">DOCUMENTATION</td>
    </tr>
    @foreach($photoPairs as $pair)
        <tr>
            @foreach($pair as $item)
                @php [$photoPath, $photoLabel] = $item ?? [null, null]; @endphp
                <td class="photo-cell">
                    @if($photoPath)
                        <img src="{{ $photoPath }}" class="photo-img" alt="{{ $photoLabel }}">
                    @else
                        <table style="width:100%; height:150px; border:1px dashed #aaa; background:#f5f5f5;">
                            <tr><td style="text-align:center; vertical-align:middle; color:#aaa; font-size:8pt;">Photo not available</td></tr>
                        </table>
                    @endif
                    <div class="photo-lbl">{{ $photoLabel }}</div>
                </td>
            @endforeach
            @if(count($pair) === 1)
                <td class="photo-cell"></td>
            @endif
        </tr>
    @endforeach
</table>

{{-- ── MOCK UP 3D (moved: now after Documentation, before BA Survey) ── --}}
@if($mockupPath || $survey?->file_mockup_3d)
<table class="section">
    <tr>
        <td class="section-head">MOCK UP 3D</td>
    </tr>
    <tr>
        <td style="padding:10px 14px; text-align:center;">
            @if($mockupPath)
                <img src="{{ $mockupPath }}" style="max-width:100%; max-height:280px;" alt="Mock Up 3D">
            @else
                <a href="{{ url(Storage::url($survey->file_mockup_3d)) }}" style="color:{{ $theme['accent_color'] }}; font-size:9.5pt;">
                    View Mock Up 3D File
                </a>
            @endif
        </td>
    </tr>
</table>
@endif

{{-- ── BA SURVEY ────────────────────────────────────────────── --}}
<table class="section">
    <tr>
        <td class="section-head">BA SURVEY</td>
    </tr>
    <tr>
        <td style="padding:10px 14px; text-align:center;">
            @if($baSurveyPath)
                <img src="{{ $baSurveyPath }}" style="max-width:100%; max-height:280px;" alt="BA Survey">
            @else
                <span style="color:#aaa; font-style:italic;">BA Survey image not uploaded</span>
            @endif
        </td>
    </tr>
</table>

{{-- ── SITE PLAN ─────────────────────────────────────────────── --}}
<table class="section">
    <tr>
        <td class="section-head">
            SITE PLAN
        </td>
    </tr>
    <tr>
        <td style="padding:10px 14px;">
            <div style="text-align:center; font-weight:bold; font-size:11pt; margin-bottom:6px;">
                AS PLAN DRAWING
            </div>
            <div style="text-align:center; font-size:9.5pt; margin-bottom:8px;">
                {{ $site->location_name ?? $site->site_code }}
            </div>

            @if($sitePlanPath)
                <div style="text-align:center;">
                    <img src="{{ $sitePlanPath }}" style="max-width:100%; max-height:220px;" alt="Site Plan">
                </div>
            @else
                <table style="width:100%; height:140px; border:1px dashed #aaa; background:#f9f9f9;">
                    <tr><td style="text-align:center; vertical-align:middle; color:#aaa; font-size:9pt;">Site plan image not uploaded</td></tr>
                </table>
            @endif

            {{-- Legend --}}
            <table class="legend-table" style="margin:10px auto 6px auto;">
                <tr>
                    <td><span class="dot" style="background:#FFD700;"></span></td>
                    <td>Sumber PLN</td>
                    <td style="width:24px;"></td>
                    <td><span class="dot" style="background:#e53e3e;"></span></td>
                    <td>Panel Listrik</td>
                    <td style="width:24px;"></td>
                    <td><span class="dot" style="background:#2563eb;"></span></td>
                    <td>BSS</td>
                </tr>
            </table>

            {{-- Signatures --}}
            <table class="sig-table" style="margin-top:12px;">
                <tr>
                    <td>
                        <div class="sig-space"></div>
                        Manager
                    </td>
                    <td>
                        <div class="sig-space"></div>
                        Waspang
                    </td>
                    <td>
                        <div class="sig-space"></div>
                        Pelaksana
                    </td>
                </tr>
            </table>

            {{-- Footer meta --}}
            <table class="meta-table">
                <tr>
                    <td style="text-align:left;">Location: {{ $site->location_name ?? '' }}</td>
                    <td style="text-align:right;">Title: Site Plan</td>
                </tr>
                <tr>
                    <td style="text-align:left;">Site: {{ $site->site_code }}</td>
                    <td style="text-align:right;">{{ $contractorName }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($pdfFooterNote)
<div style="margin-top:12px; padding:6px 10px; border-top:1px solid {{ $theme['cell_border'] }}; font-size:7.5pt; color:#777; font-style:italic;">
    {{ $pdfFooterNote }}
</div>
@endif

</body>
</html>
