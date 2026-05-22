<?php

namespace App\Http\Controllers;

use App\Support\SupportAccess;
use App\SupportTicket;
use App\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = SupportTicket::query()
            ->with([
                'user' => static function ($q) {
                    $q->select('id', 'name', 'last_name', 'email', 'image');
                },
                'latestMessage.user' => static function ($q) {
                    $q->select('id', 'name', 'last_name', 'image');
                },
            ])
            ->orderByDesc('updated_at');

        if ($isStaff) {
            $query->forStaffInbox($filter);
        } else {
            $query->where('user_id', Auth::id());
            if (in_array($filter, [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_ANSWERED, SupportTicket::STATUS_CLOSED], true)) {
                $query->where('status', $filter);
            }
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search, $isStaff) {
                $builder->where('subject', 'like', '%' . $search . '%');
                if ($isStaff) {
                    $builder->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%');
                    });
                }
            });
        }

        $tickets = $query->paginate(20)->appends($request->only(['status', 'q']));

        $counts = $this->inboxCounts($isStaff);

        return view('support.index', compact('tickets', 'filter', 'search', 'isStaff', 'counts'));
    }

    public function create(): View
    {
        $isStaff = SupportAccess::isStaff();
        $counts = $this->inboxCounts($isStaff);

        return view('support.create', [
            'isStaff' => $isStaff,
            'counts' => $counts,
            'filter' => 'all',
            'search' => '',
        ]);
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
        $canStaffReply = SupportAccess::canReplyAsStaff($ticket);
        $canUserReply = SupportAccess::canReplyAsUser($ticket);

        $counts = $this->inboxCounts($isStaff);
        $filter = request()->query('status', 'all');
        $search = trim((string) request()->query('q', ''));

        return view('support.show', compact(
            'ticket',
            'isStaff',
            'canStaffReply',
            'canUserReply',
            'counts',
            'filter',
            'search'
        ));
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

            if ($asStaff) {
                $ticket->status = SupportTicket::STATUS_ANSWERED;
            } else {
                $ticket->status = SupportTicket::STATUS_OPEN;
            }

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

    protected function authorizeTicket(SupportTicket $ticket): void
    {
        if (!SupportAccess::canViewTicket($ticket)) {
            abort(403);
        }
    }

    protected function inboxCounts(bool $isStaff): array
    {
        if ($isStaff) {
            return [
                'all' => SupportTicket::count(),
                SupportTicket::STATUS_OPEN => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count(),
                SupportTicket::STATUS_ANSWERED => SupportTicket::where('status', SupportTicket::STATUS_ANSWERED)->count(),
                SupportTicket::STATUS_CLOSED => SupportTicket::where('status', SupportTicket::STATUS_CLOSED)->count(),
            ];
        }

        $userId = Auth::id();

        return [
            'all' => SupportTicket::where('user_id', $userId)->count(),
            SupportTicket::STATUS_OPEN => SupportTicket::where('user_id', $userId)->where('status', SupportTicket::STATUS_OPEN)->count(),
            SupportTicket::STATUS_ANSWERED => SupportTicket::where('user_id', $userId)->where('status', SupportTicket::STATUS_ANSWERED)->count(),
            SupportTicket::STATUS_CLOSED => SupportTicket::where('user_id', $userId)->where('status', SupportTicket::STATUS_CLOSED)->count(),
        ];
    }
}
