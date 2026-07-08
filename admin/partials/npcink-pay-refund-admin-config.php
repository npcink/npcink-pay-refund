<?php

if (!defined('ABSPATH')) {
    exit;
}

//配置菜单
if (!class_exists('Npcink_Pay_Refund_Admin_Config')) {
    class Npcink_Pay_Refund_Admin_Config
    {
        public static $plugin_name;
        public static $plugin_version;

        public static function run($name, $version)
        {
            self::$plugin_name = $name;
            self::$plugin_version = $version;

            add_action('admin_menu', array(__CLASS__, 'config_menu'));
            add_action('admin_init', array(__CLASS__, 'register_settings'));
            add_action('admin_enqueue_scripts', array(__CLASS__, 'load_admin_script'));
            add_action('wp_ajax_npcink_pay_refund_download_data', array(__CLASS__, 'npcink_pay_refund_download_data'));
            add_action('wp_ajax_npcink_pay_refund_search_refund_users', array(__CLASS__, 'search_refund_users'));
            add_action('wp_ajax_npcink_pay_refund_check_payment_config', array(__CLASS__, 'check_payment_config'));
        }

        public static function config_menu()
        {
            add_submenu_page(
                'plugins.php',
                '退款配置',
                '退款配置',
                'manage_options',
                'npcink_pay_refund_config',
                array(__CLASS__, 'menu_displays'),
                '90.1'
            );
        }

        public static function register_settings()
        {
            self::ensure_option_autoload_policy();

            register_setting(
                'npcink_pay_refund_config_group',
                'npcink_pay_refund_config',
                array(__CLASS__, 'sanitize_config')
            );
        }

        public static function menu_displays()
        {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('您没有权限访问此页面。', 'npcink-pay-refund'));
            }

            $config = self::get_config();
            $secrets = self::get_secrets();
            $selected_users = array_map('intval', (array) self::value($config, array('user', 'user'), array()));
            $selected_refund_users = self::get_refund_users_by_ids($selected_users);
            $links = self::value($config, array('user', 'link'), array());
            if (empty($links)) {
                $links = array(array('title' => '', 'url' => ''));
            }
?>
            <div class="wrap npc_style">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p class="description"><?php echo esc_html__('完成支付配置和退款权限配置后，才可使用对应退款功能。', 'npcink-pay-refund'); ?></p>

                <form method="post" action="options.php">
                    <?php settings_fields('npcink_pay_refund_config_group'); ?>

                    <nav class="nav-tab-wrapper npcink-pay-refund-settings-tabs" aria-label="<?php echo esc_attr__('退款配置分组', 'npcink-pay-refund'); ?>">
                        <a href="#npcink-pay-refund-tab-zfb" class="nav-tab nav-tab-active" data-npcink-pay-refund-tab="npcink-pay-refund-tab-zfb" aria-selected="true"><?php echo esc_html__('支付宝', 'npcink-pay-refund'); ?></a>
                        <a href="#npcink-pay-refund-tab-wx" class="nav-tab" data-npcink-pay-refund-tab="npcink-pay-refund-tab-wx" aria-selected="false"><?php echo esc_html__('微信', 'npcink-pay-refund'); ?></a>
                        <a href="#npcink-pay-refund-tab-authority" class="nav-tab" data-npcink-pay-refund-tab="npcink-pay-refund-tab-authority" aria-selected="false"><?php echo esc_html__('退款权限', 'npcink-pay-refund'); ?></a>
                        <a href="#npcink-pay-refund-tab-data" class="nav-tab" data-npcink-pay-refund-tab="npcink-pay-refund-tab-data" aria-selected="false"><?php echo esc_html__('数据与卸载', 'npcink-pay-refund'); ?></a>
                    </nav>

                    <section id="npcink-pay-refund-tab-zfb" class="npcink-pay-refund-tab-panel is-active">
                        <h2><?php echo esc_html__('支付宝', 'npcink-pay-refund'); ?></h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-zfb-appid"><?php echo esc_html__('APP ID', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <input class="regular-text" id="npcink-pay-refund-zfb-appid" name="npcink_pay_refund_config[zfb][appid]" type="text" value="<?php echo esc_attr(self::value($config, array('zfb', 'appid'))); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-zfb-private-key"><?php echo esc_html__('应用私钥', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <textarea class="large-text code" id="npcink-pay-refund-zfb-private-key" name="npcink_pay_refund_config[zfb][private_key]" rows="7" placeholder="<?php echo esc_attr(self::secret_placeholder($secrets, array('zfb', 'private_key'))); ?>"></textarea>
                                    <p class="description"><?php echo esc_html__('留空表示保留现有应用私钥。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-zfb-public-key"><?php echo esc_html__('支付宝公钥', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <textarea class="large-text code" id="npcink-pay-refund-zfb-public-key" name="npcink_pay_refund_config[zfb][public_key]" rows="7" placeholder="<?php echo esc_attr(self::secret_placeholder($secrets, array('zfb', 'public_key'))); ?>"></textarea>
                                    <p class="description"><?php echo esc_html__('留空表示保留现有支付宝公钥。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php self::render_payment_check_panel('zfb'); ?>
                    </section>

                    <section id="npcink-pay-refund-tab-wx" class="npcink-pay-refund-tab-panel">
                        <h2><?php echo esc_html__('微信', 'npcink-pay-refund'); ?></h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-wx-mch-id"><?php echo esc_html__('商户号', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <input class="regular-text" id="npcink-pay-refund-wx-mch-id" name="npcink_pay_refund_config[wx][mch_id]" type="text" value="<?php echo esc_attr(self::value($config, array('wx', 'mch_id'))); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-wx-cert-api"><?php echo esc_html__('证书序列号', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <input class="regular-text" id="npcink-pay-refund-wx-cert-api" name="npcink_pay_refund_config[wx][cert_api]" type="text" value="<?php echo esc_attr(self::value($config, array('wx', 'cert_api'))); ?>">
                                    <p class="description"><?php echo esc_html__('商户 API 证书序列号，用于生成微信支付请求签名。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-wx-platform-key-id"><?php echo esc_html__('微信支付公钥 ID / 平台证书序列号', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <input class="regular-text" id="npcink-pay-refund-wx-platform-key-id" name="npcink_pay_refund_config[wx][platform_key_id]" type="text" value="<?php echo esc_attr(self::value($config, array('wx', 'platform_key_id'))); ?>">
                                    <p class="description"><?php echo esc_html__('用于校验微信支付 API v3 应答签名；填写微信支付公钥 ID，或填写平台证书序列号。退款功能必须配置。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-wx-cert-key"><?php echo esc_html__('商户私钥', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <textarea class="large-text code" id="npcink-pay-refund-wx-cert-key" name="npcink_pay_refund_config[wx][cert_key]" rows="7" placeholder="<?php echo esc_attr(self::secret_placeholder($secrets, array('wx', 'cert_key'))); ?>"></textarea>
                                    <p class="description"><?php echo esc_html__('留空表示保留现有商户私钥。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="npcink-pay-refund-wx-platform-public-key"><?php echo esc_html__('微信支付公钥 / 平台证书', 'npcink-pay-refund'); ?></label></th>
                                <td>
                                    <textarea class="large-text code" id="npcink-pay-refund-wx-platform-public-key" name="npcink_pay_refund_config[wx][platform_public_key]" rows="7" placeholder="<?php echo esc_attr(self::secret_placeholder($secrets, array('wx', 'platform_public_key'))); ?>"></textarea>
                                    <p class="description"><?php echo esc_html__('用于校验微信支付 API v3 应答签名；填写微信支付公钥，或填写平台证书 PEM。留空表示保留现有值。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php self::render_payment_check_panel('wx'); ?>
                    </section>

                    <section id="npcink-pay-refund-tab-authority" class="npcink-pay-refund-tab-panel">
                        <h2><?php echo esc_html__('退款权限', 'npcink-pay-refund'); ?></h2>
                        <div class="npcink-pay-refund-settings-section">
                            <div class="npcink-pay-refund-settings-section-header">
                                <h3><?php echo esc_html__('退款专员', 'npcink-pay-refund'); ?></h3>
                                <p><?php echo esc_html__('管理员默认拥有退款权限，无需添加；只有作者及以上权限的非管理员用户可以被添加为退款专员。', 'npcink-pay-refund'); ?></p>
                            </div>
                            <?php self::render_user_picker($selected_refund_users); ?>
                        </div>

                        <div class="npcink-pay-refund-settings-section">
                            <div class="npcink-pay-refund-settings-section-header npcink-pay-refund-settings-section-header-inline">
                                <div>
                                    <h3><?php echo esc_html__('可访问页面', 'npcink-pay-refund'); ?></h3>
                                    <p><?php echo esc_html__('退款专员登录后台后，仅放行这里配置的管理页面。', 'npcink-pay-refund'); ?></p>
                                </div>
                                <button type="button" class="button" id="npcink-pay-refund-add-link-row"><?php echo esc_html__('添加页面', 'npcink-pay-refund'); ?></button>
                            </div>
                            <table class="widefat striped npcink-pay-refund-link-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('名称', 'npcink-pay-refund'); ?></th>
                                        <th><?php echo esc_html__('链接', 'npcink-pay-refund'); ?></th>
                                        <th class="npcink-pay-refund-link-action-column"><?php echo esc_html__('操作', 'npcink-pay-refund'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="npcink-pay-refund-access-links">
                                    <?php foreach ($links as $index => $link) : ?>
                                        <?php self::render_link_row($index, $link); ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <script type="text/html" id="npcink-pay-refund-link-row-template">
                                <?php self::render_link_row('__INDEX__', array('title' => '', 'url' => '')); ?>
                            </script>
                        </div>
                    </section>

                    <section id="npcink-pay-refund-tab-data" class="npcink-pay-refund-tab-panel">
                        <h2><?php echo esc_html__('数据与卸载', 'npcink-pay-refund'); ?></h2>
                        <div class="npcink-pay-refund-settings-section">
                            <div class="npcink-pay-refund-settings-section-header">
                                <h3><?php echo esc_html__('运行说明', 'npcink-pay-refund'); ?></h3>
                            </div>
                            <table class="widefat striped npcink-pay-refund-info-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('退款记录', 'npcink-pay-refund'); ?></th>
                                        <td><?php echo esc_html__('统计数据保存在数据库表 npcink_pay_refund_order 中。', 'npcink-pay-refund'); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('退款原因', 'npcink-pay-refund'); ?></th>
                                        <td><?php echo esc_html__('退款原因仅自己可见。', 'npcink-pay-refund'); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('操作入口', 'npcink-pay-refund'); ?></th>
                                        <td><?php echo esc_html__('退款操作界面在“仪表盘” -> “订单退款”中。', 'npcink-pay-refund'); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('支付密钥', 'npcink-pay-refund'); ?></th>
                                        <td><?php echo esc_html__('支付密钥已单独保存；留空密钥字段会保留现有值。', 'npcink-pay-refund'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="npcink-pay-refund-settings-section">
                            <div class="npcink-pay-refund-settings-section-header">
                                <h3><?php echo esc_html__('数据导出', 'npcink-pay-refund'); ?></h3>
                            </div>
                            <p>
                                <button id="npcink-pay-refund-download" class="button button-secondary" type="button">
                                    <?php echo esc_html__('下载全部退款记录表格', 'npcink-pay-refund'); ?>
                                </button>
                            </p>
                        </div>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php echo esc_html__('删除数据库表', 'npcink-pay-refund'); ?></th>
                                <td>
                                    <label>
                                        <input name="npcink_pay_refund_config[config][mysql]" type="checkbox" value="1" <?php checked((int) self::value($config, array('config', 'mysql'), 1), 1); ?>>
                                        <?php echo esc_html__('卸载插件时删除退款记录表。', 'npcink-pay-refund'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('清空设置选项', 'npcink-pay-refund'); ?></th>
                                <td>
                                    <label>
                                        <input name="npcink_pay_refund_config[config][config]" type="checkbox" value="1" <?php checked((int) self::value($config, array('config', 'config'), 1), 1); ?>>
                                        <?php echo esc_html__('卸载插件时删除退款配置和密钥配置。', 'npcink-pay-refund'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <?php submit_button(); ?>
                </form>
            </div>
<?php
        }

        public static function render_link_row($index, $link)
        {
            $title = self::value($link, array('title'));
            $url = self::value($link, array('url'));
?>
            <tr class="npcink-pay-refund-link-row">
                <td>
                    <input type="text" name="npcink_pay_refund_config[user][link][<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr__('例如：订单退款', 'npcink-pay-refund'); ?>" class="regular-text">
                </td>
                <td>
                    <input type="url" name="npcink_pay_refund_config[user][link][<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($url); ?>" placeholder="<?php echo esc_attr__('例如：https://example.com/wp-admin/admin.php?page=refund', 'npcink-pay-refund'); ?>" class="large-text">
                </td>
                <td>
                    <button type="button" class="button npcink-pay-refund-remove-link-row"><?php echo esc_html__('移除', 'npcink-pay-refund'); ?></button>
                </td>
            </tr>
<?php
        }

        public static function render_user_picker($selected_users)
        {
?>
            <div class="npcink-pay-refund-user-picker">
                <div class="npcink-pay-refund-user-search">
                    <label for="npcink-pay-refund-refund-user-search" class="screen-reader-text"><?php echo esc_html__('搜索退款专员', 'npcink-pay-refund'); ?></label>
                    <input type="search" id="npcink-pay-refund-refund-user-search" class="regular-text" placeholder="<?php echo esc_attr__('搜索用户名、昵称或邮箱', 'npcink-pay-refund'); ?>" autocomplete="off">
                    <span class="spinner" id="npcink-pay-refund-refund-user-spinner"></span>
                </div>
                <div class="npcink-pay-refund-user-search-results" id="npcink-pay-refund-refund-user-results" aria-live="polite"></div>
                <table class="widefat striped npcink-pay-refund-user-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('姓名', 'npcink-pay-refund'); ?></th>
                            <th><?php echo esc_html__('账号', 'npcink-pay-refund'); ?></th>
                            <th><?php echo esc_html__('角色', 'npcink-pay-refund'); ?></th>
                            <th class="npcink-pay-refund-user-action-column"><?php echo esc_html__('操作', 'npcink-pay-refund'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="npcink-pay-refund-selected-refund-users">
                        <?php if (empty($selected_users)) : ?>
                            <tr data-empty-state="1">
                                <td colspan="4">
                                    <p class="description npcink-pay-refund-user-empty"><?php echo esc_html__('尚未添加退款专员。请搜索作者及以上权限的非管理员用户后添加。', 'npcink-pay-refund'); ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($selected_users as $user) : ?>
                            <?php self::render_selected_user($user); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php
        }

        public static function render_payment_check_panel($channel)
        {
            $label = 'wx' === $channel ? __('检测微信配置', 'npcink-pay-refund') : __('检测支付宝配置', 'npcink-pay-refund');
?>
            <div class="npcink-pay-refund-settings-section npcink-pay-refund-payment-check">
                <div class="npcink-pay-refund-settings-section-header npcink-pay-refund-settings-section-header-inline">
                    <div>
                        <h3><?php echo esc_html__('配置检测', 'npcink-pay-refund'); ?></h3>
                        <p><?php echo esc_html__('保存配置后检测 SDK、必填字段和密钥格式；不会发起真实退款。', 'npcink-pay-refund'); ?></p>
                    </div>
                    <button type="button" class="button npcink-pay-refund-check-payment-config" data-channel="<?php echo esc_attr($channel); ?>"><?php echo esc_html($label); ?></button>
                </div>
                <div class="npcink-pay-refund-payment-check-result" id="npcink-pay-refund-payment-check-<?php echo esc_attr($channel); ?>" aria-live="polite"></div>
            </div>
<?php
        }

        public static function render_selected_user($user)
        {
            $user_id = (int) self::value($user, array('id'), 0);
            $name = self::value($user, array('name'));
            $login = self::value($user, array('login'));
            $roles = (array) self::value($user, array('roles'), array());
?>
            <tr class="npcink-pay-refund-selected-user" data-user-id="<?php echo esc_attr($user_id); ?>">
                <td>
                    <input type="hidden" name="npcink_pay_refund_config[user][user][]" value="<?php echo esc_attr($user_id); ?>">
                    <strong><?php echo esc_html($name); ?></strong>
                </td>
                <td>
                    <?php
                    /* translators: 1: user login, 2: user ID. */
                    $user_meta = sprintf(__('账号：%1$s · ID：%2$d', 'npcink-pay-refund'), $login, $user_id);
                    echo esc_html($user_meta);
                    ?>
                </td>
                <td><?php echo esc_html(implode('、', $roles)); ?></td>
                <td><button type="button" class="button npcink-pay-refund-remove-refund-user"><?php echo esc_html__('移除', 'npcink-pay-refund'); ?></button></td>
            </tr>
<?php
        }

        public static function load_admin_script($hook)
        {
            if ('plugins_page_npcink_pay_refund_config' !== $hook) {
                return;
            }

            wp_enqueue_style(self::$plugin_name . '-admin', plugin_dir_url(dirname(__DIR__)) . 'admin/css/npcink-pay-refund-admin.css', array(), self::$plugin_version);
            wp_enqueue_script(self::$plugin_name . '-settings', plugin_dir_url(dirname(__DIR__)) . 'admin/js/npcink-pay-refund-settings.js', array('jquery'), self::$plugin_version, true);
            wp_localize_script(self::$plugin_name . '-settings', 'npcinkPayRefundSettings', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('npcink_pay_refund_action'),
                'strings' => array(
                    'searching' => __('正在搜索...', 'npcink-pay-refund'),
                    'typeToSearch' => __('请输入用户名、昵称或邮箱关键字搜索退款专员。', 'npcink-pay-refund'),
                    'noUsers' => __('没有找到可添加的作者及以上非管理员用户。', 'npcink-pay-refund'),
                    'searchFailed' => __('搜索失败，请稍后重试。', 'npcink-pay-refund'),
                    'alreadySelected' => __('已添加', 'npcink-pay-refund'),
                    'emptySelected' => __('尚未添加退款专员。请搜索作者及以上权限的非管理员用户后添加。', 'npcink-pay-refund'),
                    'checkingConfig' => __('正在检测配置...', 'npcink-pay-refund'),
                    'checkConfigFailed' => __('配置检测失败，请稍后重试。', 'npcink-pay-refund'),
                ),
            ));

            wp_add_inline_script(self::$plugin_name . '-settings', self::link_row_script());
        }

        public static function link_row_script()
        {
            return 'jQuery(function($){var allowedTabs={"npcink-pay-refund-tab-zfb":true,"npcink-pay-refund-tab-wx":true,"npcink-pay-refund-tab-authority":true,"npcink-pay-refund-tab-data":true};function activateTab(id){if(!allowedTabs[id]){id="npcink-pay-refund-tab-zfb";}var target=$(document.getElementById(id));if(!target.length){return;}$(".npcink-pay-refund-settings-tabs .nav-tab").removeClass("nav-tab-active").attr("aria-selected","false").filter(function(){return $(this).data("npcink-pay-refund-tab")===id;}).addClass("nav-tab-active").attr("aria-selected","true");$(".npcink-pay-refund-tab-panel").removeClass("is-active").attr("hidden",true);target.addClass("is-active").removeAttr("hidden");}$(".npcink-pay-refund-settings-tabs .nav-tab").on("click",function(event){event.preventDefault();var id=$(this).data("npcink-pay-refund-tab");activateTab(id);if(history.replaceState){history.replaceState(null,"","#"+id);}});activateTab(window.location.hash ? window.location.hash.substring(1) : "npcink-pay-refund-tab-zfb");$("#npcink-pay-refund-add-link-row").on("click",function(){var container=$("#npcink-pay-refund-access-links");var template=$("#npcink-pay-refund-link-row-template").html();var index=container.find(".npcink-pay-refund-link-row").length;container.append(template.replace(/__INDEX__/g,index));});$(document).on("click",".npcink-pay-refund-remove-link-row",function(){$(this).closest(".npcink-pay-refund-link-row").remove();});});';
        }

        public static function get_assignable_refund_users()
        {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
            ));
            $user_data = array();

            foreach ($users as $user) {
                if (!self::is_refund_user_assignable($user)) {
                    continue;
                }

                $user_data[] = self::format_refund_user($user);
            }
            return $user_data;
        }

        public static function get_refund_users_by_ids($user_ids)
        {
            $users = array();
            foreach (array_unique(array_map('absint', (array) $user_ids)) as $user_id) {
                $user = get_userdata($user_id);
                if ($user && self::is_refund_user_assignable($user)) {
                    $users[] = self::format_refund_user($user);
                }
            }
            return $users;
        }

        public static function npcink_pay_refund_download_data()
        {
            if (!check_ajax_referer('npcink_pay_refund_action', 'nonce', false)) {
                wp_send_json_error(array('message' => __('请求校验失败。', 'npcink-pay-refund')), 403);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('您没有权限下载退款记录。', 'npcink-pay-refund')), 403);
            }

            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'npcink_pay_refund_order');
            $limit = 5000;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom refund table export with a sanitized table name.
            $data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, n_amount, n_time, n_order, n_user, n_type, n_reason FROM {$table_name} ORDER BY id DESC LIMIT %d",
                    $limit + 1
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            $truncated = count($data) > $limit;
            if ($truncated) {
                $data = array_slice($data, 0, $limit);
            }

            wp_send_json_success(array(
                'rows' => $data,
                'truncated' => $truncated,
                'limit' => $limit,
            ));
        }

        public static function search_refund_users()
        {
            if (!check_ajax_referer('npcink_pay_refund_action', 'nonce', false)) {
                wp_send_json_error(array('message' => __('请求校验失败。', 'npcink-pay-refund')), 403);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('您没有权限搜索退款专员。', 'npcink-pay-refund')), 403);
            }

            $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
            if (strlen($term) < 1) {
                wp_send_json_success(array('users' => array()));
            }

            $user_query = new WP_User_Query(array(
                'number' => 10,
                'orderby' => 'display_name',
                'order' => 'ASC',
                'search' => '*' . $term . '*',
                'search_columns' => array('user_login', 'user_nicename', 'user_email', 'display_name'),
            ));

            $users = array();
            foreach ($user_query->get_results() as $user) {
                if (self::is_refund_user_assignable($user)) {
                    $users[] = self::format_refund_user($user);
                }
            }

            wp_send_json_success(array('users' => $users));
        }

        public static function check_payment_config()
        {
            if (!check_ajax_referer('npcink_pay_refund_action', 'nonce', false)) {
                wp_send_json_error(array('message' => __('请求校验失败。', 'npcink-pay-refund')), 403);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('您没有权限检测支付配置。', 'npcink-pay-refund')), 403);
            }

            $channel = isset($_POST['channel']) ? sanitize_key(wp_unslash($_POST['channel'])) : '';
            if (!in_array($channel, array('zfb', 'wx'), true)) {
                wp_send_json_error(array('message' => __('未知支付渠道。', 'npcink-pay-refund')), 400);
            }

            if ('zfb' === $channel) {
                $result = Npcink_Pay_Refund_Admin_Zfb::diagnose_config();
            } else {
                $result = Npcink_Pay_Refund_Admin_Wx::diagnose_config();
            }

            wp_send_json_success($result);
        }

        public static function sanitize_config($input)
        {
            $input = self::object_to_array(wp_unslash($input));
            if (!is_array($input)) {
                $input = array();
            }

            $config = self::default_config();
            $config['zfb']['appid'] = isset($input['zfb']['appid']) ? sanitize_text_field($input['zfb']['appid']) : '';

            $config['wx']['mch_id'] = isset($input['wx']['mch_id']) ? sanitize_text_field($input['wx']['mch_id']) : '';
            $config['wx']['cert_api'] = isset($input['wx']['cert_api']) ? sanitize_text_field($input['wx']['cert_api']) : '';
            $config['wx']['platform_key_id'] = isset($input['wx']['platform_key_id']) ? sanitize_text_field($input['wx']['platform_key_id']) : '';
            self::save_secrets($input);

            $config['user']['user'] = self::sanitize_refund_user_ids(self::value($input, array('user', 'user'), array()));

            if (!empty($input['user']['link']) && is_array($input['user']['link'])) {
                $config['user']['link'] = array();
                foreach ($input['user']['link'] as $link) {
                    $title = isset($link['title']) ? sanitize_text_field($link['title']) : '';
                    $url = isset($link['url']) ? esc_url_raw($link['url']) : '';
                    if ('' !== $title || '' !== $url) {
                        $config['user']['link'][] = array(
                            'title' => $title,
                            'url' => $url,
                        );
                    }
                }
            }

            $config['config']['mysql'] = !empty($input['config']['mysql']) ? 1 : 0;
            $config['config']['config'] = !empty($input['config']['config']) ? 1 : 0;

            add_action('shutdown', array(__CLASS__, 'ensure_option_autoload_policy'));

            return $config;
        }

        public static function sanitize_refund_user_ids($users)
        {
            if (empty($users) || !is_array($users)) {
                return array();
            }

            $user_ids = array();
            foreach ($users as $user_id) {
                $user_id = absint($user_id);
                $user = get_userdata($user_id);
                if ($user && self::is_refund_user_assignable($user)) {
                    $user_ids[] = $user_id;
                }
            }

            return array_values(array_unique($user_ids));
        }

        public static function is_refund_user_assignable($user)
        {
            if (!$user instanceof WP_User) {
                $user = get_userdata($user);
            }

            if (!$user || empty($user->ID)) {
                return false;
            }

            return user_can($user, 'publish_posts') && !user_can($user, 'manage_options');
        }

        public static function format_refund_user($user)
        {
            $editable_roles = get_editable_roles();
            $role_names = array();

            foreach ((array) $user->roles as $role) {
                if (isset($editable_roles[$role]['name'])) {
                    $role_names[] = translate_user_role($editable_roles[$role]['name']);
                } else {
                    $role_names[] = $role;
                }
            }

            return array(
                'id' => (int) $user->ID,
                'name' => $user->display_name,
                'login' => $user->user_login,
                'roles' => $role_names,
            );
        }

        public static function get_config()
        {
            $saved = get_option('npcink_pay_refund_config', array());
            return self::merge_config(self::object_to_array($saved), self::default_config());
        }

        public static function get_secrets()
        {
            $saved = self::object_to_array(get_option('npcink_pay_refund_secrets', array()));
            $legacy_config = self::object_to_array(get_option('npcink_pay_refund_config', array()));

            foreach (self::secret_fields() as $section => $fields) {
                if (!isset($saved[$section]) || !is_array($saved[$section])) {
                    $saved[$section] = array();
                }

                foreach ($fields as $field) {
                    if (empty($saved[$section][$field]) && !empty($legacy_config[$section][$field])) {
                        $saved[$section][$field] = $legacy_config[$section][$field];
                    }
                }
            }

            return $saved;
        }

        public static function save_secrets($input)
        {
            $secrets = self::get_secrets();

            foreach (self::secret_fields() as $section => $fields) {
                if (!isset($secrets[$section]) || !is_array($secrets[$section])) {
                    $secrets[$section] = array();
                }

                foreach ($fields as $field) {
                    if (isset($input[$section][$field])) {
                        $value = Npcink_Pay_Refund_Admin::sanitize_textarea_value($input[$section][$field]);
                        if ('' !== $value) {
                            $secrets[$section][$field] = $value;
                        }
                    }
                }
            }

            if (false === get_option('npcink_pay_refund_secrets', false)) {
                add_option('npcink_pay_refund_secrets', $secrets, '', 'no');
            } else {
                update_option('npcink_pay_refund_secrets', $secrets);
            }
            self::set_option_autoload_no('npcink_pay_refund_secrets');
        }

        public static function secret_fields()
        {
            return array(
                'zfb' => array('private_key', 'public_key'),
                'wx' => array('cert_key', 'platform_public_key'),
            );
        }

        public static function secret_placeholder($secrets, $path)
        {
            return '' !== self::value($secrets, $path, '') ? __('已配置，留空则不修改', 'npcink-pay-refund') : __('未配置', 'npcink-pay-refund');
        }

        public static function ensure_option_autoload_policy()
        {
            self::set_option_autoload_no('npcink_pay_refund_config');
            self::set_option_autoload_no('npcink_pay_refund_secrets');
        }

        public static function set_option_autoload_no($option_name)
        {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time option autoload policy maintenance.
            $wpdb->update(
                $wpdb->options,
                array('autoload' => 'no'),
                array('option_name' => $option_name),
                array('%s'),
                array('%s')
            );
        }

        public static function default_config()
        {
            return array(
                'zfb' => array(
                    'appid' => '',
                ),
                'wx' => array(
                    'mch_id' => '',
                    'cert_api' => '',
                    'platform_key_id' => '',
                ),
                'user' => array(
                    'user' => array(),
                    'link' => array(),
                ),
                'config' => array(
                    'mysql' => 1,
                    'config' => 1,
                ),
            );
        }

        public static function merge_config($saved, $defaults)
        {
            foreach ($defaults as $key => $default_value) {
                if (!isset($saved[$key])) {
                    $saved[$key] = $default_value;
                } elseif (is_array($default_value) && is_array($saved[$key])) {
                    $saved[$key] = self::merge_config($saved[$key], $default_value);
                }
            }

            return $saved;
        }

        public static function object_to_array($value)
        {
            if (is_object($value)) {
                $value = get_object_vars($value);
            }

            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = self::object_to_array($item);
                }
            }

            return $value;
        }

        public static function value($source, $path, $default = '')
        {
            if (!is_array($path)) {
                $path = array($path);
            }

            $value = self::object_to_array($source);
            foreach ($path as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    return $default;
                }
                $value = $value[$key];
            }

            return $value;
        }
    }
}
