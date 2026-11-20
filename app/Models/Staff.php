<?php

namespace App\Models;

use App\Models\Traits\AvatarAttribute;
use App\Models\Traits\ImageAttribute;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use App\Scopes\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class User
 * @package App\Models
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $status
 * @property Carbon $blocked_at
 * @property int $login_failed
 * @property string $verify_code
 */
class Staff extends BaseModel
{
    use Filterable, HasFactory, Notifiable, HasOrganization, HasRoles, HasSortDescByCreated, AvatarAttribute, HasPermissions;

    use LogsActivity, SoftDeletes;

    protected $table = 'users';

    protected string $_prefixRoute = 'user';

    protected $guard_name = 'api';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'blocked_at' => 'datetime',
    ];

    protected string $_tableNameFriendly = 'Nhân viên';

    protected static $logAttributes = [
        "name",
        "email",
        "username",
        "avatar",
        "code",
        "password",
        "department_id",
        "phone_number",
        "verify_code",
        "login_failed",
        "blocked_at",
        "is_system",
        "total_noti",
        "reset_staff_allow",
        "status",
        "organization_id",
        "thuongdo_id",
        "thuongdo_uuid",
        "dongdong_id",
        "dongdong_warehouse",
        "id_taobao",
        "user_name_taobao",
        "password_taobao",
        "id_1688",
        "total_customers",
        "is_circle_order",
        "total_chat_unread"
    ];
    protected static $ignoreChangedAttributes = ['total_chat_unread'];
    protected static $logOnlyDirty = true;
    protected static $logName = 'users';

    protected static function boot()
    {
        static::created(function ($model) {
            $id = $model->id;
            $code = '';
            if ($id < 1000) {
                $code = '0' .$id;
            }
            if ($id < 100) {
                $code = '00' . $id;
            }
            if ($id < 10) {
                $code = '000' . $id;
            }
            $model->code = $code;
            $model->save();
        });
        parent::boot();
    }

    public function scopeName($query)
    {
        return $query->where("name", 'like', '%'.request()->query('name').'%');
    }

    public function scopeDepartmentId($query)
    {
        return $query->where("department_id", request()->query('department_id'));
    }

    public function scopeRoleName($query) {
        return $query->whereHas('roles', function($q) {
            $q->where('name', request()->query('role_name'));
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }

    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class, 'user_id', 'id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
