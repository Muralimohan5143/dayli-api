<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SesWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        Log::info('SES Feedback', $data);
        // TODO: mark email as invalid in DB if bounce/complaint
        return response()->json(['status' => 'ok']);
    }
}
