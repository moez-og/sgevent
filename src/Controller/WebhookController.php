<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    #[Route('/webhook/offres/analyze-callback', name: 'webhook_offres_analyze_callback', methods: ['POST'])]
    public function offresAnalyzeCallback(Request $request, Connection $connection): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => 'JSON invalide'], 400);
            }

            // n8n can sometimes wrap the payload under body/data depending on node settings.
            $payload = $this->normalizeCallbackPayload($data);

            $trackingId = $payload['tracking_id'] ?? null;
            error_log('N8N CALLBACK RECEIVED: tracking_id=' . ($trackingId ?? 'NULL'));
            error_log('N8N CALLBACK DATA: ' . json_encode($payload));
            
            // Sauvegarder l'analyse en base de données
            $connection->insert('offre_analysis', [
                'offre_id' => $payload['offre_id'] ?? null,
                'score' => $payload['score'] ?? 0,
                'evaluation' => $payload['evaluation'] ?? '',
                'points_faibles' => json_encode($payload['points_faibles'] ?? [], JSON_UNESCAPED_UNICODE),
                'ameliorations' => json_encode($payload['ameliorations'] ?? [], JSON_UNESCAPED_UNICODE),
                'offre_optimisee' => json_encode($payload['offre_optimisee'] ?? [], JSON_UNESCAPED_UNICODE),
                'diffusion' => json_encode($payload['diffusion'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $analysisId = (int) $connection->lastInsertId();
            error_log('Analysis saved with ID: ' . $analysisId);

            // Mettre à jour le tracking
            if ($trackingId) {
                $result = $connection->update('offre_analysis_tracking', [
                    'status' => 'completed',
                    'analysis_id' => $analysisId,
                ], ['tracking_id' => $trackingId]);
                error_log('Tracking updated: ' . $result . ' rows affected');
            } else {
                error_log('WARNING: No tracking_id provided in callback');
            }

            // Retourner une réponse 200 OK à n8n
            return new JsonResponse([
                'status' => 'success', 
                'message' => 'Analyse reçue et stockée',
                'analysis_id' => $analysisId,
                'tracking_id' => $trackingId,
            ]);
        } catch (\Throwable $e) {
            error_log('ERROR in offresAnalyzeCallback: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function normalizeCallbackPayload(array $data): array
    {
        $payload = $data;

        if (isset($data['body']) && is_array($data['body'])) {
            $payload = array_replace($payload, $data['body']);
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = array_replace($payload, $payload['data']);
        }

        return $payload;
    }

    #[Route('/offres/analysis/{id}', name: 'app_offres_analysis_view', methods: ['GET'])]
    public function viewAnalysis(int $id, Connection $connection): Response
    {
        $analysis = $connection->fetchAssociative(
            'SELECT * FROM offre_analysis WHERE id = ?',
            [$id]
        );

        if (!$analysis) {
            throw $this->createNotFoundException('Analyse introuvable.');
        }

        // Décoder les JSON
        $analysis['points_faibles'] = json_decode($analysis['points_faibles'], true) ?? [];
        $analysis['ameliorations'] = json_decode($analysis['ameliorations'], true) ?? [];
        $analysis['offre_optimisee'] = json_decode($analysis['offre_optimisee'], true) ?? [];
        $analysis['diffusion'] = json_decode($analysis['diffusion'], true) ?? [];

        return $this->render('offres/analysis-view.html.twig', [
            'analysis' => $analysis,
        ]);
    }

    #[Route('/offres/analysis-waiting/{trackingId}', name: 'app_offres_analysis_waiting', methods: ['GET'])]
    public function analysisWaiting(string $trackingId, Connection $connection): Response
    {
        // Vérifier que le tracking existe
        $tracking = $connection->fetchAssociative(
            'SELECT * FROM offre_analysis_tracking WHERE tracking_id = ?',
            [$trackingId]
        );

        if (!$tracking) {
            throw $this->createNotFoundException('Tracking ID not found');
        }

        return $this->render('offres/analysis-waiting.html.twig', [
            'trackingId' => $trackingId,
        ]);
    }

    #[Route('/api/offres/analysis-status/{trackingId}', name: 'api_offres_analysis_status', methods: ['GET'])]
    public function analysisStatus(string $trackingId, Connection $connection): JsonResponse
    {
        $tracking = $connection->fetchAssociative(
            'SELECT analysis_id, status FROM offre_analysis_tracking WHERE tracking_id = ?',
            [$trackingId]
        );

        if (!$tracking) {
            return new JsonResponse(['error' => 'Tracking ID not found'], 404);
        }

        if ($tracking['status'] === 'completed' && $tracking['analysis_id']) {
            return new JsonResponse([
                'status' => 'completed',
                'analysis_id' => (int) $tracking['analysis_id'],
                'redirect_url' => $this->generateUrl('app_offres_analysis_view', ['id' => (int) $tracking['analysis_id']]),
            ]);
        }

        return new JsonResponse([
            'status' => 'pending',
        ]);
    }

    #[Route('/api/offres/analysis/{id}/optimized-data', name: 'api_offres_optimized_data', methods: ['GET'])]
    public function optimizedData(int $id, Connection $connection): JsonResponse
    {
        $analysis = $connection->fetchAssociative(
            'SELECT offre_optimisee FROM offre_analysis WHERE id = ?',
            [$id]
        );

        if (!$analysis) {
            return new JsonResponse(['error' => 'Analysis not found'], 404);
        }

        $offre_optimisee = json_decode($analysis['offre_optimisee'], true) ?? [];

        return new JsonResponse([
            'titre' => $offre_optimisee['titre'] ?? '',
            'description' => $offre_optimisee['description'] ?? '',
            'pourcentage_suggere' => $offre_optimisee['pourcentage_suggere'] ?? 10,
        ]);
    }
}

