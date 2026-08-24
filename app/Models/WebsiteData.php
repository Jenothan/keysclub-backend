<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['primary_phone', 'support_email', 'club_address', 'facebook_url', 'instagram_url'])]
class WebsiteData extends Model
{
}
