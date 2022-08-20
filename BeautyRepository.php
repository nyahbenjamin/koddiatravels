<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Beauty;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class BeautyRepository extends AbstractRepository
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
        $this->model = new Beauty();
    }

    public function getAllPostIDs($athor_id)
    {
        return $this->model->query()->where('author', $athor_id)->pluck('id')->toArray();
    }

    public function getWishlist($number, $wishlist)
    {
        return $this->model->query()->whereIn('id', $wishlist)->orderBy($this->model->getKeyName(), 'DESC')->paginate($number);
    }

    public function checkAvailability(int $unixtime, $id = null)
    {
        $dayOfWeek = date('w', $unixtime);

        $this->model = new Beauty();
        $table_name = $this->model->getTable();
        $query = $this->model->query();
        $query->leftJoin('gmz_agent_relation as ar', 'gmz_beauty.id', 'ar.post_id');
        $query->leftJoin('gmz_agent as a', 'ar.agent_id', 'a.id');
        $query->selectRaw("{$table_name}.*, GROUP_CONCAT(DISTINCT a.id) as agent_ids, (service_ends - (service_starts + service_duration)) AS working_time ");

        if (is_numeric($id)) {
            $query->where('gmz_beauty.id', $id);
        } else if (is_array($id)) {
            $query->whereIN('gmz_beauty.id', $id);
        }

        $query->whereRaw("NOT FIND_IN_SET({$dayOfWeek},day_off_week)");

        //check availability service
        $query->whereRaw("NOT EXISTS (SELECT id FROM gmz_beauty_availability AS ba WHERE ba.post_id = `gmz_beauty`.`id` AND ba.check_in = {$unixtime} AND ba.status = 'unavailable')");
        //check availability agent
        $query->whereRaw("NOT EXISTS (SELECT id FROM gmz_agent_availability AS aa WHERE aa.post_id = `a`.`id` AND aa.check_in = {$unixtime} AND aa.status = 'unavailable')");
        //check total minute
        $query->whereRaw("NOT EXISTS (SELECT id FROM gmz_agent_availability AS aa WHERE aa.post_id = `a`.`id` AND (aa.check_in >= (`gmz_beauty`.`service_starts` + {$unixtime}) AND aa.check_out <= (`gmz_beauty`.`service_ends` + {$unixtime})) AND aa.status = 'booked' GROUP BY post_id HAVING SUM(aa.check_out - aa.check_in) > `working_time`)");
        $query->groupBy('gmz_beauty.id');
        $result = $query->get();
        return $result->toArray();
    }

    public function getAgentByService(int $id)
    {
         return Beauty::find($id)->agent;
    }


    public function getSearchResult($params)
    {

        $this->model = new Beauty();
        $table_name = $this->model->getTable();
        $query = $this->model->query();
        $query->selectRaw("{$table_name}.*");

        if ($params['service']) {
            $query->leftJoin('gmz_term', 'gmz_beauty.service', 'gmz_term.id');
            $query->where("gmz_term.id", $params['service']);
        }

        if (!empty($params['lat']) && !empty($params['lng'])) {
            $distance = get_option('beauty_search_radius', '25');
            $distance = floatval($distance);
            $data['lat'] = esc_sql($params['lat']);
            $data['lng'] = esc_sql($params['lng']);
            $query->selectRaw("( 6371 * acos( cos( radians({$params['lat']}) ) * cos( radians( {$table_name}.location_lat ) ) * cos( radians( {$table_name}.location_lng ) - radians({$params['lng']}) ) + sin( radians({$params['lat']}) ) * sin( radians( {$table_name}.location_lat ) ) ) ) AS distance");
            $query->orHavingRaw("distance <= " . $distance);
            $query->orderByDesc('distance');
        } elseif (!empty($params['address'])) {
            $address = urldecode($params['address']);
            $data['address'] = esc_sql($params['address']);
            $query->whereRaw("{$table_name}.location_address LIKE '%{$address}%'");
            $query->orderByDesc("{$table_name}.id");
        }

        if ($params['checkIn']) {

            $dayofweek = date('w', $params['checkIn']);

            $query->leftJoin('gmz_agent_relation as ar', 'gmz_beauty.id', 'ar.post_id');
            $query->leftJoin('gmz_agent as a', 'ar.agent_id', 'a.id');
            $query->selectRaw("GROUP_CONCAT(DISTINCT a.id) as agent_ids, (service_ends - (service_starts + service_duration)) AS working_time ");

            $query->whereRaw("NOT FIND_IN_SET({$dayofweek},day_off_week)");

            //check availability service
            $query->whereRaw("NOT EXISTS (SELECT id FROM gmz_beauty_availability AS ba WHERE ba.post_id = `gmz_beauty`.`id` AND ba.check_in = {$params['checkIn']} AND ba.status = 'unavailable')");
            //check availability agent
            $query->whereRaw("NOT EXISTS (SELECT id FROM gmz_agent_availability AS aa WHERE aa.post_id = `a`.`id` AND aa.check_in = {$params['checkIn']} AND aa.status = 'unavailable')");
            //check total minute
            $query->whereRaw("NOT EXISTS (SELECT id FROM gmz_agent_availability AS aa WHERE aa.post_id = `a`.`id` AND (aa.check_in >= (`gmz_beauty`.`service_starts` + {$params['checkIn']}) AND aa.check_out <= (`gmz_beauty`.`service_ends` + {$params['checkIn']})) AND aa.status = 'booked' GROUP BY post_id HAVING SUM(aa.check_out - aa.check_in) > `working_time`)");
            $query->groupBy('gmz_beauty.id');
        }

        switch ($params['sort']) {
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
                })->forPage($params['page'], $params['limit']);
                break;
            case 'name_z_a':
                $total = $query->count();
                $sort = $query->get()->sortByDesc(function ($post) {
                    return get_translate($post->post_title);
                })->forPage($params['page'], $params['limit']);
                break;
        }

        if (!in_array($params['sort'], ['name_a_z', 'name_z_a'])) {
            return $query->paginate($params['limit'], '*', 'page', $params['page']);
        } else {
            $results = new LengthAwarePaginator($sort, $total, $params['limit'], $params['page'], [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page'
            ]);
            return $results;
        }
    }

}