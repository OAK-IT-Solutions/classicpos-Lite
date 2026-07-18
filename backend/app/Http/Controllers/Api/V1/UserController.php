<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Users', description: 'User management and role assignment')]
class UserController extends BaseController
{
    protected string $modelClass = User::class;

    protected array $searchableFields = ['name', 'email'];

    protected array $withRelations = ['roles', 'branches'];

    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    protected function rules(Request $request, ?string $id = null): array
    {
        if ($id) {
            return [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'password' => ['nullable', 'string', new ComplexPassword],
                'is_active' => 'boolean',
                'branch_ids' => 'nullable|array',
                'branch_ids.*' => 'uuid|exists:branches,id',
            ];
        }

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', new ComplexPassword],
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'uuid|exists:branches,id',
            'role_id' => 'required|uuid|exists:roles,id',
            'is_active' => 'boolean',
        ];
    }

    protected function beforeStore(Request $request, array $validated): array
    {
        foreach ($validated['branch_ids'] as $branchId) {
            $targetBranch = Branch::findOrFail($branchId);

            if (!$this->subscriptionService->canCreateUser($targetBranch)) {
                abort(403, json_encode([
                    'error' => [
                        'code' => 'ERR_PLAN_LIMIT_REACHED',
                        'message' => 'Your current plan does not allow creating more users for the branch "' . $targetBranch->name . '". Upgrade to add more.',
                        'details' => [],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]));
            }
        }

        $validated['branch_id'] = $validated['branch_ids'][0];

        return $validated;
    }

    protected function afterStore(Model $record): void
    {
        $roleId = request()->input('role_id');
        $branchIds = request()->input('branch_ids', []);

        foreach ($branchIds as $branchId) {
            DB::table('branch_user')->insertOrIgnore([
                'user_id' => $record->id,
                'branch_id' => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($roleId) {
                $exists = DB::table('role_user')
                    ->where('user_id', $record->id)
                    ->where('role_id', $roleId)
                    ->where('branch_id', $branchId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_user')->insert([
                        'user_id' => $record->id,
                        'role_id' => $roleId,
                        'branch_id' => $branchId,
                    ]);
                }
            }
        }
    }

    protected function beforeUpdate(Request $request, Model $record, array $validated): array
    {
        if ($record->is_protected) {
            $changes = array_diff_key($validated, array_flip(['password', 'is_active']));
            if (!empty($changes)) {
                abort(403, json_encode([
                    'error' => [
                        'code' => 'ERR_PROTECTED_ACCOUNT',
                        'message' => 'Protected accounts cannot be modified. Contact support to delete this business.',
                        'details' => [],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]));
            }
        }

        if (isset($validated['password'])) {
            if (empty($validated['password'])) {
                unset($validated['password']);
            } else {
                $validated['password'] = Hash::make($validated['password']);
            }
        }

        return $validated;
    }

    protected function afterUpdate(Model $record): void
    {
        $branchIds = request()->input('branch_ids');

        if (!empty($branchIds)) {
            DB::table('branch_user')
                ->where('user_id', $record->id)
                ->delete();

            foreach ($branchIds as $branchId) {
                DB::table('branch_user')->insertOrIgnore([
                    'user_id' => $record->id,
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $record->update(['branch_id' => $branchIds[0]]);
        }
    }

    protected function beforeDestroy(Model $record): void
    {
        if ($record->id === request()->user()->id) {
            abort(400, json_encode([
                'error' => [
                    'code' => 'ERR_CANNOT_DELETE_SELF',
                    'message' => 'You cannot delete your own account.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]));
        }

        if ($record->is_protected) {
            abort(403, json_encode([
                'error' => [
                    'code' => 'ERR_PROTECTED_ACCOUNT',
                    'message' => 'Protected accounts cannot be deleted directly. Contact support to initiate the deletion process.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]));
        }
    }

    #[OA\Get(
        path: '/api/v1/users',
        tags: ['Users'],
        summary: 'List all users',
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated list of users')]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = $this->indexQuery($request)->with('roles', 'branches')->orderByDesc('created_at');
        $users = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $users->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'branch_id' => $user->branch_id,
                    'is_active' => $user->is_active,
                    'is_protected' => $user->is_protected,
                    'is_default_account' => $user->is_default_account,
                    'roles' => $user->roles->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'pivot_branch_id' => $role->pivot?->branch_id,
                    ]),
                    'branches' => $user->branches->map(fn ($branch) => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                    ]),
                    'created_at' => $user->created_at?->toIso8601String(),
                ];
            }),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/users/{id}',
        tags: ['Users'],
        summary: 'Get user by ID',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'User details'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $user = User::with('roles', 'branches')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
                'is_protected' => $user->is_protected,
                'is_default_account' => $user->is_default_account,
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'pivot_branch_id' => $role->pivot?->branch_id,
                ]),
                'branches' => $user->branches->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ]),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/users/assign-role',
        tags: ['Users'],
        summary: 'Assign a role to a user',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'role_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'branch_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        ], required: ['user_id', 'role_id', 'branch_ids'])),
        responses: [
            new OA\Response(response: 200, description: 'Role assigned'),
            new OA\Response(response: 403, description: 'Protected account'),
        ]
    )]
    public function assignRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'role_id' => 'required|uuid|exists:roles,id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'uuid|exists:branches,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        if ($targetUser->is_protected) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_PROTECTED_ACCOUNT',
                    'message' => 'Protected accounts cannot have their roles modified.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        $inserted = [];
        foreach ($validated['branch_ids'] as $branchId) {
            $exists = DB::table('role_user')
                ->where('user_id', $validated['user_id'])
                ->where('role_id', $validated['role_id'])
                ->where('branch_id', $branchId)
                ->exists();

            if (!$exists) {
                DB::table('role_user')->insert([
                    'user_id' => $validated['user_id'],
                    'role_id' => $validated['role_id'],
                    'branch_id' => $branchId,
                ]);
                $inserted[] = $branchId;
            }
        }

        foreach ($validated['branch_ids'] as $branchId) {
            DB::table('branch_user')->insertOrIgnore([
                'user_id' => $validated['user_id'],
                'branch_id' => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Role assigned successfully.',
            'assigned_branches' => $inserted,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/users/revoke-role',
        tags: ['Users'],
        summary: 'Revoke a role from a user',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'role_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'branch_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        ], required: ['user_id', 'role_id', 'branch_ids'])),
        responses: [
            new OA\Response(response: 200, description: 'Role revoked'),
            new OA\Response(response: 404, description: 'Role assignment not found'),
        ]
    )]
    public function revokeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'role_id' => 'required|uuid|exists:roles,id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'uuid|exists:branches,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        if ($targetUser->is_protected) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_PROTECTED_ACCOUNT',
                    'message' => 'Protected accounts cannot have their roles modified.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        $deleted = DB::table('role_user')
            ->where('user_id', $validated['user_id'])
            ->where('role_id', $validated['role_id'])
            ->whereIn('branch_id', $validated['branch_ids'])
            ->delete();

        if (!$deleted) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_ROLE_NOT_ASSIGNED',
                    'message' => 'Role assignment not found for the specified branches.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 404);
        }

        return response()->json(['message' => 'Role revoked successfully.']);
    }

    #[OA\Get(
        path: '/api/v1/users/roles/all',
        tags: ['Users'],
        summary: 'List all available roles',
        responses: [new OA\Response(response: 200, description: 'List of roles')]
    )]
    public function allRoles(): JsonResponse
    {
        return response()->json(['data' => Role::all()]);
    }

    #[OA\Get(
        path: '/api/v1/users/{id}/roles',
        tags: ['Users'],
        summary: 'Get roles assigned to a user',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'User role assignments'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function roles(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $roleAssignments = DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->join('branches', 'role_user.branch_id', '=', 'branches.id')
            ->where('role_user.user_id', $id)
            ->select(
                'roles.id as role_id',
                'roles.name as role_name',
                'branches.id as branch_id',
                'branches.name as branch_name'
            )
            ->get();

        return response()->json(['data' => $roleAssignments]);
    }
}
