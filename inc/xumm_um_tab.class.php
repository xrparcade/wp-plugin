<?php
declare(strict_types=1);

class XummUMTab
{
    public function init_hooks()
    {
        add_filter('um_profile_tabs', [$this, 'xumm_um_tab_add'], 10, 1);
        add_action('um_profile_content_xumm_default', [$this, 'xumm_um_tab_content'], 10, 1);
        wp_enqueue_style('xrparcade_xumm_um_tab_css', '/wp-content/plugins/xrparcade/css/xumm-um-tab.css', [], '0.0.4');
    }

    public function xumm_um_tab_add($tabs)
    {
        if (!is_array($tabs)) {
            $tabs = [];
        }

        return array_merge($tabs, [
            'xumm' => [
                'name' => 'XUMM',
                'icon' => 'xrp-icon',
            ],
        ]);
    }

    public function xumm_um_tab_content($args)
    {
        ?>
        <p>In order to process supporter subscription payments you need to connect your XUMM. We will send you weekly payment requests, which you can decline at any time to stop your subscription.</p>
        <div style="margin-top: 30px">
            <?php the_widget('XummWidget'); ?>
        </div>
        <?php
    }
}