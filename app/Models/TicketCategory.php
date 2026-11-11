<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TicketCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'description',
        'workflow_roles',
        'initial_target_role_id',
        'workflow_type',
        'division_id',
        'max_approval_role_id',
        'template_path',
        'template_name',
        'is_attachment_required',
        'is_active',
    ];

    protected $casts = [
        'workflow_roles' => 'array',
        'is_attachment_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function initialTargetRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'initial_target_role_id');
    }

    public function maxApprovalRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'max_approval_role_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    public function hasTemplate(): bool
    {
        return !empty($this->template_path);
    }

    public function isOther(): bool
    {
        return $this->slug === 'other';
    }

    /**
     * Ambil seluruh objek Role sesuai urutan di workflow_roles (jika dikonfigurasi)
     */
    public function getCustomWorkflowRoles(): Collection
    {
        $roleIds = array_values(array_filter((array) ($this->workflow_roles ?? []), fn ($id) => !empty($id)));
        if (empty($roleIds)) {
            return collect();
        }

        $rolesById = Role::whereIn('id', $roleIds)->get()->keyBy('id');

        return collect($roleIds)
            ->map(fn ($id) => $rolesById->get((int) $id))
            ->filter()
            ->values();
    }

    /**
     * Dapatkan urutan lengkap seluruh role dalam alur kategori (termasuk role pemohon di awal, misal: Mahasiswa -> Akademik -> Yayasan)
     */
    public function getFullHierarchyRoles(?Role $creatorRole = null): Collection
    {
        $customRoles = $this->getCustomWorkflowRoles();
        if ($customRoles->isNotEmpty()) {
            if ($creatorRole && !$customRoles->contains(fn (Role $r) => (int) $r->id === (int) $creatorRole->id)) {
                return collect([$creatorRole])->concat($customRoles)->values();
            }

            return $customRoles;
        }

        $chain = $this->getWorkflowChain($creatorRole);
        if ($creatorRole) {
            return collect([$creatorRole])->concat($chain)->values();
        }

        return $chain;
    }

    /**
     * Dapatkan daftar tahapan role pemroses/penerima untuk kategori ini setelah pemohon
     */
    public function getWorkflowChain(?Role $creatorRole = null): Collection
    {
        $customRoles = $this->getCustomWorkflowRoles();
        if ($customRoles->isNotEmpty()) {
            if ($creatorRole) {
                $creatorIdx = $customRoles->search(fn (Role $r) => (int) $r->id === (int) $creatorRole->id);
                if ($creatorIdx !== false) {
                    $afterCreator = $customRoles->slice($creatorIdx + 1)->values();
                    if ($afterCreator->isNotEmpty()) {
                        return $afterCreator;
                    }
                }
            }

            // Role pertama yang tidak bisa menerima tiket (misalnya Mahasiswa) dilewati.
            // Selain itu, role pertama tetap jadi gerbang persetujuan sebelum naik ke atas.
            if ($customRoles->count() > 1 && !$customRoles->first()->can_receive_ticket) {
                return $customRoles->slice(1)->values();
            }

            return $customRoles;
        }

        $startRole = $this->initialTargetRole ?? $creatorRole?->parent;
        if (!$startRole) {
            return collect();
        }

        $chain = collect([$startRole]);
        foreach ($startRole->getAncestors() as $ancestor) {
            $chain->push($ancestor);
            if ($this->max_approval_role_id && (int) $ancestor->id === (int) $this->max_approval_role_id) {
                break;
            }
        }

        return $chain;
    }

    /**
     * Tentukan role penerima pertama saat user dengan $creatorRole membuat tiket
     */
    public function resolveTargetRoleFor(Role $creatorRole): ?Role
    {
        $chain = $this->getWorkflowChain($creatorRole);
        if ($chain->isNotEmpty()) {
            return $chain->first();
        }

        if ($this->initial_target_role_id && $this->initialTargetRole) {
            return $this->initialTargetRole;
        }

        return $creatorRole->parent;
    }

    /**
     * Cari role tahap selanjutnya setelah $currentRole berdasarkan susunan hirarki kategori layanan ini
     */
    public function getNextRoleAfter(?Role $currentRole, ?Role $creatorRole = null): ?Role
    {
        if (!$currentRole) {
            return null;
        }

        $chain = $this->getWorkflowChain($creatorRole);
        if ($chain->isNotEmpty()) {
            $idx = $chain->search(fn (Role $r) => (int) $r->id === (int) $currentRole->id);
            if ($idx !== false) {
                return $chain->get($idx + 1);
            }
        }

        return $currentRole->parent;
    }

    /**
     * Cari daftar role tahap sebelumnya untuk keperluan disposisi balik
     */
    public function getPreviousRolesBefore(?Role $currentRole, ?Role $creatorRole = null): Collection
    {
        if (!$currentRole) {
            return collect();
        }

        $chain = $this->getWorkflowChain($creatorRole);
        if ($chain->isNotEmpty()) {
            $idx = $chain->search(fn (Role $r) => (int) $r->id === (int) $currentRole->id);
            if ($idx !== false && $idx > 0) {
                return $chain->slice(0, $idx)->reverse()->values();
            }
        }

        return $currentRole->children ?? collect();
    }
}

