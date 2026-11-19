<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class PublicTicketController extends Controller
{
    public function form()
    {
        return view('tickets.track', [
            'results' => collect(),
            'q' => old('q', ''),
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:180'],
        ], [
            'q.required' => 'Isi kata kunci: judul, nomor tiket, nama pemohon, atau isi tiket.',
            'q.min' => 'Kata kunci minimal 2 karakter.',
        ]);

        $q = trim($validated['q']);
        $like = "%{$q}%";

        $results = Ticket::query()
            ->with(['category', 'currentRole', 'assignedUser', 'creator'])
            ->where(function ($query) use ($like, $q) {
                $query->where('ticket_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category_other', 'like', $like)
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like))
                    ->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', $like));
            })
            ->latest('updated_at')
            ->limit(20)
            ->get();

        if ($results->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['q' => 'Tidak ada tiket yang cocok dengan kata kunci itu.']);
        }

        if ($results->count() === 1) {
            return redirect()->route('tickets.track.show', [
                'ticketNumber' => $results->first()->ticket_number,
            ]);
        }

        return view('tickets.track', [
            'results' => $results,
            'q' => $q,
        ]);
    }

    public function show(string $ticketNumber)
    {
        $ticket = Ticket::query()
            ->where('ticket_number', strtoupper($ticketNumber))
            ->with(['category', 'currentRole', 'assignedUser', 'creatorRole', 'creator'])
            ->firstOrFail();

        $updates = $ticket->logs()
            ->with(['user', 'fromRole'])
            ->whereIn('action', ['created', 'comment', 'progress_reported', 'problem_done', 'resolved', 'final_approved', 'rejected', 'reopened'])
            ->latest()
            ->limit(12)
            ->get();

        return view('tickets.track-show', compact('ticket', 'updates'));
    }
}
