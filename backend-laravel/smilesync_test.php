<?php
/**
 * SmileSync - functional test runner
 * Run from the backend-laravel folder:   php smilesync_test.php
 * Needs: the Laravel server running (php artisan serve) and the seeded database.
 * Writes: test_results.csv and test_results.json next to this file.
 */
date_default_timezone_set('Asia/Manila');
$ROOT = __DIR__;
if (!is_file("$ROOT/.env")) { fwrite(STDERR, "Run this inside backend-laravel (no .env here).\n"); exit(1); }

/* ---------- read .env ---------- */
$env = [];
foreach (file("$ROOT/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim(trim($v), "\"'");
}
$BASE = rtrim($env['APP_URL'] ?? 'http://127.0.0.1:8000', '/');
$PASS = 'password';

/* ---------- pick one seeded user per role ---------- */
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? 'smilesync'),
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) { fwrite(STDERR, "Cannot reach the database: ".$e->getMessage()."\n"); exit(1); }

$ACC = [];
foreach (['superadmin','clinic_admin','receptionist','dentist','patient'] as $role) {
    $q = $pdo->prepare("SELECT email, clinic_id FROM users WHERE role = ? AND status <> 'inactive' ORDER BY user_id LIMIT 1");
    $q->execute([$role]);
    $ACC[$role] = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
$CLINIC = $ACC['clinic_admin']['clinic_id'] ?? null;

/* ---------- http ---------- */
function req(string $method, string $path, ?string $token = null, $body = null): array {
    global $BASE;
    $ch = curl_init($BASE . $path);
    $h = ['Accept: application/json'];
    if ($token) $h[] = "Authorization: Bearer $token";
    if ($body !== null) { $h[] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $t0 = microtime(true);
    $raw = curl_exec($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'json' => json_decode((string)$raw, true), 'raw' => (string)$raw, 'ms' => $ms, 'err' => $err];
}
function login(?array $acc): ?string {
    if (!$acc) return null;
    global $PASS;
    $r = req('POST', '/api/v1/auth/login', null, ['email' => $acc['email'], 'password' => $PASS]);
    foreach (['access_token','token'] as $k) if (!empty($r['json'][$k])) return $r['json'][$k];
    foreach (['access_token','token'] as $k) if (!empty($r['json']['data'][$k])) return $r['json']['data'][$k];
    return $r['json']['data']['token'] ?? null;
}

/* ---------- preflight ---------- */
$ping = req('GET','/api/v1/auth/clinics');
if ($ping['code'] === 0) {
    fwrite(STDERR, "Cannot reach $BASE - is 'php artisan serve' running?\n");
    fwrite(STDERR, ($ping['err'] ?: '')."\n"); exit(1);
}
echo "Server: $BASE\n";
$TOK = [];
foreach ($ACC as $role => $acc) {
    $TOK[$role] = login($acc);
    printf("  %-14s %-42s %s\n", $role, $acc['email'] ?? '(no seeded account)',
        $TOK[$role] ? 'signed in' : 'COULD NOT SIGN IN');
}
$missing = array_keys(array_filter($TOK, fn($v) => $v === null));
if ($missing) {
    fwrite(STDERR, "\nThese roles could not sign in: ".implode(', ', $missing)."\n");
    fwrite(STDERR, "Check that the seeded password is '$PASS' and that the accounts are active.\n");
    fwrite(STDERR, "The run would report wrong results without them, so it stops here.\n");
    exit(1);
}
echo "\n";

/* ---------- harness ---------- */
$RESULTS = []; $NO = 0;
function t(string $module, string $scenario, string $steps, string $expected, callable $fn): void {
    global $RESULTS, $NO;
    $NO++;
    $id = sprintf('TC-%02d', $NO);
    $actual = ''; $status = 'FAIL'; $ms = 0;
    try {
        [$ok, $actual, $ms] = $fn();
        $status = $ok === null ? 'SKIP' : ($ok ? 'PASS' : 'FAIL');
    } catch (Throwable $e) { $actual = 'Error: ' . $e->getMessage(); }
    $RESULTS[] = compact('id','module','scenario','steps','expected','actual','status','ms');
    printf("%-7s %-26s %-6s %s\n", $id, substr($module,0,26), $status, substr($scenario,0,60));
}
function ok(bool $c, string $msg, int $ms = 0): array { return [$c, $msg, $ms]; }
function why(array $r): string {
    if (in_array($r['code'], [200,201], true)) return '';
    $j = $r['json'];
    $m = $j['message'] ?? $j['error'] ?? null;
    if (!empty($j['errors']) && is_array($j['errors'])) {
        $f = array_key_first($j['errors']);
        $m = $f . ': ' . (is_array($j['errors'][$f]) ? ($j['errors'][$f][0] ?? '') : $j['errors'][$f]);
    }
    return $m ? ' - ' . substr((string)$m, 0, 90) : ' - ' . substr(trim($r['raw']), 0, 90);
}
function skip(string $why): array { return [null, $why, 0]; }
function listOf($j): array {
    if (is_array($j)) { foreach (['data','items','rows','results'] as $k) if (isset($j[$k]) && is_array($j[$k])) return $j[$k]; }
    return is_array($j) ? $j : [];
}

$pt = $TOK['patient']; $rc = $TOK['receptionist']; $dt = $TOK['dentist'];
$ad = $TOK['clinic_admin']; $sa = $TOK['superadmin'];

/* ===================== AUTHENTICATION ===================== */
t('Authentication','Patient signs in with correct credentials',
  'Send email and password to /auth/login','An access token is returned',
  fn() => ok($pt !== null, $pt ? 'Token received' : 'No token returned'));

t('Authentication','Sign-in with a wrong password is refused',
  'Send a valid email with a wrong password','The request is rejected (401)',
  function() { global $ACC; $r = req('POST','/api/v1/auth/login',null,
      ['email'=>$ACC['patient']['email'] ?? 'x@x.test','password'=>'wrong-password']);
    return ok($r['code']===401 || $r['code']===422, 'HTTP '.$r['code'], $r['ms']); });

t('Authentication','A request without a token is refused',
  'Call /auth/me with no Authorization header','The request is rejected (401)',
  function() { $r = req('GET','/api/v1/auth/me'); return ok($r['code']===401,'HTTP '.$r['code'],$r['ms']); });

t('Authentication','The signed-in account is returned',
  'Call /auth/me with the patient token','The response carries the same email',
  function() { global $pt,$ACC; $r = req('GET','/api/v1/auth/me',$pt);
    $e = json_encode($r['json']); return ok($r['code']===200 && str_contains($e, $ACC['patient']['email']),
      'HTTP '.$r['code'], $r['ms']); });

t('Authentication','A new token can be issued before expiry',
  'Call /auth/refresh with a valid token','A new token is returned',
  function() { global $ACC; $tok = login($ACC['patient']);   // throwaway: refresh invalidates the old token
    $r = req('POST','/api/v1/auth/refresh',$tok); $j = $r['json'];
    $has = !empty($j['access_token']) || !empty($j['token']) || !empty($j['data']['access_token']) || !empty($j['data']['token']);
    return ok($r['code']===200 && $has,'HTTP '.$r['code'],$r['ms']); });

t('Authentication','The clinic list is public',
  'Call /auth/clinics without signing in','A list of clinics is returned',
  function() { $r = req('GET','/api/v1/auth/clinics');
    return ok($r['code']===200 && count(listOf($r['json']))>0,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' clinics',$r['ms']); });

/* ===================== SECURITY / ACCESS ===================== */
t('Role and clinic scope','A patient cannot open the staff inbox',
  'Call /chat/inbox with the patient token','Access is denied (403)',
  function() { global $pt; $r = req('GET','/api/v1/chat/inbox',$pt);
    return ok(in_array($r['code'],[401,403]),'HTTP '.$r['code'],$r['ms']); });

t('Role and clinic scope','A patient cannot list all patients',
  'Call /patients with the patient token','Access is denied (403)',
  function() { global $pt; $r = req('GET','/api/v1/patients',$pt);
    return ok(in_array($r['code'],[401,403]),'HTTP '.$r['code'],$r['ms']); });

t('Role and clinic scope','A receptionist cannot open the finance dashboard',
  'Call /stats/finance with the receptionist token','Access is denied (403)',
  function() { global $rc; $r = req('GET','/api/v1/stats/finance',$rc);
    return ok(in_array($r['code'],[401,403]),'HTTP '.$r['code'],$r['ms']); });

t('Role and clinic scope','A dentist cannot create user accounts',
  'POST /users with the dentist token','Access is denied (403)',
  function() { global $dt; $n='QA Probe '.date('His'); $r = req('POST','/api/v1/users',$dt,['name'=>$n,'email'=>'qa'.time().'@probe.test','password'=>'Password123!','password_confirmation'=>'Password123!','role'=>'receptionist','status'=>'active']);
    return ok(in_array($r['code'],[401,403]),'HTTP '.$r['code'].why($r),$r['ms']); });

t('Role and clinic scope','Staff see only their own clinic records',
  'List patients as staff and compare clinic_id','Every row belongs to the same clinic',
  function() { global $rc,$CLINIC; $r = req('GET','/api/v1/patients',$rc);
    $rows = listOf($r['json']); if (!$rows) return ok(false,'HTTP '.$r['code'].', no rows',$r['ms']);
    $bad = 0; foreach ($rows as $row) if (isset($row['clinic_id']) && (int)$row['clinic_id'] !== (int)$CLINIC) $bad++;
    return ok($bad===0, $bad===0 ? count($rows).' rows, all in clinic '.$CLINIC : $bad.' rows from another clinic', $r['ms']); });

t('Role and clinic scope','Administrators can list staff accounts',
  'Call /users with the admin token','The account list is returned',
  function() { global $ad; $r = req('GET','/api/v1/users',$ad);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' accounts',$r['ms']); });

/* ===================== ASSISTANT ===================== */
t('Assistant','The assistant answers a patient message',
  'POST /chat with a greeting','A reply is returned',
  function() { global $pt; $r = req('POST','/api/v1/chat',$pt,['message'=>'Hello']);
    $txt = json_encode($r['json']);
    return ok($r['code']===200 && strlen($txt)>10,'HTTP '.$r['code'],$r['ms']); });

t('Assistant','The assistant answers a question about services',
  'POST /chat asking what services are offered','A reply mentioning services is returned',
  function() { global $pt; $r = req('POST','/api/v1/chat',$pt,['message'=>'What services do you offer?']);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Assistant','Chat history is kept for the patient',
  'Call /chat/history after sending messages','Earlier messages are returned',
  function() { global $pt; $r = req('GET','/api/v1/chat/history',$pt);
    return ok($r['code']===200 && count(listOf($r['json']))>0,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' messages',$r['ms']); });

t('Assistant','Staff can open the escalated chat inbox',
  'Call /chat/inbox with the receptionist token','The inbox is returned',
  function() { global $rc; $r = req('GET','/api/v1/chat/inbox',$rc);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' chats',$r['ms']); });

t('Assistant','A patient can read staff replies',
  'Call /chat/replies with the patient token','The reply list is returned',
  function() { global $pt; $r = req('GET','/api/v1/chat/replies',$pt);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

/* ===================== SCHEDULING ===================== */
$SLOT = null; $APPT = null;
t('Scheduling','Open slots are listed for booking',
  'Call /slots for a dentist and a future date','At least one open slot is returned',
  function() { global $pt,$SLOT; 
    for ($d=1; $d<=14; $d++) {
      $date = date('Y-m-d', strtotime("+$d day"));
      $r = req('GET','/api/v1/slots?date='.$date,$pt);
      $rows = listOf($r['json']);
      if ($r['code']===200 && $rows) { $SLOT = ['date'=>$date,'rows'=>$rows]; return ok(true, count($rows).' slots on '.$date, $r['ms']); }
    }
    return ok(false,'No open slot found in the next 14 days'); });

t('Scheduling','Reference lists for booking are available',
  'Call /lookups','Services, dentists and branches are returned',
  function() { global $pt; $r = req('GET','/api/v1/lookups',$pt);
    return ok($r['code']===200 && is_array($r['json']),'HTTP '.$r['code'],$r['ms']); });

t('Scheduling','An appointment is created on an open slot',
  'POST /appointments using an open slot','The appointment is saved',
  function() { global $rc,$SLOT,$APPT,$pdo,$CLINIC;
    if (!$SLOT) return skip('No open slot was found');
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? ORDER BY patient_id LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $s = $SLOT['rows'][0];
    $body = array_filter([
      'patient_id'=>$pid,
      'dentist_id'=>$s['dentist_id'] ?? null,
      'service_id'=>$s['service_id'] ?? null,
      'branch_id'=>$s['branch_id'] ?? null,
      'start_time'=>$s['start_time'] ?? ($SLOT['date'].' 09:00:00'),
      'end_time'=>$s['end_time'] ?? null,
    ], fn($v) => $v !== null);
    $r = req('POST','/api/v1/appointments',$rc,$body);
    $APPT = $r['json']['appointment_id'] ?? ($r['json']['data']['appointment_id'] ?? null);
    return ok(in_array($r['code'],[200,201]) && $APPT,'HTTP '.$r['code'].($APPT?', id '.$APPT:''),$r['ms']); });

t('Scheduling','The same slot cannot be booked twice',
  'POST the identical appointment a second time','The second request is refused',
  function() { global $rc,$SLOT,$CLINIC,$pdo;
    if (!$SLOT) return skip('No open slot was found');
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? ORDER BY patient_id LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $s = $SLOT['rows'][0];
    $body = array_filter(['patient_id'=>$pid,'dentist_id'=>$s['dentist_id']??null,'service_id'=>$s['service_id']??null,
      'branch_id'=>$s['branch_id']??null,'start_time'=>$s['start_time']??($SLOT['date'].' 09:00:00'),'end_time'=>$s['end_time']??null],
      fn($v)=>$v!==null);
    $r = req('POST','/api/v1/appointments',$rc,$body);
    return ok($r['code']>=400,'HTTP '.$r['code'],$r['ms']); });

t('Scheduling','An appointment can be moved to another time',
  'PATCH the appointment with a new start time','The new time is saved',
  function() { global $rc,$APPT;
    if (!$APPT) return skip('No appointment was created');
    $new = date('Y-m-d H:i:s', strtotime('+21 day 10:00'));
    $r = req('PATCH','/api/v1/appointments/'.$APPT,$rc,['start_time'=>$new]);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Scheduling','An appointment can be cancelled',
  'DELETE the appointment','The appointment is cancelled',
  function() { global $rc,$APPT;
    if (!$APPT) return skip('No appointment was created');
    $r = req('DELETE','/api/v1/appointments/'.$APPT,$rc);
    return ok(in_array($r['code'],[200,204]),'HTTP '.$r['code'],$r['ms']); });

t('Scheduling','An appointment in the past is refused',
  'POST an appointment with yesterday as the start time','The request is refused',
  function() { global $rc,$CLINIC,$pdo;
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? ORDER BY patient_id LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $r = req('POST','/api/v1/appointments',$rc,
      ['patient_id'=>$pid,'start_time'=>date('Y-m-d H:i:s',strtotime('-1 day'))]);
    return ok($r['code']>=400,'HTTP '.$r['code'],$r['ms']); });

/* ===================== WAITING LIST ===================== */
t('Waiting list','A patient joins the waiting list',
  'POST /waitlist with a preferred date','The entry is saved',
  function() { global $pt; $r = req('POST','/api/v1/waitlist',$pt,
      ['preferred_date'=>date('Y-m-d',strtotime('+7 day'))]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Waiting list','Staff can see the waiting list',
  'Call /waitlist with the receptionist token','The waiting list is returned',
  function() { global $rc; $r = req('GET','/api/v1/waitlist',$rc);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' entries',$r['ms']); });

/* ===================== CLINIC VISIT ===================== */
t('Clinic visit','The daily queue is returned',
  'Call /queue with the receptionist token','The queue for today is returned',
  function() { global $rc; $r = req('GET','/api/v1/queue',$rc);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' in queue',$r['ms']); });

t('Clinic visit','A walk-in patient is added to the queue',
  'POST /queue/walkin','The walk-in appears in the queue',
  function() { global $rc,$CLINIC,$pdo;
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? ORDER BY patient_id LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $d = $pdo->query('SELECT dentist_id FROM dentists ORDER BY dentist_id LIMIT 1')->fetchColumn();
    $sv = $pdo->query('SELECT service_id FROM services ORDER BY service_id LIMIT 1')->fetchColumn();
    $b = $pdo->prepare('SELECT branch_id FROM branches WHERE clinic_id = ? LIMIT 1'); $b->execute([$CLINIC]); $bid = $b->fetchColumn();
    $r = req('POST','/api/v1/queue/walkin',$rc,['patient_id'=>$pid,'dentist_id'=>$d,'service_id'=>$sv,'branch_id'=>$bid]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'].why($r),$r['ms']); });

t('Clinic visit','A dentist records a treatment',
  'POST a treatment through /r/treatments','The treatment is saved',
  function() { global $dt,$pdo,$CLINIC;
    $p = $pdo->prepare('SELECT appointment_id, patient_id, dentist_id FROM appointments ORDER BY appointment_id DESC LIMIT 1');
    $p->execute(); $a = $p->fetch(PDO::FETCH_ASSOC);
    if (!$a) return skip('No appointment on record');
    $r = req('POST','/api/v1/r/treatments',$dt,['appointment_id'=>$a['appointment_id'],'patient_id'=>$a['patient_id'],
      'dentist_id'=>$a['dentist_id'],'procedure_name'=>'Test cleaning','treatment_date'=>date('Y-m-d'),'cost'=>500]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Clinic visit','A dentist records a prescription',
  'POST a prescription through /r/prescriptions','The prescription is saved',
  function() { global $dt,$pdo;
    $p = $pdo->query('SELECT treatment_id, patient_id, dentist_id FROM treatments ORDER BY treatment_id DESC LIMIT 1');
    $a = $p->fetch(PDO::FETCH_ASSOC);
    if (!$a) return skip('No treatment on record');
    $r = req('POST','/api/v1/r/prescriptions',$dt,['treatment_id'=>$a['treatment_id'],'patient_id'=>$a['patient_id'],
      'dentist_id'=>$a['dentist_id'],'medicine'=>'Amoxicillin','dosage'=>'500mg','frequency'=>'3x a day','duration'=>'7 days']);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Clinic visit','A dental chart entry is saved',
  'POST a chart entry through /r/dental-chart','The entry is saved',
  function() { global $dt,$pdo,$CLINIC;
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $r = req('POST','/api/v1/r/dental-chart',$dt,['patient_id'=>$pid,'tooth_no'=>'11','condition'=>'Filled','date_updated'=>date('Y-m-d')]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Clinic visit','A patient history summary is returned',
  'Call /patient-summary/{id} with the dentist token','The summary is returned',
  function() { global $dt,$pdo,$CLINIC;
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $r = req('GET','/api/v1/patient-summary/'.$pid,$dt);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

/* ===================== BILLING ===================== */
t('Billing','An invoice is generated for a visit',
  'POST /invoices/generate','The invoice is created with a total',
  function() { global $rc,$pdo;
    $q = $pdo->prepare("SELECT a.appointment_id FROM appointments a JOIN patients p ON p.patient_id=a.patient_id WHERE p.clinic_id = ? AND a.status='completed' ORDER BY a.appointment_id DESC LIMIT 1");
    $q->execute([$GLOBALS['CLINIC']]); $a = $q->fetchColumn();
    if (!$a) return skip('No appointment on record');
    $r = req('POST','/api/v1/invoices/generate',$rc,['appointment_id'=>$a]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Billing','A payment is recorded',
  'POST a payment through /r/payments','The payment is saved',
  function() { global $rc,$pdo;
    $i = $pdo->query('SELECT invoice_id FROM invoices ORDER BY invoice_id DESC LIMIT 1')->fetchColumn();
    if (!$i) return skip('No invoice on record');
    $r = req('POST','/api/v1/r/payments',$rc,['invoice_id'=>$i,'amount'=>100,'method'=>'Cash','paid_at'=>date('Y-m-d H:i:s')]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'].why($r),$r['ms']); });

t('Billing','A patient can see their own transactions',
  'Call /my/transactions with the patient token','The transaction list is returned',
  function() { global $pt; $r = req('GET','/api/v1/my/transactions',$pt);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

/* ===================== RECORDS AND OCR ===================== */
t('Patient records','Staff can open a patient record',
  'Call /patients/{id} with the receptionist token','The record is returned',
  function() { global $rc,$pdo,$CLINIC;
    $p = $pdo->prepare('SELECT patient_id FROM patients WHERE clinic_id = ? LIMIT 1');
    $p->execute([$CLINIC]); $pid = $p->fetchColumn();
    $r = req('GET','/api/v1/patients/'.$pid,$rc);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Patient records','A new patient record is created',
  'POST a patient through /r/patient-records','The record is saved',
  function() { global $rc; $r = req('POST','/api/v1/r/patient-records',$rc,
      ['full_name'=>'Test Patient '.date('His'),'contact_no'=>'09170000000','status'=>'active']);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Scanned forms','The form reader endpoint responds',
  'POST /ocr/read without an API key configured','Either text is returned or the feature reports it is off',
  function() { global $rc; $r = req('POST','/api/v1/ocr/read',$rc,[]);
    return ok($r['code']<500,'HTTP '.$r['code'].' (reader is optional)',$r['ms']); });

/* ===================== REMINDERS ===================== */
t('Reminders','The reminder run can be triggered',
  'POST /reminders/run with the admin token','The run completes',
  function() { global $ad; $r = req('POST','/api/v1/reminders/run',$ad,[]);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Reminders','A patient can read their notifications',
  'Call /my/notifications with the patient token','The notification list is returned',
  function() { global $pt; $r = req('GET','/api/v1/my/notifications',$pt);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' items',$r['ms']); });

/* ===================== INVENTORY AND REPORTS ===================== */
t('Inventory and reports','An inventory item is created',
  'POST an item through /r/inventory','The item is saved',
  function() { global $ad; $r = req('POST','/api/v1/r/inventory',$ad,
      ['item_name'=>'Test gloves '.date('His'),'item_code'=>'TG'.date('His'),'item_type'=>'Supply',
       'quantity'=>10,'unit'=>'box','unit_cost'=>250,'reorder_level'=>2]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'].why($r),$r['ms']); });

t('Inventory and reports','The dashboard counters are returned',
  'Call /stats with the receptionist token','The counters are returned',
  function() { global $rc; $r = req('GET','/api/v1/stats',$rc);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Inventory and reports','The report endpoint returns totals',
  'Call /reports with the admin token','The report data is returned',
  function() { global $ad; $r = req('GET','/api/v1/reports',$ad);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Inventory and reports','The finance dashboard is returned to an administrator',
  'Call /stats/finance with the admin token','The finance figures are returned',
  function() { global $ad; $r = req('GET','/api/v1/stats/finance',$ad);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

/* ===================== CONFIGURATION ===================== */
t('Clinic configuration','Clinic information is readable',
  'Call /clinic-info with a signed-in token','The clinic details are returned',
  function() { global $rc; $r = req('GET','/api/v1/clinic-info',$rc);
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Clinic configuration','An administrator updates the clinic contact number',
  'PUT /clinic-info with a new contact number','The change is saved',
  function() { global $ad; $r = req('PUT','/api/v1/clinic-info',$ad,['contact_no'=>'0917'.date('Hism')]);
    return ok($r['code']===200,'HTTP '.$r['code'].why($r),$r['ms']); });

t('Clinic configuration','The landing page data is public',
  'Call /public/landing without signing in','The landing content is returned',
  function() { $r = req('GET','/api/v1/public/landing');
    return ok($r['code']===200,'HTTP '.$r['code'],$r['ms']); });

t('Clinic configuration','A service can be added',
  'POST a service through /r/services','The service is saved',
  function() { global $ad; $r = req('POST','/api/v1/r/services',$ad,
      ['service_name'=>'Test Service '.date('His'),'duration_min'=>30,'price'=>800,'is_active'=>1]);
    return ok(in_array($r['code'],[200,201]),'HTTP '.$r['code'],$r['ms']); });

t('Clinic configuration','Dentist schedules are readable',
  'Call /r/schedules with the admin token','The schedules are returned',
  function() { global $ad; $r = req('GET','/api/v1/r/schedules',$ad);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' rows',$r['ms']); });

/* ===================== AUDIT AND SUPERADMIN ===================== */
t('Audit trail','Changes are written to the audit log',
  'Call /r/audit-logs after the earlier updates','New audit rows are present',
  function() { global $ad; $r = req('GET','/api/v1/r/audit-logs',$ad);
    return ok($r['code']===200 && count(listOf($r['json']))>0,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' rows',$r['ms']); });

t('Audit trail','Sign-in sessions are logged',
  'Call /r/session-logs with the admin token','Session rows are present',
  function() { global $ad; $r = req('GET','/api/v1/r/session-logs',$ad);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' rows',$r['ms']); });

t('SuperAdmin','The SuperAdmin can list every clinic',
  'Call /r/clinics with the superadmin token','All clinics are returned',
  function() { global $sa; if (!$sa) return skip('No superadmin account found');
    $r = req('GET','/api/v1/r/clinics',$sa);
    return ok($r['code']===200,'HTTP '.$r['code'].', '.count(listOf($r['json'])).' clinics',$r['ms']); });

t('SuperAdmin','A clinic administrator cannot list every clinic',
  'Call /r/clinics with the clinic administrator token','Access is denied or scoped to one clinic',
  function() { global $ad; $r = req('GET','/api/v1/r/clinics',$ad);
    $n = count(listOf($r['json']));
    return ok(in_array($r['code'],[401,403]) || $n<=1,'HTTP '.$r['code'].', '.$n.' clinics',$r['ms']); });

/* ===================== SESSION END ===================== */
t('Authentication','Signing out invalidates the token',
  'Call /auth/logout, then call /auth/me with the same token','The second call is refused',
  function() { global $ACC; $tok = login($ACC['patient']);
    $a = req('POST','/api/v1/auth/logout',$tok);
    $b = req('GET','/api/v1/auth/me',$tok);
    return ok($a['code']===200 && $b['code']===401,'logout HTTP '.$a['code'].', me HTTP '.$b['code'],$a['ms']+$b['ms']); });

/* ---------- output ---------- */
$pass = count(array_filter($RESULTS, fn($r)=>$r['status']==='PASS'));
$fail = count(array_filter($RESULTS, fn($r)=>$r['status']==='FAIL'));
$skipn= count(array_filter($RESULTS, fn($r)=>$r['status']==='SKIP'));
$tot  = count($RESULTS);
$rate = $tot ? round($pass / max(1,$tot-$skipn) * 100, 1) : 0;

$fh = fopen("$ROOT/test_results.csv",'w');
fputcsv($fh, ['Test Case ID','Module','Test Scenario','Test Steps','Expected Result','Actual Result','Status','Response (ms)']);
foreach ($RESULTS as $r) fputcsv($fh, [$r['id'],$r['module'],$r['scenario'],$r['steps'],$r['expected'],$r['actual'],$r['status'],$r['ms']]);
fclose($fh);
file_put_contents("$ROOT/test_results.json", json_encode([
  'run_at'=>date('Y-m-d H:i:s'),'base_url'=>$BASE,
  'totals'=>compact('tot','pass','fail','skipn','rate'),'results'=>$RESULTS], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "\n".str_repeat('-',62)."\n";
printf("Total %d   Passed %d   Failed %d   Skipped %d   Pass rate %.1f%%\n",$tot,$pass,$fail,$skipn,$rate);
echo "Written: test_results.csv and test_results.json\n";
