<?php

namespace Tests\Feature;

use App\Exceptions\AiProviderException;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ChatFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_start_chat_and_receive_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andReturn(json_encode([
                'stage' => 'diagnostic_refinement',
                'summary' => 'quadro compativel com cefaleia sem sinais de alarme imediatos',
                'missing_information' => ['duracao da dor'],
                'follow_up_questions' => ['Ha quanto tempo a dor comecou?', 'Ha febre associada?'],
                'diagnoses' => [
                    [
                        'hypothesis' => 'Cefaleia tensional',
                        'certainty' => 'alta',
                        'rationale' => 'Dor de cabeca isolada sem sinais de alarme descritos.',
                        'supporting_evidence' => ['cefaleia'],
                        'warning_signs' => ['rigidez de nuca'],
                        'next_steps' => ['avaliar padrao da dor'],
                    ],
                    [
                        'hypothesis' => 'Sinusite',
                        'certainty' => 'media',
                        'rationale' => 'Pode cursar com dor facial e cefaleia.',
                        'supporting_evidence' => ['dor de cabeca'],
                        'warning_signs' => [],
                        'next_steps' => ['investigar congestao nasal'],
                    ],
                    [
                        'hypothesis' => 'Enxaqueca',
                        'certainty' => 'baixa',
                        'rationale' => 'Ainda faltam caracteristicas tipicas.',
                        'supporting_evidence' => [],
                        'warning_signs' => [],
                        'next_steps' => ['avaliar fotofobia'],
                    ],
                ],
                'answer' => 'Ja consigo propor tres hipoteses iniciais e ainda preciso refinar alguns pontos.',
            ], JSON_UNESCAPED_UNICODE));
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Estou com dor de cabeca',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'stage' => 'diagnostic_refinement',
                ],
            ]);

        $response->assertJsonPath('data.diagnoses.0.hypothesis', 'Cefaleia tensional');
        $response->assertJsonPath('data.diagnoses.0.certainty', 'alta');
        $response->assertJsonCount(3, 'data.diagnoses');
        $response->assertJsonCount(2, 'data.follow_up_questions');

        $conversationId = $response->json('data.conversation_id');
        $this->assertNotNull($conversationId);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => 'Estou com dor de cabeca',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'role' => 'model',
            'content' => $response->json('data.response'),
        ]);
    }

    public function test_empty_conversation_id_creates_new_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Existing conversation should not be auto-continued
        Conversation::create([
            'user_id' => $user->id,
            'title' => 'Conversa anterior',
            'last_message_at' => now(),
        ]);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Nova conversa criada');
        $this->app->instance(OllamaService::class, $ollamaMock);

        // Sending message without conversation_id should create a new conversation
        $response = $this->postJson('/api/chat', [
            'message' => 'Quero iniciar um novo assunto',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200);

        // Should have 2 conversations: the old one and the new one
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_camel_case_conversation_id_reuses_existing_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Conversa em andamento',
            'last_message_at' => now(),
        ]);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andReturn('Resposta na mesma conversa');
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Mensagem de continuidade',
            'conversationId' => $conversation->id,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->assertDatabaseCount('conversations', 1);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Mensagem de continuidade',
        ]);
    }

    public function test_user_cannot_send_message_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Conversa privada',
            'last_message_at' => now(),
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Mensagem original',
        ]);

        Sanctum::actingAs($intruder);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldNotReceive('generateResponse');
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Tentando invadir',
            'conversation_id' => $conversation->id,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(404);
    }

    public function test_returns_error_when_ai_provider_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->once()
            ->andThrow(new AiProviderException('Erro na API de IA.', 504));
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Teste com falha upstream',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(504)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Erro na API de IA.');
    }

    public function test_conversation_keeps_refining_diagnoses_across_multiple_turns(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->twice()
            ->andReturn(
                json_encode([
                    'stage' => 'anamnesis',
                    'summary' => 'dor abdominal em investigacao inicial',
                    'missing_information' => ['tempo de evolucao', 'presenca de febre'],
                    'follow_up_questions' => ['Ha quanto tempo a dor comecou?', 'Ha febre?', 'Teve vomitos?'],
                    'diagnoses' => [],
                    'answer' => 'Ainda faltam dados clinicos importantes antes de eu ranquear hipoteses com mais seguranca.',
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'stage' => 'diagnostic_refinement',
                    'summary' => 'dor abdominal com febre e vomitos, mais sugestiva de infeccao intra-abdominal',
                    'missing_information' => ['localizacao exata da dor'],
                    'follow_up_questions' => ['A dor fica mais no lado direito inferior do abdome?', 'Consegue comer e beber normalmente?'],
                    'diagnoses' => [
                        [
                            'hypothesis' => 'Apendicite',
                            'certainty' => 'alta',
                            'rationale' => 'Febre, vomitos e dor abdominal progressiva aumentam a suspeita.',
                            'supporting_evidence' => ['febre', 'vomitos', 'dor abdominal'],
                            'warning_signs' => ['dor intensa progressiva', 'abdome rigido'],
                            'next_steps' => ['avaliacao medica urgente'],
                        ],
                        [
                            'hypothesis' => 'Gastroenterite infecciosa',
                            'certainty' => 'media',
                            'rationale' => 'Vomitos e febre tambem podem ocorrer em infeccao gastrointestinal.',
                            'supporting_evidence' => ['vomitos', 'febre'],
                            'warning_signs' => ['desidratacao'],
                            'next_steps' => ['manter hidratacao'],
                        ],
                        [
                            'hypothesis' => 'Infeccao urinaria complicada',
                            'certainty' => 'baixa',
                            'rationale' => 'Ainda faltam sintomas urinarios para sustentar melhor essa hipotese.',
                            'supporting_evidence' => [],
                            'warning_signs' => ['queda do estado geral'],
                            'next_steps' => ['investigar sintomas urinarios'],
                        ],
                    ],
                    'answer' => 'Ja consigo priorizar tres hipoteses, mas ainda preciso refinar a localizacao e a progressao da dor.',
                ], JSON_UNESCAPED_UNICODE),
            );
        $this->app->instance(OllamaService::class, $ollamaMock);

        $firstResponse = $this->postJson('/api/chat', [
            'message' => 'Estou com dor abdominal',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $firstResponse->assertStatus(200)
            ->assertJsonPath('data.stage', 'anamnesis')
            ->assertJsonCount(0, 'data.diagnoses')
            ->assertJsonCount(3, 'data.follow_up_questions');

        $conversationId = $firstResponse->json('data.conversation_id');

        $secondResponse = $this->postJson('/api/chat', [
            'message' => 'A dor comecou ontem, tenho febre e vomitei duas vezes',
            'conversation_id' => $conversationId,
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $secondResponse->assertStatus(200)
            ->assertJsonPath('data.stage', 'diagnostic_refinement')
            ->assertJsonPath('data.diagnoses.0.hypothesis', 'Apendicite')
            ->assertJsonPath('data.diagnoses.0.certainty', 'alta')
            ->assertJsonCount(3, 'data.diagnoses')
            ->assertJsonCount(2, 'data.follow_up_questions');

        $conversation = Conversation::findOrFail($conversationId);

        $this->assertSame('diagnostic_refinement', $conversation->clinical_stage);
        $this->assertSame('Apendicite', $conversation->clinical_snapshot['diagnoses'][0]['hypothesis']);
        $this->assertSame('localizacao exata da dor', $conversation->clinical_snapshot['missing_information'][0]);
    }

    public function test_chat_breaks_sensitive_data_loop_and_forces_diagnostic_refinement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $ollamaMock = Mockery::mock(OllamaService::class);
        $ollamaMock->shouldReceive('generateResponse')
            ->twice()
            ->andReturn(
                json_encode([
                    'stage' => 'anamnesis',
                    'summary' => 'dor nos olhos, dor de cabeca, febre e dor abdominal ha 3 dias',
                    'missing_information' => ['Idade', 'Genero'],
                    'follow_up_questions' => ['Qual a sua idade?', 'Qual o seu genero?'],
                    'diagnoses' => [],
                    'answer' => 'Preciso da sua idade e genero para continuar.',
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'stage' => 'diagnostic_refinement',
                    'summary' => 'quadro infeccioso sistemico com cefaleia, dor ocular, febre e dor abdominal ha 3 dias',
                    'missing_information' => ['fotofobia', 'vomitos'],
                    'follow_up_questions' => ['Voce percebe piora com luz forte?', 'Teve vomitos ou rigidez na nuca?'],
                    'diagnoses' => [
                        [
                            'hypothesis' => 'Dengue',
                            'certainty' => 'media',
                            'rationale' => 'Febre, cefaleia e dor ocular sao compativeis com quadro viral sistemico.',
                            'supporting_evidence' => ['febre', 'dor nos olhos', 'dor de cabeca'],
                            'warning_signs' => ['sangramentos', 'queda do estado geral'],
                            'next_steps' => ['hidratar', 'avaliar sinais de alarme'],
                        ],
                        [
                            'hypothesis' => 'Virose inespecifica',
                            'certainty' => 'media',
                            'rationale' => 'A combinacao de febre e dor abdominal pode ocorrer em sindromes virais.',
                            'supporting_evidence' => ['febre', 'dor abdominal'],
                            'warning_signs' => ['desidratacao'],
                            'next_steps' => ['observar evolucao'],
                        ],
                        [
                            'hypothesis' => 'Meningite viral ou bacteriana',
                            'certainty' => 'baixa',
                            'rationale' => 'Dor ocular e cefaleia com febre exigem exclusao se houver sinais neurologicos.',
                            'supporting_evidence' => ['cefaleia', 'febre'],
                            'warning_signs' => ['rigidez de nuca', 'confusao mental'],
                            'next_steps' => ['avaliacao urgente se houver sinais de alarme'],
                        ],
                    ],
                    'answer' => 'Ja existem dados clinicos suficientes para levantar hipoteses iniciais sem depender de dados pessoais sensiveis.',
                ], JSON_UNESCAPED_UNICODE),
            );
        $this->app->instance(OllamaService::class, $ollamaMock);

        $response = $this->postJson('/api/chat', [
            'message' => 'Estou com muita dor nos olhos, dor de cabeca, febre e dor de barriga ha 3 dias',
        ], [
            'X-API-KEY' => env('APP_API_KEY'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stage', 'diagnostic_refinement')
            ->assertJsonPath('data.diagnoses.0.hypothesis', 'Dengue');

        $this->assertNotContains('Qual a sua idade?', $response->json('data.follow_up_questions', []));
        $this->assertNotContains('Qual o seu genero?', $response->json('data.follow_up_questions', []));

        $this->assertStringNotContainsString('idade', mb_strtolower((string) $response->json('data.response')));
        $this->assertStringNotContainsString('genero', mb_strtolower((string) $response->json('data.response')));
    }
}
