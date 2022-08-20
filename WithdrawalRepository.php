<?php

namespace App\Repositories;

use App\Models\Withdrawal;

class WithdrawalRepository extends AbstractRepository
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
        $this->model = new Withdrawal();
    }

    public function sumByWhere($column, $where = null)
    {
        $model = new Withdrawal();
        $query = $model->newQuery();
        $query->selectRaw("SUM({$column}) AS sum");
        if (!empty($where)) {
            $query->where($where);
        }
        return $query->first();
    }

    public function countByWhere($where = null)
    {
        $model = new Withdrawal();
        $query = $model->newQuery();
        $query->selectRaw("COUNT(*) AS count");
        if (!empty($where)) {
            $query->where($where);
        }
        return $query->first();
    }
}
