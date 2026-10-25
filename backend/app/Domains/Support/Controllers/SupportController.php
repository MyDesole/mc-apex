<?php

namespace App\Domains\Support\Controllers;

use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\SupportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Техподдержка со стороны игрока: свои обращения и переписка.
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportService $support)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->limit(50)
            ->get();

        return response()->json([
            'tickets' => $tickets->map(fn ($t) => $this->support->present($t))->values(),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['nullable', 'string', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'body' => ['required', 'string', 'min:5', 'max:4000'],
        ]);

        $ticket = $this->support->open($request->user(), $data['subject'], $data['category'] ?? 'other', $data['body']);

        return response()->json(['ticket' => $this->support->present($ticket, true)], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        return response()->json(['ticket' => $this->support->present($ticket, true)]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $this->support->playerReply($ticket, $request->user(), $data['body']);

        return response()->json(['ticket' => $this->support->present($ticket->fresh(), true)]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        return response()->json(['ticket' => $this->support->present($this->support->close($ticket), true)]);
    }

    private function authorizeOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }
}
