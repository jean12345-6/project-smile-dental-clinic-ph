<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ChatLog;
use App\Models\Clinic;
use App\Models\DentalChart;
use App\Models\Dentist;
use App\Models\DentistSchedule;
use App\Models\FaqEntry;
use App\Models\Feedback;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Reminder;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Builds a working demo of SmileSync.
 *
 * Two clinics are seeded so that tenant separation can be tested: every patient,
 * dentist, branch and appointment belongs to exactly one clinic, and nothing
 * crosses over. Appointments are laid on each dentist's real working hours, so
 * no two appointments of the same dentist ever overlap.
 *
 * Every account uses the password: password
 */
class DatabaseSeeder extends Seeder
{
    /** Days of history and of future schedule to generate. */
    private const DAYS_BACK = 21;
    private const DAYS_AHEAD = 21;

    /** Share of free slots that get booked. */
    private const FILL_RATE = 30; // percent

    public function run(): void
    {
        User::create([
            'clinic_id' => null,
            'branch_id' => null,
            'name'      => 'System Administrator',
            'email'     => 'superadmin@smilesync.test',
            'password'  => 'password',
            'role'      => 'superadmin',
            'status'    => 'active',
        ]);

        $this->seedClinic([
            'name'     => 'Project Smile Dental Clinic PH',
            'slug'     => 'smile',
            'contact'  => '0917 555 0100',
            'email'    => 'hello@projectsmile.test',
            'branches' => [
                ['Bongabong Main', 'Labasan, Bongabong, Oriental Mindoro', '0917 555 0101'],
                ['Roxas Branch', 'Poblacion, Roxas, Oriental Mindoro', '0917 555 0102'],
            ],
            'dentists' => [
                ['Dr. Maria Elena Reyes', 'General Dentistry', 'PRC-0012345', [1, 2, 3, 4, 5]],
                ['Dr. Antonio Cruz Uy', 'Orthodontics', 'PRC-0023456', [1, 3, 5]],
                ['Dr. Katrina Bautista', 'Pediatric Dentistry', 'PRC-0034567', [2, 4, 6]],
            ],
            'patients' => 40,
        ]);

        $this->seedClinic([
            'name'     => 'BrightSmile Dental Center',
            'slug'     => 'bright',
            'contact'  => '0918 555 0200',
            'email'    => 'info@brightsmile.test',
            'branches' => [
                ['Calapan Clinic', 'Guinobatan, Calapan City, Oriental Mindoro', '0918 555 0201'],
            ],
            'dentists' => [
                ['Dr. Joseph Lim', 'General Dentistry', 'PRC-0045678', [1, 2, 3, 4, 5]],
                ['Dr. Grace Villanueva', 'Endodontics', 'PRC-0056789', [2, 4, 6]],
            ],
            'patients' => 20,
        ]);
    }

    // ------------------------------------------------------------------
    // One clinic, end to end
    // ------------------------------------------------------------------

