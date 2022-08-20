<?php
/**
 * Created by PhpStorm.
 * User: Jream
 * Date: 5/16/2020
 * Time: 11:04 PM
 */

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractRepository
{
    protected $model;
    private static $_findQuery = [];

    public function all($order_by = '', $order = 'DESC')
    {
        if (empty($order_by)) {
            $order_by = $this->model->getKeyName();
        }
        return $this->model->orderBy($order_by, $order)->get();
    }

    public function paginate($number = 10, $where = [], $withTerm = false)
    {
        $query = $this->model->query();
        if (isset($where['status']) && $where['status'] == 'trash') {
            $query->onlyTrashed();
            unset($where['status']);
        }
        if (!empty($where)) {
            $query->where($where);
        }
        if ($withTerm) {
            $query->with('TermRelation.Term.Taxonomy');
        }
        return $query->orderBy($this->model->getKeyName(), 'DESC')->paginate($number);
    }

    public function paginateNew($number = 10, $where = [], $withTerm = false)
    {
        $query = $this->model->query();
        if (!empty($where)) {
            $query->where($where);
        }
        if ($withTerm) {
            $query->with('TermRelation.Term.Taxonomy');
        }
        return $query->orderBy('piority', 'ASC')->paginate($number);
    }

    public function find($id, $trash = false)
    {
        $table = $this->model->getTable();
        $keyCache = $table . '_' . $id;
        if($trash) {
           $keyCache .= '_1';
        }
        if(isset(self::$_findQuery[$keyCache])){
            $result = self::$_findQuery[$keyCache];
        }else{
            if ($trash) {
                $result = $this->model->withTrashed()->find($id);
            } else {
                $result = $this->model->find($id);
            }
            self::$_findQuery[$keyCache] = $result;
        }
        return $result;
    }

    public function findOneBy($criteria)
    {
        return $this->model->where($criteria)->first();
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function where($where, $first = false)
    {
        if ($first) {
            $result = $this->model->where($where)->get()->first();
        } else {
            $result = $this->model->where($where)->get();
        }
        return $result;
    }

    public function whereIn($column, array $array)
    {
        return $this->model->whereIn($column, $array)->get();
    }

    public function whereRaw($whereRaw, $first = false)
    {
        if ($first) {
            $result = $this->model->whereRaw($whereRaw)->get()->first();
        } else {
            $result = $this->model->whereRaw($whereRaw)->get();
        }
        return $result;
    }

    public function save($data)
    {
        $this->model->fill($data);
        $this->model->save();
        return $this->model->id;
    }

    public function create($data)
    {
        $query = $this->model->query()->create($data);
        $id = $this->model->getKeyName();
        return $query->$id;
    }

    public function insert(array $data)
    {
        return $this->model->query()->insert($data);
    }


    public function update($id, $data)
    {
        $result = $this->model->find($id);
        if ($result) {
            $result->update($data);
            return $result;
        }

        return false;
    }

    public function updateByWhere($where, $attributes = [])
    {
        $result = $this->model->where($where)->update($attributes);
        return $result;
    }

    public function delete($id)
    {
        $result = $this->find($id);
        if ($result) {
            $result->delete();
            return true;
        }
        return false;
    }

    public function hardDelete($id)
    {
        $this->model->withTrashed()->where('id', $id)->forceDelete();
    }

    public function restore($id)
    {
        $this->model->withTrashed()->where('id', $id)->restore();
    }

    public function restoreByWhere($where)
    {
        $this->model->withTrashed()->where($where)->restore();
    }

    public function deleteByWhere($where)
    {
        $result = $this->model->where($where)->delete();
        return $result;
    }

    public function hardDeleteByWhere($where)
    {
        $result = $this->model->where($where)->forceDelete();
        return $result;
    }

    public function deleteByWhereRaw($whereRaw)
    {
        $result = $this->model->whereRaw($whereRaw)->delete();
        return $result;
    }

    public function hardDeleteByWhereRaw($whereRaw)
    {
        $result = $this->model->whereRaw($whereRaw)->forceDelete();
        return $result;
    }

    public function truncate()
    {
        $this->model->query()->truncate();
    }

    public function getRelatedSlugs($slug, $id = 0)
    {
        return $this->model->select('post_slug')->where('post_slug', 'like', '%' . $slug . '%')
            ->where('id', '<>', $id)
            ->get();
    }

    protected function getSql($query)
    {
        $sql = $query->toSql();
        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        return $sql;
    }
}
