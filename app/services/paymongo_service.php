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
     * Creates a payment method with the specified amount and type.
     *
     * @param string $type The type of payment method to create (e.g., 'card', 'bank_transfer').
     *
     * @return array The response from the API after creating the payment method.
     *
     * @throws Exception If the API request fails or returns an error.
     */
    public function createPaymentMethod(string $type): array
    {
        $data = [
            'data' => [
                'attributes' => [
                    'type' => $type,
                    'details' => [
                        'card_number' => '123412134',
                        'exp_month' => '12',
                        'exp_year' => '2024',
                        'cvc' => '123',
                        'bank_code' => 'test_bank_one'
                    ],
                    'billing' => [
                        'address' => [
                            'line1' => '123 Main St',
                            'line2' => 'Apartment 1',
                            'city' => 'El Salvador',
                            'state' => 'Mis Or.',
                            'postal_code' => '94107',
                            'country' => 'PH'
                        ],
                        'name' => 'Juan de la Cruz',
                        'email' => 'juandelacruz@test.com',
                        'phone' => '0933423232'
                    ],
                ]
            ]
        ];

        return $this->request('/payment_methods', $data);
    }

    /**
     * Attaches a payment method to a specified payment intent.
     *
     * @param string $paymentIntentId The ID of the payment intent to which the payment method will be attached.
     * @param string $paymentMethodId The ID of the payment method to attach to the payment intent.
     *
     * @return array The response from the API after attaching the payment method.
     *
     * @throws Exception If the API request fails or returns an error response.
     */
    public function attachPaymentMethod(string $paymentIntentId, string $paymentMethodId): array
    {
        $data = [
            'data' => [
                'attributes' => [
                    'payment_method' => $paymentMethodId,
                    'client_key' => $this->secretKey,
                    'return_url' => 'https://webhook.site/0e9069d9-9859-4078-a266-17a7f36e2775'
                ]
            ]
        ];

        return $this->request("/payment_intents/{$paymentIntentId}/attach", $data);
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
