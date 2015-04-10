<?php namespace Jonasva\GoogleTrends;

use Jonasva\GoogleTrends\GoogleTrendsTerm;

use GuzzleHttp\Message\Response;

class GoogleTrendsResponse
{
    /**
     * response body content
     *
     * @var string
     */
    private $responseContent;

    /**
     * Raw guzzle response object
     *
     * @var \GuzzleHttp\Message\Response
     */
    private $response;

    /**
     * Get response body content
     *
     * @return string $responseContent
     */
    public function getResponseContent()
    {
        return $this->responseContent;
    }

    /**
     * Get response
     *
     * @return \GuzzleHttp\Message\Response $response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Create a new GoogleTrendsResponse instance
     *
     * @param Response $response
     * @param string $responseContent
     */
    public function __construct(Response $response, $responseContent)
    {
        $this->response = $response;
        $this->responseContent = $responseContent;
    }

    /*
     * Decode the body content's json
     *
     * @return array
     */
    public function jsonDecode()
    {
        // strip off unneeded parts of the response body content
        $content = substr($this->responseContent, strpos($this->responseContent, '({') + 1, -2);

        // replace invalidly formatted dates (otherwise we can't json_decode)
        $content = preg_replace_callback(
            '/new Date\(([0-9]+),([0-9]+),([0-9]+)\)/',
            function ($matches) {
                // google date formats display month as an int between 0 and 11, so have to add 1
                return '"' . $matches[1] . '-' . ($matches[2] + 1) . '-' . $matches[3] . '"';
            },
            $content
        );

        // replace empties
        $content = preg_replace_callback(
            '/\,([\,]+)?\,/',
            function ($matches) {
                ltrim ($matches[0], ',');
                $matches[0] = str_replace(',', ',{"v":null}', $matches[0]) . ',';

                return $matches[0];
            },
            $content
        );

        // decode json
        $decoded = @json_decode($content, true);

        if (is_null($decoded)) {
            throw new \Exception('Unable to decode response json');
        }

        return $decoded;
    }

    /*
     * Format response data to an array
     *
     * @return array
     */
    public function getFormattedData()
    {
        $decodedContent = $this->jsonDecode();

        $processedData = [];

        foreach ($decodedContent['table']['rows'] as $row) {
            foreach($decodedContent['table']['cols'] as $key => $col) {
                $processedData[$col['label']][] = $row['c'][$key]['v'];
            }
        }

        return $processedData;
    }

    /*
     *
     * Format data into GoogleTrendsTerm objects
     *
     * @return array
     */
    public function getTermsObjects()
    {
        $decodedContent = $this->jsonDecode();

        $results = [];

        foreach ($decodedContent['table']['rows'] as $row) {
            $term = new GoogleTrendsTerm();

            $term->term = $row['c'][0]['v'];
            $term->ranking = $row['c'][1]['v'];
            $term->productUrl = $row['c'][2]['v'];
            $term->searchUrl = $row['c'][3]['v'];

            $results[] = $term;
        }

        return $results;
    }
} 