    private function seedClinic(array $cfg): void
    {
        $clinic = Clinic::create([
            'clinic_name' => $cfg['name'],
            'logo_path'   => null,
            'contact_no'  => $cfg['contact'],
            'email'       => $cfg['email'],
            'status'      => 'active',
        ]);

        $branches = [];
        foreach ($cfg['branches'] as $b) {
            $branches[] = Branch::create([
                'clinic_id'   => $clinic->clinic_id,
                'branch_name' => $b[0],
                'address'     => $b[1],
                'contact_no'  => $b[2],
                'open_time'   => '08:00:00',
                'close_time'  => '17:00:00',
            ]);
        }

        $services = $this->seedServices($clinic);
        $this->seedFaq($clinic);

        User::create([
            'clinic_id' => $clinic->clinic_id,
            'branch_id' => $branches[0]->branch_id,
            'name'      => 'Clinic Administrator',
            'email'     => 'admin.' . $cfg['slug'] . '@smilesync.test',
            'password'  => 'password',
            'role'      => 'clinic_admin',
            'status'    => 'active',
        ]);

        $receptionists = [];
        foreach ($branches as $i => $branch) {
            $receptionists[] = User::create([
                'clinic_id' => $clinic->clinic_id,
                'branch_id' => $branch->branch_id,
                'name'      => 'Receptionist ' . ($i + 1),
                'email'     => 'reception' . ($i + 1) . '.' . $cfg['slug'] . '@smilesync.test',
                'password'  => 'password',
                'role'      => 'receptionist',
                'status'    => 'active',
            ]);
        }

        $dentists = [];
        foreach ($cfg['dentists'] as $i => $d) {
            $user = User::create([
                'clinic_id' => $clinic->clinic_id,
                'branch_id' => $branches[$i % count($branches)]->branch_id,
                'name'      => $d[0],
                'email'     => 'dentist' . ($i + 1) . '.' . $cfg['slug'] . '@smilesync.test',
                'password'  => 'password',
                'role'      => 'dentist',
                'status'    => 'active',
            ]);

            $dentist = Dentist::create([
                'user_id'        => $user->id,
                'full_name'      => $d[0],
                'specialization' => $d[1],
                'license_no'     => $d[2],
            ]);

            $branch = $branches[$i % count($branches)];

            foreach ($d[3] as $dow) {
                DentistSchedule::create([
                    'dentist_id'  => $dentist->dentist_id,
                    'branch_id'   => $branch->branch_id,
                    'day_of_week' => $dow,
                    'start_time'  => '09:00:00',
                    'end_time'    => '17:00:00',
                    'break_start' => '12:00:00',
                    'break_end'   => '13:00:00',
                    'leave_date'  => null,
                ]);
            }

            // one leave day two weeks out, so the slot engine has a gap to respect
            DentistSchedule::create([
                'dentist_id'  => $dentist->dentist_id,
                'branch_id'   => $branch->branch_id,
                'day_of_week' => null,
                'start_time'  => null,
                'end_time'    => null,
                'break_start' => null,
                'break_end'   => null,
                'leave_date'  => Carbon::today()->addDays(14 + $i)->toDateString(),
            ]);

            $dentists[] = ['model' => $dentist, 'branch' => $branch, 'days' => $d[3]];
        }

        $patients = $this->seedPatients($clinic, $cfg, (int) $cfg['patients']);

        $this->seedInventory($branches);
        $this->seedAppointments($dentists, $patients, $services, $receptionists[0]);
        $this->seedWaitlist($patients, $services, $branches, $dentists);
        $this->seedChatLogs($patients);
    }

    // ------------------------------------------------------------------
    // Reference data
    // ------------------------------------------------------------------

    private function seedServices(Clinic $clinic): array
    {
        $rows = [
            ['Consultation', 30, 500],
            ['Oral Prophylaxis', 45, 800],
            ['Tooth Extraction', 45, 1500],
            ['Composite Filling', 60, 1200],
            ['Root Canal Treatment', 90, 8000],
            ['Dental Crown', 90, 12000],
            ['Teeth Whitening', 60, 6000],
            ['Braces Adjustment', 30, 1500],
            ['Denture Fitting', 60, 15000],
            ['Dental X-ray', 30, 500],
        ];

        $services = [];
        foreach ($rows as $r) {
            $services[] = Service::create([
                'clinic_id'    => $clinic->clinic_id,
                'service_name' => $r[0],
                'duration_min' => $r[1],
                'price'        => $r[2],
                'is_active'    => true,
            ]);
        }

        return $services;
    }

    private function seedFaq(Clinic $clinic): void
    {
        $rows = [
            ['What are your clinic hours?', 'We are open Monday to Saturday, 8:00 AM to 5:00 PM. We are closed on Sundays and public holidays.', 'hours'],
            ['How much is a dental cleaning?', 'Oral prophylaxis costs PHP 800 and takes about 45 minutes.', 'pricing'],
            ['Do you accept walk-in patients?', 'Yes, but patients with appointments are seen first. Booking ahead is faster.', 'booking'],
            ['Do you accept HMO?', 'Not at the moment. We accept cash, card, GCash and bank transfer.', 'payment'],
            ['How do I cancel my appointment?', 'Tell the assistant you want to cancel, or open My Appointments and cancel there. Please do it at least one day before.', 'booking'],
            ['Is tooth extraction painful?', 'Local anaesthesia is given before the procedure. For questions about your own case, our staff will get back to you.', 'services'],
        ];

        foreach ($rows as $r) {
            FaqEntry::create([
                'clinic_id' => $clinic->clinic_id,
                'question'  => $r[0],
                'answer'    => $r[1],
                'category'  => $r[2],
            ]);
        }
    }

