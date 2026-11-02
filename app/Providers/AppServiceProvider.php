<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (!Schema::hasTable('users') || !Schema::hasTable('roles')) {
                return;
            }

            $user = Auth::user();
            if ($user) {
                $user->loadMissing(['role.parent', 'role.children']);
                $accessibleMenus = $user->accessibleMenus();

                // Hubungkan divisi / role sebagai sub-menu dinamis di bawah Manajemen Pengguna
                if (Schema::hasTable('roles')) {
                    $adminUsersMenu = $accessibleMenus->firstWhere('slug', 'admin-users');
                    if ($adminUsersMenu) {
                        $hasDivisions = Schema::hasTable('divisions') && \App\Models\Division::exists();
                        if ($hasDivisions) {
                            $divisions = \App\Models\Division::orderBy('name')->get();
                            $divisionItems = $divisions->map(function ($d) {
                                $userCount = \App\Models\User::whereHas('role', fn ($q) => $q->where('division_id', $d->id))
                                    ->orWhereIn('id', function ($query) use ($d) {
                                        $query->select('user_id')->from('division_user')->where('division_id', $d->id);
                                    })
                                    ->count();

                                return new \App\Models\Menu([
                                    'name' => $d->name,
                                    'slug' => 'division-users-' . $d->slug,
                                    'route_name' => '/admin/users/division/' . $d->slug,
                                    'icon' => 'building-office',
                                    'color' => $d->badge_color ?: 'indigo',
                                    'badge_text' => (string) $userCount,
                                    'show_in_sidebar' => true,
                                    'is_active' => true,
                                ]);
                            });

                            $standaloneRoles = \App\Models\Role::where('is_superuser', false)
                                ->whereNull('division_id')
                                ->withCount('users')
                                ->orderBy('name')
                                ->get()
                                ->map(function ($r) {
                                    return new \App\Models\Menu([
                                        'name' => $r->name,
                                        'slug' => 'role-users-' . $r->slug,
                                        'route_name' => '/admin/users/role/' . $r->slug,
                                        'icon' => $r->icon ?: 'user-group',
                                        'color' => $r->badge_color ?: 'indigo',
                                        'badge_text' => (string) ($r->users_count ?: '0'),
                                        'show_in_sidebar' => true,
                                        'is_active' => true,
                                    ]);
                                });

                            $adminUsersMenu->setRelation('children', $divisionItems->concat($standaloneRoles));
                        } else {
                            $roleItems = \App\Models\Role::where('is_superuser', false)
                                ->withCount('users')
                                ->orderBy('name')
                                ->get()
                                ->map(function ($r) {
                                    return new \App\Models\Menu([
                                        'name' => $r->name,
                                        'slug' => 'role-users-' . $r->slug,
                                        'route_name' => '/admin/users/role/' . $r->slug,
                                        'icon' => $r->icon ?: 'user-group',
                                        'color' => $r->badge_color ?: 'indigo',
                                        'badge_text' => (string) ($r->users_count ?: '0'),
                                        'show_in_sidebar' => true,
                                        'is_active' => true,
                                    ]);
                                });
                            $adminUsersMenu->setRelation('children', $roleItems);
                        }
                    }
                }

                $sidebarMenus = $accessibleMenus
                    ->where('show_in_sidebar', true)
                    ->whereNull('parent_id')
                    ->groupBy('group');
                $launcherMenus = $accessibleMenus
                    ->where('show_in_launcher', true)
                    ->whereNull('parent_id');

                // Badge sidebar "Tiket Masuk Role" dimatikan —
                // notifikasi antrian ditampilkan di dashboard & lonceng header.
                $roleInboxCount = 0;

                // Data kategori global untuk Modal Buat Tiket di seluruh halaman
                $globalCategories = Schema::hasTable('ticket_categories')
                    ? TicketCategory::with(['initialTargetRole.parent', 'maxApprovalRole'])
                        ->where('is_active', true)
                        ->get()
                    : collect();

                $globalCategoryChains = [];
                foreach ($globalCategories as $cat) {
                    $chain = $cat->getWorkflowChain($user->role);
                    $configuredRoles = $cat->getCustomWorkflowRoles();
                    $fullRoles = $configuredRoles->isNotEmpty()
                        ? $configuredRoles
                        : $cat->getFullHierarchyRoles($user->role);
                    $globalCategoryChains[$cat->id] = [
                        'workflow_type' => $cat->workflow_type,
                        'workflow_label' => $cat->workflow_type === 'tiered_approval'
                            ? 'Approval Berjenjang'
                            : 'Eskalasi / Disposisi',
                        'target_role' => $chain->first()?->name ?? ($user->role?->parent?->name ?? 'Admin'),
                        'chain_names' => $fullRoles->isNotEmpty()
                            ? $fullRoles->pluck('name')->toArray()
                            : $chain->pluck('name')->toArray(),
                        'has_template' => $cat->hasTemplate(),
                        'template_name' => $cat->template_name,
                        'template_preview_url' => $cat->hasTemplate() ? route('categories.template.preview', $cat) : null,
                        'template_download_url' => $cat->hasTemplate() ? route('categories.template.download', $cat) : null,
                        'is_attachment_required' => (bool) $cat->is_attachment_required,
                    ];
                }

                // Notifikasi Header: Tiket Masuk & Proses Tiket Selesai
                $headerNotifications = collect();
                $incomingNotifCount = 0;
                $completedNotifCount = 0;
                $totalNotifCount = 0;

                if (Schema::hasTable('tickets')) {
                    $incomingList = Ticket::actionableQueryFor($user)
                        ->with(['creator', 'category'])
                        ->latest('updated_at')
                        ->take(8)
                        ->get()
                        ->map(function ($t) {
                            return [
                                'id' => 'inc-' . $t->id,
                                'ticket_id' => $t->id,
                                'type' => 'incoming',
                                'type_label' => 'Tiket Masuk',
                                'ticket_number' => $t->ticket_number,
                                'title' => $t->title,
                                'category_name' => $t->category?->name ?? 'Layanan',
                                'creator_name' => $t->creator?->name ?? 'Pemohon',
                                'time_ago' => $t->created_at?->diffForHumans() ?? 'Baru saja',
                                'raw_time' => $t->updated_at ? $t->updated_at->timestamp : ($t->created_at ? $t->created_at->timestamp : 0),
                                'status_label' => $t->statusLabel(),
                                'priority_label' => $t->priorityLabel(),
                                'detail' => 'Dari: ' . ($t->creator?->name ?? 'Pemohon') . ' · ' . ($t->category?->name ?? 'Layanan'),
                                'url' => route('tickets.show', $t),
                                'is_finished' => false,
                            ];
                        });

                    $completedQuery = Ticket::with(['creator', 'category'])
                        ->where(function ($q) {
                            $q->whereIn('status', ['resolved', 'closed'])
                              ->orWhere('work_status', 'finished');
                        })
                        ->where(function ($q) use ($user) {
                            $q->where('creator_id', $user->id)
                                ->orWhere('assigned_user_id', $user->id);

                            if ($user->isSuperuser() || $user->isHeadOfRole()) {
                                $q->orWhere('current_role_id', $user->role_id);
                            }

                            $division = $user->division();
                            if ($division && $user->isDivisionLeader($division) && $division->staff_role_id) {
                                $q->orWhere(function ($divQ) use ($division) {
                                    $divQ->whereHas('category', fn ($c) => $c->where('division_id', $division->id))
                                        ->where('current_role_id', $division->staff_role_id);
                                });
                            }
                        });

                    $completedList = $completedQuery->latest('updated_at')
                        ->take(8)
                        ->get()
                        ->map(function ($t) {
                            return [
                                'id' => 'comp-' . $t->id,
                                'ticket_id' => $t->id,
                                'type' => 'completed',
                                'type_label' => 'Tiket Selesai',
                                'ticket_number' => $t->ticket_number,
                                'title' => $t->title,
                                'category_name' => $t->category?->name ?? 'Layanan',
                                'creator_name' => $t->creator?->name ?? 'Pemohon',
                                'time_ago' => $t->resolved_at?->diffForHumans() ?? ($t->finished_at?->diffForHumans() ?? $t->updated_at?->diffForHumans()),
                                'raw_time' => $t->resolved_at ? $t->resolved_at->timestamp : ($t->finished_at ? $t->finished_at->timestamp : ($t->updated_at ? $t->updated_at->timestamp : 0)),
                                'status_label' => 'Selesai',
                                'priority_label' => $t->priorityLabel(),
                                'detail' => 'Pengerjaan tuntas · ' . ($t->category?->name ?? 'Layanan'),
                                'url' => route('tickets.show', $t),
                                'is_finished' => true,
                            ];
                        });

                    $headerNotifications = $incomingList->concat($completedList)->sortByDesc('raw_time')->values();
                    $incomingNotifCount = $incomingList->count();
                    $completedNotifCount = $completedList->count();
                    $totalNotifCount = $headerNotifications->count();
                }

                $view->with([
                    'authUser' => $user,
                    'sidebarMenus' => $sidebarMenus,
                    'launcherMenus' => $launcherMenus,
                    'roleInboxCount' => $roleInboxCount,
                    'globalCategories' => $globalCategories,
                    'globalCategoryChains' => $globalCategoryChains,
                    'headerNotifications' => $headerNotifications,
                    'incomingNotifCount' => $incomingNotifCount,
                    'completedNotifCount' => $completedNotifCount,
                    'totalNotifCount' => $totalNotifCount,
                ]);
            }
        });
    }
}
