<?php

namespace App\Services;

class ClinicalResponseParser
{
    /**
     * @return array{
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>,
     *     answer:string,
     *     raw_response:string
     * }
     */
    public function parse(?string $rawResponse): array
    {
        $rawResponse = trim((string) $rawResponse);

        if ($rawResponse === '') {
            return $this->emptyPayload();
        }

        $decoded = $this->decodeJsonPayload($rawResponse);

        if (is_array($decoded)) {
            return $this->normalizePayload($decoded, $rawResponse);
        }

        return $this->buildFallbackPayload($rawResponse);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>,
     *     answer:string,
     *     raw_response:string
     * }
     */
    private function normalizePayload(array $payload, string $rawResponse): array
    {
        $diagnoses = $this->normalizeDiagnoses($payload['diagnoses'] ?? []);
        $followUpQuestions = $this->normalizeStringList($payload['follow_up_questions'] ?? [], 5, true);
        $missingInformation = $this->normalizeStringList($payload['missing_information'] ?? [], 8, false);
        $summary = trim((string) ($payload['summary'] ?? ''));
        $answer = trim((string) ($payload['answer'] ?? ''));
        $stage = $this->normalizeStage($payload['stage'] ?? null, $diagnoses);

        return [
            'stage' => $stage,
            'summary' => $summary,
            'missing_information' => $missingInformation,
            'follow_up_questions' => $followUpQuestions,
            'diagnoses' => $diagnoses,
            'answer' => $answer,
            'raw_response' => $rawResponse,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(string $rawResponse): ?array
    {
        $candidates = [
            $rawResponse,
            trim(preg_replace('/^```(?:json)?|```$/m', '', $rawResponse) ?? ''),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param mixed $stage
     * @param array<int, array<string, mixed>> $diagnoses
     */
    private function normalizeStage(mixed $stage, array $diagnoses): string
    {
        $stage = strtolower(trim((string) $stage));

        if ($stage === 'diagnostic_refinement' || $stage === 'anamnesis') {
            return $stage;
        }

        return $diagnoses !== [] ? 'diagnostic_refinement' : 'anamnesis';
    }

    /**
     * @param mixed $items
     * @return array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>
     */
    private function normalizeDiagnoses(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $diagnoses = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $hypothesis = trim((string) ($item['hypothesis'] ?? $item['diagnosis'] ?? $item['name'] ?? ''));

            if ($hypothesis === '') {
                continue;
            }

            $diagnoses[] = [
                'hypothesis' => $hypothesis,
                'certainty' => $this->normalizeCertainty($item['certainty'] ?? $item['confidence'] ?? null),
                'rationale' => trim((string) ($item['rationale'] ?? $item['justification'] ?? '')),
                'supporting_evidence' => $this->normalizeStringList($item['supporting_evidence'] ?? $item['evidence'] ?? [], 5, false),
                'warning_signs' => $this->normalizeStringList($item['warning_signs'] ?? $item['red_flags'] ?? [], 5, false),
                'next_steps' => $this->normalizeStringList($item['next_steps'] ?? $item['conduct'] ?? [], 5, false),
            ];
        }

        return array_slice($diagnoses, 0, 3);
    }

    private function normalizeCertainty(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            'alta', 'high', 'alto' => 'alta',
            'baixa', 'low', 'baixo' => 'baixa',
            default => 'media',
        };
    }

    /**
     * @param mixed $items
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $items, int $limit, bool $ensureQuestionMark): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            $value = trim((string) $item);

            if ($value === '') {
                continue;
            }

            if ($ensureQuestionMark && !str_ends_with($value, '?')) {
                $value .= '?';
            }

            $normalized[] = $value;
        }

        return array_slice(array_values(array_unique($normalized)), 0, $limit);
    }

    /**
     * @return array{
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>,
     *     answer:string,
     *     raw_response:string
     * }
     */
    private function buildFallbackPayload(string $rawResponse): array
    {
        $questions = [];

        foreach (preg_split('/\R+/', $rawResponse) ?: [] as $line) {
            $line = trim($line, " \t\n\r\0\x0B-*0123456789.)");

            if ($line !== '' && str_contains($line, '?')) {
                $questions[] = str_ends_with($line, '?') ? $line : $line . '?';
            }
        }

        $questions = array_slice(array_values(array_unique($questions)), 0, 5);

        return [
            'stage' => $questions !== [] ? 'anamnesis' : 'diagnostic_refinement',
            'summary' => mb_substr($rawResponse, 0, 280),
            'missing_information' => [],
            'follow_up_questions' => $questions,
            'diagnoses' => [],
            'answer' => $rawResponse,
            'raw_response' => $rawResponse,
        ];
    }

    /**
     * @return array{
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>,
     *     answer:string,
     *     raw_response:string
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'stage' => 'anamnesis',
            'summary' => '',
            'missing_information' => [],
            'follow_up_questions' => [],
            'diagnoses' => [],
            'answer' => '',
            'raw_response' => '',
        ];
    }
}