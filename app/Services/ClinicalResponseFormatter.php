<?php

namespace App\Services;

class ClinicalResponseFormatter
{
    /**
     * @param array{
     *     stage:string,
     *     summary:string,
     *     missing_information:array<int, string>,
     *     follow_up_questions:array<int, string>,
     *     diagnoses:array<int, array{hypothesis:string,certainty:string,rationale:string,supporting_evidence:array<int, string>,warning_signs:array<int, string>,next_steps:array<int, string>}>,
     *     answer:string,
     *     raw_response:string
     * } $payload
     */
    public function format(array $payload): string
    {
        $lines = [];
        $summary = trim((string) ($payload['summary'] ?? ''));
        $answer = trim((string) ($payload['answer'] ?? ''));
        $diagnoses = $payload['diagnoses'] ?? [];
        $followUpQuestions = $payload['follow_up_questions'] ?? [];
        $missingInformation = $payload['missing_information'] ?? [];

        if ($summary !== '') {
            $lines[] = 'Resumo clinico atual: ' . $summary;
        }

        if ($diagnoses !== []) {
            $lines[] = 'Hipoteses diagnosticas mais provaveis:';

            foreach ($diagnoses as $index => $diagnosis) {
                $lines[] = ($index + 1) . '. ' . $diagnosis['hypothesis'] . ' (certeza: ' . $diagnosis['certainty'] . ')';

                if ($diagnosis['rationale'] !== '') {
                    $lines[] = 'Justificativa: ' . $diagnosis['rationale'];
                }

                if ($diagnosis['supporting_evidence'] !== []) {
                    $lines[] = 'Evidencias do caso: ' . implode('; ', $diagnosis['supporting_evidence']);
                }

                if ($diagnosis['warning_signs'] !== []) {
                    $lines[] = 'Sinais de alerta: ' . implode('; ', $diagnosis['warning_signs']);
                }

                if ($diagnosis['next_steps'] !== []) {
                    $lines[] = 'Conduta inicial: ' . implode('; ', $diagnosis['next_steps']);
                }
            }
        }

        if ($missingInformation !== []) {
            $lines[] = 'Dados que ainda faltam: ' . implode('; ', $missingInformation);
        }

        if ($followUpQuestions !== []) {
            $lines[] = 'Perguntas para refinar o caso:';

            foreach ($followUpQuestions as $index => $question) {
                $lines[] = ($index + 1) . '. ' . $question;
            }
        }

        if ($answer !== '') {
            array_unshift($lines, $answer);
        }

        $formatted = trim(implode("\n", $lines));

        return $formatted !== '' ? $formatted : trim((string) ($payload['raw_response'] ?? ''));
    }
}