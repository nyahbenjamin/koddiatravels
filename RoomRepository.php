<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Room;

class RoomRepository extends AbstractRepository
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
        $this->model = new Room();
    }

    public function getRooms($data)
    {
        $hotel_id = $data['hotel_id'];
        $number_room = $data['number_room'];
        $adult = $data['adult'];
        $children = $data['children'];

        $query = $this->model->query();
        $query->where("hotel_id", $hotel_id);
        $query->where('number_of_adult', '>=', $adult);
        $query->where('number_of_children', '>=', $children);
        $query->where('number_of_room', '>=', $number_room);
        $query->where('status', 'publish');
        if (!empty($data['unavailable_id'])) {
            $query->whereNotIn('id', $data['unavailable_id']);
        }

        return $query->get();
    }
}