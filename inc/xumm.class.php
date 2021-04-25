<?php

declare(strict_types=1);

class Xumm
{
    /**
     * Sends a payment request to the given user id, by pushing
     * it to their XUMM app, if they have it linked.
     *
     * @param int $userId WP user id
     */
    public function send_payment_request($userId)
    {
        if (empty($userId)) {
            return;
        }
        $token = get_user_meta($userId, 'xumm_access_token', true);
        $xrplAddress = get_user_meta($userId, 'xumm_xrpl_account', true);
        if (empty($token) || empty($xrplAddress)) {
            return;
        }

        $payload = [
            'user_token' => $token,
            'txjson' => [
                'Account' => $xrplAddress,
                'TransactionType' => 'Payment',
                'Destination' => XUMM_XRPARCADE_ADDRESS,
                'Amount' => '' . XUMM_SUBSCRIPTION_WEEKLY_XRP_FEE * 1000000,
            ],
        ];

        $data = $this->send_payload($payload);
        if (!empty($data) && !empty($data['uuid'])) {
            update_user_meta($userId, 'xumm_payment_request_id', $data['uuid']);
        }
    }

    /**
     * Called via AJAX, this method sends a SignIn transaction
     * to XUMM, and then redirects the user to signin. Echos back
     * the xumm redirect URL, so client can redirect with JS.
     */
    public function xumm_connect(): void
    {
        $userId = get_current_user_id();
        if (empty($userId)) {
            return;
        }

        $response = $this->send_payload([
            'txjson' => [
                'TransactionType' => 'SignIn',
            ]
        ]);

        if (empty($response) || empty($response['next']['always'])) {
            return;
        }

        update_user_meta($userId, 'xumm_signin_request_id', $response['uuid']);
        exit($response['next']['always']);
    }

    /**
     * Json encodes a payload object and sends it to XUMM
     * payload API end-point, using the configured API
     * credentials.
     *
     * @param mixed $payload The object to send.
     * @return array|null Array with response data or null if error.
     */
    public function send_payload($payload): ?array
    {
        $payload = json_encode($payload);

        $response = wp_remote_post('https://xumm.app/api/v1/platform/payload', [
            'headers' => [
                'X-API-KEY' => XUMM_API_KEY,
                'X-API-SECRET' => XUMM_API_SECRET,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => $payload,
        ]);

        if (!is_array($response) || empty($response['body'])) {
            // could be WP_Error
            return null;
        }

        $data = json_decode($response['body'], true);

        return ($data === false) ? null : $data;
    }

    /**
     * Gets a payload by ID from Xumm API.
     *
     * @param string $id Payload id.
     *
     * @return array|null Array with payload data, or null if error.
     */
    public function get_payload($id): ?array
    {
        if (empty($id)) {
            return null;
        }

        $response = wp_remote_get('https://xumm.app/api/v1/platform/payload/' . $id, [
            'headers' => [
                'X-API-KEY' => XUMM_API_KEY,
                'X-API-SECRET' => XUMM_API_SECRET,
                'Accept' => 'application/json',
            ],
        ]);
        if (!is_array($response) || empty($response['body'])) {
            // could be WP_Error
            return null;
        }

        $data = json_decode($response['body'], true);

        return ($data === false) ? null : $data;
    }
}
