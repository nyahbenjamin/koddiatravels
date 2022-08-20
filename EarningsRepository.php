<?php

namespace App\Repositories;

use App\Models\Earnings;

class EarningsRepository extends AbstractRepository
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
        $this->model = new Earnings();
    }

    public function totalEarnings($user_id = '')
    {
        // TODO: Implement totalEarnings() method.
        $model = new Earnings();
        $query = $model->newQuery();
        $query->selectRaw("SUM(total) as total_earnings, SUM(net_earnings) as total_net_earnings, SUM(balance) as total_balance,  SUM(total - net_earnings) as total_commission");
        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
        }
        return $query->first();
    }

}
