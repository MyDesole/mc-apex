<?php

namespace App\Domains\Support\Controllers;

use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\SupportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Очередь техподдержки для модератора и админа.
 */
class AdminSupportController extends Controller
{
    public function __construct(private readonly SupportService $support)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'answered', 'closed', 'all'])],
        ]);

        $status = $data['status'] ?? 'open';

        $tickets = SupportTicket::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // Дольше всех ждущие — сверху
            ->orderBy($status === 'open' ? 'last_message_at' : 'id', $status === 'open' ? 'asc' : 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'tickets' => $tickets->map(fn ($t) => $this->support->present($t))->values(),
            'counts' => [
                'open' => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count(),
                'answered' => SupportTicket::where('status', SupportTicket::STATUS_ANSWERED)->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return response()->json(['ticket' => $this->support->present($ticket, true)]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $this->support->staffReply($ticket, $request->user(), $data['body']);

        return response()->json(['ticket' => $this->support->present($ticket->fresh(), true)]);
    }

    public function close(SupportTicket $ticket): JsonResponse
    {
        return response()->json(['ticket' => $this->support->present($this->support->close($ticket), true)]);
    }
}
