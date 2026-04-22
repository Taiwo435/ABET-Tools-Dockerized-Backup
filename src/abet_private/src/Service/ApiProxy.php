<?php 
namespace App\Service;
use Psr\Log\LoggerInterface;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use App\Entity\User;

// Acts as a service for stuff

/**
 * inspired by `public/AssignmentsGrades/api-proxy.php`
 * Acts as an HTTP proxy to our backend APIs
 */
class ApiProxy
{
    // TODO: possibly sort functions into a hierarchy based on service?
    //  - extraction API
    //  - reportgen API
    //  - canvas scripts API
    /////////////////////////////////////////////////////////////////////////
    // Extraction API 
    /////////////////////////////////////////////////////////////////////////

    /**
     * Uses the extraction_API endpoint to verify if a Canvas Access token is valid.
     * @param string $token     the API token to validate
     * @return ResponseInterface         The response from the API. 200 iff. valid.
     */
    public function verifyToken(string $token)  {
        $client = HttpClient::create();
        $url    = $this->api_base(API::Extraction) . '/verify-token';

        $response = $client->request(
            'GET',
            $url,
            [
                'headers' => [
                    'canvas-access-token:' => $token
                ],
            ]
        );

        return $response;
    }

    /**
     * Returns the extraction jobs that have been instantiated.
     * @param User $user The user who submitted the request
     * @return ResponseInterface The response by the API
     */
    public function getJobHistory(User $user) {
        $client = HttpClient::create();
        $url    = $this->api_base(API::Extraction) . '/jobs?limit=50';

        $response = $client->request(
            'GET',
            $url,
            [
                'headers' => [
                    'submitted-by-user-id' => $user->getId() ?? 0,
                ],
            ]
        );

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////
    // Canvas Formatting API
    /////////////////////////////////////////////////////////////////////////

    /////////////////////////////////////////////////////////////////////////
    // Report Generation API
    /////////////////////////////////////////////////////////////////////////

    // an example function
    // public function getHappyMessage(): string
    // {
    //     $messages = [
    //         'You did it! You updated the system! Amazing!',
    //         'That was one of the coolest updates I\'ve seen all day!',
    //         'Great work! Keep going!',
    //     ];

    //     $index = array_rand($messages);

    //     return $messages[$index];
    // }

    /////////////////////////////////////////////////////////////////////////
    // Shared 
    /////////////////////////////////////////////////////////////////////////

    /**
     * gets the base of a certain API
     * Made by Tan28-art
     * edited by Danny Hoang
     * @param API $service      the API you want to contact
     * @return string           the base URL of the associated API 
     */
    private function api_base(API $service): string {
        $hosts = [
            API::Extraction->value => ['EXTRACTION_HOSTNAME', 'EXTRACTION_PORT', 'extraction_api', '8000'],
            API::Formatting->value  => ['CANVAS_FORMATTING_HOSTNAME', 'CANVAS_FORMATTING_PORT', 'canvas_formatting', '8001'],
            API::ReportGeneration->value  => ['REPORTGEN_HOSTNAME', 'REPORTGEN_PORT', 'canvas_formatting', '8002'],
        ];
        [$hostEnv, $portEnv, $defaultHost, $defaultPort] = $hosts[$service->value];
        $host = getenv($hostEnv) ?: $defaultHost;
        $port = getenv($portEnv) ?: $defaultPort;
        return "http://{$host}:{$port}";
    }

}

/**
 *  enum to describe the different services that we have
 */
enum API : int{
    case Extraction = 1;
    case Formatting = 2;
    case ReportGeneration = 3;
}