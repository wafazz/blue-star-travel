<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    const ROLES = ['hq' => 'HQ Manager (full access)', 'admin' => 'Admin Staff (limited access)'];

    public function index()
    {
        $staff = User::whereIn('role', ['super_admin', 'hq', 'admin'])
            ->orderByRaw("FIELD(role,'super_admin','hq','admin')")->orderBy('name')
            ->paginate(20);

        return view('manage.staff.index', compact('staff'));
    }

    public function create()
    {
        return view('manage.staff.form', ['staff' => new User(['status' => 'active', 'role' => 'admin'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['password'] = bcrypt($data['password']);
        $data['permissions'] = $data['role'] === 'admin' ? ($request->input('permissions', [])) : null;

        User::create($data);

        return redirect()->route('manage.staff.index')->with('ok', 'Staff account created.');
    }

    public function edit(User $staff)
    {
        abort_unless(in_array($staff->role, ['hq', 'admin'], true), 403, 'This account cannot be edited here.');

        return view('manage.staff.form', compact('staff'));
    }

    public function update(Request $request, User $staff)
    {
        abort_unless(in_array($staff->role, ['hq', 'admin'], true), 403, 'This account cannot be edited here.');

        $data = $this->validated($request, $staff->id);
        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }
        $data['permissions'] = $data['role'] === 'admin' ? ($request->input('permissions', [])) : null;

        $staff->update($data);

        return redirect()->route('manage.staff.index')->with('ok', 'Staff account updated.');
    }

    public function destroy(Request $request, User $staff)
    {
        abort_if($staff->role === 'super_admin', 403, 'The owner account cannot be removed.');
        abort_if($staff->id === $request->user()->id, 403, 'You cannot remove your own account.');

        $staff->delete();

        return redirect()->route('manage.staff.index')->with('ok', 'Staff account removed.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', Rule::unique('users', 'email')->ignore($ignoreId)],
            'phone'         => ['nullable', 'string', 'max:30'],
            'role'          => ['required', Rule::in(array_keys(self::ROLES))],
            'status'        => ['required', 'in:active,pending,suspended'],
            'password'      => [$ignoreId ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ]);
    }
}
