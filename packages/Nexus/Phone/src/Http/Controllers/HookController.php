<?php

namespace Nexus\Phone\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Nexus\Phone\Services\CallLog;

/**
 * Server-to-server endpoints for the web phone, signed with the shared secret
 * (VerifyPhoneSignature). No session, no CSRF.
 */
class HookController extends Controller
{
    public function __construct(protected CallLog $callLog) {}

    /**
     * Everyone who gets a phone login: all CRM users, with their status.
     */
    public function users(): JsonResponse
    {
        $users = DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email', 'users.status', 'roles.permission_type'])
            ->map(fn ($u) => [
                'id'     => (int) $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'active' => (bool) $u->status,
                'admin'  => $u->permission_type === 'all',
            ]);

        return new JsonResponse(['users' => $users]);
    }

    /**
     * Which lead (and owner) a caller's number belongs to.
     */
    public function lookup(Request $request): JsonResponse
    {
        return new JsonResponse(['lead' => $this->callLog->leadFor((string) $request->query('number', ''))]);
    }

    /**
     * Lead names for a batch of numbers, so the phone can show who each call or
     * text was with.
     */
    public function names(Request $request): JsonResponse
    {
        $data = $request->validate([
            'numbers'   => 'present|array|max:5000',
            'numbers.*' => 'string|max:32',
        ]);

        return new JsonResponse(['leads' => (object) $this->callLog->leadsFor($data['numbers'])]);
    }

    /**
     * A finished call, logged on its lead.
     */
    public function calls(Request $request): JsonResponse
    {
        $call = $request->validate([
            'call_id'        => 'required|string|max:64',
            'direction'      => 'required|in:in,out',
            'number'         => 'required|string|max:32',
            'result'         => 'required|string|max:40',
            'started_at'     => 'required|date',
            'duration'       => 'required|integer|min:0',
            'krayin_user_id' => 'nullable|integer',
            'user_name'      => 'nullable|string|max:100',
            'lead_id'        => 'nullable|integer',
            'notes'          => 'nullable|string|max:5000',
            'recording_url'  => 'nullable|url|max:255',
            'source'         => 'nullable|string|max:40',
        ]);

        return new JsonResponse($this->callLog->record($call));
    }

    /**
     * A text sent or received on the number, logged on its lead.
     */
    public function texts(Request $request): JsonResponse
    {
        $text = $request->validate([
            'sms_id'         => 'required|string|max:64',
            'direction'      => 'required|in:in,out',
            'number'         => 'required|string|max:32',
            'body'           => 'nullable|string|max:2000',
            'media'          => 'nullable|array|max:10',
            'media.*'        => 'url|max:500',
            'sent_at'        => 'required|date',
            'krayin_user_id' => 'nullable|integer',
            'user_name'      => 'nullable|string|max:100',
            'lead_id'        => 'nullable|integer',
            'source'         => 'nullable|string|max:40',
        ]);

        return new JsonResponse($this->callLog->recordText($text));
    }
}
