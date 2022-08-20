<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Apartment;
use App\Models\ApartmentAvailability;

class ApartmentAvailabilityRepository extends AbstractRepository
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
        $this->model = new ApartmentAvailability();
    }

    public function removeCalendarItem($postID, $checkIn)
    {
        $exists = $this->model->query()
            ->where('post_id', $postID)
            ->where('check_in', $checkIn)
            ->first();

        if ($exists) {
            if ($exists['booked'] <= 0) {
                $exists->delete();
            } else {
                $apartmentModel = new Apartment();
                $apartmentObject = $apartmentModel->query()->find($postID);
                $basePrice = $apartmentObject['base_price'];
                $exists->update([
                    'price' => $basePrice,
                    'is_base' => 1
                ]);
            }
        }
    }

    public function updateBookedData($check_in, $check_out, $object)
    {
        for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
            $check_exists = $this->model->query()
                ->where('post_id', $object['id'])
                ->where('check_in', $i)
                ->where('check_out', $i)->first();

            $data = [
                'post_id' => $object['id'],
                'check_in' => $i,
                'check_out' => $i,
                'price' => $object['base_price'],
                'booked' => 1,
                'status' => 'available',
                'is_base' => 1
            ];

            if ($check_exists) {
                $check_exists->update([
                    'booked' => 1
                ]);
            } else {
                $this->model->query()->create($data);
            }
        }
    }

    public function getListUnavailable($data)
    {
        $check_in = strtotime($data['checkIn']);
        $check_out = strtotime($data['checkOut']);

        $dates = [];
        for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
            array_push($dates, $i);
        }

        $date_str = '';
        if (!empty($dates)) {
            $date_str = implode(',', $dates);
        }

        $query = $this->model->query();
        $query->selectRaw("check_in, check_out, post_id, booked, status");
        $query->whereRaw("((ISNULL(check_in) AND ISNULL(check_out)) OR check_in IN ({$date_str})) AND (status = 'unavailable' OR booked > 0)");

        $res = $query->get();

        if (!$res->isEmpty()) {
            $temp = [];
            foreach ($res as $k => $v) {
                if (!in_array($v['post_id'], $temp)) {
                    $temp[] = $v['post_id'];
                }
            }
            return $temp;
        } else {
            return '';
        }
    }

    public function checkAvailability($post_id, $check_in, $check_out)
    {
        $dates = [];
        for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
            array_push($dates, $i);
        }
        $checkAvail = $this->model->where('post_id', $post_id)
            ->whereIn('check_in', $dates)
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

    public function getDataAvailability($post_id, $check_in, $check_out)
    {
        $dates = [];
        for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
            array_push($dates, $i);
        }

        $data = $this->model->where('post_id', $post_id)
            ->whereIn('check_in', $dates)
            ->get();

        return $data;
    }

    public function getDataAvailabilityForCalendar($post_id, $check_in, $check_out)
    {
        $data = $this->model->where('post_id', $post_id)
            ->where('check_in', '>=', $check_in)
            ->where('check_out', '<=', $check_out)
            ->get();

        return $data;
    }
}