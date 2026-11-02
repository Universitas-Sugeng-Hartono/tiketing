<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user()->load(['role.parent', 'role.children', 'supervisor']);
        $role = $user->role;

        // Antrian yang perlu tindakan user (dashboard), bukan seluruh antrean role
        $incomingTickets = Ticket::actionableQueryFor($user)
            ->with(['creator', 'creatorRole', 'category', 'currentRole', 'assignedUser'])
            ->latest()
            ->take(8)
            ->get();

        $activeIncomingCount = Ticket::actionableQueryFor($user)->count();

        $escalatedFromMyRoleCount = Ticket::whereHas('logs', function ($q) use ($role) {
            $q->where('from_role_id', $role?->id)
                ->whereIn('action', ['escalated', 'approved_step']);
        })->count();

        // Tiket yang dibuat oleh user ini sendiri
        $myCreatedTickets = Ticket::with(['category', 'currentRole', 'assignedUser'])
            ->where('creator_id', $user->id)
            ->latest()
            ->take(6)
            ->get();

        // Statistik Global & Performa Tiket
        $totalAllTickets = Ticket::count();
        $resolvedTicketsCount = Ticket::where('status', 'resolved')->count();
        $inProgressTicketsCount = Ticket::whereIn('status', ['in_progress', 'escalated', 'waiting_next_approval'])->count();
        $openTicketsCount = Ticket::where('status', 'open')->count();
        $rejectedTicketsCount = Ticket::where('status', 'rejected')->count();

        $completionRate = $totalAllTickets > 0 ? (int) round(($resolvedTicketsCount / $totalAllTickets) * 100) : 0;

        // Distribusi Pengguna Civitas
        $studentsCount = User::where('user_type', 'mahasiswa')->count();
        $lecturersCount = User::where('user_type', 'pengajar')->count();
        $employeesCount = User::whereIn('user_type', ['karyawan', 'admin'])->count();

        $stats = [
            'role_inbox_active' => $activeIncomingCount,
            'my_tickets_total' => Ticket::where('creator_id', $user->id)->count(),
            'my_tickets_resolved' => Ticket::where('creator_id', $user->id)->where('status', 'resolved')->count(),
            'escalated_or_forwarded' => $escalatedFromMyRoleCount,
            'total_all_tickets' => $totalAllTickets,
            'open_tickets' => $openTicketsCount,
            'in_progress_tickets' => $inProgressTicketsCount,
            'resolved_tickets' => $resolvedTicketsCount,
            'rejected_tickets' => $rejectedTicketsCount,
            'completion_rate' => $completionRate,
            'total_roles' => Role::count(),
            'total_menus' => Menu::count(),
            'total_users' => User::count(),
            'students_count' => $studentsCount,
            'lecturers_count' => $lecturersCount,
            'employees_count' => $employeesCount,
        ];

        // Khusus Superadmin: tiket terbaru sistem seluruh kampus dan aktivitas live
        $recentSystemTickets = collect();
        $recentLogs = collect();
        $topCategories = collect();

        if ($user->isSuperuser()) {
            $recentSystemTickets = Ticket::with(['creator', 'creatorRole', 'category', 'currentRole', 'assignedUser'])
                ->latest()
                ->take(8)
                ->get();

            $recentLogs = TicketLog::with(['ticket', 'user', 'fromRole', 'toRole'])
                ->latest()
                ->take(8)
                ->get();

            $topCategories = TicketCategory::withCount('tickets')
                ->where('is_active', true)
                ->orderByDesc('tickets_count')
                ->take(5)
                ->get();
        }

        $categories = TicketCategory::with(['initialTargetRole', 'maxApprovalRole'])
            ->where('is_active', true)
            ->get();

        return view('dashboard', compact(
            'user',
            'role',
            'incomingTickets',
            'myCreatedTickets',
            'recentSystemTickets',
            'recentLogs',
            'topCategories',
            'stats',
            'categories'
        ));
    }
}
