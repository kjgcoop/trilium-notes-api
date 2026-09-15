<?php
namespace Kjgcoop\TriliumApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Michelf\Markdown;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TriliumApi
{
    private ClientInterface $client;
    private LoggerInterface $logger;
    private string $base;
    private array $headers;

    public function __construct(
        string $endpoint,
        string $apiKey,
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null
    ) {
        if (stripos($endpoint, 'https:') !== 0) {
            throw new \InvalidArgumentException(
                'The Trilium endpoint must use https:// - otherwise the API key would be sent in cleartext.'
            );
        }

        $this->base = rtrim($endpoint, '/') . '/';
        $this->headers = ['Authorization' => $apiKey];
        $this->logger = $logger ?? new NullLogger();
        $this->client = $httpClient ?? new Client(['timeout' => 10]);
    }

    public function isUsingHttps(): bool
    {
        return stripos($this->base, 'https:') === 0;
    }

    public function getContentsFromId(string $shortCode): string
    {
        return (string) $this->request('GET', 'notes/' . $shortCode . '/content')->getBody();
    }

    public function getDatesContents(string $shortCode): string
    {
        return $this->getContentsFromId($shortCode);
    }

    public function getDateNoteId(\DateTimeInterface $date): string
    {
        $path = 'calendar/days/' . $date->format('Y-m-d');
        $response = $this->request('GET', $path);
        $dateNote = json_decode((string) $response->getBody());

        if (empty($dateNote) || !isset($dateNote->attributes) || !isset(current($dateNote->attributes)->noteId)) {
            throw new \RuntimeException("No note ID found for date {$date->format('Y-m-d')}");
        }

        return current($dateNote->attributes)->noteId;
    }

    public function writeNewContents(string $idForDate, string $contents): bool
    {
        $this->request('PUT', 'notes/' . $idForDate . '/content', [
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => $contents,
        ]);

        return true; // API doesn't return anything.
    }

    public function writeNewNote(string $parentId, string $title, string $contents, bool $isMarkdown = false): bool
    {
        // Sometimes Markdown works, sometimes it doesn't. HTML always does, so
        // convert it before sending; works with mixed syntax
        if ($isMarkdown) {
            $contents = Markdown::defaultTransform($contents);
        }

        $this->request('POST', 'create-note', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'parentNoteId' => $parentId,
                'title' => $title,
                'content' => $contents,
                'type' => 'text',
            ]),
        ]);

        return true; // API doesn't return anything.
    }

    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $uri = $this->base . $path;
        $options['headers'] = array_merge($this->headers, $options['headers'] ?? []);

        try {
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            $this->logger->error("Request to {$method} {$uri} failed: {$e->getMessage()}", ['exception' => $e]);
            throw new \RuntimeException("Trilium API request to $uri failed", 0, $e);
        }
    }
}
