<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Language;

class LanguageRepository extends AbstractRepository
{
    private static $_inst;
    private static $_language;

    public static function inst()
    {
        if (empty(self::$_inst)) {
            self::$_inst = new self();
        }
        return self::$_inst;
    }

    public function __construct()
    {
        $this->model = new Language();
    }

    public function getAllLanguages()
    {
        if(isset(self::$_language)){
            $languages = self::$_language;
        }else {
            $languages = $this->model->where('status', 'on')->orderBy('priority', 'ASC')->get();
            self::$_language = $languages;
        }
        return $languages;
    }
}