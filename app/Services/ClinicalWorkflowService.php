<?php

namespace App\Services;

use App\Models\Conversation;

class ClinicalWorkflowService
{
    private const MAX_ANAMNESIS_TURNS_BEFORE_FORCE = 1;

    /**
     * @var array<int, string>
     */
    private array $sensitiveTerms = [
        'nome',
        'idade',
        'data de nascimento',
        'nascimento',
        'genero',
        'gênero',
        'sexo',
        'cpf',
        'rg',
        'endereco',
        'endereço',
        'telefone',
        'celular',
        'email',
        'e-mail',
        'nome da mae',
        'nome da mãe',
    ];

    public function __construct(
        private readonly OllamaService $ollamaService,
        private readonly DatasetContextService $datasetContextService,
        private readonly OllamaPromptBuilder $promptBuilder,
        private readonly ClinicalResponseParser $clinicalResponseParser,
        private readonly ClinicalResponseFormatter $clinicalResponseFormatter,
    ) {
    }

    /**
     * @param array<int, array{role:string,content:string}> $history
     * @return array{
     *     response:string,
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>,
     *     raw_response:string
     * }
     */
    public function generateResponse(Conversation $conversation, string $userMessage, array $history): array
    {
        $snapshot = is_array($conversation->clinical_snapshot) ? $conversation->clinical_snapshot : null;
        $datasetContext = $this->datasetContextService->buildContext(
            (int) config('services.ollama.chat_context_record_limit', 8),
            $userMessage,
            (int) config('services.ollama.chat_context_top_k', 8),
        );
        $systemInstruction = $this->promptBuilder->buildOllamaSystemInstruction(
            (string) ($conversation->clinical_stage ?: 'anamnesis'),
            $snapshot,
        );

        $rawResponse = $this->ollamaService->generateResponse(
            $userMessage,
            $history,
            $datasetContext,
            $systemInstruction,
        );

        $parsedResponse = $this->clinicalResponseParser->parse($rawResponse);
        $parsedResponse = $this->sanitizeParsedResponse($parsedResponse, $snapshot);

        if ($this->shouldForceDiagnosticRefinement($conversation, $history, $userMessage, $parsedResponse, $snapshot)) {
            $forcedInstruction = $this->promptBuilder->buildOllamaSystemInstruction(
                'diagnostic_refinement',
                $this->buildUpdatedSnapshot($snapshot, $parsedResponse),
                true,
            );

            $forcedRawResponse = $this->ollamaService->generateResponse(
                $userMessage,
                $history,
                $datasetContext,
                $forcedInstruction,
            );

            $parsedResponse = $this->sanitizeParsedResponse(
                $this->clinicalResponseParser->parse($forcedRawResponse),
                $this->buildUpdatedSnapshot($snapshot, $parsedResponse),
            );
        }

        $formattedResponse = $this->clinicalResponseFormatter->format($parsedResponse);
        $updatedSnapshot = $this->buildUpdatedSnapshot($snapshot, $parsedResponse);

        $conversation->forceFill([
            'clinical_stage' => $parsedResponse['stage'],
            'clinical_snapshot' => $updatedSnapshot,
        ])->save();

        return [
            'response' => $formattedResponse,
            'stage' => $parsedResponse['stage'],
            'summary' => $parsedResponse['summary'],
            'missing_information' => $parsedResponse['missing_information'],
            'follow_up_questions' => $parsedResponse['follow_up_questions'],
            'diagnoses' => $parsedResponse['diagnoses'],
            'raw_response' => $parsedResponse['raw_response'],
        ];
    }

