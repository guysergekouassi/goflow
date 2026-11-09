<?php

namespace Tests\Feature;

use App\Mail\MonthlyAttendanceReportMail;
use App\Jobs\GenerateMonthlyAttendanceReportJob;
use App\Http\Controllers\Company\MonthlyReportController;
use App\Models\Company;
use App\Models\MonthlyReportConfig;
use App\Models\MonthlyReportHistory;
use App\Models\User;
use App\Services\MonthlyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Employees\Models\Employee;
use Modules\Leaves\Models\Leave;
use Modules\Time\Models\Holiday;
use Modules\Time\Models\Pointeuse;
use Modules\Time\Models\TimeSheet;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MonthlyAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createCompany(array $attributes = []): Company
    {
        $creator = User::create([
            'name'       => 'Monthly Report Creator',
            'email'      => uniqid('creator_') . '@example.com',
            'password'   => bcrypt('secret123'),
            'type'       => 'super_admin',
            'created_by' => 1,
        ]);

        return Company::create(array_merge([
            'name'                   => 'Monthly Test Company',
            'email'                  => uniqid('company_') . '@example.com',
            'working_days_per_week'  => 5,
            'working_hours_per_week' => 40,
            'is_active'              => true,
            'subscription_status'    => 'active',
            'created_by'             => $creator->id,
            'settings'               => ['timezone' => 'Africa/Abidjan'],
        ], $attributes));
    }

    private function createEmployee(Company $company, string $name): Employee
    {
        $user = User::create([
            'name'       => $name,
            'email'      => uniqid('employee_') . '@example.com',
            'password'   => bcrypt('password'),
            'type'       => 'employee',
            'company_id' => $company->id,
            'created_by' => $company->created_by,
        ]);
        $company->update(['user_id' => $user->id]);

        return Employee::create([
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'name'       => $name,
            'is_active'  => true,
        ]);
    }

    public function test_report_period_handles_leap_year_and_company_isolation(): void
    {
        $firstCompany = $this->createCompany();
        $secondCompany = $this->createCompany();
        $firstEmployee = $this->createEmployee($firstCompany, 'Employé A');
        $secondEmployee = $this->createEmployee($secondCompany, 'Employé B');

        $data = app(MonthlyAttendanceReportService::class)->buildReportData(
            $firstCompany,
            Carbon::parse('2024-02-01', 'Africa/Abidjan'),
            true
        );

        $this->assertSame('2024-02-01', $data['period']['start_date']);
        $this->assertSame('2024-02-29', $data['period']['end_date']);
        $this->assertSame([$firstEmployee->id], array_column($data['employees'], 'id'));
        $this->assertNotContains($secondEmployee->id, array_column($data['employees'], 'id'));

        foreach ([
            ['2023-02-01', '2023-02-28'],
            ['2026-04-01', '2026-04-30'],
            ['2026-03-01', '2026-03-31'],
        ] as [$start, $end]) {
            $monthData = app(MonthlyAttendanceReportService::class)->buildReportData(
                $firstCompany,
                Carbon::parse($start, 'Africa/Abidjan'),
                true
            );
            $this->assertSame($start, $monthData['period']['start_date']);
            $this->assertSame($end, $monthData['period']['end_date']);
        }
    }

    public function test_company_configuration_is_stored_independently(): void
    {
        $firstCompany = $this->createCompany();
        $secondCompany = $this->createCompany();

        MonthlyReportConfig::create([
            'company_id'        => $firstCompany->id,
            'is_enabled'        => true,
            'send_day'          => 31,
            'send_hour'         => 9,
            'send_minute'       => 15,
            'recipients'        => ['rh-a@example.com'],
            'report_format'     => 'pdf',
            'auto_send_enabled' => true,
        ]);
        MonthlyReportConfig::create([
            'company_id'        => $secondCompany->id,
            'is_enabled'        => true,
            'send_day'          => 5,
            'send_hour'         => 14,
            'send_minute'       => 30,
            'recipients'        => ['rh-b@example.com'],
            'report_format'     => 'excel',
            'auto_send_enabled' => false,
        ]);

        $this->assertSame(31, $firstCompany->monthlyReportConfig->send_day);
        $this->assertSame(['rh-a@example.com'], $firstCompany->monthlyReportConfig->recipients);
        $this->assertSame(5, $secondCompany->monthlyReportConfig->send_day);
        $this->assertSame(['rh-b@example.com'], $secondCompany->monthlyReportConfig->recipients);
    }

    public function test_scheduler_uses_company_timezone_minute_and_end_of_month_clamp(): void
    {
        Queue::fake();
        $utcCompany = $this->createCompany(['settings' => ['timezone' => 'UTC']]);
        $losAngelesCompany = $this->createCompany(['settings' => ['timezone' => 'America/Los_Angeles']]);

        foreach ([$utcCompany, $losAngelesCompany] as $company) {
            MonthlyReportConfig::create([
                'company_id'        => $company->id,
                'is_enabled'        => true,
                'auto_send_enabled' => true,
                'send_day'          => 31,
                'send_hour'         => 8,
                'send_minute'       => 15,
                'recipients'        => ['rh@example.com'],
                'report_format'     => 'pdf',
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-02-28 08:15:00', 'UTC'));
        $this->artisan('attendance:send-monthly-report')->assertExitCode(0);
        Queue::assertPushed(GenerateMonthlyAttendanceReportJob::class, function ($job) use ($utcCompany) {
            return $job->company->id === $utcCompany->id;
        });
        Queue::assertNotPushed(GenerateMonthlyAttendanceReportJob::class, function ($job) use ($losAngelesCompany) {
            return $job->company->id === $losAngelesCompany->id;
        });

        Carbon::setTestNow(Carbon::parse('2026-02-28 16:15:00', 'UTC'));
        $this->artisan('attendance:send-monthly-report')->assertExitCode(0);
        Queue::assertPushed(GenerateMonthlyAttendanceReportJob::class, function ($job) use ($losAngelesCompany) {
            return $job->company->id === $losAngelesCompany->id;
        });
    }

    public function test_only_approved_leave_and_absence_records_are_counted_once(): void
    {
        $company = $this->createCompany(['working_days_per_week' => 7]);
        $employee = $this->createEmployee($company, 'Employé Présence');

        Holiday::create([
            'company_id' => $company->id,
            'name'       => 'Jour férié',
            'date'       => '2026-01-01',
            'is_active'  => true,
        ]);

        foreach ([
            ['date' => '2026-01-02', 'status' => 'approved'],
            ['date' => '2026-01-05', 'status' => 'approved'],
            ['date' => '2026-01-06', 'status' => 'rejected'],
        ] as $absence) {
            TimeSheet::create([
                'company_id'    => $company->id,
                'employee_id'   => $employee->id,
                'date'          => $absence['date'],
                'arrival_date'  => $absence['date'],
                'monthpaie'     => '2026-01',
                'motif_justify' => 'Oui',
                'statut'        => $absence['status'],
            ]);
        }

        foreach ([
            ['date' => '2026-01-02', 'status' => 'Approuvé'],
            ['date' => '2026-01-03', 'status' => 'Pending'],
            ['date' => '2026-01-04', 'status' => 'Rejeté'],
        ] as $leave) {
            Leave::create([
                'company_id'      => $company->id,
                'employee_id'     => $employee->id,
                'applied_on'      => '2025-12-01',
                'leave_back'      => $leave['date'],
                'start_date'      => $leave['date'],
                'end_date'        => $leave['date'],
                'total_leave_days'=> '1',
                'status'          => $leave['status'],
            ]);
        }

        $data = app(MonthlyAttendanceReportService::class)->buildReportData(
            $company,
            Carbon::parse('2026-01-01', 'Africa/Abidjan'),
            true
        );

        $this->assertSame(1, $data['indicators']['total_leave_days']);
        $this->assertSame(1, $data['indicators']['total_justified_absences']);
        $this->assertSame(28, $data['indicators']['total_unjustified_absences']);
    }

    public function test_rest_day_pointage_does_not_inflate_scheduled_presence_rate(): void
    {
        $company = $this->createCompany();
        $employee = $this->createEmployee($company, 'Employé Week-end');
        Pointeuse::create([
            'company_id'     => $company->id,
            'employee_id'    => $employee->id,
            'auth_date'      => '2026-01-03',
            'auth_time'      => '09:00:00',
            'auth_date_time' => '2026-01-03 09:00:00',
            'type'           => 'entree',
        ]);

        $data = app(MonthlyAttendanceReportService::class)->buildReportData(
            $company,
            Carbon::parse('2026-01-01', 'Africa/Abidjan'),
            true
        );

        $this->assertSame(1, $data['indicators']['total_presences']);
        $this->assertSame(22, $data['indicators']['total_expected_workdays']);
        $this->assertSame(0.0, $data['indicators']['presence_rate']);
        $this->assertSame(22, $data['indicators']['total_unjustified_absences']);
    }

    public function test_automatic_report_generates_both_formats_and_prevents_duplicate_send(): void
    {
        Mail::fake();
        Storage::fake('local');
        $company = $this->createCompany();
        $company->update(['email' => 'rh@example.com']);
        MonthlyReportConfig::create([
            'company_id'        => $company->id,
            'is_enabled'        => true,
            'report_format'     => 'both',
            'recipients'        => ['rh@example.com'],
            'auto_send_enabled' => true,
        ]);

        $service = app(MonthlyAttendanceReportService::class);
        $firstResult = $service->sendMonthlyReportForCompany(
            $company,
            Carbon::parse('2026-10-01', 'Africa/Abidjan'),
            false,
            null,
            false,
            true
        );
        $secondResult = $service->sendMonthlyReportForCompany(
            $company,
            Carbon::parse('2026-10-01', 'Africa/Abidjan'),
            false,
            null,
            false,
            true
        );

        $this->assertTrue($firstResult['success']);
        $this->assertFalse($secondResult['success']);
        $this->assertSame(1, MonthlyReportHistory::where('company_id', $company->id)->count());
        $this->assertSame('success', MonthlyReportHistory::first()->generation_status);
        $this->assertSame('sent', MonthlyReportHistory::first()->send_status);
        $this->assertNotNull(MonthlyReportHistory::first()->file_path_pdf);
        $this->assertNotNull(MonthlyReportHistory::first()->file_path_excel);
        Mail::assertSent(MonthlyAttendanceReportMail::class, 1);
    }

    public function test_company_cannot_download_another_company_report(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();
        $companyUser = User::create([
            'name'       => 'Company A User',
            'email'      => uniqid('company_user_') . '@example.com',
            'password'   => bcrypt('password'),
            'type'       => 'company',
            'company_id' => $companyA->id,
            'created_by' => $companyA->created_by,
        ]);
        $report = MonthlyReportHistory::create([
            'company_id'        => $companyB->id,
            'report_month'      => '2026-10',
            'period_start'      => '2026-10-01',
            'period_end'        => '2026-10-31',
            'format'            => 'pdf',
            'generation_status' => 'success',
            'send_status'       => 'skipped',
        ]);

        $this->actingAs($companyUser);

        try {
            app(MonthlyReportController::class)->downloadPdf($report);
            $this->fail('A company must not download another company report.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_job_throws_retryable_service_failures_for_queue_retries(): void
    {
        $company = $this->createCompany();
        $service = \Mockery::mock(MonthlyAttendanceReportService::class);
        $service->shouldReceive('sendMonthlyReportForCompany')
            ->once()
            ->andReturn([
                'success'    => false,
                'message'    => 'Erreur SMTP temporaire',
                'recipients' => ['rh@example.com'],
                'history_id' => 1,
                'retryable'  => true,
            ]);

        $this->expectException(\RuntimeException::class);
        (new GenerateMonthlyAttendanceReportJob($company))->handle($service);
    }

    public function test_anomalies_are_detected_and_included_in_report_data(): void
    {
        $company = $this->createCompany(['working_days_per_week' => 5]);
        $employee = $this->createEmployee($company, 'Employé Anomalie');

        // 1. Pointage incomplet (entrée sans sortie)
        Pointeuse::create([
            'company_id'     => $company->id,
            'employee_id'    => $employee->id,
            'auth_date'      => '2026-10-05',
            'auth_time'      => '08:30:00',
            'auth_date_time' => '2026-10-05 08:30:00',
            'type'           => 'entree',
            'scan_status'    => 'a_l_heure',
            'duree_retard'   => 0,
        ]);

        // 2. Retard important (45 min)
        Pointeuse::create([
            'company_id'     => $company->id,
            'employee_id'    => $employee->id,
            'auth_date'      => '2026-10-06',
            'auth_time'      => '08:45:00',
            'auth_date_time' => '2026-10-06 08:45:00',
            'type'           => 'entree',
            'scan_status'    => 'retard',
            'duree_retard'   => 45,
        ]);
        Pointeuse::create([
            'company_id'     => $company->id,
            'employee_id'    => $employee->id,
            'auth_date'      => '2026-10-06',
            'auth_time'      => '17:00:00',
            'auth_date_time' => '2026-10-06 17:00:00',
            'type'           => 'sortie',
            'scan_status'    => 'a_l_heure',
            'duree_retard'   => 0,
        ]);

        // 3. Déclaration GPS hors zone sans ordre de mission
        \App\Models\GpsDeclaration::create([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'company_id'        => $company->id,
            'employee_id'       => $employee->id,
            'date'              => '2026-10-07',
            'time'              => '08:00:00',
            'is_offsite'        => true,
            'mission_reference' => null,
            'status'            => 'En attente',
        ]);

        $service = app(MonthlyAttendanceReportService::class);
        $data = $service->buildReportData($company, Carbon::parse('2026-10-01', 'Africa/Abidjan'), true);

        $this->assertArrayHasKey('anomalies', $data);
        $this->assertCount(3, $data['anomalies']);

        $anomalyTypes = array_column($data['anomalies'], 'type');
        $this->assertContains('Pointage incomplet', $anomalyTypes);
        $this->assertContains('Retard important', $anomalyTypes);
        $this->assertContains('Pointage GPS hors zone', $anomalyTypes);

        // Vérification des aliases pour templates PDF et Email
        $this->assertSame($data['indicators']['total_presences'], $data['indicators']['total_presence_days']);
        $this->assertSame($data['indicators']['total_absences'], $data['indicators']['total_absence_days']);
        $this->assertSame($data['indicators']['total_retards_count'], $data['indicators']['total_retards']);
        $this->assertSame($data['indicators']['total_incomplete_pointages'], $data['indicators']['total_incomplete']);
    }

    public function test_update_settings_validation_requires_recipient_when_auto_send_enabled(): void
    {
        $company = $this->createCompany();
        $user = User::create([
            'name'       => 'Company Admin',
            'email'      => uniqid('admin_') . '@example.com',
            'password'   => bcrypt('password'),
            'type'       => 'company',
            'company_id' => $company->id,
            'created_by' => $company->created_by,
        ]);
        $company->update(['user_id' => $user->id]);

        $this->actingAs($user);

        // Requête invalide : envoi automatique activé sans destinataires
        $response = $this->postJson(route('company.reports.monthly.settings.update'), [
            'is_enabled'        => true,
            'send_day'          => 5,
            'send_hour'         => 8,
            'send_minute'       => 0,
            'recipients'        => [],
            'report_format'     => 'both',
            'auto_send_enabled' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipients']);

        // Requête valide avec destinataires valides
        $validResponse = $this->postJson(route('company.reports.monthly.settings.update'), [
            'is_enabled'        => true,
            'send_day'          => 5,
            'send_hour'         => 8,
            'send_minute'       => 0,
            'recipients'        => ['valid-rh@example.com'],
            'report_format'     => 'both',
            'auto_send_enabled' => true,
        ]);

        $validResponse->assertStatus(200);
        $this->assertDatabaseHas('monthly_report_configs', [
            'company_id'        => $company->id,
            'send_day'          => 5,
            'send_hour'         => 8,
            'auto_send_enabled' => 1,
        ]);
    }
}