    private function seedInventory(array $branches): void
    {
        $items = [
            ['Disposable Gloves (box)', 'box', 40, 10],
            ['Face Mask (box)', 'box', 25, 10],
            ['Composite Resin Kit', 'kit', 6, 3],
            ['Local Anaesthetic', 'vial', 8, 10],   // already below reorder level
            ['Dental Floss', 'pack', 30, 8],
            ['X-ray Film', 'pack', 4, 5],           // already below reorder level
            ['Cotton Rolls', 'pack', 50, 15],
            ['Suction Tips', 'pack', 12, 6],
        ];

        foreach ($branches as $branch) {
            foreach ($items as $i) {
                Inventory::create([
                    'branch_id'     => $branch->branch_id,
                    'item_name'     => $i[0],
                    'unit'          => $i[1],
                    'quantity'      => $i[2],
                    'reorder_level' => $i[3],
                ]);
            }
        }
    }

    private function seedPatients(Clinic $clinic, array $cfg, int $count): array
    {
        $first = ['Maria', 'Jose', 'Ana', 'Paolo', 'Grace', 'Noel', 'Teresita', 'Mark',
                  'Jenny', 'Rolando', 'Liza', 'Dennis', 'Carmela', 'Allan', 'Rosario',
                  'Benjamin', 'Sheila', 'Arnel', 'Divina', 'Christian'];
        $last  = ['Dela Cruz', 'Santos', 'Reyes', 'Bautista', 'Garcia', 'Mendoza',
                  'Lim', 'Aquino', 'Castillo', 'Villanueva', 'Ramos', 'Tolentino',
                  'Fernandez', 'Navarro', 'Alvarez', 'Pascual'];
        $towns = ['Labasan', 'Poblacion', 'Sta. Cruz', 'Malitbog', 'Hagan', 'Anilao'];

        $patients = [];
        for ($i = 0; $i < $count; $i++) {
            $name = $first[$i % count($first)] . ' ' . $last[($i * 7) % count($last)];
            $slug = $cfg['slug'];

            $user = User::create([
                'clinic_id' => $clinic->clinic_id,
                'branch_id' => null,
                'name'      => $name,
                'email'     => 'patient' . ($i + 1) . '.' . $slug . '@smilesync.test',
                'password'  => 'password',
                'role'      => 'patient',
                'status'    => 'active',
            ]);

            $patient = Patient::create([
                'user_id'         => $user->id,
                'clinic_id'       => $clinic->clinic_id,
                'full_name'       => $name,
                'birthdate'       => Carbon::today()->subYears(18 + ($i * 3) % 50)->subDays($i * 11)->toDateString(),
                'address'         => $towns[$i % count($towns)] . ', Oriental Mindoro',
                'contact_no'      => '09' . str_pad((string) (170000000 + $i * 137), 9, '0', STR_PAD_LEFT),
                'email'           => 'patient' . ($i + 1) . '.' . $slug . '@smilesync.test',
                'medical_history' => $i % 5 === 0 ? 'Allergic to penicillin.' : null,
                'no_show_count'   => 0,
            ]);

            // a dental chart for roughly every third patient
            if ($i % 3 === 0) {
                foreach ([16, 26, 36, 46] as $k => $tooth) {
                    DentalChart::create([
                        'patient_id'   => $patient->patient_id,
                        'tooth_no'     => (string) $tooth,
                        'condition'    => ['Healthy', 'Caries', 'Filled', 'Missing'][($i + $k) % 4],
                        'date_updated' => Carbon::today()->subDays(30 + $i)->toDateString(),
                    ]);
                }
            }

            $patients[] = $patient;
        }

        return $patients;
    }

    // ------------------------------------------------------------------
    // Appointments laid on real working hours
    // ------------------------------------------------------------------

