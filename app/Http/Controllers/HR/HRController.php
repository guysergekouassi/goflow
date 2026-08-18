<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HRController extends Controller
{
    /**
     * Dashboard RH
     */
    public function dashboard()
    {
        if (!Auth::check() || !in_array(Auth::user()->type, ['hr', 'paie'])) {
            abort(403, 'Accès non autorisé');
        }

        $user = Auth::user();

        // Statistiques de base pour le RH
        $stats = [
            'total_employees' => 0, // À implémenter selon la logique métier
            'pending_requests' => 0,
            'upcoming_events' => 0,
        ];

        return view('hr.dashboard', compact('user', 'stats'));
    }
}
