<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Order;

class OrderRepository extends AbstractRepository
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
        $this->model = new Order();
    }

    public function getOrderItemsGroupDate($post_id, $start_date, $end_date, $post_type)
    {
        $model = new Order();
        $query = $model->query();

        $pending = GMZ_STATUS_PENDING;
        $incomplete = GMZ_STATUS_INCOMPLETE;
        $complete = GMZ_STATUS_COMPLETE;

        $query->select('*');
        $query->selectRaw("SUM((end_time - start_time)/60) as number_minute, COUNT(id) as count_booking");

        $query->whereRaw("((start_date >= {$start_date} AND end_date <= {$end_date}) OR (start_date <= {$start_date} AND end_date >= {$start_date}) OR (start_date <= {$end_date} AND end_date >= {$end_date}))");

        $query->whereRaw("post_id = {$post_id} AND `status` IN ('{$pending}','{$incomplete}', '{$complete}')");
        $query->where('post_type', $post_type);

        $query->groupBy(['start_date']);

        return $query->get();
    }

    public function getOrderItemsWithTime($post_id, $start_date, $end_date, $start_time, $end_time, $post_type, $booking_type)
    {
        $model = new Order();
        $query = $model->query();

        $pending = GMZ_STATUS_PENDING;
        $incomplete = GMZ_STATUS_INCOMPLETE;
        $complete = GMZ_STATUS_COMPLETE;

        $query->whereRaw("((start_date >= {$start_date} AND end_date <= {$end_date}) OR (start_date <= {$start_date} AND end_date >= {$start_date}) OR (start_date <= {$end_date} AND end_date >= {$end_date}))");
        if (in_array($post_type, [GMZ_SERVICE_APARTMENT, GMZ_SERVICE_SPACE, GMZ_SERVICE_TOUR]) && $booking_type == 'per_hour') {
            $query->whereRaw("((start_time >= {$start_time} AND end_time <= {$end_time}) OR (start_time <= {$start_time} AND end_time >= {$start_time}) OR (start_date <= {$end_time} AND end_time >= {$end_time}))");
        }
        $query->whereRaw("post_id = {$post_id} AND `status` IN ('{$pending}','{$incomplete}', '{$complete}')");
        $query->where('post_type', $post_type);

        return $query->get();
    }

    public function getOrderItems($post_id, $start_date, $end_date, $post_type)
    {
        $model = new Order();
        $query = $model->query();

        $pending = GMZ_STATUS_PENDING;
        $incomplete = GMZ_STATUS_INCOMPLETE;
        $complete = GMZ_STATUS_COMPLETE;

        $query->whereRaw("((start_date >= {$start_date} AND end_date <= {$end_date}) OR (start_date <= {$start_date} AND end_date >= {$start_date}) OR (start_date <= {$end_date} AND end_date >= {$end_date})) AND post_id = {$post_id} AND `status` IN ('{$pending}','{$incomplete}', '{$complete}')");
        $query->where('post_type', $post_type);

        return $query->get();
    }

    public function appendChangeLog($id, $user, $action)
    {
        $this->model->appendChangeLog($id, $user, $action);
    }

    public function getRecentDeals($userID, $limit = 10)
    {

        $payment_completed = GMZ_PAYMENT_COMPLETED;
        $status_completed = GMZ_STATUS_COMPLETE;
        $status_refuned = GMZ_STATUS_REFUNDED;

        $model = new Order();
        $query = $model->newQuery();

        $query->select('id', 'sku', 'total', 'commission', 'status', 'updated_at');
        if ($userID != -1) {
            $query->where("owner", "=", $userID);
        }
        $query->whereIn("status", [$status_completed, $status_refuned]);
        $query->orderBy('updated_at', 'desc');
        $query->limit($limit);
        return $query->get();
    }

    public function getRevenue($userID, $startDate, $endDate)
    {

        $status_completed = GMZ_STATUS_COMPLETE;

        $model = new Order();
        $query = $model->newQuery();

        $query->select('id', 'post_type', 'total', 'status', 'updated_at');
        $query->selectRaw("SUM((total - (total * commission)/100)) as order_total, SUM(total) as sum_total, date(updated_at) as order_date, COUNT(*) as order_count");
        if ($userID != -1) {
            $query->where("owner", "=", $userID);
        }
        $query->where("status", "=", $status_completed);
        $query->whereDate('updated_at', '>=', $startDate);
        $query->whereDate('updated_at', '<=', $endDate);
        $query->orderBy('updated_at', 'desc');
        $query->groupByRaw("order_date");
        return $query->get();
    }

    public function getMinDate($userID)
    {

        $model = new Order();
        $query = $model->newQuery();

        $query->selectRaw("date(updated_at) AS min_date");
        if ($userID != -1) {
            $query->where('owner', '=', $userID);
        }
        $query->orderBy('updated_at');
        return $query->first();
    }

    public function whereWithLimit($where = [], $limit = 10)
    {
        return $this->model
            ->select('id', 'sku', 'payment_type', 'payment_status', 'total', 'commission', 'status', 'updated_at')
            ->where($where)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getOrderPaginate($number = 10, $whereRaw, $orderBy = 'id', $order = 'DESC', $withTerm = false)
    {

        $model = new Order();
        $query = $model->newQuery();

        if (!empty($whereRaw)) {
            $query->whereRaw($whereRaw);
        }

        if ($withTerm) {
            $query->with('TermRelation.Term.Taxonomy');
        }

        if ($orderBy && ($order == 'DESC')) {
            $query->orderByDesc($orderBy);
        } elseif ($orderBy) {
            $query->orderBy($orderBy);
        } else {
            $query->orderByDesc('id');
        }
        return $query->paginate($number);
    }

    public function searchPaginate($number, $string, $userID = false)
    {
        // TODO: Implement search() method.
        $model = new Order();
        $query = $model->newQuery();
        $query->selectRaw('users.email AS owner_email, gmz_order.*');
        $query->join('users', 'gmz_order.owner', '=', 'users.id');
        $query->whereRaw("users.email LIKE '{$string}%' OR gmz_order.email LIKE '{$string}%' OR gmz_order.phone LIKE '%{$string}%' OR sku LIKE '%{$string}'");
        return $query->paginate($number);

    }

    public function totalOrders($user_id = '')
    {
        $query = $this->model->where('status', '=', GMZ_STATUS_COMPLETE);
        if (!empty($user_id)) {
            $query->where('owner', $user_id);
        }
        return $query->count();
    }

    public function getStatisticsPerDay($limit, $user_id = '')
    {

        $model = new Order();
        $query = $model->newQuery();

        $query->select('id')
            ->selectRaw("SUM((total - (total * commission)/100)) as net_earn, SUM(total) as sum_total, date(updated_at) as order_date, COUNT(*) as order_count");
        if (!empty($user_id)) {
            $query->where('owner', $user_id);
        }
        $query->orderByDesc('updated_at')->limit($limit)->groupByRaw("order_date");
        return $query->get();
    }

    public function getPendingOrders($user_id = '')
    {

        $payment_complete = GMZ_PAYMENT_COMPLETED;
        $status_cancelled = GMZ_STATUS_CANCELLED;
        $status_incomplete = GMZ_STATUS_INCOMPLETE;
        $current_time = TIME();

        $model = new Order();
        $query = $model->newQuery();

        $query->select('status')
            ->selectRaw("COUNT(*) AS tasks")
            ->whereRaw("(status = '{$status_cancelled}' AND payment_status = '{$payment_complete}') OR (status = '{$status_incomplete}' AND payment_type = 'bank_transfer' AND start_date > {$current_time})");
        if (!empty($user_id)) {
            $query->where('owner', $user_id);
        }
        $query->groupByRaw("status");
        return $query->get();
    }

}