<?php

namespace App\Http\Controllers;

use App\Events\WebhookReceived;
use App\Models\MetformWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleMetform(Request $request)
    {
        try {
            $webhook = MetformWebhook::create([
                'payload' => $request->all(),
            ]);

            Log::info('Metform webhook received', [
                'webhook_id' => $webhook->id,
            ]);

            // Fire event to send Brevo email
            event(new WebhookReceived($webhook));

            return response()->json([
                'success' => true,
                'message' => 'Webhook received successfully',
                'webhook_id' => $webhook->id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to store Metform webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store webhook data',
            ], 500);
        }
    }
}
