<?php

namespace App\Abstracts;

use App\Http\Resources\ListResource;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\Resource;
use App\Interfaces\InterfaceBaseService;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractBaseService implements InterfaceBaseService
{
    public ?Model $_model;
    protected string $_paginateResource = PaginateJsonResource::class;
    protected string $_listResource = ListResource::class;
    protected string $_resource = Resource::class;
    protected array $relations = [];
    protected string $url_path = ''; // store database
    protected string $destination_path = ''; // save image to folder

    function __construct()
    {
        $this->setModel();
    }

    abstract protected function setModel();

    public function index(
        $request = []
    )
    {
        $query = $this->_model->query();
        if ($this->relations) {
            $query = $query->with($this->relations);
        }
        if (!empty($request['select_field'])) {
            $query = $query->select($request['select_field']);
        } else {
            $query = $this->selectField($query);
        }
        $query = $this->setParams($request, $query);
        if (!empty($request['is_random_order'])) {
            $query = $query->inRandomOrder();
        }
        if (!empty($request['per_page']) && (int)$request['per_page'] > 0 && empty($request['take_limit'])) {
            $query = $query->paginate((int)$request['per_page']);
        } else {
            if (!empty($request['take_limit']) && (int)$request['take_limit'] > 0) {
                $query = $query->take($request['take_limit'])->get();
            } else {
                $query = $query->get();
            }
        }
        if (!empty($request['without_resource'])) {
            return $query;
        }
        if (!empty($request['per_page']) && (int)$request['per_page'] > 0 && empty($request['take_limit'])) {
            return new $this->_paginateResource($query, $this->_resource);
        }
        return new $this->_listResource($query, $this->_resource);
    }

    public function selectField($query)
    {
        return $query->select('*');
    }
    public function setParams($request, $query)
    {
        $fillAble = $this->_model->getFillable();
        $params = [];
        if (!empty($request)) {
            $params = $request;
        }
        $arraySearch = array_values(array_intersect($fillAble, array_keys($params)));
        $filtered = array_filter(
            $params,
            function ($key) use ($arraySearch) {
                return in_array($key, $arraySearch);
            },
            ARRAY_FILTER_USE_KEY
        );
        $where = (array_filter($filtered));
        if (!empty($where)) {
            foreach ($where as $key => $value) {
                if (is_array($value)) {
                    $query = $query->whereIn($key, $value);
                } else {
                    $query = $query->where($key, $value);
                }
            }
        }

        return $query;
    }
    public function store(array $params)
    {
        return $this->_model->query()->create($params);
    }

    public function updateOrCreate($arrayFind, $arrayStore)
    {
        return $this->_model->query()->updateOrCreate($arrayFind, $arrayStore);
    }

    public function storeMany(array $params): bool
    {
        return $this->_model->query()->insert($params);
    }

    public function detail(
        int $id,
            $select = [],
            $relation = []
    )
    {
        $query = $this->_model->query();
        if (!empty($select)) {
            $query = $query->select($select);
        }
        if (!empty($relation)) {
            $query = $query->with($relation);
        }
        return $query->find($id);
    }

    public function detailWithCondition(
        $where = [],
        $relation = [],
        $select = ['*']
    )
    {
        $query = $this->_model->query();
        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $query->where($key, $value);
            }
        } else {
            return false;
        }
        if (!empty($relation)) {
            $query->with($relation);
        }
        $query->select($select);
        return $query->first();
    }

    public function update(int $id, array $params)
    {
        return $this->detail($id)->update($params);
    }

    public function updateMany(array $condition, array $params)
    {
        if (empty($params)) {
            return false;
        }
        $query = $this->_model->query();
        if (!empty($condition)) {
            foreach ($condition as $key => $value) {
                $query->where($key, $value);
            }
        }
        return $query->update($params);
    }

    public function delete(int $id)
    {
        return $this->detail($id)->delete();
    }

    public function createFolder($path): bool
    {
        if (!$path) {
            return false;
        }
        if (!is_dir(public_path($path))) {
            mkdir(public_path($path), 0775, true);
        }
        return true;
    }

    public function deleteMany(array $deleteIds): int
    {
        return $this->_model->destroy($deleteIds);
    }

    public function curl($data, $url, $method)
    {
        try {
            $data_string = json_encode($data);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                $this->setHeaderCurl($data_string)
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, false);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function setHeaderCurl($data_string): array
    {
        return [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
        ];
    }

    public function generateRandomString($length = 9): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function generateRandom($length = 13): string
    {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function newQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->_model->query();
    }
}

