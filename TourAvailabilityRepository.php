<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Tour;
use App\Models\TourAvailability;

class TourAvailabilityRepository extends AbstractRepository
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
        $this->model = new TourAvailability();
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
                $tourModel = new Tour();
                $tourObject = $tourModel->query()->find($postID);
                $adultPrice = $tourObject['adult_price'];
                $childrenPrice = $tourObject['children_price'];
                $infantPrice = $tourObject['infant_price'];
                $groupSize = $tourObject['group_size'];
                $exists->update([
                    'adult_price' => $adultPrice,
                    'children_price' => $childrenPrice,
                    'infant_price' => $infantPrice,
                    'group_size' => $groupSize,
                    'is_base' => 1
                ]);
            }
        }
    }

    public function updateBookedData($check_in, $check_out, $object, $adult, $children)
    {
        $check_exists = $this->model->query()
            ->where('post_id', $object['id'])
            ->where('check_in', $check_in)->first();

        $data = [
            'post_id' => $object['id'],
            'check_in' => $check_in,
            'check_out' => $check_in,
            'adult_price' => $object['adult_price'],
            'children_price' => $object['children_price'],
            'infant_price' => $object['infant_price'],
            'group_size' => $object['group_size'],
            'booked' => $adult + $children,
            'status' => 'available',
            'is_base' => 1
        ];

        if ($check_exists) {
            $check_exists->update([
                'booked' => (int)$check_exists['booked'] + $adult + $children
            ]);
        } else {
            $this->model->query()->create($data);
        }
    }

    public function getListUnavailable($data)
    {
        $check_in = strtotime($data['checkIn']);
        $check_out = strtotime($data['checkOut']);
        $number_days = gmz_date_diff($check_in, $check_out) + 1;

        $dates = [];
        for ($i = $check_in; $i <= $check_out; $i = strtotime('+1 days', $i)) {
            array_push($dates, $i);
        }

        $date_str = '';
        if (!empty($dates)) {
            $date_str = implode(',', $dates);
        }

        $query = $this->model->query();
        $query->selectRaw("check_in, check_out, post_id, booked, status");
        $query->whereRaw("(check_in IN ({$date_str})) AND (status = 'unavailable' OR booked >= group_size)");

        $res = $query->get();

        if (!$res->isEmpty()) {
            $count = [];
            $temp = [];
            foreach ($res as $k => $v) {
                if (!isset($count[$v['post_id']])) {
                    $count[$v['post_id']] = 1;
                } else {
                    $count[$v['post_id']]++;
                }
                if ($count[$v['post_id']] == $number_days) {
                    array_push($temp, $v['post_id']);
                }
            }
            return $temp;
        } else {
            return '';
        }
    }

    public function checkAvailability($post_id, $check_in, $check_out)
    {
        $checkAvail = $this->model->where('post_id', $post_id)
            ->where('check_in', $check_in)
            ->whereRaw("(booked >= group_size OR status = 'unavailable')")
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
        $data = $this->model->query()->where('post_id', $post_id)
            ->where('check_in', $check_in)
            ->first();

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