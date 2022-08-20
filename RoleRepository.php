<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Role;

class RoleRepository extends AbstractRepository
{
    private static $_inst;

    public static function inst()
    {
        if (empty(self::$_inst)) {
            self::$_inst = new self();
        }
        return self::$_inst;
    }

    public function __construct()
    {
        $this->model = new Role();
    }
}