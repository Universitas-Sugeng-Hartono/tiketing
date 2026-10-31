<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'attachment_path',
        'attachment_name',
        'category_id',
        'priority',
        'priority_rank',
        'assessed_priority_rank',
        'workflow_type',
        'creator_id',
        'creator_role_id',
        'current_role_id',
        'assigned_user_id',
        'status',
        'progress_percent',
        'progress_note',
        'work_status',
        'delivered_at',
        'finished_at',
        'achievement_points',
        'approval_step',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'delivered_at' => 'datetime',
        'finished_at' => 'datetime',
        'priority_rank' => 'integer',
        'assessed_priority_rank' => 'integer',
        'progress_percent' => 'integer',
        'achievement_points' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function creatorRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'creator_role_id');
    }

    public function currentRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TicketLog::class)->orderBy('created_at', 'asc');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(TicketProblem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Nomor tiket berikutnya berdasarkan nomor terbesar bulan berjalan (bukan count()).
     */
    public static function generateTicketNumber(?\DateTimeInterface $at = null): string
    {
        $at = $at ?? now();
        $prefix = sprintf('TKT-%s-', $at->format('Ym'));

        $latest = static::query()
            ->where('ticket_number', 'like', $prefix . '%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $next = 1;
        if (is_string($latest) && preg_match('/^TKT-\d{6}-(\d+)$/', $latest, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    public function progressPercent(): ?int
    {
        if ($this->progress_percent === null) {
            return null;
        }

        return max(0, min(100, (int) $this->progress_percent));
    }

    /**
     * Hitung ulang progress_percent otomatis dari bobot % (weight_percent) poin pekerjaan yang sudah dicentang staff.
     * Hanya aktif jika poin pekerjaan memiliki bobot weight_percent > 0.
     */
    public function recalculateProgressFromProblems(): ?int
    {
        $problems = $this->relationLoaded('problems') ? $this->problems : $this->problems()->get();
        if ($problems->isEmpty()) {
            return $this->progress_percent;
        }

        $totalWeight = (int) $problems->sum('weight_percent');
        if ($totalWeight <= 0) {
            return $this->progress_percent;
        }

        $doneWeight = (int) $problems->where('is_done', true)->sum('weight_percent');
        $percent = max(0, min(100, $doneWeight));

        $updates = ['progress_percent' => $percent];
        if ($percent >= 100 && $problems->every(fn ($p) => $p->is_done)) {
            $updates = array_merge($updates, $this->workMilestoneChanges('finished'));
        } elseif ($percent > 0 && in_array($this->work_status, [null, 'delivered', 'finished'], true)) {
            $updates = array_merge($updates, $this->workMilestoneChanges('in_progress'));
        }

        $this->update($updates);

        return $percent;
    }

    public function hasWeightedProblems(): bool
    {
        $problems = $this->relationLoaded('problems') ? $this->problems : $this->problems()->get();
        return $problems->isNotEmpty() && (int) $problems->sum('weight_percent') > 0;
    }

    public function isImageAttachment(): bool
    {
        if (!$this->attachment_path) {
            return false;
        }
        $ext = strtolower(pathinfo($this->attachment_name ?: $this->attachment_path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }

    public function canReportProgress(User $user): bool
    {
        if (in_array($this->status, ['resolved', 'rejected', 'closed'], true)) {
            return false;
        }

        if ($user->isSuperuser()) {
            return true;
        }

        return (int) $this->assigned_user_id === (int) $user->id && $this->canBeHandledBy($user);
    }

    public function canUpdateProblemProgress(User $user): bool
    {
        if (in_array($this->status, ['resolved', 'rejected', 'closed'], true)) {
            return false;
        }

        if ($user->isSuperuser() || $this->canBeDirectedBy($user)) {
            return true;
        }

        return $this->canBeHandledBy($user) && (int) $this->assigned_user_id === (int) $user->id;
    }

    public function nextSuperiorRole(): ?Role
    {
        if ($this->category) {
            return $this->category->getNextRoleAfter($this->currentRole, $this->creatorRole);
        }

        return $this->currentRole?->parent;
    }

    public function previousRolesInChain(): Collection
    {
        if ($this->category) {
            return $this->category->getPreviousRolesBefore($this->currentRole, $this->creatorRole);
        }

        return $this->currentRole?->children ?? collect();
    }

    public function canBeHandledBy(User $user): bool
    {
        if (in_array($this->status, ['resolved', 'rejected', 'closed'], true)) {
            return false;
        }

        if ($user->isSuperuser()) {
            return true;
        }

        if ($this->assigned_user_id && (int) $this->assigned_user_id === (int) $user->id) {
            return true;
        }

        // Shared Inbox Pimpinan Divisi:
        // Kepala & SPV menangani antrian disposisi (tahap pimpinan ATAU staff belum ditugaskan)
        // serta memantau tiket staf di layanan divisi mereka.
        $division = $this->category?->division;
        if ($division && $user->isDivisionLeader($division)) {
            $leadershipIds = $division->leadershipRoleIds();
            $staffRoleId = (int) ($division->staff_role_id ?? 0);
            if (in_array((int) $this->current_role_id, $leadershipIds, true)) {
                return true;
            }
            if ($staffRoleId > 0 && (int) $this->current_role_id === $staffRoleId) {
                return true;
            }
        }

        // Admin IT / staff operasional: hanya kepala role yang boleh triase antrean,
        // staff biasa hanya jika sudah ditugaskan (dicek di atas).
        if ($user->isAdminIt()) {
            return $user->isHeadOfRole()
                && (int) $user->role_id === (int) $this->current_role_id;
        }

        if ((int) $user->role_id !== (int) $this->current_role_id) {
            return false;
        }

        $headId = $this->currentRole?->head_user_id;
        if ($headId && (int) $user->id !== (int) $headId && (int) $this->assigned_user_id !== (int) $user->id) {
            return false;
        }

        return true;
    }

    public function canSetAchievementPoints(User $user): bool
    {
        if (in_array($this->status, ['resolved', 'rejected', 'closed'], true)) {
            return false;
        }

        if ($user->isSuperuser()) {
            return true;
        }

        $division = $this->category?->division;
        if ($division && $user->isDivisionLeader($division)) {
            return true;
        }

        $headId = $this->currentRole?->head_user_id;

        return $headId && (int) $user->id === (int) $headId;
    }

    public function canBeDirectedBy(User $user): bool
    {
        if (in_array($this->status, ['resolved', 'rejected', 'closed'], true)) {
            return false;
        }

        if ($user->isSuperuser()) {
            return true;
        }

        // Pimpinan divisi (Kepala & SPV) mendisposisikan antrian layanan divisi
        // termasuk tiket yang masuk ke role staff tapi belum ditugaskan.
        $division = $this->category?->division;
        if ($division && $user->isDivisionLeader($division)) {
            $leadershipIds = $division->leadershipRoleIds();
            $staffRoleId = (int) ($division->staff_role_id ?? 0);
            if (in_array((int) $this->current_role_id, $leadershipIds, true)) {
                return true;
            }
            if ($staffRoleId > 0 && (int) $this->current_role_id === $staffRoleId) {
                return true;
            }
        }

        $headId = $this->currentRole?->head_user_id;

        return $headId
            && (int) $user->id === (int) $headId
            && (int) $user->role_id === (int) $this->current_role_id;
    }

    public function canBeRevisedBy(User $user): bool
    {
        if ($this->status !== 'resolved') {
            return false;
        }

        if ($user->isSuperuser()) {
            return true;
        }

        $headId = $this->currentRole?->head_user_id;

        return $headId
            && (int) $user->id === (int) $headId
            && (int) $user->role_id === (int) $this->current_role_id;
    }

    public function isAtFinalApprovalStage(): bool
    {
        if ($this->category && !empty($this->category->workflow_roles)) {
            return $this->nextSuperiorRole() === null;
        }

        $maxRoleId = $this->category?->max_approval_role_id;
        if ($maxRoleId && (int) $this->current_role_id === (int) $maxRoleId) {
            return true;
        }

        return $this->nextSuperiorRole() === null;
    }

    /**
     * Stepper visual hirarki yang akurat sesuai posisi terakhir tiket
     */
    public function getHierarchyStepper(): Collection
    {
        $steps = collect();
        if ($this->creatorRole) {
            $steps->push([
                'role' => $this->creatorRole,
                'label' => 'Pemohon',
                'state' => 'completed',
            ]);
        }

        $chain = $this->category?->getWorkflowChain($this->creatorRole) ?? collect();
        $foundCurrent = false;

        foreach ($chain as $index => $role) {
            $isCurrent = ((int) $role->id === (int) $this->current_role_id);

            if ($isCurrent) {
                $foundCurrent = true;
                if ($this->status === 'resolved') {
                    $state = 'completed';
                } elseif ($this->status === 'rejected') {
                    $state = 'rejected';
                } else {
                    $state = 'active';
                }
            } elseif (!$foundCurrent) {
                $state = 'completed';
            } else {
                $state = 'pending';
            }

            $steps->push([
                'role' => $role,
                'label' => $this->workflow_type === 'tiered_approval'
                    ? 'Tahap ' . ($index + 1)
                    : ($index === 0 ? 'Tujuan' : 'Eskalasi ' . $index),
                'state' => $state,
            ]);
        }

        return $steps;
    }

    /**
     * Catat bulan delivered dan bulan selesai. Selesai tetap di bulan pertama kali Finished.
     */
    public function workMilestoneChanges(?string $status): array
    {
        if (!$status) {
            return [];
        }

        $changes = ['work_status' => $status];
        if ($status === 'finished' && !$this->finished_at) {
            $changes['finished_at'] = now();
        }

        return $changes;
    }

    /**
     * Delivered dimulai saat kepala mengarahkan tiket ke staff yang mengerjakan.
     */
    public function deliveryStamp(User $actor, User $staff): array
    {
        $headId = $this->currentRole?->head_user_id;
        $actorIsHead = $actor->isSuperuser() || ($headId && (int) $actor->id === (int) $headId);
        $staffIsWorker = !$headId || (int) $staff->id !== (int) $headId;
        if (!$actorIsHead || !$staffIsWorker || $this->delivered_at) {
            return [];
        }

        $changes = ['delivered_at' => now()];
        if (!$this->work_status) {
            $changes['work_status'] = 'delivered';
        }

        return $changes;
    }

    public function workStatusLabel(): ?string
    {
        return match ($this->work_status) {
            'delivered' => 'Delivered',
            'in_progress' => 'In progress',
            'blocked' => 'Blocked',
            'finished' => 'Finished',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Baru Masuk',
            'in_progress' => 'Diproses',
            'escalated' => 'Dieskalasi',
            'waiting_next_approval' => 'Menunggu Approval',
            'resolved' => 'Selesai',
            'rejected' => 'Ditolak',
            'closed' => 'Ditutup',
            default => ucfirst($this->status),
        };
    }

    public function priorityLabel(): string
    {
        if ($this->priority_rank) {
            return RolePriorityRoute::labelFor((int) $this->priority_rank);
        }

        if (!$this->priority) {
            return 'Belum Diset';
        }

        return match ($this->priority) {
            'low' => 'Rendah',
            'medium' => 'Normal',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => ucfirst($this->priority),
        };
    }
}
