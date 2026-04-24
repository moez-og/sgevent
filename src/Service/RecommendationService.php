<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Inscription;
use App\Entity\User;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de recommandation d'événements 100% gratuit.
 *
 * Stratégie (fallback chain, du plus intelligent au plus simple) :
 *   1. Si GEMINI_API_KEY configurée → Google Gemini 2.0 Flash (gratuit, 15 req/min)
 *   2. Sinon → heuristique locale TF-IDF + bonus (100% offline, aucun appel externe)
 *   3. Si aucun historique d'inscription → événements populaires (fallback final)
 *
 * Les "centres d'intérêt" sont déduits automatiquement des événements auxquels
 * l'utilisateur s'est inscrit (titres + descriptions + types). Aucun champ
 * "centres d'intérêt" n'est requis sur l'entité User.
 *
 * Inspiré du RecommendationService.java du projet JavaFX (Gemini + fallback local).
 */
class RecommendationService
{
    /** Cache en mémoire (durée d'une requête) — évite de recalculer */
    private array $recommendationCache = [];

    /** Dernière analyse textuelle (affichable dans le front) */
    private string $lastInterestsAnalysis = '';

    /** Dernière map des intérêts déduits (mot-clé → poids) */
    private array $lastInterestsMap = [];

    /** Dernier mode utilisé : 'gemini' | 'local' | 'popular' */
    private string $lastMode = 'local';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EvenementRepository $evenementRepository,
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly string $geminiApiKey = '',
    ) {
    }

    // ════════════════════════════════════════════════════════════════
    //  API PUBLIQUE
    // ════════════════════════════════════════════════════════════════

    /**
     * Retourne la liste des événements recommandés pour un utilisateur.
     *
     * @param User $user L'utilisateur connecté
     * @param int $limit Nombre maximum de recommandations (défaut 12)
     * @return Evenement[] Événements triés par pertinence décroissante
     */
    public function getRecommendations(User $user, int $limit = 12): array
    {
        $cacheKey = 'u' . $user->getId() . '_' . $limit;
        if (isset($this->recommendationCache[$cacheKey])) {
            return $this->recommendationCache[$cacheKey];
        }

        // 1. Récupérer tous les événements ouverts à venir
        $upcoming = $this->evenementRepository->findUpcoming();
        if (empty($upcoming)) {
            $this->lastMode = 'popular';
            $this->lastInterestsAnalysis = 'Aucun événement à venir.';
            return $this->recommendationCache[$cacheKey] = [];
        }

        // 2. Récupérer l'historique d'inscriptions de l'utilisateur
        $userEvents = $this->getUserInscribedEvents($user);

        // 3. Filtrer les événements auxquels l'utilisateur n'est PAS déjà inscrit
        $userEventIds = array_map(fn(Evenement $e) => $e->getId(), $userEvents);
        $available = array_values(array_filter(
            $upcoming,
            fn(Evenement $e) => !in_array($e->getId(), $userEventIds, true)
        ));

        if (empty($available)) {
            $this->lastMode = 'popular';
            $this->lastInterestsAnalysis = 'Vous êtes déjà inscrit à tous les événements à venir.';
            return $this->recommendationCache[$cacheKey] = [];
        }

        // 4. Si pas d'historique → populaires
        if (empty($userEvents)) {
            $this->lastMode = 'popular';
            $this->lastInterestsAnalysis = 'Inscrivez-vous à des événements pour personnaliser vos recommandations — voici les plus populaires.';
            $this->lastInterestsMap = ['populaires' => 1];
            $result = array_slice($this->sortByPopularity($available), 0, $limit);
            return $this->recommendationCache[$cacheKey] = $result;
        }

        // 5. Tentative Gemini si clé configurée
        $geminiKey = trim($this->geminiApiKey);
        if ($geminiKey !== '' && $geminiKey !== 'CHANGE_ME' && !str_starts_with($geminiKey, 'VOTRE_')) {
            try {
                $ids = $this->callGemini($userEvents, $available, $geminiKey);
                if (!empty($ids)) {
                    $this->lastMode = 'gemini';
                    $result = $this->resolveOrderedEvents($ids, $available);
                    if (!empty($result)) {
                        return $this->recommendationCache[$cacheKey] = array_slice($result, 0, $limit);
                    }
                }
            } catch (\Throwable $e) {
                // On retombe sur le fallback local silencieusement
            }
        }

        // 6. Fallback local (TF-IDF + bonus)
        $this->lastMode = 'local';
        $result = $this->localRecommendation($userEvents, $available);
        return $this->recommendationCache[$cacheKey] = array_slice($result, 0, $limit);
    }

    /** Analyse textuelle à afficher dans la page (ex: "musique, jazz, tunis") */
    public function getLastInterestsAnalysis(): string
    {
        return $this->lastInterestsAnalysis;
    }

    /** Map mot-clé → poids (pour debug / affichage admin) */
    public function getLastInterestsMap(): array
    {
        return $this->lastInterestsMap;
    }

    /** 'gemini' | 'local' | 'popular' */
    public function getLastMode(): string
    {
        return $this->lastMode;
    }

    /** Vider le cache (utile si on veut forcer un recalcul) */
    public function clearCache(): void
    {
        $this->recommendationCache = [];
    }

    // ════════════════════════════════════════════════════════════════
    //  RÉCUPÉRATION DES INSCRIPTIONS UTILISATEUR
    // ════════════════════════════════════════════════════════════════

    /**
     * Retourne les événements auxquels l'utilisateur s'est inscrit (hors annulées/rejetées).
     * @return Evenement[]
     */
    private function getUserInscribedEvents(User $user): array
    {
        $inscriptions = $this->inscriptionRepository->createQueryBuilder('i')
            ->leftJoin('i.evenement', 'e')
            ->addSelect('e')
            ->where('i.user = :user')
            ->andWhere('i.statut NOT IN (:invalid)')
            ->setParameter('user', $user)
            ->setParameter('invalid', [Inscription::STATUT_ANNULEE, Inscription::STATUT_REJETEE])
            ->getQuery()
            ->getResult();

        $events = [];
        $seen = [];
        foreach ($inscriptions as $i) {
            /** @var Inscription $i */
            $ev = $i->getEvenement();
            if ($ev && !isset($seen[$ev->getId()])) {
                $seen[$ev->getId()] = true;
                $events[] = $ev;
            }
        }
        return $events;
    }

    // ════════════════════════════════════════════════════════════════
    //  ALGORITHME LOCAL (TF-IDF simplifié + bonus)
    // ════════════════════════════════════════════════════════════════

    /**
     * Recommandation 100% locale, sans appel réseau.
     * @param Evenement[] $userEvents
     * @param Evenement[] $available
     * @return Evenement[]
     */
    private function localRecommendation(array $userEvents, array $available): array
    {
        $keywords = $this->extractKeywords($userEvents);
        $this->lastInterestsMap = $keywords;
        $topKeywords = array_slice(array_keys($keywords), 0, 6);
        $this->lastInterestsAnalysis = empty($topKeywords)
            ? 'Recommandations basées sur vos inscriptions.'
            : 'Centres d\'intérêt détectés : ' . implode(', ', $topKeywords);

        // Extraire les types / lieux / fourchette de prix préférés
        $preferredTypes = $this->extractTypes($userEvents);
        $preferredCities = $this->extractCities($userEvents);
        $avgPrice = $this->averagePrice($userEvents);

        // Popularité (nb d'inscriptions actives) pour bonus
        $popularityMap = $this->computePopularityMap($available);

        $scored = [];
        foreach ($available as $ev) {
            $score = $this->calculateSimilarityScore($ev, $keywords);

            // Bonus type identique
            if (in_array($ev->getType(), $preferredTypes, true)) {
                $score += 4.0;
            }

            // Bonus même ville
            $city = $ev->getLieu()?->getVille();
            if ($city && in_array(mb_strtolower($city), $preferredCities, true)) {
                $score += 3.0;
            }

            // Bonus fourchette de prix proche
            if ($avgPrice !== null) {
                $diff = abs($ev->getPrix() - $avgPrice);
                if ($diff < 10) {
                    $score += 2.0;
                } elseif ($diff < 30) {
                    $score += 1.0;
                }
            }

            // Bonus gratuit
            if ($ev->getPrix() <= 0) {
                $score += 1.5;
            }

            // Bonus popularité (log pour ne pas écraser le contenu)
            $pop = $popularityMap[$ev->getId()] ?? 0;
            $score += log($pop + 1) * 1.2;

            // Bonus proximité dans le temps (les événements dans moins de 14j gagnent)
            $daysUntil = $this->daysUntilEvent($ev);
            if ($daysUntil !== null && $daysUntil >= 0 && $daysUntil <= 14) {
                $score += (14 - $daysUntil) * 0.15;
            }

            $scored[] = ['event' => $ev, 'score' => $score];
        }

        // Tri décroissant
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($row) => $row['event'], $scored);
    }

    /**
     * TF-IDF simplifié : fréquence des mots significatifs dans les titres + descriptions.
     * @param Evenement[] $events
     * @return array<string,int> mot → poids (trié décroissant, top 12)
     */
    private function extractKeywords(array $events): array
    {
        $stopWords = [
            // FR
            'le','la','les','de','des','du','un','une','et','en','au','aux','à','a','ce','ces','cette','qui','que','quoi',
            'pour','par','sur','dans','avec','est','sont','ont','il','elle','nous','vous','ils','elles','ne','pas','se',
            'plus','très','bien','tout','tous','toute','toutes','mon','ma','mes','ton','ta','tes','son','sa','ses','notre','nos',
            'votre','vos','leur','leurs','ou','où','si','dont','donc','car','mais','puis','alors','aussi','encore','comme',
            'évenement','evenement','événement','soirée','soiree','apres','après',
            // EN
            'the','and','or','is','are','was','were','be','been','of','in','to','for','with','on','at','from','by','an','as','it',
            'this','that','these','those','has','have','had','will','would','could','should','can','may','our','your','their',
            'event','events',
        ];
        $stopSet = array_flip($stopWords);

        $counts = [];
        foreach ($events as $ev) {
            $titre = $this->safe($ev->getTitre());
            $desc = $this->safe($ev->getDescription());
            $text = mb_strtolower($titre . ' ' . $desc, 'UTF-8');

            // Découpage unicode-aware
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
            foreach ($tokens as $tok) {
                $tok = trim($tok);
                if (mb_strlen($tok, 'UTF-8') < 3) continue;
                if (is_numeric($tok)) continue;
                if (isset($stopSet[$tok])) continue;
                $counts[$tok] = ($counts[$tok] ?? 0) + 1;
            }
        }

        arsort($counts);
        return array_slice($counts, 0, 12, true);
    }

    /**
     * Score de similarité = somme des poids des mots-clés présents dans l'événement candidat.
     */
    private function calculateSimilarityScore(Evenement $ev, array $keywords): float
    {
        if (empty($keywords)) return 0.0;

        $text = mb_strtolower(
            $this->safe($ev->getTitre()) . ' ' . $this->safe($ev->getDescription()),
            'UTF-8'
        );

        $score = 0.0;
        foreach ($keywords as $word => $weight) {
            if (mb_strpos($text, $word, 0, 'UTF-8') !== false) {
                // boost pour les mots plus fréquents dans l'historique
                $score += $weight * 2.5;
            }
        }
        return $score;
    }

    /** @param Evenement[] $events */
    private function extractTypes(array $events): array
    {
        $types = [];
        foreach ($events as $ev) {
            if ($ev->getType()) $types[$ev->getType()] = true;
        }
        return array_keys($types);
    }

    /** @param Evenement[] $events */
    private function extractCities(array $events): array
    {
        $cities = [];
        foreach ($events as $ev) {
            $v = $ev->getLieu()?->getVille();
            if ($v) $cities[mb_strtolower($v, 'UTF-8')] = true;
        }
        return array_keys($cities);
    }

    /** @param Evenement[] $events */
    private function averagePrice(array $events): ?float
    {
        if (empty($events)) return null;
        $sum = 0.0; $n = 0;
        foreach ($events as $ev) { $sum += $ev->getPrix(); $n++; }
        return $n === 0 ? null : $sum / $n;
    }

    private function daysUntilEvent(Evenement $ev): ?int
    {
        $date = $ev->getDateDebut();
        if (!$date) return null;
        $diff = (new \DateTime())->diff($date);
        $days = (int) $diff->format('%r%a');
        return $days;
    }

    // ════════════════════════════════════════════════════════════════
    //  POPULARITÉ (fallback final + bonus)
    // ════════════════════════════════════════════════════════════════

    /** @param Evenement[] $events @return array<int,int> eventId → nbInscriptionsActives */
    private function computePopularityMap(array $events): array
    {
        if (empty($events)) return [];
        $ids = array_map(fn(Evenement $e) => $e->getId(), $events);

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(i.evenement) AS eid, COUNT(i.id) AS c')
            ->from(Inscription::class, 'i')
            ->where('i.evenement IN (:ids)')
            ->andWhere('i.statut IN (:ok)')
            ->setParameter('ids', $ids)
            ->setParameter('ok', [Inscription::STATUT_CONFIRMEE, Inscription::STATUT_PAYEE])
            ->groupBy('i.evenement')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['eid']] = (int) $r['c'];
        }
        return $map;
    }

    /** @param Evenement[] $events @return Evenement[] */
    private function sortByPopularity(array $events): array
    {
        $map = $this->computePopularityMap($events);
        usort($events, function (Evenement $a, Evenement $b) use ($map) {
            $pa = $map[$a->getId()] ?? 0;
            $pb = $map[$b->getId()] ?? 0;
            if ($pa === $pb) {
                // En cas d'égalité, priorité à celui qui est le plus proche dans le temps
                $da = $a->getDateDebut()?->getTimestamp() ?? PHP_INT_MAX;
                $db = $b->getDateDebut()?->getTimestamp() ?? PHP_INT_MAX;
                return $da <=> $db;
            }
            return $pb <=> $pa;
        });
        return $events;
    }

    // ════════════════════════════════════════════════════════════════
    //  GEMINI (optionnel — si clé API fournie)
    // ════════════════════════════════════════════════════════════════

    /**
     * @param Evenement[] $userEvents
     * @param Evenement[] $available
     * @return int[] IDs ordonnés par pertinence
     */
    private function callGemini(array $userEvents, array $available, string $apiKey): array
    {
        $prompt = $this->buildGeminiPrompt($userEvents, $available);

        $response = $this->httpClient->request(
            'POST',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey,
            [
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 500,
                    ],
                ],
                'timeout' => 15,
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Gemini HTTP ' . $response->getStatusCode());
        }

        $data = $response->toArray(false);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            throw new \RuntimeException('Réponse Gemini vide.');
        }

        return $this->parseGeminiResponse($text);
    }

    /** @param Evenement[] $userEvents @param Evenement[] $available */
    private function buildGeminiPrompt(array $userEvents, array $available): string
    {
        $sb = "Tu es un système de recommandation d'événements. Analyse les événements auxquels l'utilisateur s'est inscrit et recommande les plus pertinents parmi les disponibles.\n\n";

        $sb .= "=== ÉVÉNEMENTS AUXQUELS L'UTILISATEUR EST INSCRIT ===\n";
        foreach ($userEvents as $e) {
            $sb .= sprintf(
                "- [%d] %s : %s (type: %s, prix: %.2f TND)\n",
                $e->getId(),
                $this->truncate($this->safe($e->getTitre()), 80),
                $this->truncate($this->safe($e->getDescription()), 150),
                $this->safe($e->getType()),
                $e->getPrix()
            );
        }

        $sb .= "\n=== ÉVÉNEMENTS DISPONIBLES (à recommander) ===\n";
        foreach ($available as $e) {
            $sb .= sprintf(
                "- [%d] %s : %s (type: %s, prix: %.2f TND)\n",
                $e->getId(),
                $this->truncate($this->safe($e->getTitre()), 80),
                $this->truncate($this->safe($e->getDescription()), 150),
                $this->safe($e->getType()),
                $e->getPrix()
            );
        }

        $sb .= "\n=== FORMAT DE RÉPONSE ATTENDU (strict) ===\n";
        $sb .= "INTERETS: mot1, mot2, mot3\n";
        $sb .= "RECOMMANDATIONS: id1, id2, id3, id4, ...\n";
        $sb .= "EXPLICATION: une phrase courte.\n\n";
        $sb .= "Les RECOMMANDATIONS doivent être des IDs numériques séparés par des virgules, triés du plus pertinent au moins pertinent, uniquement parmi les événements disponibles.";

        return $sb;
    }

    /** @return int[] */
    private function parseGeminiResponse(string $text): array
    {
        $ids = [];
        $lines = preg_split('/\R/', $text) ?: [];
        foreach ($lines as $line) {
            $upper = mb_strtoupper($line, 'UTF-8');
            if (str_contains($upper, 'RECOMMANDATION') || str_contains($upper, 'RECOMMEND')) {
                $after = str_contains($line, ':') ? substr($line, strpos($line, ':') + 1) : $line;
                foreach (preg_split('/[^0-9]+/', $after) ?: [] as $part) {
                    $part = trim($part);
                    if ($part !== '' && ctype_digit($part)) {
                        $ids[] = (int) $part;
                    }
                }
                break;
            }
        }

        // Extraire aussi les intérêts pour l'affichage
        foreach ($lines as $line) {
            $upper = mb_strtoupper($line, 'UTF-8');
            if (str_contains($upper, 'INTERET') || str_contains($upper, 'INTÉRÊT')) {
                $after = str_contains($line, ':') ? trim(substr($line, strpos($line, ':') + 1)) : '';
                if ($after !== '') {
                    $this->lastInterestsAnalysis = 'Centres d\'intérêt (IA Gemini) : ' . $after;
                    $this->lastInterestsMap = [];
                    foreach (explode(',', $after) as $kw) {
                        $kw = trim($kw);
                        if ($kw !== '') $this->lastInterestsMap[$kw] = 1;
                    }
                }
                break;
            }
        }

        return $ids;
    }

    /**
     * @param int[] $ids
     * @param Evenement[] $available
     * @return Evenement[]
     */
    private function resolveOrderedEvents(array $ids, array $available): array
    {
        $byId = [];
        foreach ($available as $e) { $byId[$e->getId()] = $e; }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
                unset($byId[$id]);
            }
        }
        // Compléter avec les événements non cités par Gemini (au cas où il en oublie)
        foreach ($byId as $e) { $ordered[] = $e; }
        return $ordered;
    }

    // ════════════════════════════════════════════════════════════════
    //  UTILITAIRES
    // ════════════════════════════════════════════════════════════════

    private function safe(?string $s): string
    {
        return $s === null ? '' : $s;
    }

    private function truncate(string $s, int $max): string
    {
        if (mb_strlen($s, 'UTF-8') <= $max) return $s;
        return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
    }
}
