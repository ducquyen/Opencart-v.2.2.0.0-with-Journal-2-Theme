<?php
/* @property ModelCatalogManufacturer model_catalog_manufacturer */
/* @property ModelCatalogCategory model_catalog_category */
class ControllerModuleJournal2StaticBanners extends Controller {

    private static $CACHEABLE = null;

    protected $data = array();

    protected function render() {
        if (version_compare(VERSION, '2.2', '<')) {
            $this->template = $this->config->get('config_template') . '/template/' . $this->template;
        }

        $this->template = str_replace($this->config->get('config_template') . '/template/' . $this->config->get('config_template') . '/template/', $this->config->get('config_template') . '/template/', $this->template);

        return Front::$IS_OC2 ? $this->load->view($this->template, $this->data) : parent::render();
    }

    public function __construct($registry) {
        parent::__construct($registry);
        if (!defined('JOURNAL_INSTALLED')) {
            return;
        }
        $this->load->model('journal2/module');
        $this->load->model('journal2/menu');
        $this->load->model('tool/image');

        if (self::$CACHEABLE === null) {
            self::$CACHEABLE = (bool)$this->journal2->settings->get('config_system_settings.static_banners_cache');
        }
    }

    public function index($setting) {
        if (!defined('JOURNAL_INSTALLED')) {
            return;
        }

        Journal2::startTimer(get_class($this));

        /* get module data from db */
        $module_data = $this->model_journal2_module->getModule($setting['module_id']);
        if (!$module_data || !isset($module_data['module_data']) || !$module_data['module_data']) return;
        $module_data = $module_data['module_data'];

        /* device detection */
        $this->data['disable_on_classes'] = array();

        if ($this->journal2->settings->get('responsive_design')) {
            $device = Journal2Utils::getDevice();

            if (Journal2Utils::getProperty($module_data, 'enable_on_phone', '1') == '0') {
                if ($device === 'phone') {
                    return;
                } else {
                    $this->data['disable_on_classes'][] = 'hide-on-phone';
                }
            }

            if (Journal2Utils::getProperty($module_data, 'enable_on_tablet', '1') == '0') {
                if ($device === 'tablet') {
                    return;
                } else {
                    $this->data['disable_on_classes'][] = 'hide-on-tablet';
                }
            }

            if (Journal2Utils::getProperty($module_data, 'enable_on_desktop', '1') == '0') {
                if ($device === 'desktop') {
                    return;
                } else {
                    $this->data['disable_on_classes'][] = 'hide-on-desktop';
                }
            }
        }

        /* css for top / bottom positions */
        if (in_array($setting['position'], array('top', 'bottom'))) {
            $padding = $this->journal2->settings->get('module_margins', 20) . 'px';
            /* outer */
            $css = Journal2Utils::getBackgroundCssProperties(Journal2Utils::getProperty($module_data, 'background'));
            $css[] = 'padding-top: ' . Journal2Utils::getProperty($module_data, 'margin_top', 0) . 'px';
            $css[] = 'padding-bottom: ' . Journal2Utils::getProperty($module_data, 'margin_bottom', 0) . 'px';
            $this->journal2->settings->set('module_journal2_static_banners_' . $setting['module_id'], implode('; ', $css));
            $this->journal2->settings->set('module_journal2_static_banners_' . $setting['module_id'] . '_classes', implode(' ', $this->data['disable_on_classes']));

            /* inner css */
            $css = array();
            if (Journal2Utils::getProperty($module_data, 'fullwidth')) {
                $css[] = 'max-width: 100%';
                $css[] = 'padding-left: ' . $padding;
                $css[] = 'padding-right: ' . $padding;
            } else {
                $css[] = 'max-width: ' . $this->journal2->settings->get('site_width', 1024) . 'px';
                $css = array_merge($css, Journal2Utils::getBackgroundCssProperties(Journal2Utils::getProperty($module_data, 'module_background')));
                if (Journal2Utils::getProperty($module_data, 'module_padding')) {
                    $this->data['gutter_on_class'] = 'gutter-on';
                    $css[] = 'padding: 20px';
                }
            }
            $this->data['css'] = implode('; ', $css);
        }

        $cache_property = "module_journal_static_banners_{$setting['module_id']}_{$setting['layout_id']}_{$setting['position']}";

        $cache = $this->journal2->cache->get($cache_property);

        if ($cache === null || self::$CACHEABLE !== true) {
            $module = mt_rand();

            /* set global module properties */
            $this->data['module'] = $module;
            $this->data['title'] = Journal2Utils::getProperty($module_data, 'module_title.value.' . $this->config->get('config_language_id'), '');

            /* icon */
            $this->data['icon'] = Journal2Utils::getIconOptions2(Journal2Utils::getProperty($module_data, 'icon'));

            /* bgcolor*/
            $this->data['bgcolor'] = Journal2Utils::getProperty($module_data, 'bgcolor.value.color');

            /* image border */
            $this->data['image_border'] = implode('; ', Journal2Utils::getBorderCssProperties(Journal2Utils::getProperty($module_data, 'image_border')));

            /* sort sections */
            $sections = Journal2Utils::getProperty($module_data, 'sections', array());
            $sections = Journal2Utils::sortArray($sections);

            /* generate sections */
            $this->data['sections'] = array();
            foreach ($sections as $section) {
                if (!$section['status']) continue;
                $image = Journal2Utils::getProperty($section, 'image');
                if (is_array($image)) {
                    $image = Journal2Utils::getProperty($image, $this->config->get('config_language_id'));
                }
                if (!$image || !file_exists(DIR_IMAGE . $image)) {
                    $image = Front::$IS_OC2 ? 'no_image.png' : 'no_image.jpg';
                }
                list($width, $height) = getimagesize(DIR_IMAGE . $image);
                $this->data['sections'][] = array(
                    'type'          => 'image',
                    'link'          => $this->model_journal2_menu->getLink(Journal2Utils::getProperty($section, 'link')),
                    'target'        => Journal2Utils::getProperty($section, 'link_new_window') ? 'target="_blank"' : '',
                    'image'         => Journal2Utils::resizeImage($this->model_tool_image, $image),
                    'image_title'   => Journal2Utils::getProperty($section, 'image_title.value.' . $this->config->get('config_language_id'), ''),
                    'image_width'   => $width,
                    'image_height'  => $height
                );
            }

            /* carousel mode */
            $this->data['carousel'] = Journal2Utils::getProperty($module_data, 'carousel', '0');
            if (Journal2Utils::getProperty($module_data, 'autoplay')) {
                $this->data['autoplay'] = (int)Journal2Utils::getProperty($module_data, 'transition_delay', 3000);
            } else {
                $this->data['autoplay'] = false;
            }
            $this->data['slide_speed'] = (int)Journal2Utils::getProperty($module_data, 'transition_speed', 400);
            $this->data['pause_on_hover'] = Journal2Utils::getProperty($module_data, 'pause_on_hover');
            $this->data['touch_drag'] = Journal2Utils::getProperty($module_data, 'touch_drag');
            $this->data['default_section'] = '';
            $this->data['arrows'] = Journal2Utils::getProperty($module_data, 'arrows');
            $this->data['bullets'] = Journal2Utils::getProperty($module_data, 'bullets');

            /* grid classes */
            if (in_array($setting['position'], array('column_left', 'column_right'))) {
                $this->data['grid_classes'] = $this->data['carousel'] ? '' : 'xs-100 sm-100 md-100 lg-100 xl-100';
                $this->data['grid'] = $this->data['carousel'] ? array(
                    array(0, 1),
                    array(470, 1),
                    array(760, 1),
                    array(980, 1),
                    array(1100, 1)
                ) : array();
            } else {
                $columns = in_array($setting['position'], array('top', 'bottom')) ? 0 : $this->journal2->settings->get('config_columns_count', 0);
                $this->data['grid_classes'] = $this->data['carousel'] ? '' : Journal2Utils::getProductGridClasses(Journal2Utils::getProperty($module_data, 'items_per_row.value'), $this->journal2->settings->get('site_width', 1024), $columns);
                $grid = Journal2Utils::getItemGrid(Journal2Utils::getProperty($module_data, 'items_per_row.value'), $this->journal2->settings->get('site_width', 1024), $columns);
                $this->data['grid'] = $this->data['carousel'] ? array(
                    array(0, (int)$grid['xs']),
                    array(470, (int)$grid['sm']),
                    array(760, (int)$grid['md']),
                    array(980, (int)$grid['lg']),
                    array(1100, (int)$grid['xl'])
                ) : array();
            }

            $this->template = 'journal2/module/static_banners.tpl';

            if (self::$CACHEABLE === true) {
                $html = Minify_HTML::minify($this->render(), array(
                    'xhtml' => false,
                    'jsMinifier' => 'j2_js_minify'
                ));
                $this->journal2->cache->set($cache_property, $html);
            }
        } else {
            $this->template = 'journal2/cache/cache.tpl';
            $this->data['cache'] = $cache;
        }

        $output = $this->render();

        Journal2::stopTimer(get_class($this));

        return $output;
    }

}
