<?php

namespace App\Http\Controllers;

use App\Models\LotAcces;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Récapitulatifs des accès créés depuis RHFlow (identifiants et mots de passe provisoires).
 *
 * Réservé au super-admin (toutes les sociétés) et aux responsables d'une société
 * (compte entreprise ou RH, pour leur seule société). Les lots expirés sont supprimés.
 */
class LotAccesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $this->autoriser($user);

        // Les mots de passe provisoires ne restent pas indéfiniment sur le serveur
        LotAcces::where('expire_le', '<', now())->delete();

        $lots = LotAcces::with('company')
            ->when(!$this->estSuperAdmin($user), fn ($q) => $q->where('company_id', $user->company_id))
            ->latest()
            ->get();

        return view('acces-gpflow.index', [
            'lots'       => $lots,
            'superAdmin' => $this->estSuperAdmin($user),
        ]);
    }

    public function telecharger(LotAcces $lot)
    {
        $user = Auth::user();
        $this->autoriser($user, $lot);

        if ($lot->estExpire()) {
            $lot->delete();
            abort(410, "Ce récapitulatif a expiré et a été supprimé. Les mots de passe ne sont plus disponibles : réinitialisez-les depuis la fiche du salarié.");
        }

        $lot->forceFill(['telecharge_le' => now(), 'telechargements' => $lot->telechargements + 1])->save();
        Log::info('Récapitulatif des accès téléchargé', ['lot_id' => $lot->id, 'company_id' => $lot->company_id, 'user_id' => $user->id]);

        $contenu = $lot->contenu;
        $nomEntreprise = $contenu['entreprise']['nom'] ?? ($lot->company->name ?? 'Entreprise');
        $fichier = 'acces-gpflow-' . Str::slug($nomEntreprise) . '-' . $lot->created_at->format('Y-m-d') . '.xlsx';

        $classeur = $this->classeur($nomEntreprise, $lot, $contenu['comptes'] ?? []);

        return response()->streamDownload(function () use ($classeur) {
            (new Xlsx($classeur))->save('php://output');
        }, $fichier, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function classeur(string $nomEntreprise, LotAcces $lot, array $comptes): Spreadsheet
    {
        $classeur = new Spreadsheet();
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle('Accès GPFlow');

        $feuille->setCellValue('A1', "Accès GPFlow — {$nomEntreprise}");
        $feuille->setCellValue('A2', 'Généré le ' . $lot->created_at->format('d/m/Y à H:i') . ' · Application : ' . config('app.url'));
        $feuille->setCellValue('A3', 'CONFIDENTIEL : à remettre individuellement à chaque personne puis à détruire. Changez votre mot de passe dès la première connexion.');
        $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $feuille->getStyle('A3')->getFont()->setBold(true)->getColor()->setRGB('C00000');

        $entetes = ['Type de compte', 'Nom', 'Matricule', 'Identifiant', 'Mot de passe provisoire', 'E-mail'];
        $feuille->fromArray($entetes, null, 'A5');
        $feuille->getStyle('A5:F5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $feuille->getStyle('A5:F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');

        $ligne = 6;
        foreach ($comptes as $compte) {
            $feuille->fromArray([
                $compte['type'] ?? '',
                $compte['nom'] ?? '',
                $compte['matricule'] ?? '',
                $compte['identifiant'] ?? '',
                $compte['mot_de_passe'] ?? '',
                $compte['email'] ?? '',
            ], null, "A{$ligne}");
            // Texte brut : un matricule ou un mot de passe ne doit pas être converti en nombre
            foreach (['C', 'D', 'E'] as $colonne) {
                $feuille->getCell("{$colonne}{$ligne}")->setValueExplicit((string) ($feuille->getCell("{$colonne}{$ligne}")->getValue() ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $ligne++;
        }

        foreach (range('A', 'F') as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
        }
        $feuille->getStyle('D6:E' . max(6, $ligne - 1))->getFont()->setName('Consolas');

        return $classeur;
    }

    private function autoriser(?User $user, ?LotAcces $lot = null): void
    {
        if (!$user) {
            abort(403);
        }
        if ($this->estSuperAdmin($user)) {
            return;
        }
        if (!in_array($user->type, ['company', 'hr'], true) || !$user->company_id) {
            abort(403, 'Réservé aux responsables de la société.');
        }
        if ($lot && (int) $lot->company_id !== (int) $user->company_id) {
            abort(403, 'Ce récapitulatif concerne une autre société.');
        }
    }

    private function estSuperAdmin(User $user): bool
    {
        return $user->type === 'super_admin';
    }
}
