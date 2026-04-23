<?php

namespace App\Http\Controllers;

use App\Services\CryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    private CryptoService $crypto;
    
    public function __construct()
    {
        $this->crypto = new CryptoService();
    }
    
    // ================================================================
    // API METHODS
    // ================================================================
    
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'from_iban' => 'required|string',
                'to_iban' => 'required|string',
                'currency' => 'required|string|size:3',
                'description' => 'nullable|string'
            ]);
            
            $uuid = CryptoService::generateUuid();
            $timestamp = now()->toIso8601String();
            
            $transferData = [
                'uuid' => $uuid,
                'amount' => $validated['amount'],
                'from_iban' => $validated['from_iban'],
                'to_iban' => $validated['to_iban'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? '',
                'timestamp' => $timestamp,
                'sender_bank' => 'CREDIT_SAHARA'
            ];
            
            $jsonData = json_encode($transferData);
            
            $noorPublicKey = file_get_contents(base_path('keys/public_noor_bank.pem'));
            $encrypted = $this->crypto->encryptHybrid($jsonData, $noorPublicKey);
            
            $creditPrivateKey = file_get_contents(base_path('keys/private_credit_sahara.pem'));
            $signature = $this->crypto->sign($jsonData, $creditPrivateKey);
            
            $packet = [
                'uuid' => $uuid,
                'timestamp' => $timestamp,
                'sender_bank' => 'CREDIT_SAHARA',
                'encrypted_session_key' => $encrypted['encrypted_session_key'],
                'nonce' => $encrypted['nonce'],
                'ciphertext' => $encrypted['ciphertext'],
                'tag' => $encrypted['tag'],
                'signature' => $signature
            ];
            
            $response = Http::timeout(30)->post('http://app_noor_bank:8000/api/transfer/receive', $packet);
            
            return response()->json([
                'success' => true,
                'message' => 'Virement envoyé avec succès',
                'uuid' => $uuid,
                'status' => $response->json('status', 'pending')
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Erreur transfert: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function status($uuid)
    {
        $transaction = DB::table('transactions')->where('uuid', $uuid)->first();
        
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'uuid' => $transaction->uuid,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at
        ]);
    }
    
    // ================================================================
    // WEB METHODS (Interface Graphique)
    // ================================================================
    
    public function create()
    {
        return view('transfers.create');
    }
    
    public function index()
    {
        $transfers = DB::table('transactions')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('transfers.index', compact('transfers'));
    }
    
    public function show($uuid)
    {
        $transfer = DB::table('transactions')->where('uuid', $uuid)->first();
        
        if (!$transfer) {
            return redirect()->route('transfers.index')->with('error', 'Transaction non trouvée');
        }
        
        return view('transfers.show', compact('transfer'));
    }
    
    public function store(Request $request)
    {
        return $this->send($request);
    }
}
