<?php
declare(strict_types=1);

class XRPArcadeNewsletterPage
{
    public function init_hooks()
    {
        add_shortcode('xrparcade_newsletter', [$this, 'shortcode']);
        add_action('the_post', [$this, 'xrparcade_die_if_post_newsletter']);
    }

    public function shortcode()
    {
        // stops the query filter from excluding newsletter category
        define('XRPARCADE_NEWSLETTER_SHORTCODE', true);

        $this->shortcode_show_newsletters();
    }

    private function shortcode_show_newsletters()
    {
        $query = new WP_Query([
            'cat' => '73',
        ]);

        while($query->have_posts()) {
            $query->the_post();
            $link = esc_url(get_permalink());

            echo '<div class="newsletter-week" style="margin: 0 0 30px 0">';
            echo '<div class="newsletter-photo" style="width: 40%; float: left; padding: 0 20px;">';
            echo '<a href="' . $link . '">';
            echo '<img src="' . get_the_post_thumbnail_url() . '" />';
            echo '</a>';
            echo '</div>';
            echo '<div class="newsletter-details" style="width: 60%; float: left">';
            echo '<a href="' . $link . '">';
            echo '<h2>' . get_the_title() . '</h2>';
            echo '</a>';
            echo '<p style="text-align: justify">' . get_the_excerpt() . '</p>';
            echo '</div>';
            echo '</div>';
        }

        previous_posts_link('&laquo; Newer');
        next_posts_link('Older &raquo;', $query->max_num_pages);

        wp_reset_postdata();
    }

    public function xrparcade_die_if_post_newsletter()
    {
        $post = get_post();
        if (is_int($post->post_category) && $post->post_category === 73) {
            return;
        }
        if (is_array($post->post_category) && !in_array(73, $post->post_category)) {
            return;
        }

        if ($this->current_user_can_view()) {
            return;
        }

        if (defined('XRPARCADE_NEWSLETTER_SHORTCODE') && XRPARCADE_NEWSLETTER_SHORTCODE) {
            return;
        }

        global $post; 
        $post = get_post(11549, OBJECT);
        setup_postdata($post);        
    }

    private function current_user_can_view(): bool
    {
        $userId = get_current_user_id();
        $subscriptionEndDate = get_user_meta($userId, 'subscription_end_date', true);
        $newsletter = get_user_meta($userId, 'newsletter', true);

        return !empty($newsletter) && new DateTime($subscriptionEndDate) > new DateTime();
    }
}