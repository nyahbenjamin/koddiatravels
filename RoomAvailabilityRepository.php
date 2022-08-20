<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Room;
use App\Models\RoomAvailability;

class RoomAvailabilityRepository extends AbstractRepository
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
        $this->model = new RoomAvailability();
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
                $roomModel = new Room();
                $roomObject = $roomModel->query()->find($postID);
                $basePrice = $roomObject['base_price'];
                $exists->update([
                    'price' => $basePrice,
                    'is_base' => 1
                ]);
            }
        }
    }

    public function updateBookedData($check_in, $check_out, $rooms)
    {
        foreach ($rooms as $k => $v) {
            $roomObject = $this->model->query()->whereRaw("post_id = {$k} AND ISNULL(check_in) AND ISNULL(check_out)")->first();
            for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
                $check_exists = $this->model->query()
                    ->where('post_id', $k)
                    ->where('check_in', $i)
                    ->where('check_out', $i)->first();

                $data = [
                    'post_id' => $k,
                    'hotel_id' => $roomObject['hotel_id'],
                    'total_room' => $roomObject['total_room'],
                    'adult_number' => $roomObject['adult_number'],
                    'child_number' => $roomObject['child_number'],
                    'check_in' => $i,
                    'check_out' => $i,
                    'number' => $roomObject['number'],
                    'price' => $roomObject['price'],
                    'booked' => $v['number'],
                    'status' => 'available',
                    'is_base' => 1
                ];

                if ($check_exists) {
                    $check_exists->update([
                        'booked' => $v['number'] + $check_exists['booked']
                    ]);
                } else {
                    $this->model->query()->create($data);
                }
            }
        }
    }

    public function checkAvailabilityWithGuest($room_id, $check_in, $check_out, $number, $adult, $children)
    {
        $query = $this->model->query();
        $query->selectRaw("number, check_in, check_out, post_id, booked, status, (number - booked) AS avail_booked, adult_number, child_number");
        $query->where('post_id', $room_id);
        $query->whereRaw("(post_id = {$room_id} AND ((ISNULL(check_in) AND ISNULL(check_out)) OR ( (check_in >= {$check_in} AND check_in < {$check_out})))) AND (status = 'unavailable' OR (number - booked) < {$number} OR IFNULL(adult_number, 0) < {$adult} OR IFNULL(child_number, 0) < {$children})");
        return $query->get();
    }

    public function getNumberRoomAvailability($data)
    {
        $hotel_id = $data['hotel_id'];
        $check_in = strtotime($data['check_in']);
        $check_out = strtotime($data['check_out']);
        $number_room = $data['number_room'];
        $unavai_ids = $data['unavailable_id'];

        $dates = [];
        for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
            array_push($dates, $i);
        }

        $date_str = '';
        if (!empty($dates)) {
            $date_str = implode(',', $dates);
        }

        $query = $this->model->query();
        $query->where("hotel_id", $hotel_id);

        $query->selectRaw("number, check_in, check_out, post_id, booked, status, (number - booked) AS avail_booked");
        if (!empty($unavai_ids)) {
            $query->whereNotIn('post_id', $unavai_ids);
        }
        $query->whereRaw("((ISNULL(check_in) AND ISNULL(check_out)) OR check_in IN ({$date_str})) AND (status = 'available' AND (number - booked) >= {$number_room})");

        $res = $query->get();

        if (!$res->isEmpty()) {
            $temp = [];
            foreach ($res as $k => $v) {
                if (in_array($v['post_id'], array_keys($temp))) {
                    if ($v['avail_booked'] < $temp[$v['post_id']]) {
                        $temp[$v['post_id']] = $v['avail_booked'];
                    }
                } else {
                    $temp[$v['post_id']] = $v['avail_booked'];
                }
            }
            return $temp;
        } else {
            return [];
        }
    }

    public function getRoomUnavailable($data)
    {
        $hotel_id = $data['hotel_id'];
        $check_in = strtotime($data['check_in']);
        $check_out = strtotime($data['check_out']);
        $number_room = $data['number_room'];

        $dates = [];
        for ($i = $check_in; $i < $check_out; $i = strtotime('+1 days', $i)) {
            array_push($dates, $i);
        }

        $date_str = '';
        if (!empty($dates)) {
            $date_str = implode(',', $dates);
        }

        $query = $this->model->query();
        $query->where("hotel_id", $hotel_id);

        $query->selectRaw("number, check_in, check_out, post_id, booked, status, (number - booked) AS avail_booked");
        $query->whereRaw("((ISNULL(check_in) AND ISNULL(check_out)) OR check_in IN ({$date_str})) AND (status = 'unavailable' OR (number - booked) < {$number_room})");

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

    public function getHotelUnavailable($start, $end, $number_room, $number_adult, $number_child)
    {
        $query = $this->model->query();

        if (!$start && !$end) {
            $query->whereRaw("(ISNULL(check_in) AND ISNULL(check_out) AND (IFNULL(adult_number, 0) < {$number_adult} OR IFNULL(child_number, 0) < {$number_child} OR (CASE WHEN number > 0 THEN IFNULL(number, 0) - IFNULL(booked, 0) < {$number_room} END ) ))");
        } else {
            $dates = [];
            for ($i = $start; $i < $end; $i = strtotime('+1 days', $i)) {
                array_push($dates, $i);
            }

            $date_str = '';
            if (!empty($dates)) {
                $date_str = implode(',', $dates);
            }

            $query->whereRaw("(check_in IN ({$date_str}) AND (status = 'unavailable' OR IFNULL(adult_number, 0) < {$number_adult} OR IFNULL(child_number, 0) < {$number_child} OR (CASE WHEN number > 0 THEN IFNULL(number, 0) - IFNULL(booked, 0) < {$number_room} END ) )) OR (ISNULL(check_in) AND ISNULL(check_out) AND (IFNULL(adult_number, 0) < {$number_adult} OR IFNULL(child_number, 0) < {$number_child} OR (CASE WHEN number > 0 THEN IFNULL(number, 0) - IFNULL(booked, 0) < {$number_room} END ) ))");
        }

        return $query->get();
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

    public function getDataAvailability($post_id, $check_in, $check_out)
    {
        $data = $this->model->where('post_id', $post_id)
            ->where('check_in', '>=', $check_in)
            ->where('check_out', '<=', $check_out)
            ->get();
        return $data;
    }
}