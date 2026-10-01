<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function update(UpdateRoleRequest $request, User $user): JsonResponse
    {
        // нельзя менять свою роль (защита от слива прав)
        abort_if($user->id === $request->user()->id, 422, 'Нельзя менять свою роль.');

        $user->update(['role' => $request->string('role')->toString()]);

        return response()->json(['user' => $user->fresh()]);
    }
}
