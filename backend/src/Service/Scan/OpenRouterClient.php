<?php

declare(strict_types=1);

namespace App\Service\Scan;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenRouterClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeImage(string $imageBase64, ?string $typeHint = null): array
    {
        $imageBase64 = self::normalizeBase64($imageBase64);

        if (null === $this->apiKey || '' === $this->apiKey) {
            throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, 'Photo analysis is not configured. You can enter the item manually.');
        }

        $response = $this->httpClient->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'HTTP-Referer' => 'http://localhost:5173',
                'X-Title' => 'Penderie',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'reasoning' => ['enabled' => false],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<SYSTEM_PROMPT
You are a helpful assistant that identifies clothing and personal items from photos. 
Respond STRICTLY with a valid JSON object matching this exact schema. 
Do not add any text before, after, or around the JSON.
Use French for descriptive text. Use livre for books, vinyle for vinyl records and cd for CDs.
Only transcribe brand, author, artist, publisher or size when clearly readable in the photo.
Never guess these facts, material or condition: return an empty string when unknown.
For books use author and publisher, for music use artist and publisher; leave brand empty.

{
  "name": "string",
  "type": "pull|tshirt|chemise|blouse|polo|debardeur|sweat|gilet|veste|manteau|pantalon|jean|short|jupe|robe|combinaison|costume|sous_vetement|chaussettes|pyjama|maillot|sport|chaussure|sac|ceinture|chapeau|echarpe|gants|bijou|livre|vinyle|cd|decoration|electronique|meuble|jouet|autre",
  "category": "string",
  "brand": "string",
  "author": "string",
  "artist": "string",
  "publisher": "string",
  "color": "string",
  "size": "string",
  "material": "string",
  "condition": "neuf|comme_neuf|tres_bon|bon|a_reparer|abime",
  "confidence": 0.8
}
SYSTEM_PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => self::userPrompt($typeHint),
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:image/jpeg;base64,'.$imageBase64,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $content = $response->getContent();
        } catch (ExceptionInterface $exception) {
            if ($exception instanceof HttpExceptionInterface) {
                if (Response::HTTP_TOO_MANY_REQUESTS === $exception->getResponse()->getStatusCode()) {
                    throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, 'Photo analysis is temporarily rate-limited. Retry later or enter the item manually.', $exception);
                }
            }

            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'Photo analysis failed. You can retry later or enter the item manually.', $exception);
        }

        $data = json_decode($content, true);

        $choices = \is_array($data) ? ($data['choices'] ?? null) : null;
        $message = \is_array($choices) ? ($choices[0]['message']['content'] ?? null) : null;

        if (!\is_string($message)) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'Photo analysis returned invalid data. You can enter the item manually.');
        }

        $item = json_decode(trim($message), true);

        if (!\is_array($item)) {
            $fenced = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($message)) ?? '';
            $item = json_decode($fenced, true);

            if (!\is_array($item)) {
                throw new HttpException(Response::HTTP_BAD_GATEWAY, 'Photo analysis returned invalid data. You can enter the item manually.');
            }
        }

        return [
            'name' => \is_string($item['name'] ?? null) ? $item['name'] : '',
            'type' => \is_string($item['type'] ?? null) && \in_array($item['type'], ['pull', 'tshirt', 'chemise', 'blouse', 'polo', 'debardeur', 'sweat', 'gilet', 'veste', 'manteau', 'pantalon', 'jean', 'short', 'jupe', 'robe', 'combinaison', 'costume', 'sous_vetement', 'chaussettes', 'pyjama', 'maillot', 'sport', 'chaussure', 'sac', 'ceinture', 'chapeau', 'echarpe', 'gants', 'bijou', 'livre', 'vinyle', 'cd', 'decoration', 'electronique', 'meuble', 'jouet', 'autre'], true)
                ? $item['type']
                : 'autre',
            'category' => \is_string($item['category'] ?? null) ? $item['category'] : '',
            'author' => \is_string($item['author'] ?? null) ? mb_substr($item['author'], 0, 120) : '',
            'artist' => \is_string($item['artist'] ?? null) ? mb_substr($item['artist'], 0, 120) : '',
            'publisher' => \is_string($item['publisher'] ?? null) ? mb_substr($item['publisher'], 0, 120) : '',
            'brand' => \is_string($item['brand'] ?? null) ? $item['brand'] : '',
            'color' => \is_string($item['color'] ?? null) ? $item['color'] : '',
            'size' => \is_string($item['size'] ?? null) ? $item['size'] : '',
            'material' => \is_string($item['material'] ?? null) ? $item['material'] : '',
            'condition' => \is_string($item['condition'] ?? null) ? $item['condition'] : 'abime',
            'confidence' => (float) ($item['confidence'] ?? 0.0),
        ];
    }

    /**
     * Accepts raw base64 AND full data URLs (e.g. "data:image/jpeg;base64,/9j/…"
     * as sent by canvas.toDataURL()). OpenRouter wants exactly one prefix, so
     * any incoming prefix is stripped here before we add our own.
     */
    private static function normalizeBase64(string $input): string
    {
        $input = trim($input);

        if (str_contains($input, ',')) {
            $input = substr($input, (int) strrpos($input, ',') + 1);
        }

        return preg_replace('/\s+/', '', $input) ?? '';
    }

    private static function userPrompt(?string $typeHint): string
    {
        if (null === $typeHint) {
            return 'Identify the clothing item or object in this photo. If it is a skirt, use type "jupe".';
        }

        return \sprintf(
            'Identify the item in this photo. The user selected "%s" as a hint; use it if it matches the image, but identify the visible item accurately. If it is a skirt, use type "jupe".',
            $typeHint,
        );
    }
}
