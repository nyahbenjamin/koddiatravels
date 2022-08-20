<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Space;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class SpaceRepository extends AbstractRepository
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
        $this->model = new Space();
    }

    public function getAllPostIDs($athor_id)
    {
        return $this->model->query()->where('author', $athor_id)->pluck('id')->toArray();
    }

    public function getWishlist($number, $wishlist)
    {
        return $this->model->query()->whereIn('id', $wishlist)->orderBy($this->model->getKeyName(), 'DESC')->paginate($number);
    }

    public function getSearchResult($data)
    {
        $this->model = new Space();
        $table = $this->model->getTable();
        $query = $this->model->query();

        $booking_type = '';
        if ($data['bookingType'] == 'day') {
            $booking_type = 'per_day';
        } elseif ($data['bookingType'] == 'hour') {
            $booking_type = 'per_hour';
        }

        $query->select("{$table}.*");

        if (!empty($data['lat']) && !empty($data['lng'])) {
            $distance = get_option('space_search_radius', '25');
            $distance = floatval($distance);
            $data['lat'] = esc_sql($data['lat']);
            $data['lng'] = esc_sql($data['lng']);
            $query->selectRaw("( 6371 * acos( cos( radians({$data['lat']}) ) * cos( radians( {$table}.location_lat ) ) * cos( radians( {$table}.location_lng ) - radians({$data['lng']}) ) + sin( radians({$data['lat']}) ) * sin( radians( {$table}.location_lat ) ) ) ) AS distance");
            $query->orHavingRaw("distance <= " . $distance);
            $query->orderByDesc('distance');
        } elseif (!empty($data['address'])) {
            $address = urldecode($data['address']);
            $data['address'] = esc_sql($data['address']);
            $query->whereRaw("{$table}.location_address LIKE '%{$address}%'");
            $query->orderByDesc("{$table}.id");
        }

        if (!empty($data['adult']) || !empty($data['children'])) {
            $guests = intval($data['adult']) + intval($data['children']);
            $query->whereRaw("number_of_guest >= {$guests}");
        }

        if (!empty($data['price_range'])) {
            $min_max = convert_price_range($data['price_range']);
            $query->whereRaw("base_price >= {$min_max['min']} AND base_price <= {$min_max['max']}");
        }

        if (!empty($booking_type) && in_array($booking_type, ['per_day', 'per_hour'])) {
            $query->where('booking_type', $booking_type);
        }

        //$data['bookingType']
        if (!empty($data['checkIn']) && !empty($data['checkOut'])) {
            $avai_table = 'gmz_space_availability';
            $check_in = strtotime($data['checkIn']);
            $check_out = strtotime($data['checkOut']);

            //Check with availability
            $unavailable_space = "SELECT {$avai_table}.post_id FROM {$avai_table} WHERE ({$avai_table}.status <> 'available') AND ( ({$avai_table}.check_in <= {$check_in} AND {$avai_table}.check_out >= {$check_out}) OR ({$avai_table}.check_in >= {$check_in} AND {$avai_table}.check_out <= {$check_out}) OR ({$avai_table}.check_in <= {$check_in} AND {$avai_table}.check_out >= {$check_in}) OR ({$avai_table}.check_in <= {$check_out} AND {$avai_table}.check_out >= {$check_out}))";

            $query->whereRaw("{$table}.id NOT IN ({$unavailable_space})");

            //Check with booking table
            if (empty($booking_type) || $booking_type == 'per_day') {
                $order_table = 'gmz_order';
                $query->selectRaw("{$order_table}.id AS order_id, {$order_table}.post_id as service_id, {$order_table}.status as order_status, {$order_table}.post_type, {$order_table}.start_date, {$order_table}.end_date");
                $query->selectRaw("COUNT({$order_table}.id) as count_order");

                $query->leftJoin($order_table, function ($join) use ($check_in, $check_out, $table, $order_table) {
                    $status_complete = GMZ_STATUS_COMPLETE;
                    $status_incomplete = GMZ_STATUS_INCOMPLETE;
                    $join->on("{$table}.id", '=', "{$order_table}.post_id");
                    $join->whereRaw("{$order_table}.status IN ('{$status_complete}', '{$status_incomplete}') AND post_type = 'space' AND (({$order_table}.start_date <= {$check_in} AND {$order_table}.end_date >= {$check_out}) OR ({$order_table}.start_date >= {$check_in} AND {$order_table}.end_date <= {$check_out}) OR ({$order_table}.start_date <= {$check_in} AND {$order_table}.end_date >= {$check_in}) OR ({$order_table}.start_date <= {$check_out} AND {$order_table}.end_date >= {$check_out}))");
                });
                $query->groupBy(["{$table}.id"]);
                $query->havingRaw(("(count_order = 0 OR ISNULL(count_order))"));
            } elseif ($booking_type == 'per_hour') {

            }
        }

        $taxonomies = ['space_type', 'space_amenity'];
        foreach ($taxonomies as $tax) {
            if (!empty($data[$tax])) {
                $tax_arr = explode(',', $data[$tax]);
                $sql_tax = [];
                foreach ($tax_arr as $k => $v) {
                    array_push($sql_tax, "( FIND_IN_SET({$v}, {$table}.{$tax}) )");
                }
                if (!empty($sql_tax)) {
                    $query->whereRaw("(" . implode(' OR ', $sql_tax) . ")");
                }
            }
        }

        $query->whereRaw("{$table}.status = 'publish'");

        switch ($data['sort']) {
            case 'new':
            default:
                $query->orderByDesc('id');
                break;
            case 'price_asc':
                $query->orderBy('base_price', 'ASC');
                break;
            case 'price_desc':
                $query->orderByDesc('base_price');
                break;
            case 'name_a_z':
                $total = $query->count();
                $sort = $query->get()->sortBy(function ($post) {
                    return get_translate($post->post_title);
                })->forPage($data['page'], $data['number']);
                break;
            case 'name_z_a':
                $total = $query->count();
                $sort = $query->get()->sortByDesc(function ($post) {
                    return get_translate($post->post_title);
                })->forPage($data['page'], $data['number']);
                break;
        }

        if (!in_array($data['sort'], ['name_a_z', 'name_z_a'])) {
            return $query->paginate($data['number'], '', 'page', $data['page']);
        } else {
            $results = new LengthAwarePaginator($sort, $total, $data['number'], $data['page'], [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page'
            ]);
            return $results;
        }
    }
}