    /**
     * @param array<string, mixed>|null $snapshot
     * @param array<string, mixed> $parsedResponse
     * @return array<string, mixed>
     */
    private function buildUpdatedSnapshot(?array $snapshot, array $parsedResponse): array
    {
        $askedQuestions = array_merge(
            $this->normalizeSnapshotStringList($snapshot['asked_questions'] ?? []),
            $parsedResponse['follow_up_questions'] ?? [],
        );

        return [
            'summary' => $parsedResponse['summary'],
            'missing_information' => $parsedResponse['missing_information'],
            'follow_up_questions' => $parsedResponse['follow_up_questions'],
            'diagnoses' => $parsedResponse['diagnoses'],
            'asked_questions' => array_values(array_unique($askedQuestions)),
            'anamnesis_turns' => $parsedResponse['stage'] === 'anamnesis'
                ? ((int) ($snapshot['anamnesis_turns'] ?? 0) + 1)
                : (int) ($snapshot['anamnesis_turns'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $parsedResponse
     * @param array<string, mixed>|null $snapshot
     * @return array<string, mixed>
     */
    private function sanitizeParsedResponse(array $parsedResponse, ?array $snapshot): array
    {
        $askedQuestions = $this->normalizeSnapshotStringList($snapshot['asked_questions'] ?? []);
        $previousMissingInformation = $this->normalizeSnapshotStringList($snapshot['missing_information'] ?? []);

        $parsedResponse['follow_up_questions'] = $this->filterSensitiveAndRepeatedItems(
            $parsedResponse['follow_up_questions'] ?? [],
            $askedQuestions,
            true,
        );
        $parsedResponse['missing_information'] = $this->filterSensitiveAndRepeatedItems(
            $parsedResponse['missing_information'] ?? [],
            $previousMissingInformation,
            false,
        );

        if (($parsedResponse['diagnoses'] ?? []) !== []) {
            $parsedResponse['stage'] = 'diagnostic_refinement';
        }

        return $parsedResponse;
    }

    /**
     * @param array<string, mixed> $parsedResponse
     * @param array<int, array{role:string,content:string}> $history
     * @param array<string, mixed>|null $snapshot
     */
    private function shouldForceDiagnosticRefinement(
        Conversation $conversation,
        array $history,
        string $userMessage,
        array $parsedResponse,
        ?array $snapshot,
    ): bool {
        if (($parsedResponse['diagnoses'] ?? []) !== []) {
            return false;
        }

        if (($parsedResponse['stage'] ?? 'anamnesis') !== 'anamnesis') {
            return false;
        }

        $existingTurns = (int) ($snapshot['anamnesis_turns'] ?? 0);
        $hasEnoughClinicalData = $this->hasEnoughClinicalData($history, $userMessage);
        $isLooping = ($parsedResponse['follow_up_questions'] ?? []) === []
            || $this->containsOnlySensitiveMissingInformation($parsedResponse['missing_information'] ?? []);

        return $hasEnoughClinicalData && ($existingTurns >= self::MAX_ANAMNESIS_TURNS_BEFORE_FORCE || $isLooping);
    }

    /**
     * @param array<int, array{role:string,content:string}> $history
     */
    private function hasEnoughClinicalData(array $history, string $userMessage): bool
    {
        $userTexts = [];

        foreach ($history as $message) {
            if (($message['role'] ?? '') === 'user') {
                $userTexts[] = (string) ($message['content'] ?? '');
            }
        }

        $userTexts[] = $userMessage;
        $combined = mb_strtolower(implode(' ', $userTexts));

        $symptomTerms = [
            'dor', 'febre', 'tosse', 'vomito', 'vômito', 'nausea', 'náusea', 'fadiga', 'fraqueza',
            'falta de ar', 'dispneia', 'diarreia', 'dor de cabeca', 'dor de cabeça', 'dor abdominal',
            'olho', 'olhos', 'barriga', 'calafrio', 'mal estar', 'mal-estar',
        ];
        $timelineTerms = [
            'dia', 'dias', 'semana', 'semanas', 'mes', 'mês', 'meses', 'hoje', 'ontem', 'desde', 'ha ', 'há ',
        ];

        $symptomHits = 0;

        foreach ($symptomTerms as $term) {
            if (str_contains($combined, $term)) {
                $symptomHits++;
            }
        }

        $hasTimeline = false;

        foreach ($timelineTerms as $term) {
            if (str_contains($combined, $term)) {
                $hasTimeline = true;
                break;
            }
        }

        return $symptomHits >= 2 && $hasTimeline;
    }

    /**
     * @param array<int, string> $items
     * @param array<int, string> $previousItems
     * @return array<int, string>
     */
    private function filterSensitiveAndRepeatedItems(array $items, array $previousItems, bool $ensureQuestionMark): array
    {
        $filtered = [];
        $normalizedPreviousItems = array_map(fn (string $item): string => $this->normalizeText($item), $previousItems);

        foreach ($items as $item) {
            $value = trim((string) $item);

            if ($value === '' || $this->containsSensitiveTerm($value)) {
                continue;
            }

            if ($ensureQuestionMark && !str_ends_with($value, '?')) {
                $value .= '?';
            }

            if (in_array($this->normalizeText($value), $normalizedPreviousItems, true)) {
                continue;
            }

            $filtered[] = $value;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @param array<int, string> $missingInformation
     */
    private function containsOnlySensitiveMissingInformation(array $missingInformation): bool
    {
        if ($missingInformation === []) {
            return true;
        }

        foreach ($missingInformation as $item) {
            if (!$this->containsSensitiveTerm($item)) {
                return false;
            }
        }

        return true;
    }

    private function containsSensitiveTerm(string $value): bool
    {
        $normalized = $this->normalizeText($value);

        foreach ($this->sensitiveTerms as $term) {
            if (str_contains($normalized, $this->normalizeText($term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $items
     * @return array<int, string>
     */
    private function normalizeSnapshotStringList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($item): string => trim((string) $item), $items)));
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}