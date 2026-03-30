<?php

namespace App\Services;

class OllamaPromptBuilder
{
    public function buildOllamaSystemInstruction(): string
    {
        return 'Seu comportamento principal ja e definido pelo Modelfile deste modelo. '
            . 'Use esta instrucao apenas como orientacao leve de resposta. '
            . 'Em casos clinicos, priorize entregar uma avaliacao util ao usuario com ate 3 diagnosticos ou hipoteses diagnosticas possiveis, '
            . 'uma justificativa breve para cada uma, conduta inicial e sinais de alerta. '
            . 'Se faltarem dados, voce pode fazer perguntas curtas para refinar o caso, mas evite prolongar a conversa quando ja for possivel '
            . 'oferecer hipoteses iniciais com nivel de confianca apropriado. '
            . 'Nao contradiga o Modelfile e nao invente informacoes nao fornecidas pelo usuario.';
    }
}
