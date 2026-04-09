<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class Utility extends Model
{
    private static $settings = null;
    private static $languages = null;

    public static function settings()
    {
        if (self::$settings === null) {
            $settings = Settings::first();
            if ($settings) {
                self::$settings = $settings->toArray();
            } else {
                self::$settings = [];
            }
        }
        return self::$settings;
    }

    public static function getValByName($key)
    {
        $setting = self::settings();
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }
        return $setting[$key];
    }

    public static function languages()
    {
        if (self::$languages === null) {
            self::$languages = self::langList();
        }
        return self::$languages;
    }

    public static function langList()
    {
        return [
            "ar" => "Arabic",
            "zh" => "Chinese",
            "da" => "Danish",
            "de" => "German",
            "en" => "English",
            "es" => "Spanish",
            "fr" => "French",
            "he" => "Hebrew",
            "it" => "Italian",
            "ja" => "Japanese",
            "nl" => "Dutch",
            "pl" => "Polish",
            "pt" => "Portuguese",
            "ru" => "Russian",
            "tr" => "Turkish",
            "pt-br" => "Portuguese(Brazil)"
        ];
    }

    public static function upload_file($request, $key_name, $name, $path, $custom_validation = [])
    {
        try {
            if ($request->hasFile($key_name)) {
                $file = $request->file($key_name);
                $file->storeAs($path, $name, 'public');

                return [
                    'flag' => 1,
                    'msg'  => 'success',
                    'url'  => $path . '/' . $name
                ];
            }
            return [
                'flag' => 0,
                'msg'  => 'No file uploaded',
            ];
        } catch (\Exception $e) {
            return [
                'flag' => 0,
                'msg'  => $e->getMessage(),
            ];
        }
    }

    public static function updateStorageLimit($company_id, $image_size)
    {
        return 1;
    }

    public static function getStorageSetting()
    {
        return [
            "storage_setting" => "local",
            "local_storage_validation" => "jpg,jpeg,png,xlsx,xls,csv,pdf,doc,docx",
            "local_storage_max_upload_size" => "2048000",
        ];
    }

    public static function get_file($path)
    {
        return Storage::disk('public')->url($path);
    }
}
