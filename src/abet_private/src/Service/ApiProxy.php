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
                    'canvas-access-token' => $token,
                ],
            ]
        );

        return $response;
    }

    /**
     * Returns every Canvas course the given token has faculty access to,
     * with no term/semester filtering applied.
     *
     * Courses are loaded all at once rather than
     * requiring a semester to be selected first -- courses are identified
     * by their Canvas course ID, and any filtering happens later, after
     * data has been imported into the final compiled report shell, not
     * on this page.
     *
     * @param string $token The Canvas access token to fetch courses with
     * @return ResponseInterface The response from the API
     */
      public function getAllCourses(string $token) {
        $client = HttpClient::create();
        // No enrollment_type override here -- the extraction API's
        // /canvas/courses endpoint now defaults to fetching both
        // 'teacher' and 'ta' enrollments on its own, so this endpoint
        // works correctly for either role without the caller needing
        // to know or guess which one a given user actually holds.
        $url = $this->api_base(API::Extraction) . '/canvas/courses';

        $response = $client->request(
            'GET',
            $url,
            [
                'headers' => [
                    'canvas-access-token' => $token,
                ],
            ]
        );
        return $response;
    }

    /** Return the assignments for one Canvas course without exposing its token. */
    public function getAssignments(string $token, string $courseId): ResponseInterface {
        $client = HttpClient::create();
        $url = $this->api_base(API::Extraction)
            . '/canvas/courses/' . rawurlencode($courseId) . '/assignments';

        return $client->request('GET', $url, [
            'headers' => [
                'canvas-access-token' => $token,
            ],
        ]);
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
