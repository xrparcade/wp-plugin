<?php
declare(strict_types=1);

class XRPArcadeYoutubeChannels
{
    const API_URL = 'https://www.googleapis.com/youtube/v3/channels';

    const TABLE_POST_ID = 11396;

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