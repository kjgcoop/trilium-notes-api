<?php
namespace Kjgcoop\TriliumNextApi;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Psr7\Request;
use Michelf\Markdown;
use Michelf\SmartyPants;

class TriliumNextApi {
    private $client;
//    private $logger;

    public function __construct(array $config, \DateTime $date) {
        $this->client = new Client();

        $this->config = $config;
        $this->date = $date;

        $this->base = rtrim($config['trilium_endpoint'], '/') . '/';

        $this->headers = [
            'Authorization' => $config['trilium_key']
        ];

        // Debugging preferences
        $this->echo = $config['echo'] ?? false;
        $this->log = $config['log'] ?? false;
    }

    public function isUsingHttps(): bool {
        return substr($this->config['trilium_endpoint'], 0, 6) === 'https:';
    }

    public function getContentsFromId($shortCode) : string
    {
        $uriForContents = $this->base.'notes/'.$shortCode.'/content';

        try {
            $response = $this->client->request('GET', $uriForContents, [
                'headers' => $this->headers,
            ]);

            return (string)$response->getBody();

        } catch (\Exception $e) {
            if ($this->echo) {
                echo "Request to $uriForContents threw an exception: \n";
                print_r($e->getMessage());

            }
            throw new \Exception("Unable to get note's contents");
        }
    }

    function getDateNoteId(\DateTime $date): string
    {
        $uriForId = $this->base.'calendar/days/'.$date->format('Y-m-d');

        try {
            $response = $this->client->request('GET', $uriForId, [
                'headers' => $this->headers,
            ]);

            $request = new Request('GET', $uriForId, $this->headers);
            $dateNoteId = json_decode((string)$response->getBody());

            if (empty($dateNoteId) || !isset($dateNoteId->attributes) || !isset(current($dateNoteId->attributes)->noteId)) {
                throw new \Exception('No ID found');
            }

            return current($dateNoteId->attributes)->noteId;

        } catch (\Exception $e) {
            throw new \Exception("Request to $uriForId threw an exception: ".$e->getMessage());
        }
    }

    function writeNewContents($idForDate, $contents) : bool
    {
        $uriForContents = $this->base.'notes/'.$idForDate.'/content';

        try {
            $this->client->request('PUT', $uriForContents, [
                'headers' => array_merge($this->headers, [
                    'Content-Type' => 'text/plain'
                ]),
                'body' => $contents,
            ]);

            return true; // API doesn't return anything.

        } catch (\Exception $e) {
            echo "Request to $uriForContents threw an exception: \n";
            echo $e->getMessage();
            throw $e;
        }
    }


    function writeNewNote(string $parentId, string $title, string $contents, bool $isMarkdown = false) : bool
    {
        $uriForContents = $this->base.'create-note';

        // Sometimes Markdown works, sometimes it doesn't. HTML always does, so
        // convert it before sending; works with mixed syntax
        if ($isMarkdown) {
            $contents = Markdown::defaultTransform($contents);
        }

        $variables = [
            'parentNoteId' => $parentId,
            'title' => $title,
            'content' => urldecode($contents),
            'type' => 'text'
        ];

        try {
            $this->client->request('POST', $uriForContents, [
                'headers' => array_merge($this->headers, [
                    'Content-Type' => 'application/json'
                ]),
                'body' => json_encode($variables)
            ]);

            // @todo Actually it does; deal with this later
            return true; // API doesn't return anything.

        } catch (\Exception $e) {
            echo "Request to $uriForContents threw an exception: \n";
            echo $e->getMessage();
            throw $e;
        }
    }


    function getDatesContents($shortCode) : string {
        return $this->getContentsFromId($shortCode);
    }
}
