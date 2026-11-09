<?php

namespace Tests\Feature;

use App\Console\Commands\SendWeeklyAttendanceReportCommand;
use App\Exports\WeeklyAttendanceExport;
use App\Jobs\SendWeeklyAttendanceReportJob;
use App\Mail\WeeklyAttendanceReportMail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\GpsDeclaration;
use App\Models\User;
use App\Services\WeeklyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\Leaves\Models\Leave;
use Modules\Time\Models\Holiday;
use Modules\Time\Models\Pointeuse;
use Modules\Time\Models\TimeSheet;
use Tests\TestCase;

class WeeklyAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected WeeklyAttendanceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WeeklyAttendanceReportService();
    }

    /**
     * Helper pour créer une entreprise avec utilisateur créateur.
     */
    private function createCompany(array $attributes = []): Company
    {
        $creator = User::create([
            'name'       => 'Creator User',
            'email'      => 'creator_' . uniqid() . '@example.com',
            'password'   => bcrypt('secret123'),
            'type'       => 'super_admin',
            'created_by' => 1,
        ]);

        return Company::create(array_merge([
            'name'                   => 'ACME Corp',
            'email'                  => 'contact@acme.com',
            'working_days_per_week'  => 5, // Lundi à vendredi
            'working_hours_per_week' => 40,
            'is_active'              => true,
            'subscription_status'    => 'active',
            'created_by'             => $creator->id,
            'settings'               => ['timezone' => 'Africa/Abidjan'],
        ], $attributes));
    }

    /**
     * Helper pour créer un employé.
     */
    private function createEmployee(Company $company, array $attributes = []): Employee
    {
        $user = User::create([
            'name'       => 'Emp User ' . uniqid(),
            'email'      => 'emp_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'type'       => 'employee',
            'company_id' => $company->id,
            'created_by' => $company->created_by,
        ]);

        return Employee::create(array_merge([
            'user_id'     => $user->id,
            'company_id'  => $company->id,
            'name'        => 'Jean Dupont',
            'employee_id' => 'EMP-' . rand(1000, 9999),
            'email'       => $user->email,
            'is_active'   => 1,
            'start_date'  => '2026-01-01',
        ], $attributes));
    }

    /**
     * Test 1 : Vérification de la programmation automatique du vendredi à 18h
     */
    public function test_scheduled_task_is_configured_for_fridays_at_18h(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $weeklyAttendanceEvent = $events->first(function ($event) {
            return str_contains($event->command, 'attendance:send-weekly-report');
        });

        $this->assertNotNull($weeklyAttendanceEvent, 'La commande attendance:send-weekly-report doit être planifiée.');
        $this->assertEquals('0 18 * * 5', $weeklyAttendanceEvent->expression, 'La commande doit être planifiée chaque vendredi à 18h00.');
    }

    /**
     * Test 2 : Période couverte du lundi 00h00 au vendredi 18h00
     */
    public function test_report_period_covers_monday_to_friday_at_18h(): void
    {
        $company = $this->createCompany();
        // Vendredi 30 octobre 2026
        $fridayRef = Carbon::parse('2026-10-30 18:00:00', 'Africa/Abidjan');

        $data = $this->service->buildReportData($company, $fridayRef);

        $this->assertEquals('2026-10-26', $data['period']['start_date'], 'Le début de période doit être le lundi.');
        $this->assertEquals('2026-10-30', $data['period']['end_date'], 'La fin de période doit être le vendredi.');
        $this->assertStringContainsString('00h00', $data['period']['start_formatted']);
        $this->assertStringContainsString('18h00', $data['period']['end_formatted']);
    }

    /**
     * Test 3 : Exclusion des pointages postérieurs au vendredi 18h00
     */
    public function test_pointages_after_friday_18h_are_strictly_excluded(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);
        $friday = '2026-10-30';

        // Pointage vendredi à 17h45 (inclus)
        Pointeuse::create([
            'employee_id'     => $employee->id,
            'company_id'      => $company->id,
            'auth_date'       => $friday,
            'auth_time'       => '17:45:00',
            'auth_date_time'  => "{$friday} 17:45:00",
            'type'            => 'sortie',
            'scan_status'     => 'a_l_heure',
            'name_device'     => 'Pointeuse QR CODE',
        ]);

        // Pointage vendredi à 18h15 (exclu car postérieur à 18h00)
        Pointeuse::create([
            'employee_id'     => $employee->id,
            'company_id'      => $company->id,
            'auth_date'       => $friday,
            'auth_time'       => '18:15:00',
            'auth_date_time'  => "{$friday} 18:15:00",
            'type'            => 'sortie',
            'scan_status'     => 'a_l_heure',
            'name_device'     => 'Pointeuse QR CODE',
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse("{$friday} 18:00:00"));
        $empDays = $data['employees'][0]['days'][$friday];

        // L'heure de départ retenue doit être 17:45 et non 18:15
        $this->assertEquals('17:45', $empDays['departure_time']);
    }

    /**
     * Test 4 : Calcul des présences, des retards et durées
     */
    public function test_calculation_of_presences_and_lateness(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);
        $monday = '2026-10-26';

        // Arrivée en retard : 08h45 (au lieu de 08h30), durée 15 min
        Pointeuse::create([
            'employee_id'    => $employee->id,
            'company_id'     => $company->id,
            'auth_date'      => $monday,
            'auth_time'      => '08:45:00',
            'auth_date_time' => "{$monday} 08:45:00",
            'type'           => 'entree',
            'scan_status'    => 'retard',
            'duree_retard'   => 15,
            'name_device'    => 'Pointeuse QR CODE',
        ]);

        // Sortie : 17h30
        Pointeuse::create([
            'employee_id'    => $employee->id,
            'company_id'     => $company->id,
            'auth_date'      => $monday,
            'auth_time'      => '17:30:00',
            'auth_date_time' => "{$monday} 17:30:00",
            'type'           => 'sortie',
            'scan_status'    => 'a_l_heure',
            'name_device'    => 'Pointeuse QR CODE',
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $this->assertEquals(1, $data['indicators']['total_presences'], 'Une journée de présence doit être comptabilisée.');
        $this->assertEquals(1, $data['indicators']['late_employees_count'], 'Un employé doit être comptabilisé en retard.');
        $this->assertEquals(1, $data['indicators']['total_retards_count'], 'Un retard doit être comptabilisé.');
        $this->assertEquals(15, $data['indicators']['total_retards_minutes'], '15 minutes de retard doivent être comptabilisées.');

        $empMonday = $data['employees'][0]['days'][$monday];
        $this->assertTrue($empMonday['is_late']);
        $this->assertEquals(15, $empMonday['retard_minutes']);
        $this->assertEquals('08:45', $empMonday['arrival_time']);
        $this->assertEquals('17:30', $empMonday['departure_time']);
        $this->assertEquals('08h45', $empMonday['worked_time']);
    }

    /**
     * Test 5 : Pointages incomplets (entrée seule sans sortie)
     */
    public function test_incomplete_pointage_detection(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);
        $tuesday = '2026-10-27';

        // Entrée uniquement
        Pointeuse::create([
            'employee_id'    => $employee->id,
            'company_id'     => $company->id,
            'auth_date'      => $tuesday,
            'auth_time'      => '08:00:00',
            'auth_date_time' => "{$tuesday} 08:00:00",
            'type'           => 'entree',
            'scan_status'    => 'a_l_heure',
            'name_device'    => 'Géolocalisation GPS',
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $this->assertEquals(1, $data['indicators']['total_incomplete_pointages']);
        $this->assertTrue($data['employees'][0]['days'][$tuesday]['is_incomplete']);
        $this->assertEquals('--:--', $data['employees'][0]['days'][$tuesday]['departure_time']);
    }

    /**
     * Test 6 : Absences confirmées et justifiées (TimeSheet et Leave)
     */
    public function test_justified_absences_and_leaves(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);
        $wednesday = '2026-10-28';

        // Absence justifiée enregistrée dans TimeSheet
        TimeSheet::create([
            'employee_id'   => $employee->id,
            'company_id'    => $company->id,
            'date'          => $wednesday,
            'arrival_date'  => $wednesday,
            'motif_justify' => 'Oui',
            'type_permis'   => 'Permission exceptionnelle',
            'hours'         => 8,
            'monthpaie'     => '10-2026',
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $this->assertEquals(1, $data['indicators']['total_justified_absences']);
        $this->assertEquals('Absence justifiée', $data['employees'][0]['days'][$wednesday]['status']);
        $this->assertEquals('Permission exceptionnelle', $data['employees'][0]['days'][$wednesday]['motif']);
    }

    /**
     * Test 7 : Jours fériés chômés non comptabilisés comme des absences
     */
    public function test_holidays_are_not_counted_as_absences(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);
        $thursday = '2026-10-29';

        // L'employé pointe les autres jours (Lundi, Mardi, Mercredi, Vendredi)
        foreach (['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-30'] as $workDay) {
            Pointeuse::create([
                'employee_id'    => $employee->id,
                'company_id'     => $company->id,
                'auth_date'      => $workDay,
                'auth_time'      => '08:00:00',
                'auth_date_time' => "{$workDay} 08:00:00",
                'type'           => 'entree',
                'scan_status'    => 'a_l_heure',
            ]);
            Pointeuse::create([
                'employee_id'    => $employee->id,
                'company_id'     => $company->id,
                'auth_date'      => $workDay,
                'auth_time'      => '17:00:00',
                'auth_date_time' => "{$workDay} 17:00:00",
                'type'           => 'sortie',
                'scan_status'    => 'a_l_heure',
            ]);
        }

        // Férié chômé le jeudi
        Holiday::create([
            'company_id'   => $company->id,
            'name'         => 'Fête Nationale',
            'date'         => $thursday,
            'is_active'    => true,
            'is_worked'    => false,
            'is_recurring' => false,
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $this->assertEquals('Férié', $data['employees'][0]['days'][$thursday]['status']);
        $this->assertEquals(0, $data['employees'][0]['days'][$thursday]['retard_minutes']);
        $this->assertEquals(4, $data['indicators']['total_presences'], '4 journées de présence.');
        $this->assertEquals(0, $data['indicators']['total_unjustified_absences'], 'Le jour férié ne doit générer aucune absence.');
    }

    /**
     * Test 8 : Répartition des méthodes de pointage (QR, GPS, Biométrie)
     */
    public function test_methods_breakdown_classification(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);

        // QR Code
        Pointeuse::create([
            'employee_id'    => $employee->id,
            'company_id'     => $company->id,
            'auth_date'      => '2026-10-26',
            'auth_time'      => '08:00:00',
            'auth_date_time' => '2026-10-26 08:00:00',
            'type'           => 'entree',
            'name_device'    => 'Pointeuse QR CODE Site Alpha',
        ]);

        // GPS
        Pointeuse::create([
            'employee_id'    => $employee->id,
            'company_id'     => $company->id,
            'auth_date'      => '2026-10-27',
            'auth_time'      => '08:05:00',
            'auth_date_time' => '2026-10-27 08:05:00',
            'type'           => 'entree',
            'name_device'    => 'Géolocalisation GPS Mobile',
        ]);

        // Biométrie
        Pointeuse::create([
            'employee_id'    => $employee->id,
            'company_id'     => $company->id,
            'auth_date'      => '2026-10-28',
            'auth_time'      => '08:10:00',
            'auth_date_time' => '2026-10-28 08:10:00',
            'type'           => 'entree',
            'name_device'    => 'Pointeuse Biométrique ZKTeco',
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $this->assertEquals(1, $data['methods_breakdown']['qr_code']['count']);
        $this->assertEquals(1, $data['methods_breakdown']['gps']['count']);
        $this->assertEquals(1, $data['methods_breakdown']['biometrique']['count']);
    }

    /**
     * Test 9 : Déclarations GPS en attente et rejetées
     */
    public function test_gps_declarations_pending_and_rejected(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company);

        GpsDeclaration::create([
            'id'          => 'GPS-' . uniqid(),
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'date'        => '2026-10-26',
            'time'        => '08:00:00',
            'status'      => 'En attente',
        ]);

        GpsDeclaration::create([
            'id'          => 'GPS-' . uniqid(),
            'company_id'  => $company->id,
            'employee_id' => $employee->id,
            'date'        => '2026-10-27',
            'time'        => '08:15:00',
            'status'      => 'Rejetée',
        ]);

        $data = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $this->assertEquals(1, $data['indicators']['total_pending_declarations']);
        $this->assertEquals(1, $data['indicators']['total_rejected_declarations']);
    }

    /**
     * Test 10 : Destinataires autorisés (Entreprise, RH, Paie, settings)
     */
    public function test_authorized_recipients_resolution(): void
    {
        $company = $this->createCompany([
            'email'    => 'direction@acme.com',
            'settings' => [
                'weekly_report_recipients' => ['drh_externe@consulting.com'],
            ],
        ]);

        // Utilisateur RH
        User::create([
            'name'       => 'Responsable RH',
            'email'      => 'rh@acme.com',
            'password'   => bcrypt('password'),
            'type'       => 'hr',
            'company_id' => $company->id,
            'is_active'  => true,
            'created_by' => $company->created_by,
        ]);

        // Utilisateur Paie
        User::create([
            'name'       => 'Responsable Paie',
            'email'      => 'paie@acme.com',
            'password'   => bcrypt('password'),
            'type'       => 'paie',
            'company_id' => $company->id,
            'is_active'  => true,
            'created_by' => $company->created_by,
        ]);

        // Employé standard (NE DOIT PAS recevoir le rapport)
        User::create([
            'name'       => 'Simple Employé',
            'email'      => 'salarie@acme.com',
            'password'   => bcrypt('password'),
            'type'       => 'employee',
            'company_id' => $company->id,
            'is_active'  => true,
            'created_by' => $company->created_by,
        ]);

        $recipients = $this->service->resolveRecipients($company);

        $this->assertContains('direction@acme.com', $recipients);
        $this->assertContains('rh@acme.com', $recipients);
        $this->assertContains('paie@acme.com', $recipients);
        $this->assertContains('drh_externe@consulting.com', $recipients);
        $this->assertNotContains('salarie@acme.com', $recipients, 'Les employés non-RH ne doivent jamais être destinataires.');
    }

    /**
     * Test 11 : Génération du PDF et de l'Excel
     */
    public function test_pdf_and_excel_generation_are_functional(): void
    {
        $company = $this->createCompany();
        $this->createEmployee($company);

        $reportData = $this->service->buildReportData($company, Carbon::parse('2026-10-30 18:00:00'));

        $pdf = $this->service->generatePdf($reportData);
        $this->assertNotEmpty($pdf, 'Le contenu du PDF ne doit pas être vide.');
        $this->assertStringStartsWith('%PDF', $pdf, 'Le fichier généré doit être un document PDF valide.');

        $excel = $this->service->generateExcel($reportData);
        $this->assertNotEmpty($excel, 'Le contenu Excel ne doit pas être vide.');
    }

    /**
     * Test 12 : Envoi simulé d'e-mail avec Mail::fake()
     */
    public function test_email_sending_simulation(): void
    {
        Mail::fake();

        $company = $this->createCompany();
        $this->createEmployee($company);

        User::create([
            'name'       => 'RH User',
            'email'      => 'rh@test.com',
            'password'   => bcrypt('pass'),
            'type'       => 'hr',
            'company_id' => $company->id,
            'is_active'  => true,
            'created_by' => $company->created_by,
        ]);

        $result = $this->service->sendWeeklyReportForCompany($company, Carbon::parse('2026-10-30 18:00:00'), true);

        $this->assertTrue($result['success']);
        Mail::assertSent(WeeklyAttendanceReportMail::class, function ($mail) {
            return $mail->hasTo('contact@acme.com') || $mail->hasTo('rh@test.com');
        });
    }

    /**
     * Test 13 : Prévention des envois en doublon
     */
    public function test_duplicate_prevention_mechanism(): void
    {
        Mail::fake();

        $company = $this->createCompany();
        $this->createEmployee($company);
        $date = Carbon::parse('2026-10-30 18:00:00');

        // Premier envoi
        $res1 = $this->service->sendWeeklyReportForCompany($company, $date, false);
        $this->assertTrue($res1['success']);

        // Deuxième envoi immédiat sans force
        $res2 = $this->service->sendWeeklyReportForCompany($company, $date, false);
        $this->assertFalse($res2['success'], 'Le rapport en double doit être refusé.');
        $this->assertStringContainsString('déjà envoyé', $res2['message']);

        // Troisième envoi avec force=true
        $res3 = $this->service->sendWeeklyReportForCompany($company, $date, true);
        $this->assertTrue($res3['success'], 'Le rapport forcé doit être autorisé.');
    }

    /**
     * Test 14 : Exécution de la commande Artisan avec option --dry-run
     */
    public function test_artisan_command_dry_run(): void
    {
        $company = $this->createCompany(['name' => 'Société Test DryRun']);
        $this->createEmployee($company);

        $this->artisan('attendance:send-weekly-report', [
            '--company' => $company->id,
            '--dry-run' => true,
        ])->assertExitCode(0);
    }
}
