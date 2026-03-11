<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-flash-latest');
    }

    /**
     * Generate a response from Gemini.
     *
     * @param string $prompt
     * @param array $history
     * @param string $context
     * @return string|null
     */
    public function generateResponse(string $prompt, array $history = [], string $context = ''): ?string
    {
        $systemInstruction = "Você é um profissional de enfermagem altamente experiente, especializado em triagem e pronto-atendimento. 
Sua abordagem deve ser humana, acolhedora e extremamente profissional. 

DIRETRIZES DE COMUNICAÇÃO:
1. POSTURA PROFISSIONAL: Comporte-se com a autoridade de quem tem décadas de prática, mas NUNCA mencione explicitamente seus anos de carreira ou que você é 'sênior'. Deixe que sua competência e clareza falem por si.
2. CONHECIMENTO INTEGRADO: Use as informações abaixo como seu conhecimento clínico interno. JAMAIS mencione 'dataset', 'banco de dados', 'registros' ou 'base de dados' para o usuário. Fale como se esse conhecimento fosse fruto da sua própria experiência profissional (ex: 'É comum observarmos que...', 'Casos com esse perfil costumam apresentar...').
3. TRIAGEM HUMANA: Identifique o sintoma principal e faça perguntas investigativas naturais para entender melhor o quadro. Explique o motivo das perguntas de forma simples e cuidadosa.
4. FOCO NA SEGURANÇA: Se identificar sinais de alerta, seja firme na recomendação de auxílio médico presencial imediato.
5. SEM ALUCINAÇÕES: Mantenha-se fiel aos padrões de saúde reais e às informações fornecidas, evitando suposições infundadas.

REGRAS DE FORMATAÇÃO:
- Use **Markdown** para organizar a conversa (negritos, tópicos).
- Respostas limpas, sem excesso de formalidade robótica.

AVISO OBRIGATÓRIO: **Este é um projeto acadêmico de triagem assistida por IA. Não substitui consulta médica ou diagnóstico profissional.**

CONHECIMENTO CLÍNICO DE REFERÊNCIA (Para uso interno, não cite a origem):
" . $context;

        $contents = $history;
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $payload = [
            'contents' => $contents,
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ]
        ];

        try {
            $response = Http::withOptions([
                'verify' => false, // Bypass SSL certificate issues on common Windows environments
                'timeout' => 120,  // Increase total timeout to 120 seconds
                'connect_timeout' => 30, // Increase connection timeout to 30 seconds
            ])->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey, $payload);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text');
            }

            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $errorMsg = $response->json('error.message', 'Erro desconhecido na API do Gemini.');
            return "Erro na API: " . $errorMsg;

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            return "Erro na comunicação: " . $e->getMessage();
        }
    }
}
