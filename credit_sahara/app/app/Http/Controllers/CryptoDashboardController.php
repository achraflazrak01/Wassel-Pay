<?php

namespace App\Http\Controllers;

use App\Services\KeyRotationService;
use App\Services\SplitKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CryptoDashboardController extends Controller
{
    /**
     * Afficher le dashboard crypto
     */
    public function index()
    {
        // Rotation des clés
        $rotationService = new KeyRotationService('credit_sahara');
        $keyStatus = $rotationService->checkKeyAge();
        $rotationHistory = $rotationService->getRotationHistory(10);
        
        // Split-Key
        $splitService = new SplitKeyService('credit_sahara');
        $shares = $splitService->listShares();
        $sharesIntegrity = [];
        
        if (!empty($shares)) {
            $sharesIntegrity = $splitService->verifySharesIntegrity();
        }
        
        // Statistiques
        $stats = [
            'total_rotations' => DB::table('key_rotation_history')->count(),
            'total_revoked_keys' => DB::table('key_revocation_list')->count(),
            'shares_count' => count($shares),
            'shares_valid' => is_array($sharesIntegrity) ? count(array_filter($sharesIntegrity, fn($s) => $s['valid'] ?? false)) : 0
        ];
        
        return view('crypto.dashboard', compact('keyStatus', 'rotationHistory', 'shares', 'sharesIntegrity', 'stats'));
    }
    
    /**
     * Forcer la rotation des clés
     */
    public function forceRotation(Request $request)
    {
        $rotationService = new KeyRotationService('credit_sahara');
        $result = $rotationService->rotateKeys(true);
        
        if ($result['success'] && $result['rotated']) {
            return redirect()->route('crypto.dashboard')->with('success', 'Rotation des clés effectuée avec succès !');
        } else {
            return redirect()->route('crypto.dashboard')->with('error', $result['message']);
        }
    }
    
    /**
     * Télécharger une part Split-Key
     */
    public function downloadShare($role)
    {
        $sharePath = base_path("keys/shares/share_{$role}_credit_sahara.json");
        
        if (!file_exists($sharePath)) {
            return redirect()->route('crypto.dashboard')->with('error', 'Part non trouvée');
        }
        
        return response()->download($sharePath, "share_{$role}_credit_sahara.json");
    }
    
    /**
     * Fractionner la clé
     */
    public function splitKey(Request $request)
    {
        try {
            $splitService = new SplitKeyService('credit_sahara');
            $shares = $splitService->splitKey();
            
            return redirect()->route('crypto.dashboard')->with('success', 'Clé fractionnée avec succès en 3 parts !');
        } catch (\Exception $e) {
            return redirect()->route('crypto.dashboard')->with('error', 'Erreur: ' . $e->getMessage());
        }
    }
    
    /**
     * Vérifier l'intégrité des parts
     */
    public function verifyShares(Request $request)
    {
        try {
            $splitService = new SplitKeyService('credit_sahara');
            $results = $splitService->verifySharesIntegrity();
            
            $validCount = count(array_filter($results, fn($r) => $r['valid'] ?? false));
            $totalCount = count($results);
            
            if ($validCount === $totalCount && $totalCount > 0) {
                return redirect()->route('crypto.dashboard')->with('success', "Toutes les $totalCount parts sont valides !");
            } else {
                return redirect()->route('crypto.dashboard')->with('error', "$validCount/$totalCount parts valides");
            }
        } catch (\Exception $e) {
            return redirect()->route('crypto.dashboard')->with('error', 'Erreur: ' . $e->getMessage());
        }
    }
}
