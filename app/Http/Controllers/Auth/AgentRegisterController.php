<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AgentTreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public agent recruitment. The account is created `pending` and cannot sign in
 * until HQ approves it in /manage/agents — LoginController rejects any status
 * other than active. A `?ref=` code places the recruit under that agent's upline.
 */
class AgentRegisterController extends Controller
{
    public function __construct(private AgentTreeService $tree) {}

    public function show(Request $request)
    {
        $ref = $request->get('ref');
        $upline = $ref ? User::where('role', 'agent')->where('agent_code', $ref)->first() : null;

        return view('auth.agent-register', ['ref' => $ref, 'upline' => $upline]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'ref'      => ['nullable', 'string', 'max:20'],
        ]);

        $upline = null;
        if (! empty($data['ref'])) {
            $upline = User::where('role', 'agent')->where('agent_code', $data['ref'])->first();
            if (! $upline) {
                return back()->withInput()->withErrors(['ref' => 'That referral code does not belong to any agent.']);
            }
        }

        DB::transaction(function () use ($data, $upline) {
            $agent = User::create([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'role'       => 'agent',
                'status'     => 'pending',
                'agent_code' => User::nextAgentCode(),
                'agent_tier' => 'agent',
                'password'   => $data['password'],
            ]);
            $this->tree->register($agent, $upline);
        });

        return redirect()->route('agent.login')
            ->with('ok', 'Application received — HQ will review your account and email you once it is approved.');
    }
}
