<?php
/**
 * Simple RP Menu plugin for Craft CMS 3.x
 *
 * This is a simple menu to add Singles, Structures, Channels, Categories, Custom menus (with description), etc to your name menu for CRAFT CMS V3.x
 *
 * @link      https://github.com/rpqa99
 * @copyright Copyright (c) 2022 rpqa99
 */

namespace rpqa99\simplerpmenu\services;


use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Category;
use rpqa99\simplerpmenu\models\SimpleRpMenuModel;
use rpqa99\simplerpmenu\SimpleRpMenu;
use rpqa99\simplerpmenu\records\SimpleRpMenuRecord;

/**
 * SimpleRpMenuService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    rpqa99
 * @package   SimpleRpMenu
 * @since     1.0.0
 */
class SimpleRpMenuService extends Component
{
    // Public Methods
    // =========================================================================

    public function getAllMenus($siteId) {
        return SimpleRpMenuRecord::find()
                    ->where(['site_id' => $siteId])
                    ->all();
    }

    public function getMenuById($id) {
        $record = SimpleRpMenuRecord::findOne([
            'id' => $id
        ]);
        return new SimpleRpMenuModel($record->getAttributes());
    }

    public function getMenuByHandle($handle) {
        return SimpleRpMenuRecord::findOne([
            'handle' => $handle
        ]);
    }

    public function getMenuByName($name) {
        return SimpleRpMenuRecord::findOne([
            'name' => $name
        ]);
    }

    public function deleteMenuById($id) {
        $record = SimpleRpMenuRecord::findOne([
            'id' => $id
        ]);

        if ($record) {
            SimpleRpMenu::$plugin->simplerpmenuItems->deleteItemsByMenuId($record);
            if ($record->delete()) {
                return 1;
            };
        }
    }

