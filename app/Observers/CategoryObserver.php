<?php

namespace App\Observers;
use App\Models\Category;

class CategoryObserver
{
    public function created(Category $category)
    {
        $job = new \App\Jobs\TranslateCategoryJob($category->id);
        dispatch($job)->onQueue('sync-data-report');
    }
}
