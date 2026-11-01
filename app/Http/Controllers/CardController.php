<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/**
 * Public digital business cards. Anyone with the link can see them, so they expose exactly
 * the fields printed on the card, and only for active accounts: agents by agent code,
 * back-office staff by account id (staff have no agent code).
 */
class CardController extends Controller
{
    public function show(string $agentCode)
    {
        return view('card.show', ['user' => $this->agent($agentCode)]);
    }

    public function vcard(string $agentCode): Response
    {
        $user = $this->agent($agentCode);

        return $this->vcardResponse($user, $user->agent_code);
    }

    public function staff(User $user)
    {
        return view('card.show', ['user' => $this->staffMember($user)]);
    }

    public function staffVcard(User $user): Response
    {
        $user = $this->staffMember($user);

        return $this->vcardResponse($user, 'staff-' . $user->id);
    }

    private function agent(string $agentCode): User
    {
        return User::query()
            ->where('role', 'agent')
            ->where('status', 'active')
            ->where('agent_code', $agentCode)
            ->firstOrFail();
    }

    private function staffMember(User $user): User
    {
        abort_unless($user->isStaff() && $user->status === 'active', 404);

        return $user;
    }

    private function vcardResponse(User $user, string $fileName): Response
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $this->escape($user->name),
            'ORG:Blue Star Travel & Tour',
        ];
        if ($user->title) {
            $lines[] = 'TITLE:' . $this->escape($user->title);
        }
        if ($user->phone) {
            $lines[] = 'TEL;TYPE=CELL:' . $this->escape($user->phone);
        }
        if ($user->email) {
            $lines[] = 'EMAIL:' . $this->escape($user->email);
        }
        if ($user->agent_code) {
            $lines[] = 'NOTE:Member ID ' . $this->escape($user->agent_code);
        }
        $lines[] = 'END:VCARD';

        return response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type'        => 'text/vcard; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '.vcf"',
        ]);
    }

    // RFC 6350 text escaping: backslash, semicolon, comma and newlines.
    private function escape(string $value): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\;", "\\,", "\\n", "\\n"], $value);
    }
}
