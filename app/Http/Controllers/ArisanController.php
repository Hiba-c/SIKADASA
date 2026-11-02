<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\ArisanCycle;
use App\Models\ArisanPayout;
use App\Models\ArisanRound;
use App\Models\Member;
use App\Services\ArisanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArisanController extends Controller
{
    public function __construct(private readonly ArisanService $arisan) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ArisanCycle::class);
        $cycles = ArisanCycle::query()
            ->withCount('participants', 'rounds')
            ->latest('id')->paginate(15);

        return Inertia::render('arisan/index', ['cycles' => $cycles]);
    }

    public function create(): Response
    {
        $this->authorize('create', ArisanCycle::class);

        return Inertia::render('arisan/create', [
            'members' => Member::query()->where('status', MemberStatus::Active)->orderBy('full_name')->get(['id', 'full_name', 'registration_code']),
            'started_at' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ArisanCycle::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contribution_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'started_at' => ['required', 'date'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'integer', 'distinct', 'exists:members,id'],
        ]);
        $cycle = $this->arisan->createCycle($data, (int) $request->user()->id);

        return redirect()->route('arisan.index')->with('success', 'Siklus arisan berhasil dibuat.');
    }

    public function show(ArisanCycle $cycle): Response
    {
        $this->authorize('view', $cycle);
        $cycle->load([
            'participants.member',
            'rounds' => fn ($query) => $query->orderBy('round_number'),
            'rounds.winner.member',
            'rounds.drawnBy',
            'rounds.payments.participant.member',
            'rounds.payouts.winner.member',
        ]);
        $cycle->rounds->each(function (ArisanRound $round): void {
            $round->setAttribute('drawn_by_name', $round->drawnBy?->name);
            $round->unsetRelation('drawnBy');
        });

        return Inertia::render('arisan/history', ['cycle' => $cycle]);
    }

    public function payment(Request $request, ArisanRound $round): RedirectResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date'],
        ]);
        $this->arisan->recordPayment($round, $data['participant_id'], $data['payment_date'], (int) $request->user()->id);

        return back()->with('success', 'Pembayaran iuran arisan berhasil dicatat.');
    }

    public function draw(Request $request, ArisanRound $round): RedirectResponse
    {
        $cycle = $round->cycle;
        $this->authorize('draw', $cycle);
        $this->arisan->draw($round, (int) $request->user()->id);

        return back()->with('success', 'Undian selesai; penyaluran menunggu konfirmasi.');
    }

    public function payPayout(Request $request, ArisanPayout $payout): RedirectResponse
    {
        $cycle = $payout->round->cycle;
        $this->authorize('manageCycle', $cycle);
        $data = $request->validate(['payout_date' => ['required', 'date']]);
        $this->arisan->payPayout($payout, $data['payout_date'], (int) $request->user()->id);

        return back()->with('success', 'Penyaluran arisan berhasil dicatat.');
    }
}