<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Option;

class OptionRepository extends AbstractRepository
{
    private static $_inst;
    private static $_themeOption;

    public static function inst()
    {
        if (empty(self::$_inst)) {
            self::$_inst = new self();
        }
        return self::$_inst;
    }

    public function __construct()
    {
        $this->model = new Option();
    }

    public function getOption($key)
    {
        if(isset(self::$_themeOption[$key])){
            $option = self::$_themeOption[$key];
        }else{
            $option = $this->model->where('name', $key)->get()->first();
            self::$_themeOption[$key] = $option;
        }
        return $option;
    }
}