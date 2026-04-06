<?php

namespace App\Services;

class OllamaPromptBuilder
{
    /**
     * @param array<string, mixed>|null $clinicalSnapshot
     */
    public function buildOllamaSystemInstruction(string $stage = 'anamnesis', ?array $clinicalSnapshot = null): string
    {
        $snapshotContext = $this->buildSnapshotContext($clinicalSnapshot);

        return 'Seu comportamento principal ja e definido pelo Modelfile deste modelo. '
            . 'Use esta instrucao apenas como orientacao leve, sem contradizer o Modelfile. '
            . 'Voce esta em um fluxo clinico iterativo baseado em anamnese, contexto RAG e refinamento diagnostico. '
            . 'Baseie-se prioritariamente no contexto recuperado de dataset_records quando ele for pertinente ao caso, sem inventar fatos que nao estejam no contexto ou na conversa. '
            . 'A conversa atual esta no estagio: ' . $stage . '. '
            . $snapshotContext
            . 'Sua resposta deve ser exclusivamente um JSON valido, sem markdown e sem bloco de codigo, usando exatamente este schema: '
            . '{"stage":"anamnesis|diagnostic_refinement","summary":"string","missing_information":["string"],"follow_up_questions":["string"],"diagnoses":[{"hypothesis":"string","certainty":"alta|media|baixa","rationale":"string","supporting_evidence":["string"],"warning_signs":["string"],"next_steps":["string"]}],"answer":"string"}. '
            . 'Regras clinicas: primeiro faca anamnese ativa quando houver lacunas criticas. Assim que houver informacao minima suficiente, entregue exatamente ate 3 hipoteses diagnosticas ordenadas da mais provavel para a menos provavel, cada uma com certeza alta, media ou baixa, e continue fazendo perguntas de alto impacto para refinar as hipoteses. '
            . 'Mesmo no estagio diagnostic_refinement, continue perguntando para reduzir incerteza sempre que uma resposta adicional puder mudar o ranking ou a certeza. '
            . 'Se ainda nao houver base minima para hipoteses confiaveis, use stage=anamnesis e deixe diagnoses vazio. '
            . 'Quando houver base minima, use stage=diagnostic_refinement e preencha diagnoses com no maximo 3 itens. '
            . 'Use perguntas curtas, objetivas e clinicamente relevantes. '
            . 'No campo answer, escreva uma resposta clara em portugues do Brasil para ser mostrada diretamente ao usuario.';
    }

    /**
     * @param array<string, mixed>|null $clinicalSnapshot
     */
    private function buildSnapshotContext(?array $clinicalSnapshot): string
    {
        if (!is_array($clinicalSnapshot) || $clinicalSnapshot === []) {
            return 'Nao ha resumo clinico estruturado previo para esta conversa. ';
        }

        $summary = trim((string) ($clinicalSnapshot['summary'] ?? ''));
        $missingInformation = $clinicalSnapshot['missing_information'] ?? [];
        $followUpQuestions = $clinicalSnapshot['follow_up_questions'] ?? [];
        $diagnoses = $clinicalSnapshot['diagnoses'] ?? [];

        return 'Estado clinico estruturado anterior: '
            . json_encode([
                'summary' => $summary,
                'missing_information' => is_array($missingInformation) ? array_values($missingInformation) : [],
                'follow_up_questions' => is_array($followUpQuestions) ? array_values($followUpQuestions) : [],
                'diagnoses' => is_array($diagnoses) ? array_values($diagnoses) : [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '. ';
    }
}
