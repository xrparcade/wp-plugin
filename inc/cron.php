<?php
declare(strict_types=1);

class XRPArcadeCron
{
    /**
     * @var Xumm
     */
    private $xumm;

    /**
     * @var XRPArcadeNewsletterManager
     */
    private $manager;

    /**
     * @var XRPArcadeYoutubeChannels
     */
    private $youtubers;

    public function __construct()
    {
        $this->xumm = new Xumm();
        $this->manager = new XRPArcadeNewsletterManager();
        $this->youtubers = new XRPArcadeYoutubeChannels();
    }

    public function init_hooks()
    {
        add_action('xrparcade_cron_newsletter_checkbox', [$this, 'newsletter_checkbox_cron_exec']);
        add_action('xrparcade_cron_payments', [$this, 'payments_cron_exec']);
        add_action('xrparcade_cron_youtubers', [$this, 'youtubers_cron_exec']);
    }

    public function youtubers_cron_exec()
    {
        $this->youtubers->update_channels();
    }

    /**
     * Loops through all the users and unchecks the newsletter checkbox for those
     * whose subscription has ended
     *
     */
    public function newsletter_checkbox_cron_exec(): void
    {
        $users = get_users();
        foreach ($users as $user) {
            if (empty($user) || !($user instanceof WP_User) || empty($user->id)) {
                continue;
            }

            $this->process_user_for_newsletter_checkbox($user->id);
        }
    }

    /**
     * Loops through all the users and sends a XUMM payment request
     * to those who are subscribed to our newsletter and their
     * subscription will soon expire
     */
    public function payments_cron_exec(): void
    {
        $users = get_users();
        foreach ($users as $user) {
            if (empty($user) || !($user instanceof WP_User) || empty($user->id)) {
                continue;
            }

            $this->process_user_for_payment($user->id);
        }
    }

    /**
     * Checks if a user should be sent a payment request
     * and sends them if needed.
     *
     * @param int $userId wp user id
     */
    private function process_user_for_payment(int $userId)
    {
        $today = new DateTime();

        // minus 7 days, meaning we only push once a week at max.
        $pushIfLastPushIsBefore = (new DateTime())->sub(new DateInterval('P7D'));
        
        // do not push if user has 3+ days on their subscription
        $remindIfSubsciptionEndsBefore = (new DateTime())->add(new DateInterval('P3D'));

        $accessToken = get_user_meta($userId, 'xumm_access_token', true);
        if (empty($accessToken)) {
            // no access token, nothing we can do about this fella
            return;
        }

        $supporterSelection = get_user_meta($userId, 'supporter_selection', true);
        $signup = is_array($supporterSelection) && !empty($supporterSelection) && count($supporterSelection) == 1 && intval($supporterSelection[0]) !== 0;
        if (!$signup) {
            // user didn't sign up for our newsletter, so no need to request payment from them
            return;
        }

        $subscriptionEndDate = get_user_meta($userId, 'subscription_end_date', true);
        if (!empty($subscriptionEndDate) && new DateTime($subscriptionEndDate) > $remindIfSubsciptionEndsBefore) {
            return;
        }
        
        $lastPush = get_user_meta($userId, 'xumm_last_push_date', true);
        if (!empty($lastPush) && $lastPush > $pushIfLastPushIsBefore) {
            return;
        }

        // first mark as pushed, then push
        // because if for some weird reason updating the meta fails I really
        // don't want to spam the user
        update_user_meta($userId, 'xumm_last_push_date', $today);
        $this->xumm->send_payment_request($userId);
    }

    private function process_user_for_newsletter_checkbox($userId)
    {
        $subscriptionEndDate = get_user_meta($userId, 'subscription_end_date', true);
        $supporterSelection = get_user_meta($userId, 'supporter_selection', true);
        $signup = is_array($supporterSelection) && !empty($supporterSelection) && count($supporterSelection) == 1 && intval($supporterSelection[0]) !== 0;
        if ($signup && new DateTime($subscriptionEndDate) < new DateTime()) {
            delete_user_meta($userId, 'supporter_selection');
            $this->manager->xrparcade_update_newsletter_subscription($userId, false, $subscriptionEndDate);
        }
    }
}
