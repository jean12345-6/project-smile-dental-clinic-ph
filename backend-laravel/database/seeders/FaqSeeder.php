<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            // ---------- hours / location ----------
            ['hours', 'Are you open on weekends?', 'Please call us at {PHONE} to confirm our weekend schedule, or check the clinic hours in the Assistant.'],
            ['hours', 'Bukas ba kayo kapag holiday?', 'Maaaring iba ang oras namin tuwing holiday. Tumawag po sa {PHONE} para makumpirma bago pumunta.'],
            ['hours', 'What time is the last appointment of the day?', 'The last slot depends on the dentist schedule. You can see the available times when you book an appointment.'],
            ['location', 'Where is the clinic located?', 'You can find our address on the landing page and in the Clinic settings. You may also call {PHONE} for directions.'],
            ['location', 'Saan po ang clinic ninyo?', 'Makikita po ang address namin sa landing page. Pwede rin pong tumawag sa {PHONE} para sa direksyon.'],

            // ---------- booking ----------
            ['booking', 'How do I book an appointment?', 'Log in, open the Assistant or the booking page, choose a service, dentist, date, and time, then confirm.'],
            ['booking', 'Paano po mag-book ng appointment?', 'Mag-login po, buksan ang Assistant o ang booking page, piliin ang serbisyo, dentista, petsa, at oras, tapos i-confirm.'],
            ['booking', 'Can I reschedule my appointment?', 'Yes. Open My appointments, choose the booking, and pick a new date and time. Please do this ahead of your schedule.'],
            ['booking', 'Pwede ko bang ilipat ang appointment ko?', 'Opo. Pumunta sa My appointments, piliin ang booking, at pumili ng bagong petsa at oras. Mas mabuti kung maaga ninyo itong gawin.'],
            ['booking', 'Paano mag-cancel ng appointment?', 'Pumunta po sa My appointments at i-click ang Cancel sa booking na gusto ninyong kanselahin.'],
            ['booking', 'What if I am late for my appointment?', 'Please message or call us at {PHONE} if you will be late. We will do our best to still accommodate you.'],
            ['booking', 'Paano kung hindi ako makarating sa appointment?', 'Pakisabi po agad sa amin sa {PHONE} o i-cancel sa app para mabigyan ng slot ang ibang pasyente.'],
            ['booking', 'Can I choose my dentist?', 'Yes. When booking, you can pick the dentist who is available on your preferred date.'],
            ['booking', 'Pwede ba akong pumili ng dentista?', 'Opo. Sa pag-book, makikita ninyo ang mga dentistang available sa petsang gusto ninyo.'],
            ['booking', 'What is the waitlist?', 'If your preferred time is full, join the waitlist. We will notify you if a slot opens.'],
            ['booking', 'Ano ang waitlist?', 'Kapag puno ang oras na gusto ninyo, pwede kayong sumali sa waitlist. Aabisuhan namin kayo kapag may bakanteng slot.'],
            ['booking', 'Will I get a reminder before my appointment?', 'Yes. We send reminders before your appointment. Please keep your contact details updated.'],
            ['booking', 'Makakatanggap ba ako ng paalala bago ang appointment?', 'Opo, magpapadala kami ng paalala bago ang appointment ninyo. Siguraduhin lang pong tama ang contact details ninyo.'],

            // ---------- payment ----------
            ['payment', 'What payment methods do you accept?', 'Please call {PHONE} to ask about our accepted payment methods.'],
            ['payment', 'Anong paraan ng bayad ang tinatanggap ninyo?', 'Tumawag po sa {PHONE} para malaman ang mga tinatanggap naming paraan ng bayad.'],
            ['payment', 'Can I pay in installments?', 'Payment terms depend on the treatment. Please ask the clinic staff at {PHONE} before your procedure.'],
            ['payment', 'Pwede bang hulugan ang bayad?', 'Depende po sa gagawing treatment. Magtanong sa staff sa {PHONE} bago ang procedure.'],
            ['payment', 'Where can I see my invoices?', 'Log in and open My invoices to see your bills and payment history.'],

            // ---------- pricing ----------
            ['pricing', 'How much is a tooth extraction?', 'The price depends on the tooth and the case. Please book a consultation or call {PHONE} for an estimate.'],
            ['pricing', 'Magkano ang pasta?', 'Depende po sa laki at uri ng pasta. Magpa-consult po o tumawag sa {PHONE} para sa presyo.'],
            ['pricing', 'Magkano ang braces?', 'Iba-iba po ang presyo depende sa kaso. Mag-book po ng consultation para makuha ang tamang estimate.'],

            // ---------- services ----------
            ['services', 'What services do you offer?', 'We offer cleaning, fillings, extractions, braces, and other dental care. See the full list in the booking page.'],
            ['services', 'Anong mga serbisyo ang meron kayo?', 'Meron po kaming paglilinis, pasta, bunot, braces, at iba pang dental care. Makikita ang buong listahan sa booking page.'],
            ['services', 'Do you do teeth whitening?', 'Please check the service list when booking, or call {PHONE} to ask if whitening is available.'],
            ['services', 'Do you treat children?', 'Please call {PHONE} to ask about our services for children and the best time to bring them.'],
            ['services', 'May dentista ba kayo para sa bata?', 'Tumawag po sa {PHONE} para malaman kung anong serbisyo ang available para sa mga bata.'],

            // ---------- aftercare / emergency ----------
            ['aftercare', 'What should I do after a tooth extraction?', 'Follow your dentist instructions. In general, rest, avoid very hot food, and do not rinse hard on the first day. Call us if bleeding does not stop.'],
            ['aftercare', 'Ano ang dapat gawin pagkatapos bunutan?', 'Sundin po ang bilin ng dentista. Magpahinga, iwasan ang mainit na pagkain, at huwag magmumog nang malakas sa unang araw. Tumawag po kung hindi tumitigil ang dugo.'],
            ['emergency', 'I have a severe toothache. What should I do?', 'Please call us at {PHONE} right away so we can fit you in. If you have swelling or trouble breathing, go to the nearest emergency room.'],
            ['emergency', 'Masakit na masakit ang ngipin ko, ano ang gagawin ko?', 'Tumawag po agad sa {PHONE} para mabigyan kayo ng slot. Kung namamaga ang mukha o nahihirapang huminga, pumunta po sa pinakamalapit na emergency room.'],
            ['records', 'Where can I see my past treatments?', 'Log in and open My records to see your past treatments and prescriptions.'],
        ];

        $clinics = DB::table('clinics')->get(['clinic_id', 'contact_no']);

        foreach ($clinics as $clinic) {
            $phone = $clinic->contact_no ?: 'the clinic';

            foreach ($faqs as [$category, $question, $answer]) {
                $exists = DB::table('faq_entries')
                    ->where('clinic_id', $clinic->clinic_id)
                    ->where('question', $question)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('faq_entries')->insert([
                    'clinic_id' => $clinic->clinic_id,
                    'question' => $question,
                    'answer' => str_replace('{PHONE}', $phone, $answer),
                    'category' => $category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}