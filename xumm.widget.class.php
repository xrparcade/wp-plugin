<?php
declare(strict_types=1);

class XummWidget extends WP_Widget
{
    /**
     * @var string
     */
    private $xummToken;

    /**
     * @var Xumm
     */
    private $xumm;

    function __construct()
    {
        $userId = get_current_user_id();
        $this->xummToken = get_user_meta($userId, 'xumm_access_token', true);

        $this->xumm = new Xumm();

        parent::__construct('XummWidget', 'XUMM');
    }

    /**
     * Draws widget front-end
     */
    public function widget($args, $instance)
    {
        extract($args);
        $title = apply_filters( 'widget_title', $instance['title'] );

        echo $before_widget;

        if (!empty($title)) {
            echo $before_title . $title . $after_title;
        }

        if (empty($this->xummToken)) {
            $this->dump_javascript_connect();
            echo '<span>Click to link your account with XUMM</span><br />';
            echo '<input type="image" src="/wp-content/plugins/xrparcade/images/logo-xumm.svg" onclick="xumm_connect()"/>';
        } else {
            echo '<span><strong>All set!</strong></span><br /><img src="/wp-content/plugins/xrparcade/images/logo-xumm.svg" />';
        }

        echo $after_widget;
    }

    public function dump_javascript_connect()
    {
        ?><script type="text/javascript" >
            function xumm_connect() {
                var data = {
                    'action': 'xumm_connect',
                };
    
                jQuery.post('/wp-admin/admin-ajax.php', data, function(response) {
                    try {
                        url = new URL(response);
                        window.location = url;
                    } catch (_) {
                    }
                });
            }
        </script><?php
    }

    /**
     * Processes widget form
     */
    public function form($instance)
    {
        if (!empty($instance['title'])) {
            $title = $instance[ 'title' ];
        }
        else {
            $title = 'Connect your XUMM';
        }
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <?php
    }
      
    // Updating widget replacing old instances with new
    public function update($new_instance, $old_instance)
    {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';

        return $instance;
    }
}