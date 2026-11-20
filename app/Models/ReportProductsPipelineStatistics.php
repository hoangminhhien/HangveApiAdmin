<?php

namespace App\Models;

use App\Builders\ProductsPipelineStatisticsBuilders;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class ReportProductsPipelineStatistics extends Model
{
    protected $table = 'report_products_pipeline_statistics';
    protected $fillable = [
        'source', 'action', 'platform', 'keyword', 'ip_address', 'city', 'quantity',
        'customer_id', 'customer_name', 'customer_code', 'customer_phone',
        'week', 'month', 'year', 'order_code', 'order_id'
    ];

    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     * @return ProductsPipelineStatisticsBuilders
     */
    public function newEloquentBuilder($query): ProductsPipelineStatisticsBuilders
    {
        return new ProductsPipelineStatisticsBuilders($query);
    }
}
