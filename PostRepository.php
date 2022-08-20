<?php
/**
 * Created by PhpStorm.
 * User: JreamOQ ( jreamoq@gmail.com )
 * Date: 11/25/20
 * Time: 17:31
 */

namespace App\Repositories;

use App\Models\Post;

class PostRepository extends AbstractRepository
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
        $this->model = new Post();
    }

    public function getArchivePagination($number, $where)
    {
        $model = new Post();
        $query = $model->query();
        if (!empty($where)) {
            if (isset($where['category_id']) && !empty($where['category_id'])) {
                $category_id = $where['category_id'];
                $query->whereRaw("(FIND_IN_SET('{$category_id}', post_category))");
            }
            if (isset($where['tag_id']) && !empty($where['tag_id'])) {
                $tag_id = $where['tag_id'];
                $query->whereRaw("(FIND_IN_SET('{$tag_id}', post_tag))");
            }
        }
        $query->orderByDesc("id");
        return $query->paginate($number);
    }
}