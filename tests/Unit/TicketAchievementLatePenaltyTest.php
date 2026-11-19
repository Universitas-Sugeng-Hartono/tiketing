<?php

namespace Tests\Unit;

use App\Models\Ticket;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TicketAchievementLatePenaltyTest extends TestCase
{
    private function ticket(array $attrs): Ticket
    {
        $ticket = new class extends Ticket
        {
            protected $casts = [];
        };

        foreach ($attrs as $key => $value) {
            $ticket->setAttribute($key, $value);
        }

        return $ticket;
    }

    public function test_finished_after_due_date_reduces_achievement_points(): void
    {
        $ticket = $this->ticket([
            'achievement_points' => 50,
            'due_date' => Carbon::parse('2026-11-01')->startOfDay(),
            'work_status' => 'finished',
            'finished_at' => Carbon::parse('2026-11-04')->startOfDay(),
        ]);

        $this->assertSame(3, $ticket->daysLateOnCompletion());
        $this->assertSame(20, $ticket->earnedAchievementPoints());
        $this->assertSame(30, $ticket->achievementLatePenalty());
    }

    public function test_on_time_finish_keeps_full_points(): void
    {
        $ticket = $this->ticket([
            'achievement_points' => 50,
            'due_date' => Carbon::parse('2026-11-04')->startOfDay(),
            'work_status' => 'finished',
            'finished_at' => Carbon::parse('2026-11-04')->startOfDay(),
        ]);

        $this->assertSame(0, $ticket->daysLateOnCompletion());
        $this->assertSame(50, $ticket->earnedAchievementPoints());
        $this->assertSame(0, $ticket->achievementLatePenalty());
    }

    public function test_late_penalty_does_not_go_below_zero(): void
    {
        $ticket = $this->ticket([
            'achievement_points' => 15,
            'due_date' => Carbon::parse('2026-11-01')->startOfDay(),
            'work_status' => 'finished',
            'finished_at' => Carbon::parse('2026-11-10')->startOfDay(),
        ]);

        $this->assertSame(0, $ticket->earnedAchievementPoints());
    }
}
