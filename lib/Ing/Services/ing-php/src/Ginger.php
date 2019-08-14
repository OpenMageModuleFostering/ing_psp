<?php

namespace GingerPayments\Payment;

use GuzzleHttp\Client as HttpClient;
use Assert\Assertion as Guard;

final class Ginger
{
    /**
     * The library version.
     */
    const CLIENT_VERSION = '1.2.8';

    /**
     * The API version.
     */
    const API_VERSION = 'v1';

    /**
     * API endpoint Ginger Payments
     */
    const ENDPOINT_GINGER = 'https://api.gingerpayments.com/{version}/';

    /**
     * API Kassa Compleet endpoint
     */
    const ENDPOINT_KASSA = 'https://api.kassacompleet.nl/{version}/';

    /**
     * API endpoint ING
     */
    const ENDPOINT_ING = 'https://api.ing-checkout.com/{version}/';

    /**
     * API endpoint EPAY
     */
    const ENDPOINT_EPAY = 'https://api.epay.ing.be/{version}/';

    /**
     * Create a new API client.
     *
     * @param string $apiKey Your API key.
     * @param string $product
     * @return Client
     */
    public static function createClient($apiKey, $product = null)
    {
        Guard::uuid(
            static::apiKeyToUuid($apiKey),
            'ING API key is invalid: '.$apiKey
        );

        return new Client(
            new HttpClient(
                [
                    'base_url' => [
                        static::getEndpoint($product),
                        ['version' => self::API_VERSION]
                    ],
                    'defaults' => [
                        'headers' => [
                            'User-Agent' => 'ing-php/'.self::CLIENT_VERSION,
                            'X-PHP-Version' => PHP_VERSION
                        ],
                        'auth' => [$apiKey, '']
                    ]
                ]
            )
        );
    }

    /**
     * Get API endpoint based on product
     *
     * @param string $product
     * @return string
     */
    public static function getEndpoint($product)
    {
        switch ($product) {
            case 'kassacompleet':
                $endpoint = self::ENDPOINT_KASSA;
                break;
            case 'ingcheckout':
                $endpoint = self::ENDPOINT_ING;
                break;
            case 'epay':
                $endpoint = self::ENDPOINT_EPAY;
                break;
            default:
                $endpoint = self::ENDPOINT_GINGER;
                break;
        }
        return $endpoint;
    }

    /**
     * Method restores dashes in Ginger API key in order to validate UUID.
     *
     * @param string $apiKey
     * @return string UUID
     */
    public static function apiKeyToUuid($apiKey)
    {
        return preg_replace('/(\w{8})(\w{4})(\w{4})(\w{4})(\w{12})/', '$1-$2-$3-$4-$5', $apiKey);
    }
}
