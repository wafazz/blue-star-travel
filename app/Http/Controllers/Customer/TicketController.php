<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request)
    {
        $tickets = Ticket::where('user_id', $request->user()->id)->latest('last_reply_at')->paginate(15);

        return view('customer.tickets.index', compact('tickets'));
    }

    public function create()
    {
        return view('customer.tickets.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'  => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:' . implode(',', array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'message'  => ['required', 'string', 'max:2000'],
        ]);

        $ticket = $this->tickets->open($request->user(), $data);

        return redirect()->route('customer.tickets.show', $ticket)->with('ok', "Ticket {$ticket->ticket_no} opened.");
    }

    public function show(Ticket $ticket, Request $request)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $ticket->load('replies.user');

        return view('customer.tickets.show', compact('ticket'));
    }

    public function reply(Ticket $ticket, Request $request)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $this->tickets->reply($ticket, $request->user(), $data['message'], false);

        return back()->with('ok', 'Reply sent.');
    }
}
