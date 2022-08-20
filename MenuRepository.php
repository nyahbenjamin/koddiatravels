<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Menu;

class MenuRepository extends AbstractRepository
{
    private static $_inst;
    private static $_menu = [];

    public static function inst()
    {
        if (empty(self::$_inst)) {
            self::$_inst = new self();
        }
        return self::$_inst;
    }

    public function __construct()
    {
        $this->model = new Menu();
    }

    public function getMenuByID($menuID)
    {
        if(isset(self::$_menu[$menuID])){
            $menu = self::$_menu[$menuID];
        }else{
            $menu = $this->where(['menu_id' => $menuID], true);
            self::$_menu[$menuID] = $menu;
        }
        return $menu;
    }
}