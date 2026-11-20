<?php

namespace App\Interfaces;

interface InterfaceBaseService
{
    public function index($request = []);
    public function store(array $params);
    public function detail(int $id, $select = []);
    public function delete(int $id);
    public function update(int $id, array $params);
}
