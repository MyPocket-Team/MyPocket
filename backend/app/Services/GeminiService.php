<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash-lite');
    }

    /**
     * Analyse une image de reçu et extrait montant, date, catégorie suggérée.
     * Retourne un tableau structuré ; les champs non détectés sont à null.
     */
    public function extraireDonneesRecu(string $imageBase64, string $mimeType = 'image/jpeg'): array
    {
        $prompt = <<<PROMPT
        Analyse ce reçu/ticket de caisse et extrait les informations suivantes au format JSON STRICT, sans texte additionnel, sans balises markdown :
        {
          "montant": <nombre ou null si illisible>,
          "date": "<YYYY-MM-DD ou null si illisible>",
          "description": "<nom du commerce ou nature de l'achat, ou null>",
          "categorie_suggeree": "<une des valeurs suivantes : Alimentation, Transport, Logement, Santé, Éducation, Loisirs, Vêtements, Factures & Abonnements, Communication (crédit, internet), Famille & Dons, Autres dépenses>"
        }
        Si une information n'est pas lisible ou absente, mets null pour ce champ (sauf categorie_suggeree qui doit toujours avoir une valeur, "Autres dépenses" par défaut).
        PROMPT;

        $response = $this->callGenerateContent([
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $imageBase64,
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseJsonResponse($response);
    }

    /**
     * Transcrit un enregistrement audio et en extrait les données de transaction.
     */
    public function extraireDonneesAudio(string $audioBase64, string $mimeType = 'audio/mp3'): array
    {
        $prompt = <<<PROMPT
        Écoute cet enregistrement audio où une personne décrit une transaction financière (dépense ou revenu).
        Extrait les informations au format JSON STRICT, sans texte additionnel, sans balises markdown :
        {
          "montant": <nombre ou null si non mentionné/compris>,
          "date": "<YYYY-MM-DD ou null ; si la personne dit 'aujourd'hui' utilise la date du jour>",
          "description": "<résumé court de la transaction, ou null>",
          "type": "<'revenu' ou 'depense' selon le contexte>",
          "categorie_suggeree": "<une catégorie plausible en français>"
        }
        PROMPT;

        $response = $this->callGenerateContent([
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $audioBase64,
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseJsonResponse($response);
    }

    /**
     * Génère une réponse du chatbot financier à partir de l'historique de conversation
     * et d'un résumé agrégé (anonymisé) des finances de l'utilisateur.
     *
     * @param  array  $historiqueMessages  [['role' => 'user'|'model', 'text' => '...'], ...]
     * @param  array  $contexteFinancier  Données agrégées uniquement (jamais le détail brut)
     */
    public function reponseChatbot(array $historiqueMessages, array $contexteFinancier): string
    {
        $systemPrompt = <<<PROMPT
        Tu es l'assistant financier de MyPocket. Tu donnes des conseils personnalisés et bienveillants
        basés UNIQUEMENT sur les données agrégées fournies ci-dessous (jamais de comparaison avec d'autres utilisateurs).
        Reste concis, concret, et évite le jargon financier complexe.

        Contexte financier agrégé de l'utilisateur :
        {$this->formatContexteFinancier($contexteFinancier)}
        PROMPT;

        $contents = array_map(fn ($m) => [
            'role' => $m['role'],
            'parts' => [['text' => $m['text']]],
        ], $historiqueMessages);

        $response = $this->callGenerateContent($contents, $systemPrompt);

        return $this->extraireTexte($response);
    }

    /**
     * Appel générique à l'endpoint generateContent.
     */
    protected function callGenerateContent(array $contents, ?string $systemInstruction = null): array
    {
        $payload = ['contents' => $contents];

        if ($systemInstruction) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/{$this->model}:generateContent", $payload);

        if ($response->failed()) {
            Log::error('Erreur appel Gemini API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Échec de l'appel à l'API Gemini : {$response->status()}");
        }

        return $response->json();
    }

    protected function extraireTexte(array $response): string
    {
        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Parse la réponse JSON renvoyée par Gemini (en nettoyant d'éventuelles balises markdown).
     */
    protected function parseJsonResponse(array $response): array
    {
        $texte = $this->extraireTexte($response);
        $texte = trim(str_replace(['```json', '```'], '', $texte));

        $donnees = json_decode($texte, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Réponse Gemini non parsable en JSON', ['texte' => $texte]);

            return [];
        }

        return $donnees;
    }

    protected function formatContexteFinancier(array $contexte): string
    {
        return collect($contexte)
            ->map(fn ($valeur, $cle) => "- {$cle} : {$valeur}")
            ->implode("\n");
    }
}
