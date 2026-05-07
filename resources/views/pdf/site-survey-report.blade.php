<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #1a1a1a; }

    table { border-collapse: collapse; }

    /* ── Section wrapper ─────────────────────────────── */
    .section {
        width: 100%;
        border: 1.5px solid #333;
        margin-bottom: 14px;
    }

    /* ── Section header row ──────────────────────────── */
    .section-head {
        background: #d9d9d9;
        font-weight: bold;
        font-size: 10pt;
        padding: 7px 10px;
        border-bottom: 1px solid #888;
    }
    .section-head-blue {
        background: #1a56db;
        color: #fff;
        font-weight: bold;
        font-size: 10pt;
        padding: 7px 10px;
        border-bottom: 1px solid #1a56db;
    }

    /* ── Info rows ───────────────────────────────────── */
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table td {
        padding: 5px 10px;
        border: 1px solid #ccc;
        vertical-align: top;
        font-size: 9.5pt;
    }
    .info-table .lbl {
        width: 38%;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 8.5pt;
        color: #222;
    }
    .info-table .val { color: #111; }
    .info-table .val a { color: #1a56db; }

    /* ── Photos ──────────────────────────────────────── */
    .photo-cell { width: 50%; padding: 6px; text-align: center; vertical-align: top; }
    .photo-img  { max-width: 100%; max-height: 190px; border: 1px solid #ccc; }
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
        border: 1px solid #999;
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
        <td colspan="2" style="text-align:center; font-size:14pt; font-weight:bold; letter-spacing:1.5px; padding:10px 8px; border-bottom:1px solid #bbb;">
            SITE SURVEY REPORT
        </td>
    </tr>
    {{-- Logo / company name rows --}}
    <tr>
        <td style="width:50%; text-align:center; padding:10px 8px; border-right:1px solid #bbb; border-bottom:1px solid #bbb; font-size:10pt; font-weight:bold; color:#333;">
            @if($contractorLogoAbs)
                <img src="{{ $contractorLogoAbs }}" style="max-height:60px; max-width:160px; display:block; margin:0 auto 4px auto;" alt="Client logo">
            @endif
            {{ $site->project?->mainContractor?->name ?? 'CLIENT' }}
        </td>
        <td style="width:50%; text-align:center; padding:10px 8px; border-bottom:1px solid #bbb; font-size:10pt; font-weight:bold; color:#333;">
            @if($companyLogoAbs)
                <img src="{{ $companyLogoAbs }}" style="max-height:60px; max-width:160px; display:block; margin:0 auto 4px auto;" alt="Company logo">
            @endif
            {{ $companyName }}
        </td>
    </tr>
    {{-- Version --}}
    <tr>
        <td colspan="2" style="padding:5px 10px; font-weight:bold; font-size:9pt;">
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
        <td class="info-table lbl" style="padding:5px 10px; border:1px solid #ccc; width:38%; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">PLANT NAME</td>
        <td class="info-table val" style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $site->location_name ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">PLANT ADDRESS</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $site->address ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">GMAPS LINK</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">
            @if($site->google_map_url)
                <a href="{{ $site->google_map_url }}" style="color:#1a56db;">{{ $site->google_map_url }}</a>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">CHARGER TYPE</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->charger_type ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">SURVEY DATE</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">
            {{ $survey?->ss_schedule_date?->format('d/m/Y') ?? '' }}
        </td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">SURVEYOR NAME</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->surveyor_name ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">PLANT PIC</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->pic_location_name ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">PLANT PIC CONTACT</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->pic_location_phone ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">CHARGER SPACE AVAILABLE</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->parking_slot ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">POWER SOURCE</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->cable_pulling_type ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">POWER GRID AVAILABLE</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->pln_network_type ?? '' }}</td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">MACHINE SN (IF ANY)</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;"></td>
    </tr>
    <tr>
        <td style="padding:5px 10px; border:1px solid #ccc; font-weight:bold; font-size:8.5pt; text-transform:uppercase;">OTHER INFORMATION</td>
        <td style="padding:5px 10px; border:1px solid #ccc; font-size:9.5pt;">{{ $survey?->additional_info ?? '' }}</td>
    </tr>
</table>

{{-- ── DOCUMENTATION (Photos) ───────────────────────────────── --}}
@php
    $photoLabels = [
        'Foto Tampak Keseluruhan Site',
        'Foto Lahan Parkir EVCS / Lokasi BSS',
        'Foto Lahan Sudut Pandang Lain',
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

{{-- ── BA SURVEY ────────────────────────────────────────────── --}}
<table class="section">
    <tr>
        <td class="section-head">BA SURVEY</td>
    </tr>
    <tr>
        <td style="padding:10px 14px; font-size:9.5pt;">
            @if($baSurveyUrl)
                <a href="{{ $baSurveyUrl }}" style="color:#1a56db;">{{ $baSurveyUrl }}</a>
            @else
                <span style="color:#aaa; font-style:italic;">BA Survey document not uploaded</span>
            @endif
        </td>
    </tr>
</table>

{{-- ── AS PLAN DRAWING ──────────────────────────────────────── --}}
<table class="section">
    <tr>
        <td class="section-head">
            IDEAL REPORT <span style="font-weight:normal; font-size:8pt;">for mockup and quotation</span>
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

            @if($mockupPath)
                <div style="text-align:center;">
                    <img src="{{ $mockupPath }}" style="max-width:100%; max-height:220px;" alt="Site Plan">
                </div>
            @elseif($mockupUrl ?? null)
                <p style="text-align:center; font-size:9pt;">
                    Mock Up 3D: <a href="{{ $mockupUrl }}" style="color:#1a56db;">View File</a>
                </p>
            @else
                <table style="width:100%; height:140px; border:1px dashed #aaa; background:#f9f9f9;">
                    <tr><td style="text-align:center; vertical-align:middle; color:#aaa; font-size:9pt;">Site plan / mockup not uploaded</td></tr>
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
                    <td style="text-align:right;">PT Vahana Gasti Teknika</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
