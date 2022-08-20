<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\AgentAvailability;

class AgentAvailabilityRepository extends AbstractRepository
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
        $this->model = new AgentAvailability();
    }

    public function checkAvailability($post_id, $check_in, $check_out)
    {
        $checkAvail = $this->model->where('post_id', $post_id)
            ->where('check_in', '>=', $check_in)
            ->where('check_out', '<=', $check_out)
            ->where('status', 'unavailable')
            ->get();

        if (!$checkAvail->isEmpty()) {
            return false;
        } else {
            return true;
        }
    }

    public function insertOrUpdate($data)
    {
        $checkExitst = $this->model->where([
            'post_id' => $data['post_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
        ])->get();

        if ($checkExitst->count() > 0) {
            $this->update($checkExitst[0]['id'], $data);
        } else {
            $this->create($data);
        }
    }

    public function getDataAvailability($post_id, $check_in, $check_out, $agent_service)
    {
        $data = $this->model->where('post_id', $post_id)
            ->where('check_in', '>=', $check_in)
            ->where('check_out', '<=', $check_out)
            ->where('post_type', $agent_service)
            ->get();
        return $data;
    }

    public function getSlotBooked(array $agent_id)
    {
        $this->model = new AgentAvailability();
        $table_name = $this->model->getTable();
        $query = $this->model->newQuery();
        $query->select('post_id', 'check_in', 'check_out');
        $query->where("status", 'booked');
        $query->whereIn("post_id", $agent_id);
        return $query->get()->toArray();
    }

    public function updateBookedData(int $check_in, int $check_out, int $agent_id, int $order_id, string $status)
    {
        $this->model = new AgentAvailability();
        $table_name = $this->model->getTable();
        $query = $this->model->newQuery();

        $check_exists = $query->where('order_id', $order_id)->first();

        $data = [
            'post_id' => $agent_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'status' => $status,
            'post_type' => GMZ_SERVICE_BEAUTY,
            'order_id' => $order_id
        ];

        if ($check_exists) {
            $this->model->query()->where('order_id', $order_id)->update($data);
        } else {
            $this->model->query()->create($data);
        }
    }
}