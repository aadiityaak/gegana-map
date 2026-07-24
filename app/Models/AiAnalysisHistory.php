<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysisHistory extends Model
{
    protected $table = 'ai_analysis_history';

    protected $fillable = [
        'module',
        'action',
        'period',
        'total_data',
        'prompt',
        'result',
    ];
}