    private function seedAppointments(array $dentists, array $patients, array $services, User $receptionist): void
    {
        if (empty($patients)) {
            return;
        }

        $today = Carbon::today();
        $start = $today->copy()->subDays(self::DAYS_BACK);
        $end   = $today->copy()->addDays(self::DAYS_AHEAD);
        $seq   = 0;

        foreach ($dentists as $entry) {
            $dentist = $entry['model'];
            $branch  = $entry['branch'];
            $days    = $entry['days'];

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if (! in_array($day->dayOfWeek, $days, true)) {
                    continue;
                }

                $cursor    = $day->copy()->setTime(9, 0);
                $closeTime = $day->copy()->setTime(17, 0);
                $breakFrom = $day->copy()->setTime(12, 0);
                $breakTo   = $day->copy()->setTime(13, 0);

                while ($cursor->lt($closeTime)) {
                    $service  = $services[$seq % count($services)];
                    $duration = (int) $service->duration_min;
                    $finish   = $cursor->copy()->addMinutes($duration);

                    // never run past closing or into the lunch break
                    if ($finish->gt($closeTime)) {
                        break;
                    }
                    if ($cursor->lt($breakTo) && $finish->gt($breakFrom)) {
                        $cursor = $breakTo->copy();
                        continue;
                    }

                    $seq++;

                    if ($seq % 100 >= self::FILL_RATE) {
                        $cursor->addMinutes(30);
                        continue;
                    }

                    $patient = $patients[($seq * 13) % count($patients)];
                    $this->makeAppointment(
                        $patient, $dentist, $branch, $service,
                        $cursor->copy(), $finish->copy(), $today, $seq, $receptionist
                    );

                    $cursor = $finish->copy();
                }
            }
        }
    }

    private function makeAppointment(
        Patient $patient, Dentist $dentist, Branch $branch, Service $service,
        Carbon $from, Carbon $to, Carbon $today, int $seq, User $receptionist
    ): void {
        $isPast  = $from->lt($today);
        $isToday = $from->isSameDay($today);

        if ($isPast) {
            $roll   = $seq % 10;
            $status = $roll < 8 ? 'completed' : ($roll === 8 ? 'no_show' : 'cancelled');
        } elseif ($isToday) {
            $status = $seq % 2 === 0 ? 'checked_in' : 'confirmed';
        } else {
            $status = $seq % 5 < 3 ? 'confirmed' : 'pending';
        }

        $appointment = Appointment::create([
            'patient_id'    => $patient->patient_id,
            'dentist_id'    => $dentist->dentist_id,
            'branch_id'     => $branch->branch_id,
            'service_id'    => $service->service_id,
            'start_time'    => $from,
            'end_time'      => $to,
            'status'        => $status,
            'risk_flag'     => ! $isPast && $patient->no_show_count > 0,
            'booked_via'    => ['chatbot', 'portal', 'walk_in'][$seq % 3],
            'check_in_time' => $status === 'checked_in' ? $from->copy()->subMinutes(10) : null,
            'follow_up_of'  => null,
        ]);

        if ($status === 'no_show') {
            $patient->increment('no_show_count');
            return;
        }

        if ($status === 'completed') {
            $this->closeVisit($appointment, $patient, $dentist, $service, $from, $seq, $receptionist);
            return;
        }

        if (in_array($status, ['confirmed', 'pending'], true) && $from->gt($today)) {
            Reminder::create([
                'appointment_id' => $appointment->appointment_id,
                'type'           => 'reminder',
                'channel'        => $seq % 3 === 0 ? 'email' : 'sms',
                'send_time'      => $from->copy()->subDay()->setTime(9, 0),
                'send_status'    => 'pending',
            ]);
        }
    }

    /** Treatment, prescription, invoice, payment and sometimes feedback. */
    private function closeVisit(
        Appointment $appointment, Patient $patient, Dentist $dentist,
        Service $service, Carbon $from, int $seq, User $receptionist
    ): void {
        $teeth = ['11', '16', '21', '26', '36', '46'];

        $treatment = Treatment::create([
            'appointment_id' => $appointment->appointment_id,
            'patient_id'     => $patient->patient_id,
            'dentist_id'     => $dentist->dentist_id,
            'tooth_no'       => $teeth[$seq % count($teeth)],
            'procedure_name' => $service->service_name,
            'notes'          => 'Completed without complications.',
            'treatment_date' => $from->toDateString(),
            'cost'           => $service->price,
        ]);

        if ($seq % 3 === 0) {
            Prescription::create([
                'treatment_id' => $treatment->treatment_id,
                'patient_id'   => $patient->patient_id,
                'dentist_id'   => $dentist->dentist_id,
                'medicine'     => ['Mefenamic acid 500mg', 'Amoxicillin 500mg', 'Paracetamol 500mg'][$seq % 3],
                'dosage'       => '1 tablet',
                'frequency'    => '3 times a day',
                'duration'     => '5 days',
            ]);
        }

        $total = (float) $service->price;
        $paid  = $seq % 4 === 0 ? $total / 2 : $total;   // every fourth visit leaves a balance

        $invoice = Invoice::create([
            'appointment_id' => $appointment->appointment_id,
            'patient_id'     => $patient->patient_id,
            'total_amount'   => $total,
            'balance'        => $total - $paid,
            'date_issued'    => $from->toDateString(),
        ]);

        Payment::create([
            'invoice_id'  => $invoice->invoice_id,
            'amount'      => $paid,
            'method'      => ['cash', 'gcash', 'card', 'bank_transfer'][$seq % 4],
            'paid_at'     => $from->copy()->addMinutes(60),
            'received_by' => $receptionist->id,
        ]);

        if ($seq % 3 === 1) {
            Feedback::create([
                'appointment_id' => $appointment->appointment_id,
                'patient_id'     => $patient->patient_id,
                'rating'         => 4 + ($seq % 2),
                'comment'        => ['Mabilis ang serbisyo.', 'Very accommodating staff.', 'Malinis ang clinic.'][$seq % 3],
                'date_submitted' => $from->copy()->addDay()->toDateString(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Waiting list and chat history
    // ------------------------------------------------------------------

    private function seedWaitlist(array $patients, array $services, array $branches, array $dentists): void
    {
        $count = min(6, count($patients));

        for ($i = 0; $i < $count; $i++) {
            Waitlist::create([
                'patient_id'     => $patients[$i * 3 % count($patients)]->patient_id,
                'service_id'     => $services[$i % count($services)]->service_id,
                'branch_id'      => $branches[$i % count($branches)]->branch_id,
                'dentist_id'     => $i % 2 === 0 ? $dentists[$i % count($dentists)]['model']->dentist_id : null,
                'preferred_date' => Carbon::today()->addDays(3 + $i)->toDateString(),
                'status'         => $i === 0 ? 'offered' : 'waiting',
                'offered_at'     => $i === 0 ? Carbon::now()->subHours(2) : null,
            ]);
        }
    }

    private function seedChatLogs(array $patients): void
    {
        $turns = [
            ['Magkano po ang cleaning?', 'Oral prophylaxis is PHP 800 and takes about 45 minutes. Would you like to book?', 'faq', false],
            ['Opo, this Saturday morning po.', 'Saturday has open slots at 9:00, 10:30 and 11:15. Which one would you like?', 'book', false],
            ['When is my next appointment?', 'Your next appointment is on Tuesday at 10:00 AM with Dr. Reyes for a composite filling.', 'personal', false],
            ['Pwede po bang ipalipat sa Friday?', 'Friday has open slots at 9:00 and 2:00 PM. Shall I move your appointment?', 'reschedule', false],
            ['Masakit po ang ngipin ko, ano po gamot?', 'I cannot give medical advice. I have passed your message to the clinic staff and they will contact you.', 'escalate', true],
            ['Anong oras po kayo bukas?', 'We are open Monday to Saturday, 8:00 AM to 5:00 PM.', 'faq', false],
        ];

        $count = min(count($turns), count($patients));

        for ($i = 0; $i < $count; $i++) {
            ChatLog::create([
                'patient_id'  => $patients[$i * 5 % count($patients)]->patient_id,
                'message_in'  => $turns[$i][0],
                'message_out' => $turns[$i][1],
                'intent'      => $turns[$i][2],
                'escalated'   => $turns[$i][3],
                'timestamp'   => Carbon::now()->subHours($i * 7 + 1),
            ]);
        }
    }
}
