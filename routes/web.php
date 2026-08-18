<?php

use Illuminate\Support\Facades\Route;
use Modules\LandingPage\Http\Controllers\LandingPageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AllowanceController;
use App\Http\Controllers\AllowanceOptionController;

// Inclure les routes d'authentification (sans middleware maintenance pour permettre la connexion)
require __DIR__.'/auth.php';

// Routes de test pour les pages d'erreur (uniquement en mode debug)
if (config('app.debug')) {
    require __DIR__.'/test-errors.php';
}

// Page de maintenance (accessible à tous)
Route::get('/maintenance', function () {
    $maintenanceMessage = \App\Models\Setting::get('maintenance_message', 'Le site est en maintenance.');
    return view('maintenance', [
        'message' => $maintenanceMessage,
        'title' => 'Maintenance - RH Flow'
    ]);
})->name('maintenance');

// Routes des notifications (accessibles à tous les utilisateurs authentifiés)
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    // Actions AJAX
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::patch('/notifications/{notification}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.mark-unread');
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/notifications/ajax/unread', [NotificationController::class, 'getUnread'])->name('notifications.ajax.unread');
    Route::get('/notifications/stats', [NotificationController::class, 'stats'])->name('notifications.stats');

    // Guide utilisateur complet
    Route::get('/guide-utilisateur', function () {
        return view('guide.index');
    })->name('guide-utilisateur');
});

// URL encodée dans le QR Code unique par succursale.
// L'application mobile employé n'ouvre jamais cette page : elle reconnaît le chemin
// /pointage/scanner, puis poste le contenu du QR sur /api/pointage/scanner.
// Scanné avec un appareil photo classique, on bascule sur le portail superviseur.
Route::get('/pointage/scanner', function (\Illuminate\Http\Request $request) {
    $branchId = $request->query('branch_id') ?: $request->query('site_id');

    if (!$branchId) {
        abort(400, 'QR Code incomplet : aucune succursale précisée.');
    }

    // Un QR supprimé ou régénéré depuis l'administration n'ouvre plus le portail.
    $branch  = \App\Models\Branch::find($branchId);
    $qrToken = $request->query('qr');

    if (!$branch || empty($branch->qr_token)) {
        abort(410, 'QR Code supprimé : ce code n\'est plus actif. Demandez le nouveau QR Code à votre administrateur.');
    }

    if (!$qrToken || !hash_equals((string) $branch->qr_token, (string) $qrToken)) {
        abort(410, 'QR Code périmé : ce code a été remplacé par un nouveau QR Code.');
    }

    return redirect()->route('company.times.qrcode-pointage.branch-portal', ['branch_id' => $branchId]);
})->name('pointage.scanner');

// Inclure les routes des utilisateurs
require __DIR__.'/user.php';

// Routes pour les allowances (web interface)
Route::middleware(['auth'])->group(function () {
    // Options d'allocation
    Route::resource('allowance-options', AllowanceOptionController::class)->except(['show']);

    // Allocations
    Route::resource('allowances', AllowanceController::class)->except(['show']);
    Route::get('allowances/employee/{employeeId}', [AllowanceController::class, 'getByEmployee'])->name('allowances.by-employee');
    Route::get('allowances/{allowance}/print', [AllowanceController::class, 'print'])->name('allowances.print');
});

// Inclure les routes des modules activés
Route::middleware(['auth', 'maintenance'])->group(function () {

});

// Inclure les routes Super Admin (avec leur propre middleware)
require __DIR__.'/super-admin.php';
