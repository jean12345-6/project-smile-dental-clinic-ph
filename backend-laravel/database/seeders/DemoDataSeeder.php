<?php

namespace Database\Seeders;

use App\Support\Dbx;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills the empty clinic screens with believable demo data. Safe to run again: each part runs only when its table is (nearly) empty.
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        $clinic = DB::table('clinics')->orderBy('clinic_id')->value('clinic_id');
        $branch = DB::table('branches')->orderBy('branch_id')->value('branch_id');

        // ---- questions the assistant can answer (FR-09, FR-10)
        if (DB::table('faq_entries')->count() < 5) {
            $faq = [
                ['How do I book an appointment? Paano magpa-appointment?', 'Just tap "Book an appointment" in the assistant, choose a service, a dentist, a date and a time. Pindutin lang ang "Book an appointment" at sundin ang mga hakbang.', 'booking'],
                ['Do I need to pay a deposit?', 'No deposit is needed. You pay at the clinic counter after your visit by cash, GCash or card.', 'payment'],
                ['What payment methods do you accept? Paano magbayad?', 'We accept cash, GCash, bank transfer and credit or debit card at the counter.', 'payment'],
                ['What should I bring on my first visit?', 'Please bring a valid ID and any old dental records, X-rays or medicine list you have.', 'visit'],
                ['Is tooth extraction painful? Masakit ba ang bunot?', 'We use local anesthesia, so you should feel pressure but not pain. Your dentist will explain everything first.', 'treatment'],
                ['How often should I have a cleaning? Gaano kadalas magpalinis?', 'Dentists recommend a cleaning every six months to keep teeth and gums healthy.', 'treatment'],
                ['Can I bring my child? Pwede ba ang bata?', 'Yes, we welcome children. Please book a Consultation first so the dentist can plan the visit.', 'visit'],
                ['What if I am late for my appointment?', 'Please message us as soon as you can. If you are more than 15 minutes late we may need to move you to the next open slot.', 'booking'],
                ['Do you accept HMO or insurance?', 'Please ask our staff at the counter. They will check if your HMO card is accepted.', 'payment'],
                ['How do I cancel or move my appointment? Paano mag-cancel?', 'Open the assistant and tap "My appointments", then choose Cancel or Reschedule. The slot will open for other patients.', 'booking'],
                ['Is it safe to get X-rays? Safe ba ang X-ray?', 'Yes. Dental X-rays use a very small dose and we use protective gear. Tell us if you are pregnant.', 'treatment'],
                ['What do I do after a tooth extraction?', 'Bite on the gauze for 30 minutes, avoid hot food and straws for a day, and rest. Call the clinic if bleeding does not stop.', 'treatment'],
            ];
            foreach ($faq as [$q, $a, $c]) {
                Dbx::insert('faq_entries', ['clinic_id' => $clinic, 'question' => $q, 'answer' => $a, 'category' => $c]);
            }
        }

        // ---- inventory (FR-34, FR-35): some items are low on purpose
        if (DB::table('inventory')->count() < 5) {
            $items = [['Disposable gloves', 'box', 3, 5], ['Face masks', 'box', 12, 5], ['Anesthetic cartridges', 'box', 2, 4], ['Composite resin', 'syringe', 9, 3], ['Dental floss', 'pack', 25, 10],
                ['Cotton rolls', 'pack', 14, 6], ['Prophylaxis paste', 'jar', 4, 4], ['Impression material', 'kit', 7, 2], ['Bonding agent', 'bottle', 6, 2], ['Suction tips', 'pack', 18, 8], ['Gauze pads', 'pack', 1, 5], ['Sterilization pouches', 'box', 10, 4]];
            foreach ($items as [$n, $u, $q, $r]) {
                Dbx::insert('inventory', ['branch_id' => $branch, 'item_name' => $n, 'unit' => $u, 'quantity' => $q, 'reorder_level' => $r]);
            }
        }

        // ---- finished visits with treatments, prescriptions, chart, invoices, payments, feedback
        if (DB::table('treatments')->count() < 5) {
            // make sure there are completed visits: past bookings count as completed
            $past = DB::table('appointments')->whereIn('status', ['pending', 'confirmed'])->where('start_time', '<', now()->subDay())->orderBy('start_time', 'desc')->limit(45)->pluck('appointment_id');
            DB::table('appointments')->whereIn('appointment_id', $past)->update(['status' => 'completed']);

            $conds = ['Cavity', 'Filled', 'Crown', 'Extracted', 'Healthy', 'Root canal done', 'Needs cleaning'];
            $meds = [['Amoxicillin 500mg', '1 capsule', '3 times a day', '5 days'], ['Mefenamic acid 500mg', '1 tablet', 'every 6 hours if in pain', '3 days'], ['Ibuprofen 400mg', '1 tablet', 'every 8 hours', '3 days'], ['Chlorhexidine mouthwash', '15 ml', '2 times a day', '7 days']];
            $visits = DB::table('appointments')->join('services', 'services.service_id', '=', 'appointments.service_id')->where('appointments.status', 'completed')
                ->orderByDesc('appointments.start_time')->limit(60)->get(['appointments.*', 'services.service_name', 'services.price']);
            $dentistUsers = [];
            foreach ($visits as $i => $v) {
                $tooth = (string) mt_rand(11, 48);
                $tid = Dbx::insert('treatments', ['appointment_id' => $v->appointment_id, 'patient_id' => $v->patient_id, 'dentist_id' => $v->dentist_id, 'tooth_no' => $tooth, 'procedure_name' => $v->service_name,
                    'notes' => 'Routine ' . strtolower($v->service_name) . '. Patient tolerated well.', 'treatment_date' => $v->start_time, 'cost' => $v->price]);
                if ($i % 2 === 0) {
                    $m = $meds[$i % count($meds)];
                    Dbx::insert('prescriptions', ['treatment_id' => $tid, 'patient_id' => $v->patient_id, 'dentist_id' => $v->dentist_id, 'medicine' => $m[0], 'dosage' => $m[1], 'frequency' => $m[2], 'duration' => $m[3]]);
                }
                Dbx::insert('dental_chart', ['patient_id' => $v->patient_id, 'tooth_no' => $tooth, 'condition' => $conds[$i % count($conds)], 'date_updated' => $v->start_time]);

                $inv = Dbx::insert('invoices', ['appointment_id' => $v->appointment_id, 'patient_id' => $v->patient_id, 'total_amount' => $v->price, 'balance' => $v->price, 'date_issued' => $v->start_time]);
                $roll = $i % 10;
                $pay = $roll < 6 ? $v->price : ($roll < 8 ? round($v->price / 2, 2) : 0);
                if ($pay > 0) {
                    Dbx::insert('payments', ['invoice_id' => $inv, 'amount' => $pay, 'method' => ['cash', 'gcash', 'card'][$i % 3], 'paid_at' => $v->start_time]);
                    DB::table('invoices')->where('invoice_id', $inv)->update(['balance' => round($v->price - $pay, 2)]);
                }
                if ($i % 5 < 2) {
                    Dbx::insert('feedback', ['appointment_id' => $v->appointment_id, 'patient_id' => $v->patient_id, 'rating' => mt_rand(3, 5), 'comment' => ['Very gentle dentist!', 'Clean clinic and friendly staff.', 'Medyo matagal maghintay pero maayos.', 'Will come back.'][$i % 4], 'date_submitted' => $v->start_time]);
                }
            }
        }

        // ---- a few missed appointments so the risk flag has something to show
        if (DB::table('appointments')->where('status', 'no_show')->count() < 3) {
            $ids = DB::table('appointments')->where('status', 'completed')->orderBy('start_time')->limit(8)->pluck('appointment_id');
            DB::table('appointments')->whereIn('appointment_id', $ids)->update(['status' => 'no_show']);
            DB::table('invoices')->whereIn('appointment_id', $ids)->delete();
        }
        DB::statement("UPDATE patients p SET no_show_count = (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id AND a.status = 'no_show')");

        // ---- sample chats sent to staff
        if (DB::table('chat_logs')->where('escalated', 1)->count() < 2) {
            $pid = DB::table('patients')->value('patient_id');
            foreach (['masakit ang ngipin ko', 'Is it okay to take antibiotics before cleaning?'] as $q) {
                Dbx::insert('chat_logs', ['patient_id' => $pid, 'message_in' => $q, 'message_out' => 'Questions about symptoms or treatment need a dentist. I have passed your message to our clinic staff.', 'intent' => 'escalated', 'escalated' => 1, 'timestamp' => now()->subHours(3)]);
            }
        }
    }
}
