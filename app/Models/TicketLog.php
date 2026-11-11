<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'from_role_id',
        'to_role_id',
        'action',
        'note',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'from_role_id');
    }

    public function toRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'to_role_id');
    }

    public function actionBadge(): array
    {
        return match ($this->action) {
            'created' => ['label' => 'Dibuat', 'color' => 'indigo'],
            'priority_set' => ['label' => 'Set Prioritas', 'color' => 'sky'],
            'achievement_set' => ['label' => 'Poin Achievement', 'color' => 'amber'],
            'due_date_set' => ['label' => 'Estimasi Selesai', 'color' => 'sky'],
            'deleted' => ['label' => 'Dihapus', 'color' => 'rose'],
            'in_progress' => ['label' => 'Diproses', 'color' => 'sky'],
            'escalated' => ['label' => 'Eskalasi', 'color' => 'amber'],
            'disposed' => ['label' => 'Disposisi', 'color' => 'violet'],
            'approved_step' => ['label' => 'Disetujui Tahap', 'color' => 'emerald'],
            'final_approved' => ['label' => 'Disetujui Final', 'color' => 'emerald'],
            'resolved' => ['label' => 'Selesai', 'color' => 'emerald'],
            'reopened' => ['label' => 'Diedit Ulang', 'color' => 'amber'],
            'rejected' => ['label' => 'Ditolak', 'color' => 'rose'],
            'comment' => ['label' => 'Diskusi', 'color' => 'slate'],
            'problems_set' => ['label' => 'Masalah Ditetapkan', 'color' => 'sky'],
            'problem_done' => ['label' => 'Masalah Selesai', 'color' => 'emerald'],
            'problem_reopened' => ['label' => 'Masalah Dibuka Lagi', 'color' => 'amber'],
            'progress_reported' => ['label' => 'Progres Dilaporkan', 'color' => 'sky'],
            default => ['label' => ucfirst($this->action), 'color' => 'slate'],
        };
    }
}
