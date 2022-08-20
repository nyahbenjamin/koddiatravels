<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Tour;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class TourRepository extends AbstractRepository
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
        $this->model = new Tour();
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
        $this->model = new Tour();
        $table = $this->model->getTable();
        $query = $this->model->query();

        $query->select("{$table}.*");
        $query->selectRaw("LEAST(adult_price,children_price,infant_price) as min_price");

        if (!empty($data['lat']) && !empty($data['lng'])) {
            $distance = get_option('tour_search_radius', '25');
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
            $query->whereRaw("group_size >= {$guests}");
        }

        if (!empty($data['price_range'])) {
            $min_max = convert_price_range($data['price_range']);
            $query->havingRaw("adult_price >= {$min_max['min']} AND adult_price <= {$min_max['max']}");
        }

        if (!empty($data['unavailable_id'])) {
            $query->whereNotIn('id', $data['unavailable_id']);
        }

        $taxonomies = ['tour_type', 'tour_include', 'tour_exclude'];
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
                $query->orderBy('adult_price', 'ASC');
                break;
            case 'price_desc':
                $query->orderByDesc('adult_price');
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