<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(private NotificationService $notifications) {}

    public function generateTicketNo(): string
    {
        $prefix = 'TKT-' . now()->format('Y') . '-';
        $last = Ticket::where('ticket_no', 'like', $prefix . '%')->orderByDesc('id')->value('ticket_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function open(User $user, array $data): Ticket
    {
        return DB::transaction(function () use ($user, $data) {
            $ticket = Ticket::create([
                'ticket_no'     => $this->generateTicketNo(),
                'user_id'       => $user->id,
                'subject'       => $data['subject'],
                'category'      => $data['category'] ?? 'other',
                'priority'      => $data['priority'] ?? 'normal',
                'status'        => 'open',
                'last_reply_at' => now(),
            ]);

            $ticket->replies()->create([
                'user_id'  => $user->id,
                'message'  => $data['message'],
                'is_staff' => false,
            ]);

            // Notify HQ/admins.
            $this->notifications->notifyMany(
                User::whereIn('role', ['super_admin', 'hq', 'admin'])->get(),
                'ticket',
                "New ticket {$ticket->ticket_no}",
                "{$user->name}: {$ticket->subject}",
                route('manage.tickets.show', $ticket),
            );

            return $ticket;
        });
    }

    public function reply(Ticket $ticket, User $user, string $message, bool $isStaff): TicketReply
    {
        return DB::transaction(function () use ($ticket, $user, $message, $isStaff) {
            $reply = $ticket->replies()->create([
                'user_id'  => $user->id,
                'message'  => $message,
                'is_staff' => $isStaff,
            ]);

            $ticket->update([
                'last_reply_at' => now(),
                'status'        => $isStaff ? 'pending' : 'open',
            ]);

            // Notify the counterparty.
            if ($isStaff) {
                $this->notifications->notify(
                    $ticket->user, 'ticket',
                    "Reply on {$ticket->ticket_no}",
                    'Support replied to your ticket.',
                    $this->openerUrl($ticket),
                );
            } else {
                $this->notifications->notifyMany(
                    User::whereIn('role', ['super_admin', 'hq', 'admin'])->get(),
                    'ticket',
                    "Reply on {$ticket->ticket_no}",
                    "{$user->name} replied.",
                    route('manage.tickets.show', $ticket),
                );
            }

            return $reply;
        });
    }

    public function setStatus(Ticket $ticket, string $status): void
    {
        $ticket->update(['status' => $status]);
        $this->notifications->notify(
            $ticket->user, 'ticket',
            "Ticket {$ticket->ticket_no} {$status}",
            "Your ticket was marked {$status}.",
            $this->openerUrl($ticket),
        );
    }

    private function openerUrl(Ticket $ticket): string
    {
        return $ticket->user->hasRole('agent')
            ? route('agent.tickets.show', $ticket)
            : route('customer.tickets.show', $ticket);
    }
}
