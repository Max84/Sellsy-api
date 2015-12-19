<?php


namespace SellsyApi;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

class Request {

    /**
     * @var string
     */
    protected $endPoint;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $userToken;
    /**
     * @var string
     */
    protected $userSecret;
    /**
     * @var string
     */
    protected $consumerToken;
    /**
     * @var string
     */
    protected $consumerSecret;

    /**
     * Request constructor.
     *
     * @param string $userToken
     * @param string $userSecret
     * @param string $consumerToken
     * @param string $consumerSecret
     * @param string $endPoint
     */
    public function __construct ($userToken, $userSecret, $consumerToken, $consumerSecret, $endPoint = NULL) {
        $this->userToken      = $userToken;
        $this->userSecret     = $userSecret;
        $this->consumerToken  = $consumerToken;
        $this->consumerSecret = $consumerSecret;
        if (!is_null($endPoint)) {
            $this->endPoint = $endPoint;
        }
    }

    /**
     * @param string $method
     * @param mixed  $params
     *
     * @return PromiseInterface
     */
    public function callAsync ($method, $params) {
        $requestId = uniqid();
        $options   = $this->prepareCall($method, $params, $requestId);
        $response  = $this->client->postAsync('', $options);

        $promise = new Promise(function ($unwrap) use ($response) {
            return $response->wait($unwrap);
        }, function () use ($response) {
            return $response->cancel();
        });

        $response->then(function (ResponseInterface $res) use ($promise, $requestId) {
            try {
                $promise->resolve($this->handleResponse($res->getBody()));
                return $res;
            } catch (\Exception $e) {
                $promise->reject($e);
                throw $e;
            }
        }, function (RequestException $reqException) use ($promise, $requestId) {
            $this->logResponse($requestId, strval($reqException));
            $promise->reject($reqException);
            return $reqException;
        });

        $promise->then(function ($res) use ($requestId) {
            $this->logResponse($requestId, json_encode($res));
            return $res;
        }, function ($res) use ($requestId) {
            $this->logResponse($requestId, strval($res));
            return $res;
        });

        return $promise;

    }

    /**
     * @param $method
     * @param $params
     *
     * @return ResponseInterface
     */
    public function call ($method, $params) {
        $requestId = uniqid();
        $options   = $this->prepareCall($method, $params, $requestId);

        return $this->handleResponse($this->client->post('', $options));
    }

    /**
     * TODO
     *
     * @param string $requestId
     * @param string $message
     */
    protected function logRequest ($requestId, $message) {
        printf("[%s]%s --> %s\n", $requestId, date('c'), $message);
    }

    /**
     * TODO
     *
     * @param string $requestId
     * @param string $message
     */
    protected function logResponse ($requestId, $message) {
        printf("[%s]%s <-- %s\n", $requestId, date('c'), $message);
    }

    /**
     * @return string[]
     */
    protected function getOAuthHeader () {
        $encodedKey  = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->userSecret);
        $oauthParams = ['oauth_consumer_key'     => $this->consumerToken,
                        'oauth_token'            => $this->userToken,
                        'oauth_nonce'            => md5(time() + rand(0, 1000)),
                        'oauth_timestamp'        => time(),
                        'oauth_signature_method' => 'PLAINTEXT',
                        'oauth_version'          => '1.0',
                        'oauth_signature'        => $encodedKey,
        ];


        $values = [];
        foreach ($oauthParams as $key => $value) {
            $values[] = sprintf('%s="%s"', $key, rawurlencode($value));
        }

        return ['Authorization' => 'OAuth ' . implode(', ', $values), 'Expect' => ''];
    }

    /**
     * @param mixed $response
     *
     * @return mixed
     * @throws \OAuthException
     * @throws \SellsyError
     * @throws \UnexpectedValueException
     */
    protected function handleResponse ($response) {

        if (strstr($response, 'oauth_problem')) {
            throw new \OAuthException($response);
        }

        $array = json_decode($response, TRUE);

        if (!is_array($array)) {
            throw  new \UnexpectedValueException(sprintf('Unable  to decode JSON Sellsy\'s response (%s)', $response));
        }

        if (!array_key_exists('status', $array)) {
            throw new \UnexpectedValueException(sprintf('Field status not found in Sellsy\'s response (%s)',
                                                        $response));
        }

        if ($array['status'] != 'success') {
            if (array_key_exists('error', $array) && is_array($error = $array['error'])
                && array_key_exists('code', $error)
            ) {
                throw new \SellsyError(array_key_exists('message', $error) ? $error['message'] : '', $error['code'],
                                       array_key_exists('more', $error) ? $error['more'] : NULL);
            } else {
                throw new \UnexpectedValueException('Unknown Sellsy error');
            }
        }

        if (!array_key_exists('response', $array)) {
            throw new \UnexpectedValueException(sprintf('Field response not found in Sellsy\'s response (%s)',
                                                        $response));
        }

        return $array['response'];

    }

    /**
     * @param $method
     * @param $params
     * @param $requestId
     *
     * @return array
     */
    protected function prepareCall (&$method, &$params, &$requestId) {
        $doIn = ['method' => $method, 'params' => $params];

        $postFields = ['request' => '1', 'io_mode' => 'json', 'do_in' => json_encode($doIn)];
        $multipart  = [];
        foreach ($postFields as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => $value];
        }

        $options = ['headers'   => $this->getOAuthHeader(),
                    'multipart' => $multipart,
                    'verify'    => !preg_match("!^https!i", $this->endPoint),
        ];

        $this->logRequest($requestId, json_encode($doIn));

        return $options;
    }


}