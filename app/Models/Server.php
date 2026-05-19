<?php

namespace App\Models;

use Carbon\Carbon;
use App\Classes\PterodactylClient;
use App\Enums\BillingPriority;
use App\Settings\PterodactylSettings;
use GuzzleHttp\Promise\PromiseInterface;
use Hidehalo\Nanoid\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Client\Response;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Exception;

/**
 * Class Server
 */
class Server extends Model
{
    use HasFactory;
    use LogsActivity;

    private PterodactylClient $pterodactyl;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string[]
     */
    protected static $ignoreChangedAttributes = ['pterodactyl_id', 'identifier', 'updated_at'];

    /**
     * @var string[]
     */
    protected static $logAttributes = ['name', 'description'];

    /**
     * @var string[]
     */
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING_RECONCILIATION = 'pending_reconciliation';

    protected $fillable = [
        "name",
        "description",
        "suspended",
        "suspension_warning_sent_at",
        "identifier",
        "billing_priority",
        "product_id",
        "pterodactyl_id",
        "user_id",
        "last_billed",
        "canceled",
        "status"
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'suspended' => 'datetime',
        'last_billed' => 'datetime',
        'canceled' => 'datetime',
        'billing_priority' => BillingPriority::class
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $ptero_settings = new PterodactylSettings();
        $this->pterodactyl = new PterodactylClient($ptero_settings);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function (Server $server) {
            if (!$server->{$server->getKeyName()}) {
                $client = new Client();

                $server->{$server->getKeyName()} = $client->generateId($size = 21);
            }
        });

        static::created(function (Server $server) {
            // Recalculate credit runout when server is created
            \App\Jobs\RecalculateCreditRunoutJob::dispatch($server->user_id);
        });

        static::updated(function (Server $server) {
            // Recalculate if product_id or billing_period-affecting fields changed
            if ($server->wasChanged(['product_id', 'suspended', 'canceled'])) {
                \App\Jobs\RecalculateCreditRunoutJob::dispatch($server->user_id);
            }
        });

        static::deleting(function (Server $server) {
            $response = $server->pterodactyl->application->delete("/application/servers/{$server->pterodactyl_id}");
            if ($response->failed() && !is_null($server->pterodactyl_id)) {
                //only return error when it's not a 404 error
                if ($response['errors'][0]['status'] != '404') {
                    throw new Exception($response['errors'][0]['code']);
                }
            }
        });

        static::deleted(function (Server $server) {
            // Recalculate credit runout when server is deleted
            \App\Jobs\RecalculateCreditRunoutJob::dispatch($server->user_id);
        });
    }

    /**
     * @return bool
     */
    public function isSuspended()
    {
        return !is_null($this->suspended);
    }

    /**
     * @return PromiseInterface|Response
     */
    public function getPterodactylServer()
    {
        return $this->pterodactyl->application->get("/application/servers/{$this->pterodactyl_id}");
    }

    /**
     * @throws Exception
     */
    public function suspend()
    {
        $response = $this->pterodactyl->suspendServer($this);

        if ($response->successful()) {
            $this->update([
                'suspended' => now(),
            ]);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function unSuspend()
    {
        $response = $this->pterodactyl->unSuspendServer($this);

        if ($response->successful()) {
            $this->update([
                'suspended' => null,
                'last_billed' => Carbon::now()->toDateTimeString(),
                'suspension_warning_sent_at' => null,
            ]);
        }


        return $this;
    }

    /**
     * @return BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getEffectiveBillingPriorityAttribute()
    {
        return $this->billing_priority ?? $this->product->default_billing_priority;
    }

    public function scopeByBillingPriority($query)
    {
        return $query->orderByRaw('COALESCE(servers.billing_priority, (
                SELECT default_billing_priority
                FROM products
                WHERE products.id = servers.product_id
            ))')
            ->orderBy('created_at', 'asc');
    }
}
