<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GenerateOfferLetter extends Model
{
    protected $table = 'generate_offer_letters';
    protected $fillable = [
        'id',
        'lang',
        'content',
        'created_by',
    ];

    public static function replaceVariable($content, $obj)
    {
        $arrVariable = [
            '{applicant_name}',
            '{app_name}',
            '{job_title}',
            '{job_type}',
            '{start_date}',
            '{workplace_location}',
            '{days_of_week}',
            '{salary}',
            '{salary_type}',
            '{salary_duration}',
            '{next_pay_period}',
            '{offer_expiration_date}',
        ];
        $arrValue    = [
            'applicant_name' => '-',
            'app_name' => '-',
            'job_title' => '-',
            'job_type' => '-',
            'start_date' => '-',
            'workplace_location' => '-',
            'days_of_week' => '-',
            'salary'=>'-',
            'salary_type' => '-',
            'salary_duration' => '-',
            'next_pay_period' => '-',
            'offer_expiration_date' => '-',
        ];

        foreach($obj as $key => $val)
        {
            $arrValue[$key] = $val;
        }
       
        $arrValue['app_name']     = env('APP_NAME', 'PAYR');
       
        return str_replace($arrVariable, array_values($arrValue), $content);
    }
}
