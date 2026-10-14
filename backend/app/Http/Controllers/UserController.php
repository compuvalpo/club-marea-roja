<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\UserResource;
use App\Models\AuthSession;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Gestión de usuarios (sólo Admin).
 */
class UserController extends Controller
{
    /** Cambios que obligan a cerrar las sesiones del usuario (sus permisos o credenciales ya no son los mismos). */
    private const SENSITIVE = ['role', 'active', 'password', 'email'];

    public function __construct(private readonly AuthService $auth) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['role' => ['nullable', Rule::in(Role::values())]]);
        $users = User::query()
            ->when($q['role'] ?? null, fn ($b, $role) => $b->where('role', $role))
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate($this->rules());
        $user = User::query()->create($data + ['active' => true]);

        return $this->data(new UserResource($user), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate($this->rules($user));
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $me = $this->user($request);
        $demotes = (isset($data['role']) && $data['role'] !== Role::Admin->value) || (array_key_exists('active', $data) && ! $data['active']);
        if ($user->id === $me->id && $demotes) {
            throw new ConflictHttpException('No puede quitarse a sí mismo el rol de administrador ni desactivar su propia cuenta.');
        }
        if ($user->isAdmin() && $demotes) {
            $this->ensureAnotherAdminRemains($user);
        }

        $user->fill($data);
        $sensitive = array_intersect(array_keys($user->getDirty()), self::SENSITIVE) !== [];
        $user->save();

        // Revocación inmediata: el usuario deberá iniciar sesión otra vez con sus nuevos permisos.
        if ($sensitive) {
            $keep = $user->id === $me->id ? $request->attributes->get('auth_session')?->id : null;
            $this->auth->revokeAllFor($user, AuthSession::REASON_PERMISSIONS, $keep);
        }

        return $this->data(new UserResource($user));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $this->user($request)->id) {
            throw new ConflictHttpException('No puede eliminar su propio usuario.');
        }
        if ($user->isAdmin()) {
            $this->ensureAnotherAdminRemains($user);
        }
        $this->auth->revokeAllFor($user, AuthSession::REASON_ADMIN);
        $user->delete();

        return $this->noContent();
    }

    /** Cierra todas las sesiones abiertas de un usuario (p. ej. equipo perdido o sospecha de acceso indebido). */
    public function revokeSessions(Request $request, User $user): JsonResponse
    {
        $keep = $user->id === $this->user($request)->id ? $request->attributes->get('auth_session')?->id : null;

        return $this->data(['revoked' => $this->auth->revokeAllFor($user, AuthSession::REASON_ADMIN, $keep)]);
    }

    /** El sistema siempre debe conservar al menos un administrador activo. */
    private function ensureAnotherAdminRemains(User $user): void
    {
        $others = User::query()->where('role', Role::Admin->value)->where('active', true)->where('id', '!=', $user->id)->exists();
        if (! $others) {
            throw new ConflictHttpException('Debe existir al menos otro administrador activo.');
        }
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?User $user = null): array
    {
        $sometimes = $user ? 'sometimes' : 'required';

        return [
            'name' => [$sometimes, 'string', 'min:2', 'max:120'],
            'email' => [$sometimes, 'email', 'max:180', Rule::unique('users', 'email')->ignore($user?->id)],
            // NIST SP 800-63B: largo mínimo, sin reglas de composición obligatorias; máximo amplio para frases.
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(12)->max(128), 'different:email'],
            'role' => [$sometimes, Rule::in(Role::values())],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
