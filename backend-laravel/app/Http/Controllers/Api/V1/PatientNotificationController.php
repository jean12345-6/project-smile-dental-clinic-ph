<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dbx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @group Patient portal
 */
class PatientNotificationController extends APIController
{
    private function patientId(Request $r)
    {
        return $r->user()->role === 'patient' ? $r->user()->patient?->patient_id : null;
    }

    /** GET /api/v1/my/notifications — built from reminders, staff replies, invoices, payments and appointment updates. */
    public function index(Request $r)
    {
        $pid = $this->patientId($r);
        if (! $pid) {
            return Dbx::err('Only patients have notifications.', 403);
        }
        $items = [];

        $appts = DB::table('appointments')->join('services', 'services.service_id', '=', 'appointments.service_id')
            ->where('appointments.patient_id', $pid)->orderByDesc('appointments.start_time')->limit(25)
            ->get(['appointments.appointment_id', 'appointments.start_time', 'appointments.status', 'appointments.updated_at', 'appointments.created_at', 'services.service_name']);
        $ids = $appts->pluck('appointment_id')->all();
        foreach ($appts as $a) {
            $when = date('M j, Y g:i A', strtotime($a->start_time));
            if (in_array($a->status, ['confirmed', 'cancelled', 'completed', 'no_show'], true)) {
                // "at" is when the patient was told, not when the visit is. Using start_time
                // here dated a notice sent today as Nov 24, so it read like a notification
                // from the future and sorted above everything that really was newer.
                $items[] = ['key' => "appt-{$a->appointment_id}-{$a->status}", 'type' => 'appointment', 'title' => 'Appointment ' . str_replace('_', ' ', $a->status), 'body' => "{$a->service_name} on {$when}", 'at' => $a->updated_at ?: ($a->created_at ?: $a->start_time)];
            }
        }
        if ($ids) {
            foreach (DB::table('reminders')->whereIn('appointment_id', $ids)->where('send_status', 'sent')->orderByDesc('send_time')->limit(20)->get() as $m) {
                $a = $appts->firstWhere('appointment_id', $m->appointment_id);
                $items[] = ['key' => "rem-{$m->reminder_id}", 'type' => 'reminder', 'title' => 'Appointment reminder', 'body' => $a ? "{$a->service_name} on " . date('M j, Y g:i A', strtotime($a->start_time)) : 'You have an upcoming appointment.', 'at' => $m->send_time];
            }
        }
        foreach (DB::table('chat_logs')->where('patient_id', $pid)->where('intent', 'staff_reply')->orderByDesc('chat_id')->limit(10)->get() as $c) {
            $items[] = ['key' => "staff-{$c->chat_id}", 'type' => 'message', 'title' => 'Reply from clinic staff', 'body' => (string) $c->message_out, 'at' => $c->timestamp];
        }
        foreach (DB::table('invoices')->where('patient_id', $pid)->orderByDesc('invoice_id')->limit(10)->get() as $i) {
            $items[] = ['key' => "inv-{$i->invoice_id}", 'type' => 'invoice', 'title' => "Invoice #{$i->invoice_id} issued", 'body' => 'Total PHP ' . number_format($i->total_amount, 2) . ', balance PHP ' . number_format($i->balance, 2), 'at' => $i->date_issued];
        }
        $pays = DB::table('payments')->join('invoices', 'invoices.invoice_id', '=', 'payments.invoice_id')->where('invoices.patient_id', $pid)
            ->orderByDesc('payments.payment_id')->limit(10)->get(['payments.payment_id', 'payments.invoice_id', 'payments.amount', 'payments.method', 'payments.paid_at']);
        foreach ($pays as $p) {
            $items[] = ['key' => "pay-{$p->payment_id}", 'type' => 'payment', 'title' => 'Payment received', 'body' => 'PHP ' . number_format($p->amount, 2) . " by {$p->method} for invoice #{$p->invoice_id}", 'at' => $p->paid_at];
        }

        $read = Cache::get('notif-read-' . $r->user()->id, []);
        usort($items, fn ($x, $y) => strcmp((string) $y['at'], (string) $x['at']));
        $items = array_slice($items, 0, 40);
        $items = array_map(fn ($i) => $i + ['read' => in_array($i['key'], $read, true)], $items);

        return response()->json(['data' => $items, 'unread' => count(array_filter($items, fn ($i) => ! $i['read']))]);
    }

    /** POST /api/v1/my/notifications/read  {keys:[...]} or {all:true} */
    public function read(Request $r)
    {
        if (! $this->patientId($r)) {
            return Dbx::err('Only patients have notifications.', 403);
        }
        $k = 'notif-read-' . $r->user()->id;
        $read = Cache::get($k, []);
        if ($r->boolean('all')) {
            $read = array_values(array_unique(array_merge($read, array_column($this->index($r)->getData(true)['data'], 'key'))));
        } else {
            $read = array_values(array_unique(array_merge($read, array_map('strval', (array) $r->input('keys', [])))));
        }
        Cache::forever($k, array_slice($read, -500));

        return response()->json(['ok' => true]);
    }
}
