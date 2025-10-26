<?php

/**
 * PayMongoService is a service class responsible for interacting with the PayMongo API.
 * It supports creating payment sources, retrieving payment statuses, and handling API requests.
 */
class PayMongoService
{
    private string $publicKey;
    private string $secretKey;
    private string $baseUrl;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        # Load up your config from the config file
        $this->publicKey = config('config.payment.paymongo.public_key');
        $this->secretKey = config('config.payment.paymongo.secret_key');
        $this->baseUrl = config('config.payment.paymongo.base_url');
        echo $this->baseUrl;
        echo $this->publicKey;
        echo $this->secretKey;
        if (!$this->secretKey) {
            throw new Exception("PayMongo secret key not set in environment variables");
        }
    }

    /**
     * Create a payment source (similar to creating a payment intent)
     * @param float $amount in PHP peso (will convert to centavos)
     * @param string $type 'gcash', 'grab_pay', etc.
     * @return array
     * @throws Exception
     */
    public function createPaymentIntent(float $amount, string $type): array
    {
        # PayMongo uses centavos
        $amountInCents = intval($amount * 100);

        $data = [
            'data' => [
                'attributes' => [
                    'amount' => $amountInCents,
                    'currency' => 'PHP',
                    'type' => $type,
                    'payment_method_allowed' => ['gcash', 'paymaya'],
                    'description' => 'Stay Booking Payment',
                    'statement_descriptor' => 'Your payment statement'
                ]
            ]
        ];

        return $this->request('/payment_intents', $data);
    }

    /**
     * Retrieve source/payment status by ID
     */
    public function getSource(string $sourceId): array
    {
        return $this->request("/sources/$sourceId", [], "GET");
    }

    /**
     * Sends an HTTP request to a specified API endpoint with the given data and method.
     *
     * @param string $endpoint The API endpoint to send the request to.
     * @param array $data The data to be included in the request payload. Default is an empty array.
     * @param string $method The HTTP method to use for the request (e.g., 'POST' or 'GET'). Default is 'POST'.
     *
     * @return array The decoded response from the API.
     *
     * @throws Exception If there is a cURL error or the API returns an error response.
     */
    private function request(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);

        $payload = json_encode($data);

        $headers = [
            'Authorization: Basic ' . base64_encode($this->secretKey . ":"),
            'Content-Type: application/json',
            'accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("Curl error: " . curl_error($ch));
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception("PayMongo API error: " . ($decoded['errors'][0]['detail'] ?? $response));
        }

        return $decoded;
    }
}
