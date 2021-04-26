<?php
declare(strict_types=1);

class XRPArcadeYoutubeChannels
{
    const API_URL = 'https://www.googleapis.com/youtube/v3/channels';

    const TABLE_POST_ID = 11396;

    public function init_hooks() {
        add_shortcode('xrparcade_youtubers', [$this, 'youtubers_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'load_js']);
    }

    public function load_js()
    {
        wp_enqueue_script('xrparcade_youtubers_js','/wp-content/plugins/xrparcade/js/xrparcade.youtubers.js', [], '0.1.0');
    }

    public function youtubers_shortcode(): string
    {
        $channels = $this->channels();
        if (empty($channels) || count($channels) == 1) {
            return null;
        }

        // first element is header
        array_shift($channels);

        $channels = array_filter($channels, function ($channel) {
            return is_array($channel) && count($channel) == 3 && !empty($channel[0]) && !empty($channel[1]) && !empty($channel[2]);
        });

        // apparently Google prefixes UU for user uploads and UC for user channel
        // but the remainding of the key is identical.
        $channels = array_map(function ($channel) {
            $channel[1] = 'UU' . substr($channel[1], 2);
            
            return $channel;
        }, $channels);

        usort($channels, function ($a, $b) {
            return $a[2] < $b[2] ? 1 : -1;
        });

        $html = '
            <label>Youtuber:</label>
            <select id="youtuber-selection" name="youtuber-selection">
            ';
        foreach ($channels as $channel) {
            $html .= '<option value="' . $channel[1] . '">' . $channel[0] . '</option>';
        }
        $html .= '</select>';
        $html .= '
        <div id="xrparcade-youtuber">
        <iframe src="https://www.youtube.com/embed/?listType=playlist&list=' . $channels[0][1] . '" width="600" height="340"></iframe>
        </div>
        ';

        return $html;
    }

    public function update_channels()
    {
        $post = $this->get_youtubers_post();
        if (empty($post)) {
            return;
        }
        $channels = json_decode($post->post_content, true);
        if (empty($channels)) {
            return;
        }

        // first row is header
        for ($i = 1; $i < count($channels); $i++) {
            if (empty($channels[$i][0]) || empty($channels[$i][1])) {
                continue;
            }
            $channels[$i][2] = $this->channel_subscribers($channels[$i][1]);
        }

        $post->post_content = json_encode($channels);

        wp_update_post($post);
    }

    private function get_youtubers_post(): ?WP_Post
    {
        /**
         * @var WP_Post
         */
        $post = get_post(self::TABLE_POST_ID);
        if (!($post instanceof WP_Post)) {
            return null;
        }

        return $post;
    }

    public function channels(): array
    {
        $post = $this->get_youtubers_post();

        return json_decode($post->post_content, true);
    }

    private function channel_subscribers(string $channelId): ?int
    {
        $params = http_build_query([
            'part' => 'statistics',
            'id' => $channelId,
            'key' => YOUTUBE_API_KEY,
        ]);
            
        $data = wp_remote_get(self::API_URL . '?' . $params);

        if ($data instanceof WP_Error) {
            return null;
        }
        if ($data['response']['code'] !== 200) {
            return null;
        }

        $body = json_decode($data['body'], true);

        return (int) $body['items'][0]['statistics']['subscriberCount'];
    }
}