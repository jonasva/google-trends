<?php namespace Jonasva\GoogleTrends;

use Jonasva\GoogleTrends\GoogleSession;
use Jonasva\GoogleTrends\GoogleTrendsResponse;

use GuzzleHttp\Exception\BadResponseException;

class GoogleTrendsRequest
{
    /**
     * Google trends fetch URL
     *
     * @const string
     */
    CONST TRENDS_URL = 'https://www.google.com/trends/fetchComponent';

    /**
     * Cid to get a trends graph for one or more terms
     *
     * @const string
     */
    CONST CID_GRAPH = 'TIMESERIES_GRAPH_0';

    /**
     * Cid to get a list of top queries
     *
     * @const string
     */
    CONST CID_TOP_QUERIES = 'TOP_QUERIES_0_0';

    /**
     * Cid to get a list of rising queries
     *
     * @const string
     */
    CONST CID_RISING_QUERIES = 'RISING_QUERIES_0_0';

    /**
     * Google session
     *
     * @var GoogleSession
     */
    private $session;

    /**
     * trends query terms
     *
     * @var string
     */
    private $query;

    /**
     * trends query category
     *
     * @var string
     */
    private $cid;

    /**
     * Language
     *
     * @var string
     */
    private $language;

    /**
     * Geo location
     *
     * @var string
     */
    private $location;

    /**
     * Trends category
     *
     * @var string
     */
    private $category;

    /**
     * Date range
     *
     * @var string
     */
    private $dateRange;

    /**
     * Guzzle Client
     *
     * @var \GuzzleHttp\Client
     */
    private $guzzleClient;

    /**
     * Create a new GoogleTrendsRequest instance.
     *
     * @param GoogleSession $session
     */
    public function __construct(GoogleSession $session)
    {
        $this->session = $session;
        $this->cid = 'TIMESERIES_GRAPH_0';
        $this->query = [];
        $this->language = str_replace('_', '-', $this->session->getLanguage());
        $this->setDateRange((new \DateTime())->modify('-12 months'), new \DateTime());
        $this->guzzleClient = $session->getGuzzleClient();
    }

    /**
     * @param string $term
     * @return $this
     */
    public function addTerm($term)
    {
        if (count($this->query) <= 5) {
            $this->query[] = $term;
        }
        return $this;
    }

    /**
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return $this
     */
    public function setDateRange(\DateTime $startDate, \DateTime $endDate)
    {
        if ($startDate->format('Ym') === $endDate->format('Ym')) {
            $this->dateRange = $startDate->format('m/Y') . ' ' . '1m';
        }
        else {

            $monthsDifference = ($startDate->format('m') - $endDate->format('m')) * -1;
            $yearsDifference = ($startDate->format('Y') - $endDate->format('Y')) * 12;

            $this->dateRange = $startDate->format('m/Y') . ' ' . (($yearsDifference - $monthsDifference) * -1) . 'm';
        }
        return $this;
    }

    /**
     * Category can be found on https://www.google.com/trends/explore#cmpt=q&tz=
     * To get a category's identifier, select a category on google trends and look for what comes behind the cat= querystring
     * Normally it should be something like 0-3
     *
     * @param string $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @param string $location
     * @return $this
     */
    public function setLocation($location)
    {
        $this->location = $location;
        return $this;
    }

    /**
     * @param string $cid
     * @return $this
     */
    public function setCid($cid)
    {
        $this->cid = $cid;
        return $this;
    }

    /**
     * Get top queries
     *
     * @return $this
     */
    public function getTopQueries()
    {
        $this->cid = self::CID_TOP_QUERIES;
        return $this;
    }

    /**
     * Get rising queries
     *
     * @return $this
     */
    public function getRisingQueries()
    {
        $this->cid = self::CID_RISING_QUERIES;
        return $this;
    }

    /**
     * Get comparison graph of terms
     *
     * @return $this
     */
    public function getGraph()
    {
        $this->cid = self::CID_GRAPH;
        return $this;
    }

    /*
     * Send trends query
     *
     * @return
     */
    public function send()
    {
        $request = $this->guzzleClient->createRequest('GET', self::TRENDS_URL, ['cookies' => $this->session->getCookieJar()]);
        $query = $request->getQuery();

        $params = [
            'hl'        =>  $this->language,
            'q'         =>  implode(',+', $this->query),
            'cid'       =>  $this->cid,
            'date'      =>  $this->dateRange,
            'cmpt'      =>  'q',
            'content'   =>  '1',
            'export'    =>  '3',
        ];

        !$this->category ?: $params['cat'] = $this->category;
        !$this->location ?: $params['geo'] = $this->location;

        foreach ($params as $key => $param) {
            $query->set($key, $param);
        }

        // wait a random amount of seconds
        if ($this->session->getMaxSleepInterval() > 10) {
            sleep(rand(10, $this->session->getMaxSleepInterval())/100);
        }

        $response = $this->guzzleClient->send($request);
        $content = $response->getBody()->getContents();

        // quota limit error
        if ($response->getStatusCode() == 203 && strpos($content, 'You have reached your quota limit.') !== false) {
            throw new BadResponseException('You have reached your quota limit. Please try again later.', $request, $response);
        }

        // other error
        if (strpos($content, '"status":"error"') !== false) {
            $content = substr($content, strpos($content, '({') + 1, -2);
            $content = @json_decode($content, true);

            $errorMsg = $content['errors'][0]['message'] . '. ';
            !isset($content['errors'][0]['detailed_message']) ?:  $errorMsg .= $content['errors'][0]['detailed_message'];

            throw new BadResponseException($errorMsg, $request, $response);
        }

        return new GoogleTrendsResponse($response, $content);
    }
} 
