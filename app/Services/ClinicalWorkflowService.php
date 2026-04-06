<?php

namespace App\Services;

use App\Models\Conversation;

class ClinicalWorkflowService
{
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
        $datasetContext = $this->datasetContextService->buildContext(30, $userMessage, 25);
        $systemInstruction = $this->promptBuilder->buildOllamaSystemInstruction(
            (string) ($conversation->clinical_stage ?: 'anamnesis'),
            is_array($conversation->clinical_snapshot) ? $conversation->clinical_snapshot : null,
        );

        $rawResponse = $this->ollamaService->generateResponse(
            $userMessage,
            $history,
            $datasetContext,
            $systemInstruction,
        );

        $parsedResponse = $this->clinicalResponseParser->parse($rawResponse);
        $formattedResponse = $this->clinicalResponseFormatter->format($parsedResponse);

        $conversation->forceFill([
            'clinical_stage' => $parsedResponse['stage'],
            'clinical_snapshot' => [
                'summary' => $parsedResponse['summary'],
                'missing_information' => $parsedResponse['missing_information'],
                'follow_up_questions' => $parsedResponse['follow_up_questions'],
                'diagnoses' => $parsedResponse['diagnoses'],
            ],
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
}