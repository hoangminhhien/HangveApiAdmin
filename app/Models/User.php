<?php

namespace App\Models;

use App\Builders\UserBuilders;
use App\Models\Traits\AvatarAttribute;
use App\Scopes\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

use NotificationChannels\WebPush\HasPushSubscriptions; //import the trait
use ThuongDo\Permissions\Traits\UserTrait;

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
class User extends Authenticate
{
    use HasApiTokens, HasFactory, Notifiable, HasOrganization, HasRoles, AvatarAttribute;
    use HasPushSubscriptions; // add the trait to your class
    use UserTrait; // các custom methods support authentication & authorization

    use LogsActivity, SoftDeletes;

    public const ADMIN_EMAIL = 'admin@thuongdo.com';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $guard_name = 'api';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'blocked_at' => 'datetime',
    ];

    protected string $_tableNameFriendly = 'Người dùng';

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
        "total_chat_unread",
        "conversation_id_current"
    ];
    protected static $ignoreChangedAttributes = ['total_chat_unread', 'conversation_id_current'];
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

    public function orderPackageConsignment(): HasMany
    {
        return $this->hasMany(OrderPackage::class, 'staff_group_id', 'id')
            ->where('order_type', Consignment::class);
    }


    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function departmentQuote(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id')->where('type', 'QUOTE');
    }

    public function departmentOrder(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id')->where('type', 'ORDER');
    }

    public function departmentComplain(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id')->where('type', 'COMPLAIN');
    }

    public function orderStaff(): HasMany
    {
        return $this->hasMany(Order::class, 'staff_group_id', 'id');
    }

    /**
     * Lấy ra các orders của auth user
     *
     * @return HasMany
     */
    public function ordersSelf(): HasMany
    {
        return $this->hasMany(Order::class, 'staff_order_id', 'id');
    }

    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     * @return UserBuilders
     */
    public function newEloquentBuilder($query): UserBuilders
    {
        return new UserBuilders($query);
    }

    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_id', 'id');
    }

    public function userExternal()
    {
        return $this->hasMany(UserExternal::class, 'user_id', 'id');
    }

    public function checkUserHasPer($permission): bool
    {
        $userOnline = auth()->user();
        $permissionDBs = $userOnline->getAllPermissions();
        $perOfRole = Arr::pluck($permissionDBs, 'name');
        if (in_array($permission, $perOfRole)) {
            return true;
        }
        return false;
    }

    public function getAvatarOriginAttribute(?string $value): ?string
    {
        return $this->attributes['avatar'];
    }

    public function getCountConversationUnread()
    {
        $conversationModel = \App::make('App\Models\Conversation');
        $conversationUnredCount = $conversationModel->where('user_id', $this->id)
                                                ->where('unread_admin', '>', 0)
                                                ->count();
        return $conversationUnredCount;
    }

    public function scopeWithCurrentGroup($query)
    {
        $currentMonth = now()->format('m/Y');

        return $query->leftJoin('users_group as ug', function ($join) use ($currentMonth) {
            $join->on('ug.user_id', '=', 'users.id')
                ->whereRaw("
                ug.id = (
                    SELECT ug2.id
                    FROM users_group ug2
                    WHERE ug2.user_id = users.id
                    ORDER BY
                        CASE
                            WHEN ug2.month = ? THEN 0
                            ELSE 1
                        END,
                        STR_TO_DATE(ug2.month, '%m/%Y') DESC
                    LIMIT 1
                )
            ", [$currentMonth]);
        })
            ->addSelect('users.*', 'ug.group_id as current_group_id');
    }
}
