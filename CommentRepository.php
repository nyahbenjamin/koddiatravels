<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Comment;

class CommentRepository extends AbstractRepository
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
        $this->model = new Comment();
    }

    public function paginate($number = 10, $where = [], $withTerm = false, $where_in = [])
    {
        $query = $this->model->query();
        if (!empty($where)) {
            $query->where($where);
        }
        if ($withTerm) {
            $query->with('TermRelation.Term.Taxonomy');
        }

        if (!empty($where_in)) {
            $query->whereIn($where_in['column'], $where_in['value']);
        }

        return $query->orderBy($this->model->getKeyName(), 'DESC')->paginate($number);
    }
}