<?php

namespace App\Services;

use App\Models\Categorie;
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
        Analyse cette image (reçu, ticket de caisse, ou relevé listant plusieurs transactions) LIGNE PAR LIGNE.

        RÈGLES IMPORTANTES :
        - Chaque ligne de transaction distincte (achat, retrait, dépôt, paiement...) doit devenir un objet séparé dans "transactions".
        - N'ADDITIONNE JAMAIS plusieurs lignes entre elles et ne renvoie JAMAIS un seul "total général" à la place du détail : si le document liste plusieurs transactions, chacune doit ressortir individuellement avec son propre montant et sa propre date.
        - N'utilise le "total" du document comme montant que s'il n'y a réellement qu'UNE seule transaction sur tout le document.
        - Si une seule transaction est présente, renvoie un seul objet et "multiples_transactions": false.
        - Si plusieurs transactions/lignes sont présentes, renvoie un objet par ligne et "multiples_transactions": true.

        Réponds au format JSON STRICT, sans texte additionnel, sans balises markdown :
                {
                    "multiples_transactions": <true|false>,
                    "transactions": [
                        {
                            "montant": <nombre ou null si illisible>,
                            "date": "<YYYY-MM-DD ou null si illisible>",
                            "description": "<nom du commerce ou nature de l'achat, ou null>",
                            "categorie_suggeree": "<une des valeurs suivantes : Alimentation, Transport, Logement, Santé, Éducation, Loisirs, Vêtements, Factures & Abonnements, Communication (crédit, internet), Famille & Dons, Autres dépenses>",
                            "type": "<'revenu'|'depense'|null>"
                        }
                    ]
                }
                Si une information n'est pas lisible ou absente, mets null pour ce champ.
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
     * Analyse un texte libre décrivant une transaction et en extrait les données structurées.
     * Utilise le même pipeline (callGenerateContent + parseJsonResponse) que les autres
     * méthodes d'extraction, pour bénéficier de la même gestion d'erreur et des mêmes logs.
     */
    public function extraireDonnees(string $texte): array
    {
        $categories = Categorie::pluck('nom')->implode(', ');

        $prompt = <<<PROMPT
        Analyse ce texte décrivant une ou plusieurs transactions financières, LIGNE PAR LIGNE / MENTION PAR MENTION.
        Texte: "{$texte}"

        Catégories disponibles: {$categories}

        RÈGLES IMPORTANTES :
        - Chaque transaction distincte mentionnée dans le texte doit devenir un objet séparé dans "transactions".
        - N'ADDITIONNE JAMAIS plusieurs montants entre eux pour renvoyer un seul total : si le texte mentionne plusieurs achats/paiements/revenus, chacun doit ressortir individuellement avec son propre montant et sa propre date.
        - Si une seule transaction est décrite, renvoie un seul objet et "multiples_transactions": false.
        - Si plusieurs transactions sont décrites, renvoie un objet par transaction et "multiples_transactions": true.

                Réponds UNIQUEMENT en JSON valide, sans texte autour, avec ce format exact. Retourne systématiquement les champs `multiples_transactions` (boolean) et `transactions` (array d'objets) ; si une seule transaction est détectée, `transactions` contiendra un seul objet.
                Exemple minimal : {"multiples_transactions": false, "transactions": [{"montant": 12.5, "date": "2026-01-01", "description": "...", "type": "depense", "categorie_suggeree": "Alimentation"}]}
        PROMPT;

        $response = $this->callGenerateContent([
            [
                'parts' => [['text' => $prompt]],
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
        Écoute cet enregistrement audio où une personne décrit une ou plusieurs transactions financières (dépense ou revenu).

        RÈGLES IMPORTANTES :
        - Traite chaque transaction distincte mentionnée dans l'audio, DANS L'ORDRE où elle est décrite, comme un objet séparé dans "transactions".
        - N'ADDITIONNE JAMAIS plusieurs montants entre eux pour renvoyer un seul total : si la personne décrit plusieurs achats/paiements/revenus, chacun doit ressortir individuellement avec son propre montant et sa propre date.
        - Si une seule transaction est décrite, renvoie un seul objet et "multiples_transactions": false.
        - Si plusieurs transactions sont décrites, renvoie un objet par transaction et "multiples_transactions": true.

        Réponds au format JSON STRICT, sans texte additionnel, sans balises markdown :
                {
                    "multiples_transactions": <true|false>,
                    "transactions": [
                        {
                            "montant": <nombre ou null si illisible>,
                            "date": "<YYYY-MM-DD ou null si non mentionnée>",
                            "description": "<nature de l'achat/du revenu, ou null>",
                            "categorie_suggeree": "<une des valeurs suivantes : Alimentation, Transport, Logement, Santé, Éducation, Loisirs, Vêtements, Factures & Abonnements, Communication (crédit, internet), Famille & Dons, Autres dépenses>",
                            "type": "<'revenu'|'depense'|null>"
                        }
                    ]
                }
                Si une information n'est pas compréhensible ou absente, mets null pour ce champ.
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

        La devise de l'utilisateur est le Franc CFA (FCFA). Exprime TOUJOURS les montants en FCFA
        (jamais en euros, en €, ni en dollars), en utilisant le format "12 000 FCFA".

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
     * Point d'entrée UNIQUE pour tous les appels Gemini du service — garantit que
     * toute méthode d'extraction bénéficie de la même gestion d'erreur et des mêmes logs.
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
                'model' => $this->model,
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

            return ['multiples_transactions' => false, 'transactions' => []];
        }

        // Normalise la structure pour garantir les clés attendues
        if (! isset($donnees['multiples_transactions'])) {
            $donnees['multiples_transactions'] = false;
        }

        if (! isset($donnees['transactions'])) {
            // Si la réponse contient les champs d'une transaction unique, convertit en tableau
            if (isset($donnees['montant']) || isset($donnees['type']) || isset($donnees['categorie_suggeree'])) {
                $donnees = [
                    'multiples_transactions' => false,
                    'transactions' => [
                        [
                            'montant' => $donnees['montant'] ?? null,
                            'date' => $donnees['date'] ?? null,
                            'description' => $donnees['description'] ?? null,
                            'type' => $donnees['type'] ?? null,
                            'categorie_suggeree' => $donnees['categorie_suggeree'] ?? null,
                        ],
                    ],
                ];
            } else {
                $donnees['transactions'] = [];
            }
        }

        return $donnees;
    }

    protected function formatContexteFinancier(array $contexte): string
    {
        $champsMonetaires = [
            'solde_actuel',
            'solde_initial',
            'total_revenus_ce_mois',
            'total_depenses_ce_mois',
        ];

        return collect($contexte)
            ->map(function ($valeur, $cle) use ($champsMonetaires) {
                if (in_array($cle, $champsMonetaires, true)) {
                    $valeur = number_format((float) $valeur, 0, ',', ' ').' FCFA';
                }

                return "- {$cle} : {$valeur}";
            })
            ->implode("\n");
    }
}