    public function saveMenu(SimpleRpMenuModel $model) {
        $record = false;
        if (isset($model->id)) {
            $record = SimpleRpMenuRecord::findOne( [
                'id' => $model->id
            ]);
        }

        if (!$record) {
            $record = new SimpleRpMenuRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->site_id = $model->site_id;

        $save = $record->save();
        if (!$save) {
            Craft::getLogger()->log( $record->getErrors(), LOG_ERR, 'rp-simple-menu' );
        }
        return $save;
    }

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     SimpleRpMenu::$plugin->simpleRpMenusService->getMenuHTML()
     *
     * @return mixed
     */
    public function getMenuHTML($handle = false, $config ) {
        if ($handle === false || ($menu = $this->getMenuByHandle($handle)) === null) {
            echo '<p>' . Craft::t('rp-simple-menu', 'A menu with this handle does not exist!') . '</p>';
            return;
        }

        $menu_id = '';
        $menu_class = '';
        $ul_class = '';
        $withoutContainer = false;
        $withoutUl = false;

        if (!empty($config)) {
            if (isset($config['menu-id'])) {
                $menu_id = ' id="' .$config['menu-id']. '"';
            }
            if (isset($config['menu-class'])) {
                $menu_class .= ' ' . $config['menu-class'];
            }
            if (isset($config['ul-class'])) {
                $ul_class = $config['ul-class'];
            }
            if (isset($config['without-container'])) {
                $withoutContainer = $config['without-container'];
            }
            if (isset($config['without-ul'])) {
                $withoutUl = $config['without-ul'];
            }
        }

        $localHTML = '';

        $menu_items = SimpleRpMenu::$plugin->simplerpmenuItems->getMenuItems($menu->id);
        foreach ($menu_items as $menu_item) {
            $localHTML .= $this->getMenuItemHTML($menu_item, $config);
        }

        if ($withoutUl !== true) {
            $localHTML = '<ul class="' . $ul_class . '">' . $localHTML . '</ul>';
        }

        if ($withoutContainer !== true) {
            $localHTML = '<div' . $menu_id . ' class="menu' . $menu_class . '">' . $localHTML . '</div>';
        }

        echo $localHTML;
    }

    private function getMenuItemHTML($menu_item, $config) {
        $menu_item_url = '';
        $ul_class = '';
        $menu_item_class = 'menu-item';
        $custom_url = $menu_item['custom_url'];
        $class = $menu_item['class'];
        $class_parent = $menu_item['class_parent'];

        $data_attributes = '';
        $data_json = $menu_item['data_json'];

        $menu_class = $class;
        $menu_item_class = $menu_item_class . ' ' .$class_parent;

        if (!empty($config)) {
            if (isset($config['li-class'])) {
                $menu_item_class .= ' ' . $config['li-class'];
            }

            if (isset($config['link-class'])) {
                $menu_class .= ' ' . $config['link-class'];
            }
        }

        if ($custom_url != '') {
            $menu_item_url = $this->replaceEnvironmentVariables($custom_url);
        } else {
            $entry = Entry::find()
                ->id($menu_item['entry_id'])
                ->one();

            if (!empty($entry) ) $menu_item_url = $entry->url;
            else {
                $entry = Category::find()
                ->id($menu_item['entry_id'])
                ->one();

                if (!empty($entry) ) $menu_item_url = $entry->url;
            }
        }

        if ($data_json) {
            $data_attributes = ' ';
            $data_json = explode(PHP_EOL, $data_json);
            foreach ($data_json as $data_item) {
                $data_item = explode(':', $data_item);
                $data_attributes .= trim($data_item[0]) . '="' .trim($data_item[1]). '"';
            }

        }

        //extract target option
        $target = $menu_item['target'];
        $noLink = $menu_item['noLink'];
        $customShortContent = $menu_item['customShortContent'];
        $title = $menu_item['title'];
        if($noLink){
            $menu_item_url = '';
            $menu_class .= ' nav-link';
        }

        $menuItemName = Craft::t('rp-simple-menu', $menu_item['name']);
        if($customShortContent){
            $menuItemName = $customShortContent;
        }

        if(!$title){
            $title = $menuItemName;
        }

        $current_active_url = Craft::$app->request->getUrl();
        $current_active_segments = explode('/', trim($current_active_url, '/'));

        if ($current_active_url != '' && $menu_item_url != '') {
            $menu_item_url_filtered = parse_url($menu_item_url, PHP_URL_PATH);
            $current_active_url = parse_url($current_active_url, PHP_URL_PATH);

            $menu_segments = explode('/', trim($menu_item_url_filtered, '/'));

            if ( $current_active_url == $menu_item_url_filtered || strpos($current_active_url, $menu_item_url_filtered) === 0) {
                $menu_class .= ' active';
                $menu_item_class .= ' current-menu-item';
            }

            if (count($current_active_segments) > 1) {
                $parent_segment = $current_active_segments[0];
        
                if (!empty($menu_segments) && $menu_segments[0] == $parent_segment) {
                    $menu_class .= ' parent-active';
                    $menu_item_class .= ' current-parent-menu';
                }
            }
        }
        $menu_item_class .= isset($menu_item['children'])?' dropdown':'';
        $localHTML = '';
        $localHTML .= '<li id="menu-item-' .$menu_item['id']. '" class="' .$menu_item_class. '">';

        if ($menu_item_url) {
            $localHTML .= '<a class="nav-link '. $menu_class .'" target="'. $target .'" title="' .$title. '" href="' .$menu_item_url. '"' .$data_attributes. '>' . $menuItemName . '</a>';
        } else {
            $localHTML .= '<span class="'. $menu_class. '"' .$data_attributes. ' title="' .$title. '">' . $menuItemName . '</span>';
        }
        if(isset($menu_item['children'])){
            $localHTML .='<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown"><span class="ddarow"></span></a>';
        }

        // if($hasShortDescp){
        //     $localHTML .='<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
        //     <span class="ddarow"></span>
        // </a><div class="dropdown-menu dropdown-lst-label">
        //                     <ul class=" container mega-menu px-0 px-lg-_5 px-xl-1_5">
        //                         <li class="nav-item">
        //                             <span data-bs-auto-close="outside" data-bs-toggle="dropdown" class="nav-link">'.$menu_item['name'].'</span>
        //                         </li>
        //                     </ul>
        //                 </div>';
        // }
        

        if (isset($menu_item['children'])) {

            if (isset($config['sub-menu-ul-class'])) {
                $ul_class = $config['sub-menu-ul-class'];
            }

            $localHTML .= '<div class="dropdown-menu dropdown-lst-label">';
                $localHTML .= '<ul class="'.$ul_class.'">';
                    foreach ( $menu_item['children'] as $child )
                    {
                    $localHTML .= $this->getMenuItemHTML($child, $config);
                    }
                $localHTML .= '</ul>';
            $localHTML .= '</div>';
        }
        $localHTML .= '</li>';

        return $localHTML;
    }

    private function replaceEnvironmentVariables($str) {
        $environmentVariables = Craft::$app->config->general->aliases;
        if (is_array($environmentVariables)) {
            $tmp = [];
            foreach ($environmentVariables as $tag => $val) {
                $tmp[sprintf("{%s}", $tag)] = $val;
            }
            $environmentVariables = $tmp;

            return str_replace(array_keys($environmentVariables), array_values($environmentVariables), $str);
        }
    }
}
