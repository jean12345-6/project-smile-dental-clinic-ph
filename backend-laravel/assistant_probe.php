<?php

/**
 * Temporary probe: shows what the assistant's language model is given and what it answers.
 * Run from backend-laravel:  php assistant_probe.php
 * Delete once the assistant is confirmed working.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\V1\AssistantAI;
use Illuminate\Support\Facades\DB;

$question = $argv[1] ?? 'Anong oras kayo bukas sa Sabado at magkano ang linis?';
$patient = DB::table('patients')->orderBy('patient_id')->first();
$clinicId = $patient->clinic_id ?? 1;

echo "provider key present : " . (config('assistant.anthropic.key') ? 'yes' : 'no') . "\n";
echo "model                : " . config('assistant.anthropic.model') . "\n";
echo "enabled              : " . (config('assistant.enabled') ? 'yes' : 'no') . "\n";
echo "timeout              : " . config('assistant.timeout') . "s\n";
echo "clinic id            : {$clinicId}\n\n";

$ai = new AssistantAI;
$r = new ReflectionClass($ai);

$facts = $r->getMethod('facts');
$facts->setAccessible(true);
echo "---- facts given to the model ----\n";
echo $facts->invoke($ai, $clinicId, $patient) . "\n\n";

echo "---- question ----\n{$question}\n\n";

$t = microtime(true);
$answer = $ai->answer($question, $clinicId, $patient);
$ms = round((microtime(true) - $t) * 1000);

echo "---- answer (" . $ms . " ms) ----\n";
echo $answer === null ? "(null - the assistant would escalate to staff)\n" : $answer . "\n";
