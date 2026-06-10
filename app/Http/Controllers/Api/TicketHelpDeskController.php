<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TicketHelpDesk;
use Illuminate\Support\Facades\Log;

class TicketHelpDeskController extends Controller
{
    public function getTickets()
    {
        $user = auth()->user();
    
        // Check if the logged-in user is admin
        if ($user->role_type === 'admin') {
            // Admin can view all tickets
            $tickets = TicketHelpDesk::with('user:id,name,email')
                ->latest()
                ->get();
        } else {
            // Non-admins can view only their own tickets
            $tickets = TicketHelpDesk::with('user:id,name,email')
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        }
    
        return response()->json([
            'message' => 'Tickets fetched successfully.',
            'data' => $tickets,
        ]);
    }

    public function storeTicket(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id'        => 'required|exists:users,id',
                'transaction_id' => 'nullable|string',
                'priority'       => 'nullable|in:low,medium,high',
                'subject'        => 'nullable|string|max:255',
                'description'    => 'nullable|string',
                'attachment'     => 'nullable|string',
                'assigned_to'    => 'nullable|string',
            ]);

            $ticket = TicketHelpDesk::create($validated);

            return response()->json([
                'message' => 'Ticket created successfully.',
                'data' => $ticket,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Ticket creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    public function showTicket($id)
    {
        $ticket = TicketHelpDesk::with('user:id,name,email')->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json([
            'message' => 'Ticket retrieved successfully.',
            'data' => $ticket,
        ]);
    }

    public function updateTicket(Request $request, $id)
    {
        try {
            $ticket = TicketHelpDesk::find($id);

            if (!$ticket) {
                return response()->json(['message' => 'Ticket not found.'], 404);
            }

            $validated = $request->validate([
                'transaction_id' => 'nullable|string',
                'status'         => 'nullable|in:open,in_progress,resolved,closed',
                'priority'       => 'nullable|in:low,medium,high',
                'subject'        => 'nullable|string|max:255',
                'description'    => 'nullable|string',
                'attachment'     => 'nullable|string',
                'admin_reply'    => 'nullable|array',
                'assigned_to'    => 'nullable|string',
            ]);

            $ticket->update($validated);

            return response()->json([
                'message' => 'Ticket updated successfully.',
                'data' => $ticket,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Ticket update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    public function deleteTicket($id)
    {
        $ticket = TicketHelpDesk::find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully.']);
    }
    
    public function manageStatusAndPriority(Request $request){
        dd("manageStatusAndPriority");
    }
    
}