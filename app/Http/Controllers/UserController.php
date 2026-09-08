<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['permission:users.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('roles:id,name')
                ->orderBy('username')
                ->get(['id', 'username', 'name', 'full_name', 'email', 'is_active', 'must_change_password', 'last_login_at'])
                ->map(fn (User $u) => [
                    ...$u->only('id', 'username', 'full_name', 'email', 'is_active', 'must_change_password'),
                    'roles' => $u->roles->pluck('name'),
                    'permissions' => $u->getDirectPermissions()->pluck('name'),
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                ]),
            'roles' => Role::orderBy('name')->pluck('name'),
            'catalogue' => Permissions::catalogue(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users')],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
            'is_active' => ['boolean'],
        ], [], [
            'username' => 'نام کاربری',
            'full_name' => 'نام و نام خانوادگی',
            'password' => 'رمز عبور',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'username' => Str::lower($data['username']),
                'name' => $data['full_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
                'must_change_password' => true,
            ]);

            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);

            return $user;
        });

        ActivityLogger::record('created', $user, "ایجاد کاربر {$user->username}");

        return back()->with('success', 'کاربر ایجاد شد. در اولین ورود باید رمز عبور را تغییر دهد.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
            'is_active' => ['boolean'],
        ]);

        // Never let the last admin lock everyone out of the system.
        if ($this->wouldRemoveLastAdmin($user, $data)) {
            return back()->withErrors([
                'roles' => 'حداقل یک کاربر با نقش مدیر باید فعال بماند.',
            ]);
        }

        DB::transaction(function () use ($user, $data) {
            $user->fill([
                'username' => Str::lower($data['username']),
                'name' => $data['full_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = true;
            }

            $user->save();
            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);
        });

        ActivityLogger::record('updated', $user, "ویرایش کاربر {$user->username}");

        return back()->with('success', 'کاربر به‌روزرسانی شد.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'نمی‌توانید حساب کاربری خودتان را حذف کنید.']);
        }

        if ($this->wouldRemoveLastAdmin($user, ['roles' => [], 'is_active' => false])) {
            return back()->withErrors(['user' => 'حداقل یک کاربر با نقش مدیر باید فعال بماند.']);
        }

        $user->delete();
        ActivityLogger::record('deleted', $user, "حذف کاربر {$user->username}");

        return back()->with('success', 'کاربر حذف شد.');
    }

    public function activity(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        return Inertia::render('Users/Activity', [
            'logs' => ActivityLog::with('user:id,full_name,name,username')
                ->when($request->query('user'), fn ($q, $v) => $q->where('user_id', $v))
                ->when($request->query('event'), fn ($q, $v) => $q->where('event', $v))
                ->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (ActivityLog $l) => [
                    'id' => $l->id,
                    'event' => $l->event,
                    'description' => $l->description,
                    'subject_type' => class_basename((string) $l->subject_type),
                    'subject_id' => $l->subject_id,
                    'user' => $l->user?->display_name,
                    'ip_address' => $l->ip_address,
                    'created_at' => $l->created_at?->toIso8601String(),
                    'properties' => $l->properties,
                ]),
            'filters' => $request->only('user', 'event'),
            'users' => User::orderBy('username')->get(['id', 'username', 'full_name']),
        ]);
    }

    /** True when applying $data would leave zero active admins. */
    private function wouldRemoveLastAdmin(User $user, array $data): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        $stillAdmin = in_array('admin', $data['roles'] ?? [], true)
            && ($data['is_active'] ?? true);

        if ($stillAdmin) {
            return false;
        }

        return User::role('admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }
}
