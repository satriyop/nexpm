<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/settings/Company', [
            'pdfFooterNote' => AppSetting::get('pdf.footer_note', ''),
            'reportEmail' => AppSetting::get('notifications.report_email', ''),
            'surveySlaDays' => (int) AppSetting::get('sla.survey_days', 14),
            'constructionSlaDays' => (int) AppSetting::get('sla.construction_days', 30),
            'slowDays' => (int) AppSetting::get('sla.slow_days', 15),
            'stalledDays' => (int) AppSetting::get('sla.stalled_days', 30),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'pdf_footer_note' => ['nullable', 'string', 'max:1000'],
            'report_email' => ['nullable', 'email', 'max:255'],
            'survey_sla_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'construction_sla_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'slow_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'stalled_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        AppSetting::set('pdf.footer_note', $request->input('pdf_footer_note', ''));
        AppSetting::set('notifications.report_email', $request->input('report_email', ''));
        AppSetting::set('sla.survey_days', $request->input('survey_sla_days', 14));
        AppSetting::set('sla.construction_days', $request->input('construction_sla_days', 30));
        AppSetting::set('sla.slow_days', max(1, (int) $request->input('slow_days', 15)));
        AppSetting::set('sla.stalled_days', max(1, (int) $request->input('stalled_days', 30)));

        return back()->with('success', 'App settings saved.');
    }
}
