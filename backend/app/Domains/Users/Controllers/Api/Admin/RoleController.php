<?php

namespace App\Domains\Users\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Users\Requests\Admin\UpdateRoleRequest;
use App\Domains\Users\Models\User;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function update(UpdateRoleRequest $request, User $user): JsonResponse
    {
        // нельзя менять свою роль (защита от слива прав)
        abort_if($user->id === $request->user()->id, 422, 'Нельзя менять свою роль.');

        $from = $user->role;
        $user->update(['role' => $request->string('role')->toString()]);

        \App\Domains\Moderation\Models\ModerationLog::record($request->user(), 'user.role', $user->id, [
            'from' => $from,
            'to' => $user->role,
        ]);

        return response()->json(['user' => $user->fresh()]);
    }
}
