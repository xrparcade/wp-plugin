<?php
declare(strict_types=1);

final class XRPArcadePlugin
{
    private const SUPPORTERS_NEWSLETTER_LIST_ID = 2;

    /**
     * @var NewsletterLogger
     */
    private $logger;

    /**
     * @var Xumm
     */
    private $xumm;

    /**
     * Constructor. Instantiates logger from newsletter plugin logger.
     */
    public function __construct()
    {
        $this->logger = new NewsletterLogger(get_class());
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

        $this->xumm->init_hooks();
    }

    /**
     * Hook called when the user updates their profile.
     *
     * @return void
     */
	public function xrparcade_update_profile($profile): void
    {
        $userId = get_current_user_id();
        $signup = !empty($profile['newsletter'][0]);

        $this->xrparcade_update_newsletter_subscription($userId, $signup);
        if ($userId == 2) {
            $this->xumm->send_payment_request(2);
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
            $this->logger->fatal('xrparcade_update_newsletter_subscription called with empty user id');
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
            $this->logger->fatal('xrparcade_update_newsletter_subscription called with user id ' . $userId . ' but no respective newsletter user was found.');
            return;
        }

        $subscription = NewsletterSubscription::instance()->get_default_subscription();
        $subscription->data->referrer = 'XRPArcadePlugin';
        $subscription->data->email = $user->email;
        $subscription->optin = 'single';
        $subscription->if_exists = TNP_Subscription::EXISTING_MERGE;
        $subscription->data->lists['' . self::SUPPORTERS_NEWSLETTER_LIST_ID] =  ($signup && new DateTime($subscriptionEndDate) > new DateTime());

        $user = NewsletterSubscription::instance()->subscribe2( $subscription );

        if ($user instanceof WP_Error) {
            $this->logger->fatal( 'Unable to modify user subscription for user id ' . $userId . ': ' . print_r($user->get_error_message()));
        }
    }

}