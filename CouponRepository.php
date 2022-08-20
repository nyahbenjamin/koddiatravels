<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Coupon;

class CouponRepository extends AbstractRepository
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
        $this->model = new Coupon();
    }

    public function checkCouponExists($coupon_code, $date)
    {
        $model = new Coupon();
        $data = $model->query()->where('code', $coupon_code)->whereRaw("{$date} >= start_date AND {$date} <= end_date")->where('status', 'publish')->get()->first();
        return $data;
    }

    public function checkExistsByTitle($coupon_id, $coupon_code)
    {
        $data = $this->model->where('id', '<>', $coupon_id)->where('code', $coupon_code)->get()->first();
        if (!is_null($data)) {
            return true;
        } else {
            return false;
        }
    }
}