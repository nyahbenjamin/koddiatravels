<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Apartment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ApartmentRepository extends AbstractRepository
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
        $this->model = new Apartment();
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
        $this->model = new Apartment();
        $table = $this->model->getTable();
        $query = $this->model->query();

        $query->select("{$table}.*");

        if (!empty($data['lat']) && !empty($data['lng'])) {
            $distance = get_option('apartment_search_radius', '25');
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

        if (!empty($data['unavailable_id'])) {
            $query->whereNotIn('id', $data['unavailable_id']);
        }

        $taxonomies = ['apartment_type', 'apartment_amenity'];
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