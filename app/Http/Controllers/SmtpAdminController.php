<?php

namespace App\Http\Controllers;

use App\Services\SmtpAdminService;
use App\Support\SmtpSettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SmtpAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(SmtpAdminService $smtp): View
    {
        return view('admin.smtp.index', [
            'settings' => SmtpSettingsRegistry::forAdmin(),
            'status' => $smtp->statusSummary(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'provider_label' => 'nullable|string|max:120',
            'driver' => 'nullable|string|max:32',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|string|max:16',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:500',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:120',
            'from_name_en' => 'nullable|string|max:120',
        ]);

        $data['enabled'] = $request->boolean('enabled');

        if ($data['enabled']) {
            if (trim((string) ($data['host'] ?? '')) === '') {
                flash()->overlay(__('SMTP admin host required'), __('Error'))->error();

                return redirect()->route('admin.smtp.index');
            }
            if (trim((string) ($data['from_address'] ?? '')) === '') {
                flash()->overlay(__('SMTP admin from required'), __('Error'))->error();

                return redirect()->route('admin.smtp.index');
            }
        }

        SmtpSettingsRegistry::save($data);
        SmtpSettingsRegistry::applyToConfig();

        flash()->overlay(__('SMTP admin saved'), __('Success'))->success();

        return redirect()->route('admin.smtp.index');
    }

    public function importFromEnv(): RedirectResponse
    {
        SmtpSettingsRegistry::importFromEnv();
        flash()->overlay(__('SMTP admin imported env'), __('Success'))->success();

        return redirect()->route('admin.smtp.index');
    }

    public function testEmail(Request $request, SmtpAdminService $smtp): JsonResponse
    {
        $email = trim((string) $request->input('email', Auth::user()->email ?? ''));

        return response()->json($smtp->sendTestEmail($email));
    }
}
