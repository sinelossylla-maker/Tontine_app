<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class UserNotifiable extends Model
{
    use Notifiable;

    protected $table = 'notifications';
    protected $primaryKey = 'phone';
    public $incrementing = false;
    protected $keyType = 'string';

    public function __construct(string $phone = '')
    {
        parent::__construct();
        if ($phone) {
            $this->setAttribute('phone', $phone);
        }
    }

    public function getMorphClass()
    {
        return 'App\Models\UserNotifiable';
    }
}
