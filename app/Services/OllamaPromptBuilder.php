<?php

namespace App\Services;

class OllamaPromptBuilder
{
    public function buildOllamaSystemInstruction(): string
    {
        return 'Seu comportamento principal ja e definido pelo Modelfile deste modelo. '
            . 'Use esta instrucao apenas como orientacao leve de resposta. '
            . 'Em casos clinicos, trabalhe em duas fases. '
            . 'Fase 1 (obrigatoria quando houver informacao incompleta): faca anamnese ativa e priorize perguntas objetivas para refinar o caso. '
            . 'Nessa fase, nao entregue lista de diagnosticos finais; faca de 2 a 5 perguntas curtas e de alto impacto clinico por resposta. '
            . 'Fase 2 (somente quando houver dados suficientes): entregue ate 3 diagnosticos ou hipoteses diagnosticas possiveis, '
            . 'com justificativa breve para cada uma, classificacao de certeza (alta, media ou baixa), conduta inicial e sinais de alerta. '
            . 'Para cada hipotese, deixe explicito no texto o nivel de certeza usando exatamente os termos: alta, media ou baixa. '
            . 'Sempre explicite de forma curta se ja ha dados suficientes para avancar para as hipoteses. '
            . 'Se ainda faltar dado critico, continue perguntando antes de concluir. '
            . 'Nao contradiga o Modelfile e nao invente informacoes nao fornecidas pelo usuario.';
    }
}
