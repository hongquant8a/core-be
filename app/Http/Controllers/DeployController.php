<?php

namespace App\Http\Controllers;

use App\Jobs\RunDeployJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.deploy.webhook_secret');
        if ($secret === '') {
            return response()->json(['error' => 'Deploy webhook is not configured.'], 500);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $ref = (string) $request->input('ref');
        if ($ref !== '' && $ref !== 'refs/heads/stable') {
            return response()->json(['error' => 'Branch not allowed', 'ref' => $ref], 422);
        }

        dispatch(new RunDeployJob());
        Log::info('[deploy] Webhook received, job dispatched.');

        return response()->json(['status' => 'ok', 'message' => 'Deploy job queued']);
    }
}
