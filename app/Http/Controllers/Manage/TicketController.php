<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request)
    {
        $query = Ticket::query()->with('user');
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }
        $tickets = $query->latest('last_reply_at')->paginate(15)->withQueryString();

        $counts = [
            'open'    => Ticket::where('status', 'open')->count(),
            'pending' => Ticket::where('status', 'pending')->count(),
        ];

        return view('manage.tickets.index', compact('tickets', 'counts'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('user', 'assignee', 'replies.user');

        return view('manage.tickets.show', compact('ticket'));
    }

    public function reply(Ticket $ticket, Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $this->tickets->reply($ticket, $request->user(), $data['message'], true);

        return back()->with('ok', 'Reply sent.');
    }

    public function status(Ticket $ticket, Request $request)
    {
        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved,closed']]);
        $this->tickets->setStatus($ticket, $data['status']);

        return back()->with('ok', "Ticket marked {$data['status']}.");
    }
}
