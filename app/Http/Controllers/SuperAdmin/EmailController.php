<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SendTestEmailRequest;
use App\Services\MailDiagnosticsService;
use App\Services\SystemConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

// Mail server diagnostics are kept out of the business back office.
class EmailController extends Controller
{
    public function __construct(private readonly MailDiagnosticsService $mailDiagnostics) {}

    public function edit(SystemConfigurationService $systemConfiguration): View
    {
        return view('suadmin.email', [
            'title' => 'Email delivery',
            'deliveryStatus' => $this->mailDiagnostics->deliveryStatus(),
            'defaultRecipient' => $systemConfiguration->settings()['low_stock_notification_email'],
        ]);
    }

    public function sendTest(SendTestEmailRequest $request): RedirectResponse
    {
        $result = $this->mailDiagnostics->sendTestEmail($request->validated('test_email_recipient'));

        return redirect()->route('suadmin.email.edit')
            ->with($result['sent'] ? 'success' : 'error', $result['message'])
            ->withInput($request->only('test_email_recipient'));
    }
}
