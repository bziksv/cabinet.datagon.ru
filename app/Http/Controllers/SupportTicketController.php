<?php

namespace App\Http\Controllers;

use App\Support\SupportAccess;
use App\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        $filter = $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $isStaff = SupportAccess::isStaff();

        $query = $this->baseTicketQuery($isStaff)
            ->with([
                'user' => static function ($q) {
                    $q->select('id', 'name', 'last_name', 'email', 'image');
                },
                'latestMessage.user' => static function ($q) {
                    $q->select('id', 'name', 'last_name', 'image');
                },
            ])
            ->orderByDesc('updated_at');

        $this->applyInboxFilter($query, $isStaff, $filter);
        $this->applySearch($query, $search, $isStaff);

        $tickets = $query->paginate(20)->appends($request->only(['status', 'q']));

        return view('support.index', $this->inboxViewData($isStaff, $filter, $search, [
            'tickets' => $tickets,
        ]));
    }

    public function create(): View
    {
        $isStaff = SupportAccess::isStaff();

        return view('support.create', $this->inboxViewData($isStaff, 'all', ''));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket = DB::transaction(function () use ($data) {
            $ticket = SupportTicket::create([
                'user_id' => Auth::id(),
                'subject' => $data['subject'],
                'status' => SupportTicket::STATUS_OPEN,
            ]);

            $ticket->messages()->create([
                'user_id' => Auth::id(),
                'body' => $data['body'],
                'is_staff' => false,
            ]);

            return $ticket;
        });

        flash()->overlay(__('Ticket created'), __('Success'))->success();

        return redirect()->route('support.show', $ticket);
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorizeTicket($ticket);

        $ticket->load([
            'user' => static function ($q) {
                $q->select('id', 'name', 'last_name', 'email', 'image');
            },
            'messages.user' => static function ($q) {
                $q->select('id', 'name', 'last_name', 'image');
            },
        ]);

        $isStaff = SupportAccess::isStaff();
        $filter = request()->query('status', 'all');
        $search = trim((string) request()->query('q', ''));

        return view('support.show', $this->inboxViewData($isStaff, $filter, $search, [
            'ticket' => $ticket,
            'activeTicketId' => $ticket->id,
            'canStaffReply' => SupportAccess::canReplyAsStaff($ticket),
            'canUserReply' => SupportAccess::canReplyAsUser($ticket),
            'canReopen' => SupportAccess::canReopen($ticket),
            'ticketNav' => $this->ticketNeighbors($ticket, $isStaff),
        ]));
    }

    public function storeMessage(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);

        if ($ticket->isClosed()) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $asStaff = $request->boolean('as_staff') && SupportAccess::isStaff();

        if ($asStaff) {
            if (!SupportAccess::canReplyAsStaff($ticket)) {
                abort(403);
            }
        } elseif (!SupportAccess::canReplyAsUser($ticket)) {
            abort(403);
        }

        DB::transaction(function () use ($ticket, $data, $asStaff) {
            $ticket->messages()->create([
                'user_id' => Auth::id(),
                'body' => $data['body'],
                'is_staff' => $asStaff,
            ]);

            $ticket->status = $asStaff ? SupportTicket::STATUS_ANSWERED : SupportTicket::STATUS_OPEN;
            $ticket->save();
        });

        flash()->overlay(__('Message sent'), __('Success'))->success();

        return redirect()->route('support.show', $ticket);
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);

        if ($ticket->isClosed()) {
            return redirect()->route('support.show', $ticket);
        }

        if (!SupportAccess::isStaff() && (int) $ticket->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $ticket->status = SupportTicket::STATUS_CLOSED;
        $ticket->closed_at = now();
        $ticket->save();

        flash()->overlay(__('Ticket closed'), __('Success'))->info();

        return redirect()->route('support.index', ['status' => 'closed']);
    }

    public function reopen(SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);

        if (!$ticket->isClosed()) {
            return redirect()->route('support.show', $ticket);
        }

        if (!SupportAccess::canReopen($ticket)) {
            abort(403);
        }

        $ticket->status = SupportTicket::STATUS_OPEN;
        $ticket->closed_at = null;
        $ticket->save();

        flash()->overlay(__('Ticket reopened'), __('Success'))->success();

        return redirect()->route('support.show', $ticket);
    }

    protected function authorizeTicket(SupportTicket $ticket): void
    {
        if (!SupportAccess::canViewTicket($ticket)) {
            abort(403);
        }
    }

    protected function baseTicketQuery(bool $isStaff)
    {
        $query = SupportTicket::query();

        if (!$isStaff) {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }

    protected function applyInboxFilter($query, bool $isStaff, string $filter): void
    {
        if ($isStaff) {
            $query->forStaffInbox($filter);

            return;
        }

        if (in_array($filter, [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_ANSWERED, SupportTicket::STATUS_CLOSED], true)) {
            $query->where('status', $filter);
        }
    }

    protected function applySearch($query, string $search, bool $isStaff): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($builder) use ($search, $isStaff) {
            $builder->where('subject', 'like', '%' . $search . '%')
                ->orWhereHas('messages', function ($messageQuery) use ($search) {
                    $messageQuery->where('body', 'like', '%' . $search . '%');
                });

            if ($isStaff) {
                $builder->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('email', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            }
        });
    }

    protected function inboxViewData(bool $isStaff, string $filter, string $search, array $extra = []): array
    {
        return array_merge([
            'isStaff' => $isStaff,
            'counts' => $this->inboxCounts($isStaff),
            'filter' => $filter,
            'search' => $search,
            'recentTickets' => $this->recentTickets($isStaff, $extra['activeTicketId'] ?? null),
        ], $extra);
    }

    protected function inboxCounts(bool $isStaff): array
    {
        $rows = $this->baseTicketQuery($isStaff)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'all' => (int) $rows->sum(),
            SupportTicket::STATUS_OPEN => (int) ($rows[SupportTicket::STATUS_OPEN] ?? 0),
            SupportTicket::STATUS_ANSWERED => (int) ($rows[SupportTicket::STATUS_ANSWERED] ?? 0),
            SupportTicket::STATUS_CLOSED => (int) ($rows[SupportTicket::STATUS_CLOSED] ?? 0),
        ];
    }

    protected function recentTickets(bool $isStaff, ?int $activeTicketId = null): Collection
    {
        return $this->baseTicketQuery($isStaff)
            ->with(['user' => static function ($q) {
                $q->select('id', 'name', 'last_name', 'email');
            }])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();
    }

    protected function ticketNeighbors(SupportTicket $ticket, bool $isStaff): array
    {
        $base = $this->baseTicketQuery($isStaff);

        $prev = (clone $base)
            ->where('updated_at', '>', $ticket->updated_at)
            ->orderBy('updated_at', 'asc')
            ->first();

        $next = (clone $base)
            ->where('updated_at', '<', $ticket->updated_at)
            ->orderByDesc('updated_at')
            ->first();

        return [
            'prev' => $prev,
            'next' => $next,
        ];
    }
}
