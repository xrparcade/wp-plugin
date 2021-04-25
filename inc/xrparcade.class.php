<?php

declare(strict_types=1);

final class XRPArcadePlugin
{
    private const SUPPORTERS_NEWSLETTER_LIST_ID = 2;

    private const LOGS_PATH = __DIR__ . '/logs/xrparcade.log';

    /**
     * @var Xumm
     */
    private $xumm;

    /**
     * Constructor. Instantiates new xumm client.
     */
    public function __construct()
    {
        $this->xumm = new Xumm();
    }

    /**
     * Called to add the required class methods to WP hooks.
     *
     * @return void
     */
    public function init_hooks(): void
    {
        add_action('um_user_after_updating_profile', [$this, 'xrparcade_update_profile']);

        add_action('rest_api_init', function () {
            register_rest_route('xrparcade/v1', 'xumm', [
                'methods' => 'POST',
                'callback' => [$this, 'xumm_webhook']
            ]);
        });

        add_action('wp_ajax_xumm_connect', [$this->xumm, 'xumm_connect']);
    }

    /**
     * Hook called when the user updates their profile.
     *
     * @param mixed[] $profile User profile.
     *
     * @return void
     */
    public function xrparcade_update_profile(array $profile): void
    {
        $userId = get_current_user_id();
        $signup = !empty($profile['newsletter'][0]);

        $subscriptionEndDate = get_user_meta($userId, 'subscription_end_date', true);
        if (empty($subscriptionEndDate) || new DateTime($subscriptionEndDate) < new DateTime()) {
            $this->xumm->send_payment_request($userId);
        } else {
            $this->xrparcade_update_newsletter_subscription($userId, $signup, $subscriptionEndDate);
        }
    }

    /**
     * Webhook. Called via /wp-json/xrparcade/v1/xumm (@see init_hooks()), by XUMM
     * when a transaction is signed.
     *
     * @param WP_REST_Request $request
     */
    public function xumm_webhook(WP_REST_Request $request): void
    {
        $payload = $request->get_json_params();
        if (empty($payload) || empty($payload['meta']['payload_uuidv4'])) {
            return;
        }

        $payloadId = $payload['meta']['payload_uuidv4'];
        // since the webhook is open and anyone can send whatever bs they want
        // we'll call back XUMM with the payload ID to get the details, instead
        // of relying on the received request body
        $response = $this->xumm->get_payload($payloadId);

        if (
            empty($response)
            || empty($response['meta'])
            || !$response['meta']['exists']
            || empty($response['application']['issued_user_token'])
            || empty($response['response']['account'])
        ) {
            // yup, someone else called this with junk
            return;
        }

        $token = $response['application']['issued_user_token'];
        $account = $response['response']['account'];

        $users = get_users([
            'meta_key' => 'xumm_signin_request_id',
            'meta_value' => $payloadId,
        ]);

        // xumm sign-in
        if (count($users) === 1) {
            $userId = $users[0]->id;
            update_user_meta($userId, 'xumm_access_token', $token);
            update_user_meta($userId, 'xumm_xrpl_account', $account);
            delete_user_meta($userId, 'xumm_signin_request_id');

            return;
        }

        $users = get_users([
            'meta_key' => 'xumm_payment_request_id',
            'meta_value' => $payloadId,
        ]);

        // payment
        if (count($users) === 1) {
            $userId = $users[0]->id;
            $subscriptionEndDate = get_user_meta($userId, 'subscription_end_date', true);
            $today = new DateTime();
            // add 7 days to subscription, either from today, or from the end-date if the user still
            // has remaining days.
            if (empty($subscriptionEndDate) || new DateTime($subscriptionEndDate) < $today) {
                $subscriptionEndDate = $today->add(new DateInterval('P7D'))->format('yy/m/d');
            } else {
                $subscriptionEndDate = (new DateTime($subscriptionEndDate))->add(new DateInterval('P7D'))->format('yy/m/d');
            }
            update_user_meta($userId, 'subscription_end_date', $subscriptionEndDate);
            delete_user_meta($userId, 'xumm_payment_request_id');
            
            $this->xrparcade_update_newsletter_subscription($userId, null, $subscriptionEndDate);
        }
    }

    /**
     * Updates newsletter subscription and adds or remove the user to the newsletter list.
     * For a user to be added in the list the signup checkbox in their profile must be checked ($signup)
     * and the subscription end date must be greater than today's day.
     *
     * @param int $userId WP user id.
     * @param boolean $signup Whether the user has opted in the newsletter or not.
     * @param DateTime $subscriptionEndDate The date this user's subscription ends, according to payments.
     *
     * @return void
     */
    private function xrparcade_update_newsletter_subscription($userId, $signup = null, $subscriptionEndDate = null): void
    {
        if (empty($userId)) {
            error_log('xrparcade_update_newsletter_subscription called with empty user id', 3, self::LOGS_PATH);
            return;
        }

        if ($signup === null) {
            // newsletter meta is an array, but we only have 1 newsletter for now
            // if we add more newsletter in the future then we'll have to filter
            // for array values.
            $signup = count(get_user_meta($userId, 'newsletter', true)) === 1;
        }

        if ($subscriptionEndDate === null) {
            $subscriptionEndDate = get_user_meta($userId, 'subscription_end_date', true);
        }

        $newsletter = Newsletter::instance();
        $user = $newsletter->get_user_by_wp_user_id($userId);
        if (!$user) {
            error_log('xrparcade_update_newsletter_subscription called with user id ' . $userId . ' but no respective newsletter user was found.', 3, self::LOGS_PATH);

            return;
        }

        $subscription = NewsletterSubscription::instance()->get_default_subscription();
        $subscription->data->referrer = 'XRPArcadePlugin';
        $subscription->data->email = $user->email;
        $subscription->optin = 'single';
        $subscription->if_exists = TNP_Subscription::EXISTING_MERGE;
        $subscription->data->lists['' . self::SUPPORTERS_NEWSLETTER_LIST_ID] =  ($signup && new DateTime($subscriptionEndDate) > new DateTime());

        $user = NewsletterSubscription::instance()->subscribe2($subscription);

        if ($user instanceof WP_Error) {
            error_log('Unable to modify user subscription for user id ' . $userId . ': ' . print_r($user->get_error_message()), 3, self::LOGS_PATH);
        }
    }
}
