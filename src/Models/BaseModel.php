<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ongudidan\PaymentsEngine\Traits\ConfiguresTableNames;
use Ongudidan\PaymentsEngine\Traits\ResolvesModels;

abstract class BaseModel extends Model
{
    use ConfiguresTableNames, HasFactory, ResolvesModels;

    protected $guarded = ['id'];
}
