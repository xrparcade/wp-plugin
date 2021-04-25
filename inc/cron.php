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

    public function __construct()
    {
        $this->xumm = new Xumm();
        $this->manager = new XRPArcadeNewsletterManager();
    }

    public function init_hooks()
    {
        add_action('xrparcade_cron_newsletter_checkbox', [$this, 'newsletter_checkbox_cron_exec']);
        add_action('xrparcade_cron_payments', [$this, 'payments_cron_exec']);
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

    private function process_user_for_payment($userId)
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

        $signup = count(get_user_meta($userId, 'newsletter', true)) === 1;
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
        $newsletter = get_user_meta($userId, 'newsletter', true);

        if (!empty($newsletter) && new DateTime($subscriptionEndDate) < new DateTime()) {
            delete_user_meta($userId, 'newsletter');
        }
    }
}
