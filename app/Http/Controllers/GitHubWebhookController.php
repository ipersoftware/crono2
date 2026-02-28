<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GitHubWebhookController extends Controller
{
    /**
     * Gestisce i webhook di GitHub per il deploy automatico
     */
    public function hook(Request $request)
    {
        Log::info('GitHub webhook CALLED');
        
        $secret = config('services.github.webhook_secret');
        Log::info('Secret configured:', [
            'secret_length' => strlen($secret), 
            'secret_empty' => empty($secret),
            'secret_first_10' => substr($secret, 0, 10) . '...'
        ]);
        
        // Log degli headers
        $headers = $request->headers->all();
        Log::info('Headers received:', $headers);
        
        // Ottieni il body raw
        $body = $request->getContent();
        Log::info('Body received:', ['body_length' => strlen($body), 'body_preview' => substr($body, 0, 100)]);

        // Verifica che l'header della signature sia presente
        if (!$request->hasHeader('X-Hub-Signature-256')) {
            Log::warning('Secret header not defined');
            return response()->json([
                'message' => 'KO_SECRET_HEADER_NOT_DEFINED'
            ], 401);
        }

        $secretHeader = $request->header('X-Hub-Signature-256');
        Log::info('Secret header from GitHub:', ['header' => $secretHeader]);

        // Calcola l'hash con il secret
        $calculatedHash = 'sha256=' . hash_hmac('sha256', $body, $secret);
        Log::info('Calculated hash:', ['hash' => $calculatedHash]);
        
        Log::info('Hash comparison:', [
            'received' => $secretHeader,
            'calculated' => $calculatedHash,
            'match' => hash_equals($secretHeader, $calculatedHash)
        ]);

        // Verifica che l'hash corrisponda
        if (hash_equals($secretHeader, $calculatedHash)) {
            Log::info('UGUALI - Attivazione deploy');

            try {
                // Reset hard e git pull per sovrascrivere eventuali modifiche locali
                $gitResult = Process::path('/var/www/ermes')
                    ->run('git reset --hard && git pull');

                if (!$gitResult->successful()) {
                    Log::error('Git pull fallito', [
                        'error' => $gitResult->errorOutput()
                    ]);

                    return response()->json([
                        'message' => 'KO_GIT_PULL_FAILED',
                        'error' => $gitResult->errorOutput()
                    ], 500);
                }

                Log::info('Git pull eseguito con successo', [
                    'output' => $gitResult->output()
                ]);

                // Esegui npm ci e build solo se i permessi lo permettono
                // Altrimenti fai manualmente: cd /var/www/ermes && npm ci && npm run build
                $npmResult = Process::path('/var/www/ermes')
                    ->timeout(300) // 5 minuti per npm
                    ->run('npm ci --prefer-offline && npm run build');

                if (!$npmResult->successful()) {
                    Log::warning('npm build fallito (permessi?)', [
                        'error' => $npmResult->errorOutput()
                    ]);

                    return response()->json([
                        'message' => 'OK_GIT_ONLY',
                        'warning' => 'Git pull OK, ma npm build fallito. Esegui manualmente: cd /var/www/ermes && npm ci && npm run build',
                        'error' => $npmResult->errorOutput(),
                        'git_output' => $gitResult->output()
                    ], 200); // 200 perché git pull è comunque riuscito
                }

                Log::info('npm build eseguito con successo', [
                    'output' => $npmResult->output()
                ]);

                return response()->json([
                    'message' => 'OK',
                    'git_output' => $gitResult->output(),
                    'npm_output' => $npmResult->output()
                ]);
            } catch (\Exception $e) {
                Log::error('Errore durante git pull', [
                    'exception' => $e->getMessage()
                ]);

                return response()->json([
                    'message' => 'KO_EXCEPTION',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            Log::warning('DIVERSI - Hash non corrispondente');
            return response()->json([
                'message' => 'KO_INVALID_SIGNATURE'
            ], 403);
        }
    }
}
