<?php

namespace App\Services;

use App\Enums\ArisanCycleStatus;
use App\Enums\ArisanRoundStatus;
use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Models\ArisanCycle;
use App\Models\ArisanParticipant;
use App\Models\ArisanPayment;
use App\Models\ArisanPayout;
use App\Models\ArisanRound;
use App\Models\Member;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArisanService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly JournalService $journals,
        private readonly AuditService $audit,
    ) {}

    public function createCycle(array $data, int $userId): ArisanCycle
    {
        return DB::transaction(function () use ($data, $userId): ArisanCycle {
            $members = Member::query()
                ->whereIn('id', $data['member_ids'])
                ->where('status', MemberStatus::Active)
                ->lockForUpdate()
                ->get();
            if ($members->count() !== count(array_unique($data['member_ids']))) {
                throw ValidationException::withMessages(['member_ids' => 'Pilih hanya anggota yang aktif.']);
            }

            $cycle = ArisanCycle::query()->create([
                'cycle_number' => $this->sequences->next('ARS'),
                'name' => $data['name'],
                'contribution_amount' => $data['contribution_amount'],
                'status' => ArisanCycleStatus::Active,
                'started_at' => $data['started_at'],
                'created_by' => $userId,
            ]);

            foreach ($members as $member) {
                $cycle->participants()->create(['member_id' => $member->id]);
            }

            $start = CarbonImmutable::parse($data['started_at']);
            foreach (range(1, $members->count()) as $number) {
                $cycle->rounds()->create([
                    'round_number' => $number,
                    'round_date' => $start->addDays(35 * ($number - 1))->toDateString(),
                    'status' => ArisanRoundStatus::Open,
                ]);
            }
            $this->audit->record('arisan', 'cycle_created', $cycle, null, [
                'participants' => $members->count(),
                'rounds' => $members->count(),
                'contribution_amount' => $cycle->contribution_amount,
            ]);

            return $cycle->load('participants.member', 'rounds');
        });
    }

    public function recordPayment(ArisanRound $round, int $participantId, string $date, int $userId): ArisanPayment
    {
        return DB::transaction(function () use ($round, $participantId, $date, $userId): ArisanPayment {
            $lockedRound = ArisanRound::query()->lockForUpdate()->with('cycle')->findOrFail($round->id);
            if ($lockedRound->status !== ArisanRoundStatus::Open || $lockedRound->cycle->status !== ArisanCycleStatus::Active) {
                throw ValidationException::withMessages(['round' => 'Putaran ini tidak menerima pembayaran.']);
            }
            $participant = ArisanParticipant::query()
                ->where('id', $participantId)
                ->where('cycle_id', $lockedRound->cycle_id)
                ->with('member')
                ->firstOrFail();
            if ($participant->member->status !== MemberStatus::Active) {
                throw ValidationException::withMessages(['participant_id' => 'Anggota tidak aktif.']);
            }
            if ($lockedRound->payments()->where('participant_id', $participantId)->exists()) {
                throw ValidationException::withMessages(['participant_id' => 'Pembayaran anggota untuk putaran ini sudah tercatat.']);
            }

            $number = $this->sequences->next('ARI');
            $payment = $lockedRound->payments()->create([
                'participant_id' => $participantId,
                'transaction_number' => $number,
                'amount' => $lockedRound->cycle->contribution_amount,
                'payment_date' => $date,
                'status' => TransactionStatus::Posted,
                'created_by' => $userId,
            ]);
            $total = Money::cents($lockedRound->total_collected) + Money::cents($payment->amount);
            $lockedRound->update(['total_collected' => Money::decimal($total)]);

            $amount = Money::cents($payment->amount);
            $this->journals->post(
                $number,
                $date,
                'Iuran arisan '.$lockedRound->cycle->name.' putaran '.$lockedRound->round_number,
                $payment,
                $userId,
                [
                    ['account_code' => '1001', 'debit' => $amount, 'credit' => 0],
                    ['account_code' => '2101', 'debit' => 0, 'credit' => $amount],
                ],
            );
            $this->audit->record('arisan', 'payment_recorded', $payment, null, [
                'round_id' => $lockedRound->id, 'participant_id' => $participantId, 'amount' => $payment->amount,
            ]);

            return $payment->load('participant.member');
        });
    }

    public function draw(ArisanRound $round, int $userId): ArisanPayout
    {
        return DB::transaction(function () use ($round, $userId): ArisanPayout {
            $cycle = ArisanCycle::query()->lockForUpdate()->findOrFail($round->cycle_id);
            $locked = ArisanRound::query()->lockForUpdate()->findOrFail($round->id);
            if ($locked->cycle_id !== $cycle->id || $locked->status !== ArisanRoundStatus::Open || $cycle->status !== ArisanCycleStatus::Active) {
                throw ValidationException::withMessages(['round' => 'Putaran ini tidak dapat diundi.']);
            }
            $participantCount = $cycle->participants()->count();
            $paidCount = $locked->payments()->where('status', TransactionStatus::Posted)->count();
            if ($participantCount === 0 || $paidCount !== $participantCount) {
                throw ValidationException::withMessages(['round' => 'Semua peserta harus membayar dan putaran ini belum boleh diundi.']);
            }

            $winner = $cycle->participants()
                ->where('has_won', false)
                ->inRandomOrder()
                ->first();
            if ($winner === null) {
                throw ValidationException::withMessages(['round' => 'Tidak ada peserta yang memenuhi syarat untuk menang.']);
            }
            $locked->update([
                'status' => ArisanRoundStatus::Drawn,
                'winner_participant_id' => $winner->id,
                'drawn_at' => now(),
                'drawn_by' => $userId,
            ]);
            $winner->update(['has_won' => true, 'won_at' => now()]);
            $winner->load('member');
            $payout = $locked->payouts()->create([
                'winner_participant_id' => $winner->id,
                'payout_number' => $this->sequences->next('ARP'),
                'amount' => $locked->total_collected,
                'payout_date' => now()->toDateString(),
                'status' => 'pending',
                'created_by' => $userId,
            ]);
            $this->audit->record('arisan', 'round_drawn', $locked, null, [
                'cycle_id' => $cycle->id,
                'round_number' => $locked->round_number,
                'winner_participant_id' => $winner->id,
                'winner_name' => $winner->member->full_name,
                'amount' => $locked->total_collected,
                'drawn_at' => $locked->drawn_at?->toDateTimeString(),
                'drawn_by' => $userId,
                'payout_id' => $payout->id,
            ]);

            return $payout->load('winner.member');
        });
    }

    public function payPayout(ArisanPayout $payout, string $date, int $userId): ArisanPayout
    {
        return DB::transaction(function () use ($payout, $date, $userId): ArisanPayout {
            $locked = ArisanPayout::query()->lockForUpdate()->with('round.cycle')->findOrFail($payout->id);
            if ($locked->status !== 'pending' || $locked->round->status !== ArisanRoundStatus::Drawn) {
                throw ValidationException::withMessages(['payout' => 'Penyaluran ini tidak lagi berstatus menunggu.']);
            }
            $locked->update(['status' => 'paid', 'payout_date' => $date]);
            $locked->round->update(['status' => ArisanRoundStatus::Completed]);

            $amount = Money::cents($locked->amount);
            $number = $this->sequences->next('JAR');
            $this->journals->post(
                $number,
                $date,
                'Penyaluran arisan '.$locked->round->cycle->name.' putaran '.$locked->round->round_number,
                $locked,
                $userId,
                [
                    ['account_code' => '2101', 'debit' => $amount, 'credit' => 0],
                    ['account_code' => '1001', 'debit' => 0, 'credit' => $amount],
                ],
            );
            if (! $locked->round->cycle->rounds()->where('status', '!=', ArisanRoundStatus::Completed)->exists()) {
                $locked->round->cycle->update([
                    'status' => ArisanCycleStatus::Completed,
                    'completed_at' => $date,
                ]);
            }
            $this->audit->record('arisan', 'payout_paid', $locked, ['status' => 'pending'], [
                'status' => 'paid', 'amount' => $locked->amount,
            ]);

            return $locked->fresh('winner.member', 'round');
        });
    }

}