<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KeyUpdateController extends Controller
{
    public function update(Request $request)
    {
        try {
            $data = $request->all();
            
            // Sauvegarder la nouvelle clé
            $keyPath = base_path("keys/public_{$data['bank']}.pem");
            file_put_contents($keyPath, $data['new_public_key']);
            
            Log::info('KEY_RECEIVED', ['bank' => $data['bank']]);
            
            return response()->json([
                'success' => true,
                'message' => 'Clé mise à jour'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function status(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }
}
