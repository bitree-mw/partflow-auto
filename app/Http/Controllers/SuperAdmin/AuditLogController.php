<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AuditLogIndexRequest;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function __invoke(AuditLogIndexRequest $request): View
    {
        $filters = $request->validated();
        $ready = $this->auditLog->tableReady();

        return view('suadmin.audit-logs', [
            'title' => 'Audit log',
            'ready' => $ready,
            'logs' => $ready ? $this->auditLog->paginate($filters) : null,
            'filters' => $filters,
            'groups' => AuditLogService::EVENT_GROUPS,
        ]);
    }
}
