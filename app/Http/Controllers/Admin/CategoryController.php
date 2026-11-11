<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = TicketCategory::with([
            'initialTargetRole.parent',
            'maxApprovalRole',
            'division.headRole.head',
            'division.roles',
        ])
            ->withCount('tickets')
            ->latest()
            ->get();

        $roles = Role::where('is_superuser', false)
            ->with(['priorityRoutes.user:id,name', 'head:id,name', 'division:id,name'])
            ->withCount('users')
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        $roles->whereNotNull('head_user_id')->load(['users:id,role_id,name,keahlian']);

        $divisions = Division::with([
            'roles' => fn ($q) => $q->orderBy('level')->orderBy('name')->with('head:id,name'),
            'headRole.head:id,name',
            'spvRole.head:id,name',
            'staffRole',
        ])->orderBy('name')->get();

        return view('admin.categories', compact('categories', 'roles', 'divisions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:500'],
            'workflow_type' => ['required', 'in:escalation,tiered_approval'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'workflow_roles' => ['nullable', 'array'],
            'workflow_roles.*' => ['nullable', 'exists:roles,id'],
            'initial_target_role_id' => ['nullable', 'exists:roles,id'],
            'max_approval_role_id' => ['nullable', 'exists:roles,id'],
            'template_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip', 'max:10240'],
        ]);

        [$workflowRoles, $initialTargetRoleId, $maxApprovalRoleId, $divisionId] = $this->resolveServicePlot(
            $validated,
            $validated['initial_target_role_id'] ?? null,
            $validated['max_approval_role_id'] ?? null,
        );

        $templatePath = null;
        $templateName = null;
        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $templateName = $file->getClientOriginalName();
            $templatePath = Uploads::store($file, 'category-templates');
        }

        $slug = Str::slug($validated['name']);
        if (TicketCategory::where('slug', $slug)->exists()) {
            $slug .= '-' . rand(10, 99);
        }

        TicketCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => 'ticket',
            'color' => $validated['color'] ?? 'indigo',
            'description' => $validated['description'] ?? null,
            'workflow_roles' => !empty($workflowRoles) ? $workflowRoles : null,
            'initial_target_role_id' => $initialTargetRoleId ?: null,
            'workflow_type' => $validated['workflow_type'],
            'division_id' => $divisionId,
            'max_approval_role_id' => $maxApprovalRoleId ?: null,
            'template_path' => $templatePath,
            'template_name' => $templateName,
            'is_attachment_required' => $request->has('is_attachment_required')
                ? $request->boolean('is_attachment_required')
                : ($templatePath !== null),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Kategori layanan '{$validated['name']}' beserta tahapan hirarki dan template dokumennya berhasil ditambahkan!");
    }

    public function update(Request $request, TicketCategory $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'in:indigo,emerald,amber,rose,violet,sky,teal,fuchsia'],
            'description' => ['nullable', 'string', 'max:500'],
            'workflow_type' => ['required', 'in:escalation,tiered_approval'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'workflow_roles' => ['nullable', 'array'],
            'workflow_roles.*' => ['nullable', 'exists:roles,id'],
            'initial_target_role_id' => ['nullable', 'exists:roles,id'],
            'max_approval_role_id' => ['nullable', 'exists:roles,id'],
            'template_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip', 'max:10240'],
        ]);

        $postedRoles = collect($validated['workflow_roles'] ?? [])->filter(fn ($id) => !empty($id));
        if (empty($validated['division_id']) && $postedRoles->isEmpty()) {
            $workflowRoles = $category->workflow_roles ?? [];
            $initialTargetRoleId = $category->initial_target_role_id;
            $maxApprovalRoleId = $category->max_approval_role_id;
            $divisionId = $category->division_id;
        } else {
            [$workflowRoles, $initialTargetRoleId, $maxApprovalRoleId, $divisionId] = $this->resolveServicePlot(
                $validated,
                $validated['initial_target_role_id'] ?? $category->initial_target_role_id,
                $validated['max_approval_role_id'] ?? $category->max_approval_role_id,
            );
        }

        $templatePath = $category->template_path;
        $templateName = $category->template_name;

        if ($request->boolean('remove_template') && $templatePath) {
            Uploads::delete($templatePath);
            $templatePath = null;
            $templateName = null;
        }

        if ($request->hasFile('template_file')) {
            Uploads::delete($templatePath);
            $file = $request->file('template_file');
            $templateName = $file->getClientOriginalName();
            $templatePath = Uploads::store($file, 'category-templates');
        }

        $category->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $category->color ?? 'indigo',
            'description' => $validated['description'] ?? null,
            'workflow_roles' => !empty($workflowRoles) ? $workflowRoles : null,
            'initial_target_role_id' => $initialTargetRoleId ?: null,
            'workflow_type' => $validated['workflow_type'],
            'division_id' => $divisionId,
            'max_approval_role_id' => $maxApprovalRoleId ?: null,
            'template_path' => $templatePath,
            'template_name' => $templateName,
            'is_attachment_required' => $request->boolean('is_attachment_required'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Kategori layanan '{$category->name}' berhasil diperbarui!");
    }

    public function destroy(TicketCategory $category)
    {
        if ($category->tickets()->exists()) {
            return back()->with('error', 'Kategori layanan ini memiliki tiket aktif dan tidak dapat dihapus.');
        }

        Uploads::delete($category->template_path);

        $name = $category->name;
        $category->delete();

        return back()->with('success', "Kategori layanan '{$name}' berhasil dihapus.");
    }

    /**
     * @return array{0: array<int, int>, 1: ?int, 2: ?int, 3: ?int}
     */
    private function resolveServicePlot(array $validated, ?int $initialTargetRoleId, ?int $maxApprovalRoleId): array
    {
        $divisionId = !empty($validated['division_id']) ? (int) $validated['division_id'] : null;

        $workflowRoles = collect($validated['workflow_roles'] ?? [])
            ->filter(fn ($id) => !empty($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($workflowRoles) && $divisionId) {
            $division = Division::find($divisionId);
            $workflowRoles = $division ? $division->workflowRoleIds() : [];
        }

        if (!empty($workflowRoles)) {
            $rolesById = Role::whereIn('id', $workflowRoles)->get()->keyBy('id');
            $orderedRoles = collect($workflowRoles)->map(fn ($id) => $rolesById->get($id))->filter()->values();
            $recipientRoles = ($orderedRoles->count() > 1 && !$orderedRoles->first()->can_receive_ticket)
                ? $orderedRoles->slice(1)->values()
                : $orderedRoles;

            $initialTargetRoleId = $recipientRoles->first()?->id ?? $workflowRoles[0];
            $maxApprovalRoleId = $recipientRoles->last()?->id ?? end($workflowRoles);
        }

        return [$workflowRoles, $initialTargetRoleId, $maxApprovalRoleId, $divisionId];
    }
}

