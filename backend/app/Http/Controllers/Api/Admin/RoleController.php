<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:user,tester,moderator,admin'],
        ]);

        // нельзя менять свою роль (защита от слива прав)
        abort_if($user->id === $request->user()->id, 422, 'Нельзя менять свою роль.');

        // нельзя снять роль с другого админа, если ты не главный админ
        // (опционально — можно убрать)

        $user->update(['role' => $validated['role']]);

        return response()->json(['user' => $user->fresh()]);
    }
}
